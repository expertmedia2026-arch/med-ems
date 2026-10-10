<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('department'));
    }

    public function update(ProfileUpdateRequest $request, ProfileService $profiles): UserResource
    {
        return new UserResource($profiles->update($request->user(), $request->validated()));
    }

    public function destroy(Request $request, ProfileService $profiles)
    {
        $request->validate(['password' => ['required', 'current_password:sanctum']]);
        $profiles->delete($request->user());

        return response()->noContent();
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);
        $request->user()->update(['password' => $data['password']]);
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Password updated. Sign in again with your new password.']);
    }
}
