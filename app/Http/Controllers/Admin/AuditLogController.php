<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = AdminAuditLog::query()->with('admin')->latest();

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('admin_name', 'like', "%{$search}%");
            });
        }

        return view('admin.audit', [
            'logs' => $query->paginate(30)->withQueryString(),
            'actions' => AdminAuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'activeAction' => $action,
            'search' => $search,
        ]);
    }
}
