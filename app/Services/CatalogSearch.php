<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogSearch
{
    public function filters(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:120'],
            'condition' => ['nullable', Rule::in(['brand_new', 'pre_owned', 'refurbished'])],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'in_stock' => ['nullable', Rule::in(['1'])],
            'min_rating' => ['nullable', Rule::in(['3', '4'])],
            'sort' => ['nullable', Rule::in(['relevance', 'newest', 'price_asc', 'price_desc', 'rating', 'name'])],
        ]);

        $filters['q'] = trim($filters['q'] ?? $filters['search'] ?? '');
        if (isset($filters['min_price'], $filters['max_price']) && (float) $filters['min_price'] > (float) $filters['max_price']) {
            throw ValidationException::withMessages(['max_price' => 'Maximum price must be at least the minimum price.']);
        }

        return $filters;
    }

    public function query(array $filters): Builder
    {
        $query = Product::query()->with('category')->where('active', true)
            ->withCount('publishedReviews as reviews_count')
            ->withAvg('publishedReviews as average_rating', 'rating');

        if ($filters['q'] !== '') {
            $term = mb_strtolower($filters['q']);
            $conditionMatch = match ($term) {
                'new', 'brand new' => 'brand_new',
                'used', 'second hand', 'second-hand', 'pre owned', 'pre-owned' => 'pre_owned',
                'refurbished', 'refurb' => 'refurbished',
                default => null,
            };
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
            $pattern = '%'.$term.'%';
            $query->where(function (Builder $builder) use ($pattern, $conditionMatch) {
                foreach (['name', 'brand', 'model_name', 'short_description', 'condition'] as $field) {
                    $builder->orWhereRaw("LOWER(products.{$field}) LIKE ? ESCAPE '!'", [$pattern]);
                }
                $builder->orWhereHas('category', fn (Builder $category) => $category
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]));
                if ($conditionMatch) {
                    $builder->orWhere('condition', $conditionMatch);
                }
            });
        }

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('slug', $filters['category']));
        }
        if (! empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }
        if (! empty($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        if (! empty($filters['in_stock'])) {
            $query->whereColumn('stock', '>', 'reserved_stock');
        }
        if (! empty($filters['min_rating'])) {
            $query->whereHas('publishedReviews')
                ->whereRaw('(SELECT AVG(rating) FROM reviews WHERE reviews.product_id = products.id AND reviews.status = ?) >= ?',
                    ['published', (int) $filters['min_rating']]);
        }

        match ($filters['sort'] ?? 'relevance') {
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderBy('id'),
            'newest' => $query->latest()->orderByDesc('id'),
            'rating' => $query->orderByDesc('average_rating')->orderByDesc('reviews_count')->orderByDesc('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            default => $query->orderByDesc('featured')->latest()->orderByDesc('id'),
        };

        return $query;
    }
}
