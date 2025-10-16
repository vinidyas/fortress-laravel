<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Http\Requests\Dashboard\DashboardWidgetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardWidgetPreferenceController extends Controller
{
    public function update(DashboardWidgetRequest $request): JsonResponse
    {
        $user = $request->user();

        $definitions = DashboardController::WIDGET_DEFINITIONS;
        $payload = collect($request->input('widgets', []))
            ->filter(fn ($item) => isset($item['key']))
            ->unique('key')
            ->map(fn ($item) => [
                'key' => (string) $item['key'],
                'hidden' => (bool) ($item['hidden'] ?? false),
                'position' => (int) ($item['position'] ?? 0),
            ]);

        DB::transaction(function () use ($user, $definitions, $payload) {
            $allowedKeys = array_keys($definitions);

            // Remove widgets that are no longer allowed
            $user->dashboardWidgets()
                ->whereNotIn('widget_key', $allowedKeys)
                ->delete();

            foreach ($allowedKeys as $index => $key) {
                $data = $payload->firstWhere('key', $key);

                if (! $data) {
                    $user->dashboardWidgets()
                        ->where('widget_key', $key)
                        ->delete();

                    continue;
                }

                $user->dashboardWidgets()->updateOrCreate(
                    ['widget_key' => $key],
                    [
                        'position' => $data['position'] ?? $index,
                        'hidden' => $data['hidden'],
                    ],
                );
            }
        });

        return response()->json([
            'message' => 'Preferências do dashboard atualizadas.',
        ]);
    }
}
