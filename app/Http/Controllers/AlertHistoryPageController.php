<?php

namespace App\Http\Controllers;

use App\Http\Requests\Alert\AlertHistoryFilterRequest;
use App\Http\Resources\Alerts\DashboardAlertResource;
use App\Models\DashboardAlert;
use Inertia\Inertia;
use Inertia\Response;

class AlertHistoryPageController extends Controller
{
    public function __invoke(AlertHistoryFilterRequest $request): Response
    {
        $this->authorize('viewAny', DashboardAlert::class);

        $query = DashboardAlert::query()->with('resolvedBy')->orderByDesc('occurred_at');

        $status = $request->input('status', 'open');
        if ($status === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            if ($search !== '') {
                $query->where(function ($inner) use ($search) {
                    $like = '%'.strtolower($search).'%';
                    $inner->whereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(message) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(key) LIKE ?', [$like]);
                });
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('occurred_at', '>=', $request->date('date_from')->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('occurred_at', '<=', $request->date('date_to')->toDateString());
        }

        $perPage = min(max($request->integer('per_page', 15), 5), 100);

        $alerts = $query->paginate($perPage)->appends($request->validated());

        $resource = DashboardAlertResource::collection($alerts);
        $alertsPayload = $resource->response()->getData(true);

        $categories = DashboardAlert::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();

        $severities = DashboardAlert::query()
            ->select('severity')
            ->distinct()
            ->orderBy('severity')
            ->pluck('severity')
            ->filter()
            ->values();

        return Inertia::render('Alerts/History', [
            'alerts' => $alertsPayload,
            'filters' => [
                'status' => $status,
                'category' => $request->input('category'),
                'severity' => $request->input('severity'),
                'search' => $request->input('search'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'per_page' => $perPage,
            ],
            'categories' => $categories,
            'severities' => $severities,
            'canResolve' => $request->user()?->hasPermission('alerts.resolve') ?? false,
        ]);
    }
}
