# Storyblok content setup

Create a Storyblok story with the full slug `home` and component `home_page`. Laravel reads it through the server-side Content Delivery API. No Storyblok script or token is sent to the browser.

| `home_page` field | Field type | Component / value |
| --- | --- | --- |
| `hero` | Bloks, one item | `hero_section` |
| `promotional_banners` | Bloks | `promotional_banner` |
| `featured_categories` | Bloks | Category references with a `slug` text field |
| `featured_products` | Bloks | Product references with a `slug` text field; numeric Laravel IDs also work |
| `repair_promo` | Bloks, one item | `repair_promo` |
| `announcements` | Bloks | `announcement` |
| `faq_preview` | Bloks | `faq_item` |
| `shop_information` | Bloks, one item | `shop_information` |

## Components and fields

- `hero_section`: `eyebrow`, `headline`, `subheadline`, `primary_cta_label`, `primary_cta_url`, `secondary_cta_label`, `secondary_cta_url` (text or Storyblok link), `image` (asset), `enabled` (boolean).
- `promotional_banner`: `title`, `description`, `cta_label`, `cta_url` (text or link), `image` (asset), `enabled` (boolean).
- Category and product reference components: `slug` (text). Use the Laravel slugs from the local catalog. Duplicate, inactive, and missing product references are ignored. The reference order determines display order, up to eight products.
- `repair_promo`: `heading`, `description`, `cta_label`, `cta_url` (text or link), `image` (asset), `enabled` (boolean).
- `announcement`: `title`, `description`, `enabled` (boolean), `start_date` and `end_date` (optional dates in `YYYY-MM-DD` format or timestamps). Date-only end dates include the full day in the Laravel app timezone.
- `faq_item`: `question`, `answer` (text or rich text), `enabled` (boolean). Rich text is displayed as plain text.
- `shop_information`: `description`, `about`, `opening_hours`, `contact_information`, `location_text` (text), `social_links` (Bloks), `enabled` (boolean).
- Social link component: `label` (text), `url` (HTTPS link).

Text is escaped in Blade. CTA links can be root-relative paths or HTTPS URLs. Image and social URLs must use HTTPS. The homepage uses its existing content when an optional field is missing, disabled, or unavailable. The shared footer uses Storyblok shop details on the homepage and its existing text on other pages.

## Laravel configuration

Copy the three `STORYBLOK_*` entries from `.env.example` into the private `.env`. Set `STORYBLOK_ACCESS_TOKEN` to the space's preview token when `STORYBLOK_VERSION=draft`; use a public delivery token with `STORYBLOK_VERSION=published`. The default `STORYBLOK_CACHE_TTL=300` caches normalized, public CMS content for five minutes. Set it to `0` to disable caching. Changes appear after the TTL expires; there is no webhook invalidation yet.

Storyblok selects catalog references and supplies marketing copy only. Laravel remains the source of truth for products, prices, stock, ratings, accounts, carts, orders, payments, refunds, reviews, addresses, repairs, notifications, and audit data.
