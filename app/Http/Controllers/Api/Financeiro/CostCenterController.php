<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\CostCenterImportRequest;
use App\Http\Requests\Financeiro\CostCenterRequest;
use App\Http\Resources\Financeiro\CostCenterResource;
use App\Models\CostCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CostCenterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CostCenter::class);

        if ($request->boolean('tree')) {
            return CostCenterResource::collection($this->queryRoots());
        }

        $centers = CostCenter::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search')->trim('%').'%';
                $query->where('nome', 'like', $term);
            })
            ->orderByRaw("CAST(REPLACE(codigo, '.', '') AS UNSIGNED)")
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return CostCenterResource::collection($centers);
    }

    public function store(CostCenterRequest $request): JsonResponse
    {
        $this->authorize('create', CostCenter::class);

        $data = $request->validated();
        $parent = $this->resolveParent($data['parent_id'] ?? null);
        $data['codigo'] = $this->resolveCodigo($data['codigo'] ?? null, $parent);
        $data['parent_id'] = $parent?->id;

        $center = DB::transaction(fn () => CostCenter::create($data));

        return CostCenterResource::make($center)
            ->additional(['message' => 'Centro de custo criado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(CostCenter $costCenter): CostCenterResource
    {
        $this->authorize('view', $costCenter);

        return CostCenterResource::make($costCenter->load('parent'));
    }

    public function update(CostCenterRequest $request, CostCenter $costCenter): CostCenterResource
    {
        $this->authorize('update', $costCenter);

        $data = $request->validated();
        $parent = $this->resolveParent($data['parent_id'] ?? null, $costCenter);
        $data['codigo'] = $this->resolveCodigo($data['codigo'] ?? null, $parent, $costCenter);
        $data['parent_id'] = $parent?->id;

        DB::transaction(fn () => $costCenter->update($data));

        return CostCenterResource::make($costCenter->refresh()->load('parent'))->additional([
            'message' => 'Centro de custo atualizado com sucesso.',
        ]);
    }

    public function destroy(CostCenter $costCenter): Response
    {
        $this->authorize('delete', $costCenter);

        DB::transaction(fn () => $costCenter->delete());

        return response()->noContent();
    }

    public function tree(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CostCenter::class);

        return CostCenterResource::collection($this->queryRoots());
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', CostCenter::class);

        $query = CostCenter::with('parent')
            ->orderByRaw("CAST(REPLACE(codigo, '.', '') AS UNSIGNED)");

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Centros de custo');
        $sheet->fromArray([
            ['Codigo', 'Nome', 'Descricao', 'Codigo do pai'],
        ]);

        $rowIndex = 2;
        foreach ($query->lazy(500) as $center) {
            $sheet->setCellValueExplicit("A{$rowIndex}", (string) $center->codigo, DataType::TYPE_STRING);
            $sheet->setCellValue("B{$rowIndex}", $center->nome);
            $sheet->setCellValue("C{$rowIndex}", $center->descricao ?? '');
            $sheet->setCellValueExplicit("D{$rowIndex}", (string) ($center->parent?->codigo ?? ''), DataType::TYPE_STRING);
            $rowIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'centros-de-custo-'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(CostCenterImportRequest $request): JsonResponse
    {
        $this->authorize('import', CostCenter::class);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) <= 1) {
            return response()->json([
                'message' => 'Nenhum dado encontrado no arquivo informado.',
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $headers = array_map(fn ($value) => strtolower(Str::ascii(trim((string) $value))), $rows[1]);
        $codigoColumn = $this->findColumn($headers, ['codigo']);
        $nomeColumn = $this->findColumn($headers, ['nome']);
        $descricaoColumn = $this->findColumn($headers, ['descricao']);
        $parentColumn = $this->findColumn($headers, ['codigo_pai', 'pai', 'codigo do pai']);

        if ($nomeColumn === null) {
            return response()->json([
                'message' => 'O arquivo deve conter uma coluna "Nome".',
                'created' => 0,
                'updated' => 0,
                'skipped' => count($rows) - 1,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $pending = [];
        foreach (array_slice($rows, 1) as $row) {
            $payload = $this->makeRowPayload($row, $nomeColumn, $codigoColumn, $descricaoColumn, $parentColumn);
            if (! $payload) {
                $skipped++;
                continue;
            }
            $pending[] = $payload;
        }

        DB::transaction(function () use (&$pending, &$created, &$updated, &$skipped) {
            $progress = true;
            while ($progress && $pending !== []) {
                $progress = false;
                $nextRound = [];

                foreach ($pending as $payload) {
                    $parent = $this->resolveParentByCode($payload['parent_codigo']);
                    if ($payload['parent_codigo'] && ! $parent) {
                        $nextRound[] = $payload;
                        continue;
                    }

                    $codigo = $this->resolveCodigo($payload['codigo'], $parent);
                    $attributes = [
                        'nome' => $payload['nome'],
                        'descricao' => $payload['descricao'],
                        'parent_id' => $parent?->id,
                    ];

                    $existing = CostCenter::query()->where('codigo', $codigo)->first();
                    if ($existing) {
                        $existing->update($attributes);
                        $updated++;
                    } else {
                        CostCenter::create(array_merge($attributes, ['codigo' => $codigo]));
                        $created++;
                    }

                    $progress = true;
                }

                if (! $progress) {
                    $skipped += count($nextRound);
                }

                $pending = $nextRound;
            }
        });

        return response()->json([
            'message' => 'Importacao de centros de custo concluida.',
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    private function resolveParent(?int $parentId, ?CostCenter $current = null): ?CostCenter
    {
        if (! $parentId) {
            return null;
        }

        $parent = CostCenter::query()->find($parentId);

        if (! $parent) {
            return null;
        }

        if ($current && $current->id === $parent->id) {
            return null;
        }

        return $parent;
    }

    private function resolveParentByCode(?string $codigo): ?CostCenter
    {
        if (! $codigo) {
            return null;
        }

        return CostCenter::query()->where('codigo', $codigo)->first();
    }

    private function resolveCodigo(?string $codigo, ?CostCenter $parent, ?CostCenter $current = null): string
    {
        if ($codigo) {
            return $codigo;
        }

        return $this->generateCodigo($parent, $current);
    }

    private function generateCodigo(?CostCenter $parent, ?CostCenter $ignore = null): string
    {
        if ($parent) {
            $prefix = $this->normalizeParentPrefix($parent->codigo);
            $query = CostCenter::query()->where('parent_id', $parent->id);
            if ($ignore) {
                $query->where('id', '<>', $ignore->id);
            }
            $siblings = $query->pluck('codigo');
            $max = 0;
            $pattern = '/^'.preg_quote($prefix, '/').'\.(\d+)$/';
            foreach ($siblings as $code) {
                if (preg_match($pattern, (string) $code, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }

            return sprintf('%s.%d', $prefix, $max + 1);
        }

        $query = CostCenter::query()->whereNull('parent_id');
        if ($ignore) {
            $query->where('id', '<>', $ignore->id);
        }
        $siblings = $query->pluck('codigo');
        $max = 0;
        foreach ($siblings as $code) {
            if (preg_match('/^(\d+)\.0$/', (string) $code, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return sprintf('%d.0', max(1, $max + 1));
    }

    private function normalizeParentPrefix(string $codigo): string
    {
        $segments = explode('.', $codigo);
        while (count($segments) > 1 && end($segments) === '0') {
            array_pop($segments);
        }

        $prefix = implode('.', $segments);

        return $prefix !== '' ? $prefix : $codigo;
    }

    private function findColumn(array $headers, array $candidates): ?string
    {
        foreach ($headers as $column => $value) {
            if (in_array($value, $candidates, true)) {
                return $column;
            }
        }

        return null;
    }

    private function makeRowPayload(array $row, ?string $nomeColumn, ?string $codigoColumn, ?string $descricaoColumn, ?string $parentColumn): ?array
    {
        $nome = $nomeColumn ? trim((string) ($row[$nomeColumn] ?? '')) : '';

        if ($nome === '') {
            return null;
        }

        return [
            'nome' => $nome,
            'codigo' => $codigoColumn ? $this->normalizeCodigo($row[$codigoColumn] ?? null) : null,
            'descricao' => $descricaoColumn ? trim((string) ($row[$descricaoColumn] ?? '')) ?: null : null,
            'parent_codigo' => $parentColumn ? $this->normalizeCodigo($row[$parentColumn] ?? null) : null,
        ];
    }

    private function normalizeCodigo(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        return $trimmed;
    }

    private function queryRoots()
    {
        return CostCenter::with('childrenRecursive')
            ->whereNull('parent_id')
            ->orderByRaw("CAST(REPLACE(codigo, '.', '') AS UNSIGNED)")
            ->get();
    }
}
