<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'slug', 'brand', 'condition', 'grade', 'price', 'compare_price',
        'stock', 'sku', 'short_description', 'description', 'specifications', 'image',
        'featured', 'active', 'warranty_note', 'model_name', 'images',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'specifications' => 'array',
            'images' => 'array',
            'featured' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function publishedReviews()
    {
        return $this->reviews()->where('status', 'published');
    }

    public function getGalleryUrlsAttribute(): array
    {
        $all = array_merge([$this->image], $this->images ?? []);
        $all = array_values(array_unique(array_filter($all, fn ($value) => is_string($value) && trim($value) !== '')));

        return $all ? array_map(fn ($value) => $this->imagePathToUrl($value), $all)
            : [asset('images/products/phone-blue.svg')];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getConditionLabelAttribute(): string
    {
        return match ($this->condition) {
            'brand_new' => 'Brand New',
            'pre_owned' => 'Pre-Owned',
            'refurbished' => 'Refurbished',
            default => ucfirst(str_replace('_', ' ', $this->condition)),
        };
    }

    public function getAvailableStockAttribute(): int
    {
        return max(0, (int) $this->stock - (int) $this->reserved_stock);
    }

    public function getStockLabelAttribute(): string
    {
        if ($this->available_stock === 0) {
            return 'Out of Stock';
        }

        return $this->available_stock <= (int) config('mobile-arena.inventory.low_stock_threshold', 3)
            ? 'Only '.$this->available_stock.' left' : 'In Stock';
    }

    public function getImageUrlAttribute(): string
    {
        if (blank($this->image)) {
            return asset('images/products/phone-blue.svg');
        }

        return $this->imagePathToUrl($this->image);
    }

    private function imagePathToUrl(string $path): string
    {
        return str_starts_with($path, 'https://') || str_starts_with($path, 'http://')
            ? $path : asset('images/'.$path);
    }
}
