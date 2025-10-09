<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Database\Concerns\InteractsWithJsonLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditTrailPageController extends Controller
{
    use InteractsWithJsonLike;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $perPage = max(5, min(100, $request->integer('per_page', 25)));

        $query = AuditLog::query()->with('user');
        $query = $this->applyFilters($query, $request);

        $append = $request->only([
            'action',
            'user_id',
            'auditable_type',
            'ip_address',
            'date_from',
            'date_to',
            'search',
            'per_page',
        ]);

        $logs = $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($append)
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user?->only(['id', 'nome', 'username']),
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'payload' => $log->payload,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        $filters = [
            'action' => $request->input('action'),
            'user_id' => $request->input('user_id'),
            'auditable_type' => $request->input('auditable_type'),
            'ip_address' => $request->input('ip_address'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
            'per_page' => $perPage,
        ];

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->values();

        $resourceTypes = AuditLog::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->filter()
            ->values();

        $users = User::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'username']);

        return Inertia::render('Auditoria/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => $actions,
            'resourceTypes' => $resourceTypes,
            'users' => $users,
            'canExport' => $request->user()->hasPermission('auditoria.export'),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('auditable_type'), fn ($q) => $q->where('auditable_type', $request->string('auditable_type')))
            ->when($request->filled('ip_address'), fn ($q) => $q->where('ip_address', $request->string('ip_address')))
            ->when(
                $request->filled('date_from'),
                fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')->toDateString())
            )
            ->when(
                $request->filled('date_to'),
                fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')->toDateString())
            )
            ->when($request->filled('search'), function ($q) use ($request) {
                $raw = (string) $request->string('search');
                $clean = trim(str_replace('%', '', $raw));

                if ($clean === '') {
                    return;
                }

                $term = '%'.mb_strtolower($clean).'%';

                $q->where(function ($inner) use ($term) {
                    $inner->whereRaw('LOWER(action) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(user_agent) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(ip_address) LIKE ?', [$term]);

                    $this->orWhereJsonContainsLike($inner, 'payload', $term);
                });
            });
    }
}
