<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auditoria\AuditLogFilterRequest;
use App\Http\Resources\Auditoria\AuditLogResource;
use App\Models\AuditLog;
use App\Support\Database\Concerns\InteractsWithJsonLike;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    use InteractsWithJsonLike;

    public function index(AuditLogFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class);

        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $logs = $this->makeFilteredQuery($request)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->validated());

        return AuditLogResource::collection($logs);
    }

    public function export(AuditLogFilterRequest $request): Response|StreamedResponse
    {
        Gate::authorize('export', AuditLog::class);

        $format = strtolower((string) $request->input('format', 'csv'));
        if (! in_array($format, ['json', 'csv'], true)) {
            abort(422, 'Formato solicitado nao e suportado.');
        }

        $query = $this->makeFilteredQuery($request)->with('user')->orderByDesc('created_at');

        if ($format === 'json') {
            $filename = 'auditoria-'.now()->format('Ymd_His').'.json';
            $generatedAt = now()->toDateTimeString();

            return response()->streamDownload(function () use ($query, $generatedAt) {
                echo "{\n";
                echo '  "generated_at": "'.$generatedAt."\",\n";
                echo '  "rows": [';

                $first = true;

                foreach ((clone $query)->lazy(500) as $row) {
                    $entry = json_encode([
                        'id' => $row->id,
                        'timestamp' => $row->created_at?->toDateTimeString(),
                        'user' => $row->user?->username,
                        'action' => $row->action,
                        'resource' => $row->auditable_type.'#'.$row->auditable_id,
                        'ip_address' => $row->ip_address,
                        'user_agent' => $row->user_agent,
                        'payload' => $row->payload,
                        'context' => $row->context,
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

        $filename = 'auditoria-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Data', 'Usuario', 'Acao', 'Recurso', 'IP', 'Metodo', 'Origem', 'URL', 'Payload']);

            foreach ((clone $query)->lazy(500) as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->created_at?->toDateTimeString(),
                    optional($row->user)->username,
                    $row->action,
                    $row->auditable_type.'#'.$row->auditable_id,
                    $row->ip_address,
                    $row->context['http_method'] ?? null,
                    $row->context['origin'] ?? null,
                    $row->context['url'] ?? null,
                    json_encode($row->payload),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function makeFilteredQuery(AuditLogFilterRequest $request)
    {
        return AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('auditable_type'), fn ($q) => $q->where('auditable_type', $request->string('auditable_type')))
            ->when($request->filled('auditable_id'), fn ($q) => $q->where('auditable_id', $request->integer('auditable_id')))
            ->when($request->filled('ip_address'), fn ($q) => $q->where('ip_address', $request->string('ip_address')))
            ->when($request->filled('guard'), fn ($q) => $q->where('context->guard', $request->string('guard')))
            ->when($request->filled('origin'), fn ($q) => $q->where('context->origin', $request->string('origin')))
            ->when($request->filled('http_method'), fn ($q) => $q->where('context->http_method', $request->string('http_method')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')->toDateString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')->toDateString()))
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
