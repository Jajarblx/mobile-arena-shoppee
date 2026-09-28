<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminReviewController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['published', 'hidden'])]]);
        $status = $data['status'] ?? null;
        $reviews = Review::with(['user', 'product', 'orderItem.order'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.reviews', compact('reviews', 'status'));
    }

    public function moderate(Request $request, Review $review, AuditTrail $audit)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'hidden'])]]);
        DB::transaction(function () use ($request, $review, $data, $audit) {
            $locked = Review::whereKey($review->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $data['status']) {
                return;
            }
            $before = $locked->status;
            $locked->status = $data['status'];
            $locked->save();
            $audit->record($request->user(), $locked->status === 'hidden' ? 'review.hidden' : 'review.restored',
                $locked, ['status' => $before], ['status' => $locked->status]);
        }, 3);

        return back()->with('success', 'Review visibility updated.');
    }
}
