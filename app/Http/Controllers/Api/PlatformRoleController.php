<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\PlatformRoleRequest;
use App\Services\PlatformAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformRoleController extends ApiController
{
    public function __construct(
        protected PlatformAccessService $platformAccess,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->platformAccess->listRoles(),
        ]);
    }

    public function store(PlatformRoleRequest $request): JsonResponse
    {
        $role = $this->platformAccess->createRole($request->validated());

        return response()->json([
            'data' => $this->platformAccess->listRoles()->firstWhere('id', $role->id),
        ], 201);
    }

    public function update(PlatformRoleRequest $request, int $role): JsonResponse
    {
        $role = $this->platformAccess->updateRole($role, $request->validated());

        return response()->json([
            'data' => $this->platformAccess->listRoles()->firstWhere('id', $role->id),
        ]);
    }

    public function destroy(Request $request, int $role): JsonResponse
    {
        $this->platformAccess->deleteRole($role);

        return response()->json(null, 204);
    }
}
