<?php

namespace gersonalves\laravelBase\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Yajra\DataTables\Facades\DataTables;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

trait ControllerTrait
{

    public function search(Request $request): \Illuminate\Support\Collection|Collection|LengthAwarePaginator|array
    {

        $baseClass = $this->service->getModel()::class;
        $queryBase = $this->service->getModel()->query();
        if (method_exists($baseClass, 'scopeWithRelations')) {
            $queryBase = $queryBase->withRelations();
        }
        $query = $this->makeQuery($queryBase, $request);

        if ($request->has('paginate')) {
            $paginated = $query->paginate($request->get('per_page', 10));
            if (property_exists($this, 'resource') && method_exists($this->resource, 'resource')) {
                $paginated->getCollection()->transform(function ($item) {
                    return $this->resource::resource($item);
                });
            }
            return $paginated;
        }
        if ($request->has('limit')) {
            return $query->limit($request->get('limit'))->get();
        }


        return $query->get();
    }

    public function makeQuery($subject, Request $request)
    {
        $allowedFilters = array_merge(
            $this->service->getModel()->getFillable(),
            $this->extraFilters ?? [],
            [AllowedFilter::trashed()]
        );

        foreach ($allowedFilters as $index => $allowedFilter) {
            if ($allowedFilter instanceof AllowedFilter) {
                $position = array_search($allowedFilter->getName(), $allowedFilters);
                if ($position !== false)
                    unset($allowedFilters[$position]);
            }
        }

        $sortables = array_merge(
            ['created_at', $this->service->getModel()->getKeyName()],
            $this->extraSortables ?? [],
            $this->service->getModel()->getFillable()
        );

        return QueryBuilder::for($subject)
            ->orderBy('created_at', 'desc')
            ->allowedFilters(
                $allowedFilters
            )
            ->allowedSorts($sortables);
    }

    public function index()
    {
        try {
            if (request()->limit) {
                return response()->json($this->service->paginate());
            }

            $response = $this->service->get(null, request());
            if (property_exists($this, 'resource') && method_exists($this?->resource, 'collection')) {
                return new $this->resource($response);
            }

            return responseSuccess(200, 'success', $response);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function show(int $id, Request $request)
    {
        try {
            $response = $this->service->get($id, $request);

            if (property_exists($this, 'resource') && method_exists($this?->resource, 'resource')) {
                return $this->resource::resource($response);
            }

            return responseSuccess(200, 'success', $response);
        } catch (\Exception $e) {
            return response()->json($this->getErrorString($e, 'Registro não encontrado.'), 404);
        }
    }

    public function update(int $id, Request $request): JsonResponse|Response
    {
        return responseSuccess(200, 'success', $this->service->update($id, $request));

    }

    public function store(Request $request): JsonResponse|Response
    {
        return responseSuccess(200, 'success', $this->service->store($request));
    }

    public function destroy(int $id): JsonResponse|Response
    {
        try {
            $this->service->destroy($id);

            return response()->json([
                'success' => 'true',
                'message' => 'Registro deletado com sucesso',
            ]);
        } catch (\Exception $e) {
            return response()->json($this->getErrorString($e, 'Registro não encontrado.'), 404);
        }
    }

    public function getErrorString($e, string $customMessage = 'Server error'): string
    {
        return env('APP_DEBUG') ? $e->getMessage() : $customMessage;
    }

    public function getTable(Request $request): JsonResponse|Response
    {
        return DataTables::eloquent($this->service->query()->getModel())->toJson();
    }
}
