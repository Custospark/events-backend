<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleCollection;
use App\Http\Resources\RoleResource;
use App\Services\Contracts\RoleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends ApiController
{
    public function __construct(
        protected RoleServiceInterface $roleService,
    ) {}

    public function index(Request $request): RoleCollection
    {
        return new RoleCollection(
            $this->roleService->getAll(
                $this->tenantId($request),
                $request->only(['search']),
                $this->perPage($request),
            ),
        );
    }

    public function show(Request $request, int $role): RoleResource
    {
        return new RoleResource(
            $this->roleService->getById($this->tenantId($request), $role),
        );
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = $this->roleService->create(
            $this->tenantId($request),
            $request->validated(),
        );

        return (new RoleResource($role))->response()->setStatusCode(201);
    }

    public function update(RoleRequest $request, int $role): RoleResource
    {
        $role = $this->roleService->update(
            $this->tenantId($request),
            $role,
            $request->validated(),
        );

        return new RoleResource($role);
    }

    public function destroy(Request $request, int $role): JsonResponse
    {
        $this->roleService->delete($this->tenantId($request), $role);

        return response()->json(null, 204);
    }
}
