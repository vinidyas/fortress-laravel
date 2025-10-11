<?php

namespace App\Http\Controllers\Api;

use App\Actions\Imovel\GenerateCodigo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imovel\ImovelStoreRequest;
use App\Http\Requests\Imovel\ImovelUpdateRequest;
use App\Http\Resources\ImovelResource;
use App\Models\Imovel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ImovelController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Imovel::class);

        $query = Imovel::query()->with([
            'proprietario',
            'agenciador',
            'responsavel',
            'condominio',
        ]);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $imoveis = QueryBuilder::for($query)
            ->defaultSort('-created_at')
            ->allowedSorts(['codigo', 'cidade', 'valor_locacao', 'created_at'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($builder, $value) {
                    $value = is_array($value) ? implode(' ', $value) : $value;
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }

                    $builder->where(function ($query) use ($value) {
                        $query->where('codigo', 'like', "%{$value}%")
                            ->orWhere('cidade', 'like', "%{$value}%")
                            ->orWhere('bairro', 'like', "%{$value}%")
                            ->orWhere('rua', 'like', "%{$value}%")
                            ->orWhere('logradouro', 'like', "%{$value}%")
                            ->orWhere('complemento', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('tipo_imovel'),
                AllowedFilter::exact('disponibilidade'),
                AllowedFilter::exact('cidade'),
                AllowedFilter::callback('finalidade', function ($builder, $value) {
                    $values = collect(is_array($value) ? $value : [$value])
                        ->filter()
                        ->map(fn ($item) => ucfirst(mb_strtolower((string) $item)))
                        ->unique()
                        ->all();

                    foreach ($values as $item) {
                        $builder->whereJsonContains('finalidade', $item);
                    }
                }),
            ])
            ->paginate($perPage)
            ->appends($request->query());

        return ImovelResource::collection($imoveis);
    }

    public function show(Imovel $imovel)
    {
        $this->authorize('view', $imovel);

        return new ImovelResource($imovel->load(['proprietario', 'agenciador', 'responsavel', 'condominio']));
    }

    public function store(ImovelStoreRequest $request, GenerateCodigo $generateCodigo)
    {
        $this->authorize('create', Imovel::class);

        $data = $request->validated();
        if (empty($data['codigo'])) {
            $data['codigo'] = $generateCodigo->generate();
        }

        $imovel = Imovel::query()->create($data)->load(['proprietario', 'agenciador', 'responsavel', 'condominio']);

        return (new ImovelResource($imovel))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(ImovelUpdateRequest $request, Imovel $imovel)
    {
        $this->authorize('update', $imovel);

        $data = $request->validated();
        if (empty($data['codigo'])) {
            $data['codigo'] = $imovel->codigo;
        }

        $imovel->update($data);

        $imovel->refresh()->load(['proprietario', 'agenciador', 'responsavel', 'condominio']);

        return new ImovelResource($imovel);
    }

    public function destroy(Imovel $imovel)
    {
        $this->authorize('delete', $imovel);

        $imovel->delete();

        return response()->noContent();
    }
}
