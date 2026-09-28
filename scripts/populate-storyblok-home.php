<?php

declare(strict_types=1);

// Preview with: php scripts/populate-storyblok-home.php --dry-run
// Apply with:   php scripts/populate-storyblok-home.php --apply
// This updates only the existing unpublished Storyblok home story.

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function stop(string $reason): never
{
    fwrite(STDERR, $reason.PHP_EOL);
    exit(1);
}

function uid(string $key): string
{
    $hex = substr(hash('sha1', 'mobile-arena:home:v1:'.$key), 0, 32);
    $hex[12] = '5';
    $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

    return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4)
        .'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
}

function blok(string $key, string $component, array $fields): array
{
    return ['_uid' => uid($key), 'component' => $component, ...$fields];
}

function linkTo(string $path): array
{
    return ['fieldtype' => 'multilink', 'linktype' => 'url', 'url' => $path, 'cached_url' => $path];
}

function richText(string $text): array
{
    return [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]],
    ];
}

$mode = $argv[1] ?? '--dry-run';
if (! in_array($mode, ['--dry-run', '--apply'], true) || count($argv) > 2) {
    stop('Usage: php scripts/populate-storyblok-home.php [--dry-run|--apply]');
}

$personalToken = trim((string) env('STORYBLOK_PERSONAL_ACCESS_TOKEN'));
$deliveryToken = trim((string) config('storyblok.access_token'));
$spaceId = trim((string) env('STORYBLOK_SPACE_ID'));
if ($personalToken === '' || $deliveryToken === '' || ! ctype_digit($spaceId)) {
    stop('Storyblok credentials or space ID are missing.');
}
if (env('STORYBLOK_REGION') !== 'eu' || config('storyblok.version') !== 'draft') {
    stop('This script requires the EU Storyblok space and Laravel draft mode.');
}

$preferredProductSlugs = [
    'iphone-13-128gb-brand-new',
    'iphone-12-128gb-pre-owned',
    'galaxy-a56-5g-brand-new',
    'galaxy-s23-256gb-refurbished',
    'ipad-10th-gen-pre-owned',
    'smart-watch-series',
    '20w-usb-c-fast-charger',
    '10000mah-power-bank',
];

// Categories have no active flag; a category qualifies when it has an active product.
$categorySlugs = Category::query()
    ->whereHas('products', fn ($query) => $query->where('active', true))
    ->orderBy('sort_order')->pluck('slug')->all();
$products = Product::query()->where('active', true)
    ->whereIn('slug', $preferredProductSlugs)->get()->keyBy('slug');
$productSlugs = array_values(array_filter($preferredProductSlugs, fn ($slug) =>
    isset($products[$slug]) && $products[$slug]->available_stock > 0));
if ($categorySlugs === [] || $productSlugs === []) {
    stop('No eligible categories or active, available products were found in Laravel.');
}

