<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Condominio\CondominioStoreRequest;
use App\Http\Requests\Condominio\CondominioUpdateRequest;
use App\Http\Resources\CondominioResource;
use App\Models\Condominio;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CondominioController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Condominio::class);

        $query = Condominio::query();

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $items = QueryBuilder::for($query)
            ->defaultSort('nome')
            ->allowedSorts(['nome', 'cidade', 'estado', 'created_at'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($builder, $value) {
                    $value = is_array($value) ? implode(' ', $value) : $value;
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }

                    $builder->where(function ($q) use ($value) {
                        $q->where('nome', 'like', "%{$value}%")
                            ->orWhere('cnpj', 'like', "%{$value}%")
                            ->orWhere('cidade', 'like', "%{$value}%")
                            ->orWhere('bairro', 'like', "%{$value}%")
                            ->orWhere('rua', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('estado'),
                AllowedFilter::callback('cidade', function ($builder, $value) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }
                    $builder->where('cidade', 'like', "%{$value}%");
                }),
            ])
            ->paginate($perPage)
            ->appends($request->query());

        return CondominioResource::collection($items);
    }

    public function show(Condominio $condominio)
    {
        $this->authorize('view', $condominio);

        return new CondominioResource($condominio);
    }

    public function store(CondominioStoreRequest $request)
    {
        $this->authorize('create', Condominio::class);

        $model = Condominio::query()->create($request->validated());

        return (new CondominioResource($model))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(CondominioUpdateRequest $request, Condominio $condominio)
    {
        $this->authorize('update', $condominio);

        $condominio->update($request->validated());

        return new CondominioResource($condominio);
    }

    public function destroy(Condominio $condominio)
    {
        $this->authorize('delete', $condominio);

        // Impede exclusão se houver imóveis vinculados
        if ($condominio->imoveis()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir o condomínio: existem imóveis vinculados.'
            ], Response::HTTP_CONFLICT);
        }

        $condominio->delete();

        return response()->noContent();
    }
}

