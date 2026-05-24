<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'phone'      => 'sometimes|string|unique:users,phone,' . $request->user()->id,
            'avatar'     => 'sometimes|url|max:500',
        ]);

        $request->user()->update($request->only(['first_name', 'last_name', 'phone', 'avatar']));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'data'    => new UserResource($request->user()->fresh()->load('wallet')),
        ]);
    }
}
