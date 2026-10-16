<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $action = (string) $request->query('action', '');

        $query = AuditLog::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($action !== '') {
            $query->where('action', $action);
        }

        $logs = $query->paginate(20)->withQueryString();

        return Inertia::render('admin/audit/Index', [
            'logs' => $logs->getCollection()->map(fn (AuditLog $log): array => $this->payload($log))->values()->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
                'prev_page_url' => $logs->previousPageUrl(),
                'next_page_url' => $logs->nextPageUrl(),
            ],
            'actions' => AuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->all(),
            'filter' => ['action' => $action],
        ]);
    }

    /**
     * @return array{id: int, action: string, time: string, user: string, object: string, old_values: array<string, mixed>|null, new_values: array<string, mixed>|null}
     */
    private function payload(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'time' => $log->created_at->format('d/m/Y H:i:s'),
            'user' => $log->user->name ?? 'Pengguna terhapus',
            'object' => $log->auditable_type === null
                ? '-'
                : Str::headline(class_basename($log->auditable_type)).' #'.$log->auditable_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
        ];
    }
}