$sections = [
    'hero' => [blok('hero', 'hero_section', [
        'enabled' => true,
        'eyebrow' => 'Mobile Arena · Devices and service',
        'headline' => 'Find your next device. Keep the one you have going.',
        'subheadline' => 'Explore brand-new, pre-owned and refurbished device listings, or send a repair or refurbishment request for assessment.',
        'primary_cta_label' => 'Browse devices',
        'primary_cta_url' => linkTo('/shop'),
        'secondary_cta_label' => 'Request service',
        'secondary_cta_url' => linkTo('/repair'),
    ])],
    'promotional_banners' => [
        blok('banner-new', 'promotional_banner', [
            'enabled' => true,
            'title' => 'Explore brand-new devices',
            'description' => 'Browse brand-new listings and review product details before placing an order request.',
            'cta_label' => 'Shop brand-new',
            'cta_url' => linkTo('/shop?condition=brand_new'),
        ]),
        blok('banner-refurbished', 'promotional_banner', [
            'enabled' => true,
            'title' => 'Consider a refurbished device',
            'description' => 'Compare refurbished listings and check each item’s condition details.',
            'cta_label' => 'Browse refurbished',
            'cta_url' => linkTo('/shop?condition=refurbished'),
        ]),
        blok('banner-repair', 'promotional_banner', [
            'enabled' => true,
            'title' => 'Need help with a device?',
            'description' => 'Describe the issue in a repair or refurbishment request for store assessment.',
            'cta_label' => 'Request assessment',
            'cta_url' => linkTo('/repair'),
        ]),
    ],
    'featured_categories' => array_map(fn ($slug) =>
        blok('category-'.$slug, 'category_reference', ['slug' => $slug]), $categorySlugs),
    'featured_products' => array_map(fn ($slug) =>
        blok('product-'.$slug, 'product_reference', ['slug' => $slug]), $productSlugs),
    'repair_promo' => [blok('repair', 'repair_promo', [
        'enabled' => true,
        'heading' => 'Tell us what is happening with your device.',
        'description' => 'Send the device type, model and issue, plus an optional preferred visit date. Diagnosis, parts, cost and timing require store assessment.',
        'cta_label' => 'Request an assessment',
        'cta_url' => linkTo('/repair'),
    ])],
    // There is no confirmed current announcement or sale to display.
    'announcements' => [],
    'faq_preview' => [
        blok('faq-ordering', 'faq_item', [
            'enabled' => true,
            'question' => 'How do I place an order request?',
            'answer' => richText('Add a listed product to your cart, sign in, then choose pickup or delivery at checkout. The application checks current prices and availability when you place the order.'),
        ]),
        blok('faq-conditions', 'faq_item', [
            'enabled' => true,
            'question' => 'How are pre-owned and refurbished devices described?',
            'answer' => richText('Listings show their condition and any available grade or condition notes. Review the individual product details and confirm the exact unit with the store before purchase.'),
        ]),
        blok('faq-repairs', 'faq_item', [
            'enabled' => true,
            'question' => 'Can I request a repair or refurbishment?',
            'answer' => richText('Yes. Sign in and submit your device type, model and issue. You can add a preferred visit date and follow the request from your account. Diagnosis, cost and timing need store confirmation.'),
        ]),
        blok('faq-checkout', 'faq_item', [
            'enabled' => true,
            'question' => 'What happens at checkout?',
            'answer' => richText('Choose store pickup or a delivery request and provide the required details. The application recalculates item prices, availability and any delivery fee before recording your order request.'),
        ]),
        blok('faq-payment', 'faq_item', [
            'enabled' => true,
            'question' => 'How does GCash verification work here?',
            'answer' => richText('If you select the GCash option, the application records an order and accepts a transaction reference for staff review. It does not collect an online payment. Confirm payment instructions with the store before sending money.'),
        ]),
        blok('faq-refunds', 'faq_item', [
            'enabled' => true,
            'question' => 'Can I request a refund?',
            'answer' => richText('Eligible paid and completed orders can submit a refund request from the customer account. Staff review requests; any money transfer is handled outside this application.'),
        ]),
    ],
    'shop_information' => [blok('shop-info', 'shop_information', [
        'enabled' => true,
        'description' => 'Mobile Arena brings brand-new, pre-owned and refurbished device listings together with service requests.',
        'about' => 'Mobile Arena Cellphone & Accessories is listed at XentroMall Calapan. Explore the catalog or submit a device service request through this storefront.',
        'opening_hours' => '',
        'contact_information' => '',
        'location_text' => 'XentroMall Calapan, Roxas Drive, Lumang Bayan, Calapan City, Oriental Mindoro 5200',
        'social_links' => [],
    ])],
];

$storyId = 224588504674625;
$managementBase = 'https://mapi.storyblok.com/v1/spaces/'.$spaceId;
$management = Http::acceptJson()->withHeaders(['Authorization' => $personalToken])
    ->connectTimeout(5)->timeout(25);

