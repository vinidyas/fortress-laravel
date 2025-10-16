<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auditoria\EntityAuditRequest;
use App\Http\Resources\Auditoria\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Contrato;
use App\Models\Imovel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntityAuditController extends Controller
{
    public function imovelTimeline(EntityAuditRequest $request, Imovel $imovel)
    {
        $this->authorize('view', $imovel);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $logs = $this->buildQuery($request, $imovel)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->validated());

        return AuditLogResource::collection($logs);
    }

    public function contratoTimeline(EntityAuditRequest $request, Contrato $contrato)
    {
        $this->authorize('view', $contrato);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $logs = $this->buildQuery($request, $contrato)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->validated());

        return AuditLogResource::collection($logs);
    }

    public function imovelExport(EntityAuditRequest $request, Imovel $imovel)
    {
        $this->authorize('view', $imovel);
        Gate::authorize('export', AuditLog::class);

        return $this->export($request, $imovel, 'imovel');
    }

    public function contratoExport(EntityAuditRequest $request, Contrato $contrato)
    {
        $this->authorize('view', $contrato);
        Gate::authorize('export', AuditLog::class);

        return $this->export($request, $contrato, 'contrato');
    }

    private function buildQuery(EntityAuditRequest $request, Model $model)
    {
        return AuditLog::query()
            ->where('auditable_type', $model::class)
            ->where('auditable_id', $model->getKey())
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')->toDateString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')->toDateString()));
    }

    private function export(EntityAuditRequest $request, Model $model, string $prefix): Response|StreamedResponse
    {
        $format = strtolower((string) $request->input('format', 'csv'));
        if (! in_array($format, ['json', 'csv'], true)) {
            abort(422, 'Formato solicitado não é suportado.');
        }

        $query = $this->buildQuery($request, $model)->with('user')->orderByDesc('created_at');
        $filename = sprintf('%s-auditoria-%s.%s', $prefix, now()->format('Ymd_His'), $format);

        if ($format === 'json') {
            $generatedAt = now()->toDateTimeString();

            return response()->streamDownload(function () use ($query, $generatedAt) {
                echo "{\n";
                echo '  "generated_at": "'.$generatedAt."\",\n";
                echo '  "rows": [';

                $first = true;

                foreach ((clone $query)->lazy(500) as $row) {
                    $entry = json_encode([
                        'id' => $row->id,
                        'timestamp' => $row->created_at?->toIso8601String(),
                        'user' => $row->user?->username,
                        'action' => $row->action,
                        'payload' => $row->payload,
                        'ip_address' => $row->ip_address,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    if (! $entry) {
                        continue;
                    }

                    echo $first ? "\n    {$entry}" : ",\n    {$entry}";
                    $first = false;
                }

                if (! $first) {
                    echo "\n";
                }

                echo "  ]\n}";
            }, $filename, [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Data', 'Usuario', 'Ação', 'IP', 'Payload']);

            foreach ((clone $query)->lazy(500) as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->created_at?->toIso8601String(),
                    optional($row->user)->username,
                    $row->action,
                    $row->ip_address,
                    json_encode($row->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
