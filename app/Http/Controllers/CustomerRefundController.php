<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\RefundWorkflow;
use Illuminate\Http\Request;

class CustomerRefundController extends Controller
{
    public function index(Request $request)
    {
        return view('account.refunds', [
            'refunds' => $request->user()->refunds()->with('order')->latest()->paginate(10),
        ]);
    }

    public function store(Request $request, Order $order, RefundWorkflow $workflow)
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $workflow->request($request->user(), $order, $data['reason']);

        return redirect()->route('account.orders.show', $order)->with('success', 'Your refund request has been submitted.');
    }
}