try {
    $componentsResponse = $management->get($managementBase.'/components', ['per_page' => 100]);
    $storyResponse = $management->get($managementBase.'/stories/'.$storyId);
} catch (Throwable) {
    stop('Could not reach the Storyblok Management API.');
}
if (! $componentsResponse->successful() || ! $storyResponse->successful()) {
    stop('Storyblok Management API read failed.');
}

$components = collect($componentsResponse->json('components', []))->keyBy('name');
$required = [
    'home_page' => ['hero', 'promotional_banners', 'featured_categories', 'featured_products', 'repair_promo', 'announcements', 'faq_preview', 'shop_information'],
    'hero_section' => ['headline', 'subheadline', 'primary_cta_url', 'secondary_cta_url'],
    'promotional_banner' => ['title', 'description', 'cta_url'],
    'category_reference' => ['slug'],
    'product_reference' => ['slug'],
    'repair_promo' => ['heading', 'description', 'cta_url'],
    'faq_item' => ['question', 'answer'],
    'shop_information' => ['description', 'location_text'],
];
foreach ($required as $component => $fields) {
    $schema = $components->get($component)['schema'] ?? null;
    if (! is_array($schema) || array_diff($fields, array_keys($schema))) {
        stop('Storyblok schema is missing expected fields for '.$component.'.');
    }
}

$story = $storyResponse->json('story');
if (! is_array($story) || ($story['id'] ?? null) != $storyId || ($story['full_slug'] ?? null) !== 'home'
    || ($story['published'] ?? null) !== false || ($story['content']['component'] ?? null) !== 'home_page') {
    stop('The existing home story is missing, published, or has an unexpected root component.');
}

$content = $story['content'];
$changed = false;
foreach ($sections as $field => $value) {
    if (($content[$field] ?? null) != $value) {
        $content[$field] = $value;
        $changed = true;
    }
}

echo 'Categories: '.implode(', ', $categorySlugs).PHP_EOL;
echo 'Products: '.implode(', ', $productSlugs).PHP_EOL;
echo 'Sections: hero, '.count($sections['promotional_banners']).' banners, '.count($sections['faq_preview'])
    .' FAQs, repair, shop information; announcements empty.'.PHP_EOL;
echo 'Existing legacy body blocks preserved: '.count($content['body'] ?? []).PHP_EOL;
echo 'Draft story change needed: '.($changed ? 'yes' : 'no').PHP_EOL;

if ($mode === '--dry-run') {
    exit(0);
}

if ($changed) {
    try {
        $update = $management->put($managementBase.'/stories/'.$storyId, [
            'publish' => false,
            'story' => [
                'id' => $storyId,
                'name' => $story['name'],
                'slug' => $story['slug'],
                'content' => $content,
            ],
        ]);
    } catch (Throwable) {
        stop('Storyblok Management API update could not be completed.');
    }
    if (! $update->successful()) {
        stop('Storyblok Management API update failed with HTTP '.$update->status().'.');
    }
}

try {
    $saved = $management->get($managementBase.'/stories/'.$storyId);
    $draft = Http::acceptJson()->connectTimeout(5)->timeout(25)
        ->get('https://api.storyblok.com/v2/cdn/stories/home', [
            'token' => $deliveryToken,
            'version' => 'draft',
            'cv' => time(),
        ]);
} catch (Throwable) {
    stop('Storyblok verification request could not be completed.');
}
if (! $saved->successful() || ! $draft->successful()
    || $saved->json('story.published') !== false
    || $saved->json('story.content.component') !== 'home_page'
    || $draft->json('story.content.component') !== 'home_page') {
    stop('The saved draft story did not pass verification.');
}
foreach ($sections as $field => $value) {
    if (count($draft->json('story.content.'.$field, [])) !== count($value)) {
        stop('Draft Content Delivery API returned an unexpected '.$field.' section.');
    }
}

try {
    Cache::forget('storyblok:home:draft:'.hash('sha256', $deliveryToken));
} catch (Throwable) {
    // A cache failure must not affect the Storyblok update.
}

echo 'Verified draft Content Delivery API: home_page; unpublished.'.PHP_EOL;
