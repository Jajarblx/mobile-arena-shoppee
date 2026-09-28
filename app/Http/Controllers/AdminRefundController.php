<?php

namespace App\Http\Controllers;

use App\Models\Refund;
use App\Services\RefundWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRefundController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        abort_unless($status === null || in_array($status, array_keys(Refund::STATUS_LABELS), true), 404);
        $refunds = Refund::with(['order.items', 'user', 'reviewer'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.refunds', compact('refunds', 'status'));
    }

    public function review(Request $request, Refund $refund, RefundWorkflow $workflow)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'approved', 'rejected'])],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'staff_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->review($refund, $request->user(), $data['status'], $data['amount'] ?? null, $data['staff_notes'] ?? null);

        return back()->with('success', 'Refund review updated.');
    }

    public function complete(Request $request, Refund $refund, RefundWorkflow $workflow)
    {
        $data = $request->validate([
            'return_disposition' => ['required', Rule::in(['sellable', 'damaged', 'not_returned'])],
            'staff_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->complete($refund, $request->user(), $data['return_disposition'], $data['staff_notes'] ?? null);

        return back()->with('success', 'Refund marked complete. Record any external transfer separately.');
    }
}
