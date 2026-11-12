<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UserRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Services\Contracts\UserServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function __construct(
        protected UserServiceInterface $userService,
    ) {}

    public function index(Request $request): UserCollection
    {
        return new UserCollection(
            $this->userService->getAll(
                $this->tenantId($request),
                $request->only(['search', 'status', 'role_id']),
                $this->perPage($request),
            ),
        );
    }

    public function show(Request $request, int $user): UserResource
    {
        return new UserResource(
            $this->userService->getById($this->tenantId($request), $user),
        );
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = $this->userService->invite(
            $this->tenantId($request),
            $request->validated(),
        );

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(UserRequest $request, int $user): UserResource
    {
        $user = $this->userService->update(
            $this->tenantId($request),
            $user,
            $request->validated(),
        );

        return new UserResource($user);
    }

    public function destroy(Request $request, int $user): JsonResponse
    {
        $this->userService->delete($this->tenantId($request), $user);

        return response()->json(null, 204);
    }
}
