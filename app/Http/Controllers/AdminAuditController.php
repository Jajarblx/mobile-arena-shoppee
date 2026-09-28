<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer'],
            'entity_type' => ['nullable', 'in:Order,Product,Refund,RepairBooking,Review'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $logs = AuditLog::with('actor')
            ->when($data['action'] ?? null, fn ($query, $value) => $query->where('action', $value))
            ->when($data['user_id'] ?? null, fn ($query, $value) => $query->where('user_id', $value))
            ->when($data['entity_type'] ?? null, fn ($query, $value) => $query->where('auditable_type', $value))
            ->when($data['date'] ?? null, fn ($query, $value) => $query->whereDate('created_at', $value))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
            'actors' => User::whereIn('id', AuditLog::whereNotNull('user_id')->select('user_id'))->orderBy('name')->get(),
        ]);
    }
}
