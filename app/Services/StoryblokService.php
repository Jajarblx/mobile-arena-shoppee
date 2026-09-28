<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class StoryblokService
{
    public function homepage(): array
    {
        $token = trim((string) config('storyblok.access_token'));
        if ($token === '') {
            return [];
        }

        $version = config('storyblok.version') === 'published' ? 'published' : 'draft';
        $key = 'storyblok:home:'.$version.':'.hash('sha256', $token);

        try {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $this->withCurrentAnnouncements($cached);
            }
        } catch (Throwable) {
            // A cache failure must not take the storefront down.
        }

        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(4)
                ->get('https://api.storyblok.com/v2/cdn/stories/home', [
                    'token' => $token,
                    'version' => $version,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $content = $response->json('story.content');
            if (! is_array($content) || ($content['component'] ?? null) !== 'home_page') {
                return [];
            }

            $home = $this->normalize($content);
            $ttl = max(0, (int) config('storyblok.cache_ttl', 300));
            if ($ttl > 0) {
                try {
                    Cache::put($key, $home, $ttl);
                } catch (Throwable) {
                    // Fresh CMS content can still be used without a working cache.
                }
            }

            return $this->withCurrentAnnouncements($home);
        } catch (Throwable) {
            // Do not log the HTTP exception: its URL may contain the access token.
            return [];
        }
    }

    private function normalize(array $content): array
    {
        $hero = $this->firstEnabled($content['hero'] ?? null);
        $repair = $this->firstEnabled($content['repair_promo'] ?? null);
        $shop = $this->firstEnabled($content['shop_information'] ?? null);
        $shopInformation = $shop ? [
            'description' => $this->plainText($shop['description'] ?? null),
            'about' => $this->plainText($shop['about'] ?? null),
            'opening_hours' => $this->plainText($shop['opening_hours'] ?? null),
            'contact_information' => $this->plainText($shop['contact_information'] ?? null),
            'location_text' => $this->plainText($shop['location_text'] ?? null),
            'social_links' => array_values(array_filter(array_map(function (array $link) {
                $url = $this->safeUrl($link['url'] ?? null, true);
                $label = $this->plainText($link['label'] ?? null);
                return $url && $label ? compact('label', 'url') : null;
            }, $this->blocks($shop['social_links'] ?? null)))),
        ] : null;
        if ($shopInformation && ! array_filter($shopInformation)) {
            $shopInformation = null;
        }

        return [
            'hero' => $hero ? [
                'eyebrow' => $this->plainText($hero['eyebrow'] ?? null),
                'headline' => $this->plainText($hero['headline'] ?? null),
                'subheadline' => $this->plainText($hero['subheadline'] ?? null),
                'primary_cta_label' => $this->plainText($hero['primary_cta_label'] ?? null),
                'primary_cta_url' => $this->safeUrl($hero['primary_cta_url'] ?? null),
                'secondary_cta_label' => $this->plainText($hero['secondary_cta_label'] ?? null),
                'secondary_cta_url' => $this->safeUrl($hero['secondary_cta_url'] ?? null),
                'image' => $this->safeUrl($hero['image'] ?? null, true),
            ] : null,
            'promotions' => array_values(array_filter(array_map(function (array $block) {
                $title = $this->plainText($block['title'] ?? null);
                return $this->enabled($block) && $title !== '' ? [
                    'title' => $title,
                    'description' => $this->plainText($block['description'] ?? null),
                    'image' => $this->safeUrl($block['image'] ?? null, true),
                    'cta_label' => $this->plainText($block['cta_label'] ?? null),
                    'cta_url' => $this->safeUrl($block['cta_url'] ?? null),
                ] : null;
            }, $this->blocks($content['promotional_banners'] ?? null)))),
            'featured_categories' => $this->references($content['featured_categories'] ?? null),
            'featured_products' => $this->references($content['featured_products'] ?? null),
            'repair_promo' => $repair ? [
                'heading' => $this->plainText($repair['heading'] ?? null),
                'description' => $this->plainText($repair['description'] ?? null),
                'cta_label' => $this->plainText($repair['cta_label'] ?? null),
                'cta_url' => $this->safeUrl($repair['cta_url'] ?? null),
                'image' => $this->safeUrl($repair['image'] ?? null, true),
            ] : null,
            'announcements' => array_values(array_filter(array_map(function (array $block) {
                $title = $this->plainText($block['title'] ?? null);
                return $this->enabled($block) && $title !== '' ? [
                    'title' => $title,
                    'description' => $this->plainText($block['description'] ?? null),
                    'start_date' => $this->plainText($block['start_date'] ?? null),
                    'end_date' => $this->plainText($block['end_date'] ?? null),
                ] : null;
            }, $this->blocks($content['announcements'] ?? null)))),
            'faq' => array_values(array_filter(array_map(function (array $block) {
                $question = $this->plainText($block['question'] ?? null);
                return $this->enabled($block) && $question !== '' ? [
                    'question' => $question,
                    'answer' => $this->plainText($block['answer'] ?? null),
                ] : null;
            }, $this->blocks($content['faq_preview'] ?? null)))),
            'shop_information' => $shopInformation,
        ];
    }

    private function withCurrentAnnouncements(array $home): array
    {
        $now = now();
        $home['announcements'] = array_values(array_filter($home['announcements'] ?? [], function (array $announcement) use ($now) {
            foreach (['start_date', 'end_date'] as $field) {
                $value = $announcement[$field] ?? '';
                if ($value === '') {
                    continue;
                }
                if (! preg_match('/^\d{4}-\d{2}-\d{2}(?:$|[ T])/', $value)) {
                    return false;
                }
                try {
                    $date = Carbon::parse($value);
                    if (strlen($value) === 10) {
                        $date = $field === 'start_date' ? $date->startOfDay() : $date->endOfDay();
                    }
                } catch (Throwable) {
                    return false;
                }
                if (($field === 'start_date' && $now->lt($date)) || ($field === 'end_date' && $now->gt($date))) {
                    return false;
                }
            }
            return true;
        }));

        return $home;
    }

    private function firstEnabled(mixed $value): ?array
    {
        foreach ($this->blocks($value) as $block) {
            if ($this->enabled($block)) {
                return $block;
            }
        }
        return null;
    }

    private function blocks(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        if (isset($value['component']) || isset($value['title']) || isset($value['heading'])) {
            return [$value];
        }
        return array_values(array_filter($value, 'is_array'));
    }

    private function enabled(array $block): bool
    {
        return ! array_key_exists('enabled', $block)
            || filter_var($block['enabled'], FILTER_VALIDATE_BOOLEAN);
    }

    private function references(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            $identifier = is_array($item)
                ? ($item['slug'] ?? $item['product_slug'] ?? $item['category_slug'] ?? $item['product_id'] ?? null)
                : $item;
            if (is_string($identifier) || is_int($identifier)) {
                $identifier = trim((string) $identifier);
                if ($identifier !== '' && preg_match('/^[a-z0-9][a-z0-9-]*$/i', $identifier)) {
                    $result[] = $identifier;
                }
            }
        }
        return array_values(array_unique($result));
    }

    private function plainText(mixed $value): string
    {
        if (is_string($value)) {
            return trim(mb_substr($value, 0, 5000));
        }
        if (is_array($value) && isset($value['content']) && is_array($value['content'])) {
            $parts = [];
            foreach ($value['content'] as $node) {
                $text = $this->plainText($node);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
            return implode("\n", $parts);
        }
        return is_array($value) && is_string($value['text'] ?? null)
            ? trim(mb_substr($value['text'], 0, 5000)) : '';
    }

    private function safeUrl(mixed $value, bool $externalOnly = false): ?string
    {
        if (is_array($value)) {
            $link = $value;
            $value = $link['filename'] ?? (($link['url'] ?? null) ?: ($link['cached_url'] ?? null));
            if (! $externalOnly && ($link['linktype'] ?? null) === 'story' && is_string($value)
                && preg_match('/^[a-z0-9][a-z0-9\/_-]*$/i', $value)) {
                $value = '/'.$value;
            }
        }
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x1f\x7f\\\\]/', $value)) {
            return null;
        }
        if (! $externalOnly && str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }
        $parts = parse_url($value);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
            ? $value : null;
    }
}
