<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Simpele accounts met Sanctum-tokens; bruikbaar voor web en de latere Expo-app. */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($data);

        return response()->json($this->tokenResponse($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Onjuiste combinatie van e-mail en wachtwoord.']]);
        }

        return response()->json($this->tokenResponse($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Uitgelogd.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userData($request->user())]);
    }

    private function tokenResponse(User $user): array
    {
        return [
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $this->userData($user),
        ];
    }

    private function userData(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }
}
