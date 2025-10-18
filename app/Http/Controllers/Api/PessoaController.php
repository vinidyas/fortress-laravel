<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pessoa\PessoaStoreRequest;
use App\Http\Requests\Pessoa\PessoaUpdateRequest;
use App\Http\Resources\PessoaResource;
use App\Models\Pessoa;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PessoaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Pessoa::class);

        $query = Pessoa::query();

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $pessoas = QueryBuilder::for($query)
            ->defaultSort('nome_razao_social')
            ->allowedSorts(['nome_razao_social', 'created_at'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($builder, $value) {
                    $value = is_array($value) ? implode(' ', $value) : $value;
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }

                    $builder->where(function ($query) use ($value) {
                        $query->where('nome_razao_social', 'like', "%{$value}%")
                            ->orWhere('cpf_cnpj', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%")
                            ->orWhere('telefone', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('tipo_pessoa'),
                AllowedFilter::exact('estado'),
                AllowedFilter::callback('cidade', function ($builder, $value) {
                    $value = trim((string) $value);
                    if ($value === '') { return; }
                    $builder->where('cidade', 'like', "%{$value}%");
                }),
                AllowedFilter::callback('papel', function ($builder, $value) {
                    $aliasMap = [
                        'Inquilino' => 'Locatario',
                        'Locatário' => 'Locatario',
                    ];

                    $values = collect(is_array($value) ? $value : [$value])
                        ->filter()
                        ->map(fn ($item) => ucfirst(mb_strtolower((string) $item)))
                        ->map(fn ($item) => $aliasMap[$item] ?? $item)
                        ->unique()
                        ->values();

                    if ($values->isEmpty()) {
                        return;
                    }

                    $searchValues = $values
                        ->flatMap(function ($papel) use ($aliasMap) {
                            if ($papel === 'Locatario') {
                                return ['Locatario', 'Inquilino'];
                            }

                            return [$papel];
                        })
                        ->unique()
                        ->values();

                    $builder->where(function ($q) use ($searchValues) {
                        foreach ($searchValues as $papel) {
                            $q->orWhereJsonContains('papeis', $papel);
                        }
                    });
                }),
            ])
            ->paginate($perPage)
            ->appends($request->query());

        return PessoaResource::collection($pessoas);
    }

    public function show(Pessoa $pessoa)
    {
        $this->authorize('view', $pessoa);

        return new PessoaResource($pessoa);
    }

    public function store(PessoaStoreRequest $request)
    {
        $this->authorize('create', Pessoa::class);

        $pessoa = Pessoa::query()->create($request->validated());

        return (new PessoaResource($pessoa))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(PessoaUpdateRequest $request, Pessoa $pessoa)
    {
        $this->authorize('update', $pessoa);

        $pessoa->update($request->validated());

        return new PessoaResource($pessoa);
    }

    public function destroy(Pessoa $pessoa)
    {
        $this->authorize('delete', $pessoa);

        $pessoa->delete();

        return response()->noContent();
    }
}
