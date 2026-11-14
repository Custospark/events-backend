<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\PlatformUserRequest;
use App\Http\Resources\PlatformUserCollection;
use App\Http\Resources\PlatformUserResource;
use App\Services\PlatformAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformUserController extends ApiController
{
    public function __construct(
        protected PlatformAccessService $platformAccess,
    ) {}

    public function index(Request $request): PlatformUserCollection
    {
        return new PlatformUserCollection(
            $this->platformAccess->listUsers(
                $request->only(['search', 'status', 'role_id']),
                $this->perPage($request),
            ),
        );
    }

    public function store(PlatformUserRequest $request): JsonResponse
    {
        $user = $this->platformAccess->createUser($request->validated());

        return (new PlatformUserResource($user->load('role')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(PlatformUserRequest $request, int $user): PlatformUserResource
    {
        $validated = $request->validated();

        if (isset($validated['password']) && $validated['password'] === '') {
            unset($validated['password']);
        }

        return new PlatformUserResource(
            $this->platformAccess->updateUser($user, $validated, $request->user())->load('role'),
        );
    }

    public function destroy(Request $request, int $user): JsonResponse
    {
        $this->platformAccess->deleteUser($user, $request->user());

        return response()->json(null, 204);
    }
}
