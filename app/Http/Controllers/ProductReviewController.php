<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Services\ReviewEligibility;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends Controller
{
    public function store(Request $request, Product $product, ReviewEligibility $eligibility)
    {
        $data = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        try {
            DB::transaction(function () use ($request, $product, $eligibility, $data) {
                $item = OrderItem::with('order')->whereKey($data['order_item_id'])->lockForUpdate()->firstOrFail();
                if (! $eligibility->ownsCompletedItem($request->user(), $product, $item)) {
                    throw ValidationException::withMessages(['order_item_id' => 'Only a completed purchase of this product can be reviewed.']);
                }
                if ($item->review()->exists()) {
                    throw ValidationException::withMessages(['order_item_id' => 'This purchased item already has a review.']);
                }
                $review = new Review(collect($data)->only(['rating', 'title', 'body'])->all());
                $review->user()->associate($request->user());
                $review->product()->associate($product);
                $review->orderItem()->associate($item);
                $review->is_verified_purchase = true;
                $review->status = 'published';
                $review->save();
            }, 3);
        } catch (QueryException $exception) {
            if (OrderItem::find($data['order_item_id'])?->review()->exists()) {
                throw ValidationException::withMessages(['order_item_id' => 'This purchased item already has a review.']);
            }
            throw $exception;
        }

        return redirect()->to($product->active ? route('products.show', $product).'#reviews' : route('account.purchases'))
            ->with('success', 'Your review has been posted.');
    }

    public function edit(Request $request, Review $review)
    {
        abort_unless($review->user_id === $request->user()->id, 404);
        $review->load('product');

        return view('account.review-edit', compact('review'));
    }

    public function update(Request $request, Review $review)
    {
        abort_unless($review->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:3000'],
        ]);
        $review->update($data);

        return redirect()->to($review->product?->active
            ? route('products.show', $review->product).'#review-'.$review->id
            : route('account.purchases'))->with('success', 'Your review has been updated.');
    }

    public function destroy(Request $request, Review $review)
    {
        abort_unless($review->user_id === $request->user()->id, 404);
        $product = $review->product;
        $review->delete();

        return redirect()->to($product?->active ? route('products.show', $product).'#reviews' : route('account.purchases'))
            ->with('success', 'Your review has been removed.');
    }
}
