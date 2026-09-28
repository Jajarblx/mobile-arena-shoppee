<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\AuditTrail;
use App\Services\InventoryLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminProductController extends Controller
{
    private const EDITABLE = [
        'category_id', 'name', 'slug', 'brand', 'model_name', 'condition', 'grade',
        'price', 'compare_price', 'sku', 'short_description', 'description',
        'specifications', 'image', 'images', 'featured', 'active', 'warranty_note',
    ];

    public function index()
    {
        return view('admin.products.index', [
            'products' => Product::with('category')->orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product(['active' => true]),
            'categories' => Category::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, InventoryLedger $inventory, AuditTrail $audit)
    {
        $data = $this->validatedData($request);
        $stockData = $request->validate([
            'opening_stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'stock_reason' => [Rule::requiredIf((int) $request->input('opening_stock') > 0), 'nullable', 'string', 'min:3', 'max:500'],
        ]);
        if ((int) $stockData['opening_stock'] > 0 && mb_strlen(trim((string) ($stockData['stock_reason'] ?? ''))) < 3) {
            throw ValidationException::withMessages(['stock_reason' => 'Explain the opening inventory when stock is greater than zero.']);
        }

        $product = DB::transaction(function () use ($request, $data, $stockData, $inventory, $audit) {
            $product = Product::create([...$data, 'stock' => 0])->refresh();
            $audit->record($request->user(), 'product.created', $product, [], $product->only(self::EDITABLE));
            if ((int) $stockData['opening_stock'] > 0) {
                $before = clone $product;
                $product->update(['stock' => (int) $stockData['opening_stock']]);
                $inventory->record($before, $product, 'adjustment', $request->user(), $product,
                    'Opening inventory: '.trim($stockData['stock_reason']));
                $audit->record($request->user(), 'product.stock_adjusted', $product,
                    ['stock' => 0], ['stock' => $product->stock], trim($stockData['stock_reason']));
            }

            return $product;
        }, 3);

        return redirect()->route('admin.products.edit', $product)->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product->load('category'),
            'categories' => Category::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Product $product, AuditTrail $audit)
    {
        $data = $this->validatedData($request, $product);
        DB::transaction(function () use ($request, $product, $data, $audit) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(self::EDITABLE);
            $locked->fill($data);
            if (! $locked->isDirty()) {
                return;
            }
            $locked->save();
            $audit->record($request->user(), 'product.updated', $locked,
                $before, $locked->only(self::EDITABLE));
        }, 3);

        return redirect()->route('admin.products.edit', $product->fresh())->with('success', 'Product details updated.');
    }

    private function validatedData(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255', Rule::unique('products', 'slug')->ignore($product?->id)],
            'brand' => ['required', 'string', 'max:120'],
            'model_name' => ['nullable', 'string', 'max:150'],
            'condition' => ['required', Rule::in(['brand_new', 'pre_owned', 'refurbished'])],
            'grade' => ['nullable', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'compare_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'sku' => ['required', 'string', 'max:120', Rule::unique('products', 'sku')->ignore($product?->id)],
            'short_description' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'warranty_note' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:255'],
            'images_text' => ['nullable', 'string', 'max:2500'],
            'specifications_text' => ['nullable', 'string', 'max:5000'],
            'featured' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ]);

        foreach ([$data['image'] ?? null, ...preg_split('/\r?\n/', trim($data['images_text'] ?? ''))] as $source) {
            if ($source !== null && trim($source) !== '' && ! $this->validImageSource(trim($source))) {
                throw ValidationException::withMessages(['image' => 'Use an HTTPS image URL or a safe path under public/images.']);
            }
        }
        $images = array_values(array_unique(array_filter(array_map('trim', preg_split('/\r?\n/', $data['images_text'] ?? '')))));
        if (count($images) > 6) {
            throw ValidationException::withMessages(['images_text' => 'Add no more than six gallery images.']);
        }
        $specifications = [];
        foreach (preg_split('/\r?\n/', $data['specifications_text'] ?? '') as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === ''
                || mb_strlen(trim($parts[0])) > 80 || mb_strlen(trim($parts[1])) > 300) {
                throw ValidationException::withMessages(['specifications_text' => 'Write each specification as Label: Value.']);
            }
            $specifications[trim($parts[0])] = trim($parts[1]);
        }

        $slug = $data['slug'] ?? null;
        if (! $slug && $product) {
            $slug = $product->slug;
        }
        if (! $slug) {
            $base = Str::slug($data['name']) ?: 'product';
            $slug = $base;
            $number = 2;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $base.'-'.$number++;
            }
        }

        return [
            ...collect($data)->except(['images_text', 'specifications_text'])->all(),
            'slug' => $slug,
            'image' => ($data['image'] ?? null) ? trim($data['image']) : null,
            'images' => $images ?: null,
            'specifications' => $specifications ?: null,
            'featured' => (bool) $data['featured'],
            'active' => (bool) $data['active'],
        ];
    }

    private function validImageSource(string $source): bool
    {
        if (str_starts_with($source, 'https://')) {
            return mb_strlen($source) <= 255 && filter_var($source, FILTER_VALIDATE_URL) !== false;
        }

        return mb_strlen($source) <= 255 && ! str_contains($source, '..')
            && preg_match('~^[a-zA-Z0-9][a-zA-Z0-9/_\-.]*$~', $source) === 1;
    }
}
