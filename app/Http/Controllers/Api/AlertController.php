<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alert\DismissAlertsRequest;
use App\Models\UserAlert;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    public function dismiss(DismissAlertsRequest $request): JsonResponse
    {
        $user = $request->user();
        $keys = collect($request->input('keys', []))
            ->filter()
            ->unique()
            ->values();

        $existing = UserAlert::query()
            ->where('user_id', $user->getKey())
            ->whereIn('alert_key', $keys)
            ->pluck('alert_key')
            ->all();

        $now = now();

        $keys
            ->diff($existing)
            ->each(function (string $key) use ($user, $now) {
                UserAlert::query()->create([
                    'user_id' => $user->getKey(),
                    'alert_key' => $key,
                    'read_at' => $now,
                ]);
            });

        return response()->json([
            'message' => 'Alertas marcados como lidos.',
        ]);
    }
}
