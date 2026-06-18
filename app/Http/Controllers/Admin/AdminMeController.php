<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user('user_jwt');

        return response()->json([
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role instanceof BackedEnum ? $admin->role->value : $admin->role,
            ],
        ]);
    }
}
