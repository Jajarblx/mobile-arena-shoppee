<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Smartphones', 'slug' => 'smartphones', 'icon' => 'phone', 'sort_order' => 1],
            ['name' => 'Tablets', 'slug' => 'tablets', 'icon' => 'tablet', 'sort_order' => 2],
            ['name' => 'Accessories', 'slug' => 'accessories', 'icon' => 'cable', 'sort_order' => 3],
            ['name' => 'Wearables', 'slug' => 'wearables', 'icon' => 'watch', 'sort_order' => 4],
        ])->mapWithKeys(fn ($data) => [$data['slug'] => Category::create($data)]);

        $products = [
            [
                'category' => 'smartphones', 'name' => 'iPhone 13 128GB', 'slug' => 'iphone-13-128gb-brand-new',
                'brand' => 'Apple', 'condition' => 'brand_new', 'grade' => null, 'price' => 29990, 'compare_price' => 32990,
                'stock' => 5, 'sku' => 'MA-IP13-128-BN', 'image' => 'https://commons.wikimedia.org/wiki/Special:Redirect/file/IPhone%2013.jpg?width=900', 'featured' => true,
                'short_description' => 'Demo listing for a sealed, brand-new smartphone unit.',
                'description' => 'A prototype product listing showing how Mobile Arena can present brand-new stock with clear warranty, condition, stock and specification information.',
                'warranty_note' => 'Prototype warranty text — replace with Mobile Arena’s actual warranty policy.',
                'specifications' => ['Storage' => '128GB', 'Display' => '6.1-inch', 'Network' => '5G', 'Color' => 'Blue'],
            ],
            [
                'category' => 'smartphones', 'name' => 'iPhone 12 128GB', 'slug' => 'iphone-12-128gb-pre-owned',
                'brand' => 'Apple', 'condition' => 'pre_owned', 'grade' => 'Grade A', 'price' => 18990, 'compare_price' => 21990,
                'stock' => 2, 'sku' => 'MA-IP12-128-PO', 'image' => 'https://commons.wikimedia.org/wiki/Special:Redirect/file/IPhone%2012%20Black%20256g.jpg?width=900', 'featured' => true,
                'short_description' => 'Pre-owned demo unit with a condition grade and inspection notes.',
                'description' => 'This prototype demonstrates transparent second-hand listings. Actual battery health, cosmetic condition, inclusions and warranty should be entered per physical unit before publishing.',
                'warranty_note' => 'Prototype: unit-specific warranty and inspection details must be confirmed by store staff.',
                'specifications' => ['Storage' => '128GB', 'Condition' => 'Grade A', 'Inspection' => 'Demo: tested basic functions', 'Inclusions' => 'Demo: unit + cable'],
            ],
            [
                'category' => 'smartphones', 'name' => 'Galaxy A56 5G', 'slug' => 'galaxy-a56-5g-brand-new',
                'brand' => 'Samsung', 'condition' => 'brand_new', 'grade' => null, 'price' => 23990, 'compare_price' => 25990,
                'stock' => 6, 'sku' => 'MA-SA56-BN', 'image' => 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Samsung%20Galaxy%20A56%205G%202025%20%282%29.jpg?width=900', 'featured' => true,
                'short_description' => 'Brand-new Android phone demo listing with stock visibility.',
                'description' => 'Sample catalog content for a modern Android phone. Replace demo pricing and availability with Mobile Arena’s actual inventory before public launch.',
                'warranty_note' => 'Prototype warranty text — verify actual store/manufacturer coverage.',
                'specifications' => ['Network' => '5G', 'Storage' => '128GB', 'SIM' => 'Dual SIM', 'Condition' => 'Brand New'],
            ],
            [
                'category' => 'smartphones', 'name' => 'Galaxy S23 256GB', 'slug' => 'galaxy-s23-256gb-refurbished',
                'brand' => 'Samsung', 'condition' => 'refurbished', 'grade' => 'Refurbished A', 'price' => 24990, 'compare_price' => 28990,
                'stock' => 3, 'sku' => 'MA-S23-RF', 'image' => 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Back%20of%20the%20Samsung%20Galaxy%20S23.jpg?width=900', 'featured' => true,
                'short_description' => 'Refurbished demo unit with condition grading and service history fields.',
                'description' => 'Prototype refurbished listing designed to disclose refurbishment status, inspection results, inclusions and warranty notes clearly.',
                'warranty_note' => 'Prototype: replace with the actual refurbishment warranty and work performed.',
                'specifications' => ['Storage' => '256GB', 'Condition' => 'Refurbished A', 'Inspection' => 'Demo multi-point check', 'Network' => '5G'],
            ],
            [
                'category' => 'smartphones', 'name' => 'Redmi Note Series 256GB', 'slug' => 'redmi-note-series-256gb',
                'brand' => 'Xiaomi', 'condition' => 'brand_new', 'grade' => null, 'price' => 12990, 'compare_price' => 13990,
                'stock' => 8, 'sku' => 'MA-RN-256-BN', 'image' => 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Redmi%20Note%2014%20-%202024-09-27%2001.jpg?width=900', 'featured' => true,
                'short_description' => 'Value-focused brand-new Android demo listing.',
                'description' => 'Sample inventory entry for the prototype. Exact model, memory variant, price and stock should be imported from Mobile Arena’s real inventory.',
                'warranty_note' => 'Prototype warranty text — verify before launch.',
                'specifications' => ['Storage' => '256GB', 'Network' => '4G/5G varies by model', 'Condition' => 'Brand New'],
            ],
            [
                'category' => 'smartphones', 'name' => 'Vivo V Series 256GB', 'slug' => 'vivo-v-series-256gb',
                'brand' => 'Vivo', 'condition' => 'brand_new', 'grade' => null, 'price' => 17999, 'compare_price' => 19999,
                'stock' => 4, 'sku' => 'MA-VIVO-V-BN', 'image' => 'https://asia-exstatic-vivofs.vivo.com/PSee2l50xoirPK7y/1740648602489/8407ffaf300f069cdccc58238682afc0.png', 'featured' => true,
                'short_description' => 'Brand-new Vivo demo listing for the Mobile Arena catalog.',
                'description' => 'Vivo is included as a sample brand in the prototype. Public merchant listings show Mobile Arena Cellphone and Accessories at Xentro Mall Calapan, but this exact stock item is not claimed as current store inventory.',
                'warranty_note' => 'Prototype warranty text — verify before launch.',
                'specifications' => ['Storage' => '256GB', 'Condition' => 'Brand New', 'Inventory' => 'Demo only'],
            ],
            [
                'category' => 'tablets', 'name' => 'iPad 10th Gen 64GB', 'slug' => 'ipad-10th-gen-pre-owned',
                'brand' => 'Apple', 'condition' => 'pre_owned', 'grade' => 'Grade B+', 'price' => 19990, 'compare_price' => 22990,
                'stock' => 1, 'sku' => 'MA-IPAD10-PO', 'image' => 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Ipadtenthgen.jpg?width=900', 'featured' => false,
                'short_description' => 'Pre-owned tablet demo listing with transparent condition grading.',
                'description' => 'A sample second-hand tablet listing intended to demonstrate per-unit condition and inspection disclosure.',
                'warranty_note' => 'Prototype: warranty and inclusions must be confirmed per unit.',
                'specifications' => ['Storage' => '64GB', 'Condition' => 'Grade B+', 'Inventory' => 'Demo only'],
            ],
            [
                'category' => 'accessories', 'name' => '20W USB-C Fast Charger', 'slug' => '20w-usb-c-fast-charger',
                'brand' => 'Mobile Arena', 'condition' => 'brand_new', 'grade' => null, 'price' => 699, 'compare_price' => 899,
                'stock' => 15, 'sku' => 'MA-ACC-20W', 'image' => 'https://commons.wikimedia.org/wiki/Special:Redirect/file/SAMSUNG%20EP-TA800%2025W%20POWER%20ADAPER%20WHITE%20%284%29.jpg?width=900', 'featured' => true,
                'short_description' => 'Demo accessory listing for chargers and everyday essentials.',
                'description' => 'Prototype accessory item. Replace branding, compatibility claims and pricing with actual store stock.',
                'warranty_note' => 'Prototype warranty text.',
                'specifications' => ['Output' => '20W demo', 'Connector' => 'USB-C', 'Inventory' => 'Demo only'],
            ],
            [
                'category' => 'accessories', 'name' => '10,000mAh Power Bank', 'slug' => '10000mah-power-bank',
                'brand' => 'Mobile Arena', 'condition' => 'brand_new', 'grade' => null, 'price' => 999, 'compare_price' => 1299,
                'stock' => 12, 'sku' => 'MA-ACC-PB10', 'image' => 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Powerbank%20Xiaomi%2C%201.jpg?width=900', 'featured' => true,
                'short_description' => 'Portable power demo listing for the accessories catalog.',
                'description' => 'Prototype accessory listing. Actual capacity, certifications and brand should be verified before publishing.',
                'warranty_note' => 'Prototype warranty text.',
                'specifications' => ['Capacity' => '10,000mAh demo', 'Inventory' => 'Demo only'],
            ],
            [
                'category' => 'wearables', 'name' => 'Smart Watch Series', 'slug' => 'smart-watch-series',
                'brand' => 'Mobile Arena', 'condition' => 'brand_new', 'grade' => null, 'price' => 1499, 'compare_price' => 1799,
                'stock' => 7, 'sku' => 'MA-WEAR-SW', 'image' => 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Mi%20Watch.jpg?width=900', 'featured' => false,
                'short_description' => 'Wearable demo listing with simple product specification support.',
                'description' => 'Prototype wearable listing; replace with actual brand/model and verified technical specifications.',
                'warranty_note' => 'Prototype warranty text.',
                'specifications' => ['Compatibility' => 'Demo: Android/iOS', 'Inventory' => 'Demo only'],
            ],
        ];

        foreach ($products as $data) {
            $category = $categories[$data['category']];
            unset($data['category']);
            Product::create(['category_id' => $category->id, ...$data, 'active' => true]);
        }
    }
}
