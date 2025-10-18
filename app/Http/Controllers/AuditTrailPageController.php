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
            'auditable_id',
            'ip_address',
            'guard',
            'origin',
            'http_method',
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
                'context' => $log->context,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        $filters = [
            'action' => $request->input('action'),
            'user_id' => $request->input('user_id'),
            'auditable_type' => $request->input('auditable_type'),
            'auditable_id' => $request->input('auditable_id'),
            'ip_address' => $request->input('ip_address'),
            'guard' => $request->input('guard'),
            'origin' => $request->input('origin'),
            'http_method' => $request->input('http_method'),
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

        $guards = AuditLog::query()
            ->whereNotNull('context->guard')
            ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(context, '$.guard')) AS guard")
            ->orderBy('guard')
            ->pluck('guard')
            ->filter()
            ->values();

        $origins = AuditLog::query()
            ->whereNotNull('context->origin')
            ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(context, '$.origin')) AS origin")
            ->orderBy('origin')
            ->pluck('origin')
            ->filter()
            ->values();

        $requestMethods = AuditLog::query()
            ->whereNotNull('context->http_method')
            ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(context, '$.http_method')) AS method")
            ->orderBy('method')
            ->pluck('method')
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
            'guards' => $guards,
            'origins' => $origins,
            'requestMethods' => $requestMethods,
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
            ->when($request->filled('auditable_id'), fn ($q) => $q->where('auditable_id', $request->integer('auditable_id')))
            ->when($request->filled('ip_address'), fn ($q) => $q->where('ip_address', $request->string('ip_address')))
            ->when($request->filled('guard'), fn ($q) => $q->where('context->guard', $request->string('guard')))
            ->when($request->filled('origin'), fn ($q) => $q->where('context->origin', $request->string('origin')))
            ->when($request->filled('http_method'), fn ($q) => $q->where('context->http_method', $request->string('http_method')))
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
                    $this->orWhereJsonContainsLike($inner, 'context', $term);
                });
            });
    }
}
