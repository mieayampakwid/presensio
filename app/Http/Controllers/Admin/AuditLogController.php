<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): Response
    {
        $query = AuditLog::query()->with('user:id,username')->latest('created_at')->latest('id');

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->string('auditable_type')->toString());
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->integer('auditable_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from')->toString());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to')->toString());
        }

        $logs = $query->paginate(25)->withQueryString();

        return Inertia::render('admin/audit-logs/index', [
            'logs' => $logs,
            'filters' => $request->only(['auditable_type', 'auditable_id', 'user_id', 'from', 'to']),
        ]);
    }
}
