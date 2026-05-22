<?php

namespace App\Http\Controllers\User;

use App\Actions\User\ShowUserAction;
use App\Actions\User\StoreUserAction;
use App\Actions\User\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $storeUserRequest, StoreUserAction $storeUserAction): JsonResponse
    {
        $user = $storeUserAction($storeUserRequest);

        return response()
            ->json(
                [
                    'message' => 'User created successfully',
                    'user' => new UserResource($user),
                ],
                Response::HTTP_CREATED
            );
    }

    /**
     * Display the specified resource.
     */
    public function show(ShowUserAction $showUserAction): JsonResponse
    {
        return response()->json([
            'message' => 'User retrieved successfully',
            'user' => new UserResource($showUserAction()),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $updateUserRequest, UpdateUserAction $updateUserAction): JsonResponse
    {
        $user = $updateUserAction($updateUserRequest);

        return response()
            ->json(
                [
                    'message' => 'User updated successfully',
                    'user' => new UserResource($user),
                ], Response::HTTP_OK
            );

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
