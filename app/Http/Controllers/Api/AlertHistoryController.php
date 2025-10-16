<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alert\AlertHistoryFilterRequest;
use App\Http\Requests\Alert\AlertResolveRequest;
use App\Http\Resources\Alerts\DashboardAlertResource;
use App\Models\DashboardAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AlertHistoryController extends Controller
{
    public function index(AlertHistoryFilterRequest $request): AnonymousResourceCollection
    {
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

        return DashboardAlertResource::collection(
            $query->paginate($perPage)->appends($request->validated())
        );
    }

    public function resolve(AlertResolveRequest $request, DashboardAlert $dashboardAlert): DashboardAlertResource|JsonResponse
    {
        if ($dashboardAlert->resolved_at) {
            $dashboardAlert->forceFill([
                'resolution_notes' => $request->input('notes', $dashboardAlert->resolution_notes),
            ])->save();

            return new DashboardAlertResource($dashboardAlert->refresh()->load('resolvedBy'));
        }

        $dashboardAlert->forceFill([
            'resolved_at' => now(),
            'resolved_by' => optional($request->user())->getKey(),
            'resolution_notes' => $request->input('notes'),
        ])->save();

        return new DashboardAlertResource($dashboardAlert->refresh()->load('resolvedBy'));
    }
}
