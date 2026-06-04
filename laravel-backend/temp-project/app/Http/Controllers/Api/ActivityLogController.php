<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::latest();

        if ($request->filled('user')) {
            $query->where(function ($q) use ($request) {
                $q->where('user_name', 'like', "%{$request->user}%")
                  ->orWhere('user_id', $request->user);
            });
        }
        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }
        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->resource_type);
        }

        $logs = $query->paginate($request->per_page ?? 50);

        return response()->json(['data' => $logs]);
    }

    public function export(Request $request)
    {
        $query = ActivityLog::latest();

        if ($request->filled('user')) {
            $query->where(function ($q) use ($request) {
                $q->where('user_name', 'like', "%{$request->user}%")
                  ->orWhere('user_id', $request->user);
            });
        }
        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        $logs = $query->limit(10000)->get();

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'User', 'Role', 'Action', 'Resource', 'Resource ID', 'Details', 'Created At']);
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->user_name ?? $log->user_id,
                    $log->user_role ?? '',
                    $log->action ?? $log->event,
                    $log->resource_type ?? '',
                    $log->resource_id ?? '',
                    is_string($log->details) ? $log->details : json_encode($log->details),
                    $log->created_at->toDateTimeString(),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="activity-log.csv"',
        ]);
    }
}
