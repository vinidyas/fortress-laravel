<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\CostCenterRequest;
use App\Http\Resources\Financeiro\CostCenterResource;
use App\Models\CostCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CostCenterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CostCenter::class);

        $centers = CostCenter::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search')->trim('%').'%';
                $query->where('nome', 'like', $term);
            })
            ->orderBy('nome')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return CostCenterResource::collection($centers);
    }

    public function store(CostCenterRequest $request): JsonResponse
    {
        $this->authorize('create', CostCenter::class);

        $center = CostCenter::create($request->validated());

        return CostCenterResource::make($center)
            ->additional(['message' => 'Centro de custo criado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(CostCenter $costCenter): CostCenterResource
    {
        $this->authorize('view', $costCenter);

        return CostCenterResource::make($costCenter);
    }

    public function update(CostCenterRequest $request, CostCenter $costCenter): CostCenterResource
    {
        $this->authorize('update', $costCenter);

        $costCenter->update($request->validated());

        return CostCenterResource::make($costCenter)->additional([
            'message' => 'Centro de custo atualizado com sucesso.',
        ]);
    }

    public function destroy(CostCenter $costCenter): Response
    {
        $this->authorize('delete', $costCenter);

        $costCenter->delete();

        return response()->noContent();
    }
}
