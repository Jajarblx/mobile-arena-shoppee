<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogSearch;
use App\Services\ReviewEligibility;
use App\Services\StoryblokService;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function home(Request $request, StoryblokService $storyblok)
    {
        $cms = $storyblok->homepage();
        $categories = Category::orderBy('sort_order')->get();
        if ($categoryReferences = ($cms['featured_categories'] ?? [])) {
            $categories = collect($categoryReferences)->map(fn ($slug) => $categories->firstWhere('slug', $slug))
                ->filter()->values();
        }

        if ($productReferences = ($cms['featured_products'] ?? [])) {
            $productReferences = array_slice($productReferences, 0, 8);
            $products = $this->ratedProducts()->where(function ($query) use ($productReferences) {
                $query->whereIn('slug', $productReferences);
                $ids = array_values(array_filter($productReferences, fn ($reference) => ctype_digit($reference)));
                if ($ids) {
                    $query->orWhereIn('id', $ids);
                }
            })->get();
            $featured = collect($productReferences)->map(fn ($reference) => $products->firstWhere('slug', $reference)
                ?? (ctype_digit($reference) ? $products->firstWhere('id', $reference) : null))
                ->filter()->unique('id')->values();
        } else {
            $featured = $this->ratedProducts()->where('featured', true)->take(8)->get();
        }
        $preOwned = $this->ratedProducts()->whereIn('condition', ['pre_owned', 'refurbished'])->take(6)->get();
        $wishlistIds = $this->wishlistIds($request, $featured->merge($preOwned)->pluck('id')->all());

        return view('home', compact('categories', 'featured', 'preOwned', 'wishlistIds', 'cms'));
    }

    public function products(Request $request, CatalogSearch $search)
    {
        $filters = $search->filters($request);
        $products = $search->query($filters)->paginate(12)->withQueryString();
        $categories = Category::whereHas('products', fn ($query) => $query->where('active', true))
            ->orderBy('sort_order')->get();
        $brands = Product::where('active', true)->select('brand')->distinct()->orderBy('brand')->pluck('brand');
        $conditions = Product::where('active', true)->distinct()->pluck('condition')->all();
        $wishlistIds = $this->wishlistIds($request, $products->getCollection()->pluck('id')->all());

        return view('products.index', compact('products', 'categories', 'brands', 'conditions', 'filters', 'wishlistIds'));
    }

    public function show(Request $request, Product $product, ReviewEligibility $eligibility)
    {
        abort_unless($product->active, 404);
        $product->load('category')->loadCount('publishedReviews as reviews_count')
            ->loadAvg('publishedReviews as average_rating', 'rating');
        $reviews = $product->publishedReviews()->with('user')->latest()->paginate(8, ['*'], 'reviews_page');
        $ratingDistribution = $product->publishedReviews()->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')->pluck('total', 'rating');
        $eligibleItems = $request->user() ? $eligibility->eligibleItems($request->user(), $product) : collect();
        $related = $this->ratedProducts()->whereKeyNot($product->id)
            ->where(function ($query) use ($product) {
                $query->where('category_id', $product->category_id)->orWhere('brand', $product->brand);
            })
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$product->category_id])
            ->orderByRaw('ABS(price - ?)', [$product->price])->take(4)->get();
        $wishlistIds = $this->wishlistIds($request, [$product->id, ...$related->pluck('id')->all()]);

        return view('products.show', compact('product', 'related', 'reviews', 'ratingDistribution', 'eligibleItems', 'wishlistIds'));
    }

    private function ratedProducts()
    {
        return Product::with('category')->where('active', true)
            ->withCount('publishedReviews as reviews_count')
            ->withAvg('publishedReviews as average_rating', 'rating');
    }

    private function wishlistIds(Request $request, array $productIds): array
    {
        return $request->user() && $productIds
            ? $request->user()->wishlistProducts()->whereIn('products.id', $productIds)->pluck('products.id')->all()
            : [];
    }
}
