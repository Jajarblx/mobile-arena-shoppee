<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoryblokHomepageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Cache::flush();
        config()->set('storyblok.access_token', 'test-preview-token');
        config()->set('storyblok.version', 'draft');
        config()->set('storyblok.cache_ttl', 300);
    }

    private function fakeHome(array $content): void
    {
        Http::fake(['api.storyblok.com/*' => Http::response([
            'story' => ['content' => array_merge(['component' => 'home_page'], $content)],
        ])]);
    }

    public function test_cms_hero_promotions_repair_faq_and_shop_information_render_safely(): void
    {
        $this->fakeHome([
            'hero' => [['component' => 'hero_section', 'enabled' => true, 'headline' => 'Find your next favorite device',
                'eyebrow' => 'Local tech', 'subheadline' => 'Shop with confidence', 'primary_cta_label' => 'See phones',
                'primary_cta_url' => ['linktype' => 'story', 'url' => '', 'cached_url' => 'shop'],
                'secondary_cta_label' => 'Unsafe', 'secondary_cta_url' => 'javascript:alert(1)']],
            'promotional_banners' => [['component' => 'promotional_banner', 'enabled' => true,
                'title' => 'Weekend picks', 'description' => 'Explore selected devices', 'cta_label' => 'Browse', 'cta_url' => '/shop']],
            'repair_promo' => [['component' => 'repair_promo', 'enabled' => true, 'heading' => 'Give your phone new life',
                'description' => 'Request an assessment', 'cta_label' => 'Ask for repair', 'cta_url' => '/repair']],
            'faq_preview' => [['component' => 'faq_item', 'enabled' => true, 'question' => 'Can I visit?', 'answer' => '<script>alert(1)</script> Yes.']],
            'shop_information' => [['component' => 'shop_information', 'description' => 'A neighborhood gadget shop',
                'about' => 'Serving Calapan', 'opening_hours' => 'Daily 9 to 9', 'contact_information' => 'Call the shop',
                'location_text' => 'At the mall', 'social_links' => [['label' => 'Facebook', 'url' => 'https://facebook.com/mobilearena']]]],
        ]);

        $this->get('/')->assertOk()
            ->assertSee('Find your next favorite device')
            ->assertSee('Weekend picks')
            ->assertSee('Give your phone new life')
            ->assertSee('Can I visit?')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Yes.', false)
            ->assertSee('Daily 9 to 9')
            ->assertSee('href="/shop"', false)
            ->assertSee('https://facebook.com/mobilearena')
            ->assertDontSee('href="javascript:alert(1)"', false)
            ->assertDontSee('test-preview-token');
    }

    public function test_cms_product_references_use_only_unique_active_laravel_records_and_laravel_commerce_values(): void
    {
        $selected = Product::where('slug', 'iphone-13-128gb-brand-new')->firstOrFail();
        $selected->update(['price' => 29990, 'stock' => 0, 'featured' => false]);
        $inactive = Product::where('slug', 'galaxy-s23-256gb-refurbished')->firstOrFail();
        $inactive->update(['active' => false]);
        $this->fakeHome([
            'featured_products' => [
                ['slug' => $selected->slug, 'price' => 1, 'stock' => 999],
                ['slug' => $selected->slug], ['slug' => 'missing-product'], ['slug' => $inactive->slug],
            ],
            'featured_categories' => [['slug' => 'smartphones'], ['slug' => 'smartphones'], ['slug' => 'missing-category']],
        ]);

        $response = $this->get('/')->assertOk()->assertSee('₱29,990')->assertSee('Out of Stock')
            ->assertDontSee('₱1</strong>', false)->assertDontSee('999 left')
            ->assertDontSee('Galaxy S23 256GB');

        $this->assertSame(1, substr_count($response->getContent(), '>iPhone 13 128GB</a></h3>'));
        $this->assertSame(1, substr_count($response->getContent(), 'category=smartphones'));
        $this->assertStringNotContainsString('missing-product', $response->getContent());
        $this->assertStringNotContainsString('missing-category', $response->getContent());
    }

    public function test_announcements_are_filtered_by_enabled_and_date_even_from_cached_content(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(23, 59, 58));
        $this->fakeHome(['announcements' => [
            ['title' => 'Active today', 'enabled' => true, 'start_date' => '2026-09-28', 'end_date' => '2026-09-28'],
            ['title' => 'Already ended', 'enabled' => true, 'end_date' => '2026-09-27'],
            ['title' => 'Not started', 'enabled' => true, 'start_date' => '2026-09-29'],
            ['title' => 'Disabled', 'enabled' => false],
        ]]);

        $this->get('/')->assertOk()->assertSee('Active today')
            ->assertDontSee('Already ended')->assertDontSee('Not started')->assertDontSee('Disabled');
        $this->travelTo(now()->addSeconds(3));
        $this->get('/')->assertOk()->assertDontSee('Active today');
        Http::assertSentCount(1);
    }

    public function test_missing_token_and_http_failures_keep_the_existing_homepage(): void
    {
        config()->set('storyblok.access_token', '');
        Http::fake();
        $this->get('/')->assertOk()->assertSee('New tech, second chances')->assertSee('Your device may not need replacing.');
        Http::assertNothingSent();

        config()->set('storyblok.access_token', 'test-preview-token');
        Http::fake(['api.storyblok.com/*' => Http::response([], 503)]);
        $this->get('/')->assertOk()->assertSee('New tech, second chances')->assertSee('Brand New');
    }

    public function test_valid_cms_content_is_cached(): void
    {
        $this->fakeHome(['hero' => [['component' => 'hero_section', 'headline' => 'First CMS headline']]]);
        $this->get('/')->assertOk()->assertSee('First CMS headline');
        Http::assertSentCount(1);
        $this->get('/')->assertOk()->assertSee('First CMS headline');
        Http::assertSentCount(1);

    }

    public function test_incomplete_content_falls_back(): void
    {
        Http::fake(['api.storyblok.com/*' => Http::response(['story' => ['content' => ['component' => 'other_page']]])]);
        $this->get('/')->assertOk()->assertSee('New tech, second chances');
    }

    public function test_connection_timeout_falls_back_without_exposing_error(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('CMS connection timed out'));
        $this->get('/')->assertOk()->assertSee('New tech, second chances')
            ->assertDontSee('CMS connection timed out');
    }

    public function test_published_mode_requests_published_content(): void
    {
        config()->set('storyblok.version', 'published');
        $this->fakeHome(['hero' => [['component' => 'hero_section', 'headline' => 'Published CMS headline']]]);
        $this->get('/')->assertOk()->assertSee('Published CMS headline');
        Http::assertSent(fn ($request) => $request['version'] === 'published' && $request['token'] === 'test-preview-token');
    }
}
