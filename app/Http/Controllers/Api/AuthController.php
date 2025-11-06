<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Accept either email or username in the "email" field for convenience
        $data = $request->validate([
            'email' => ['required','string'],
            'password' => ['required','string'],
        ]);

        $identifier = $data['email'];
        $query = User::query();
        if (str_contains($identifier, '@')) {
            $query->where('email', $identifier);
        } else {
            // Try username via name column, or fallback to email match
            $query->where(function ($q) use ($identifier) {
                $q->where('name', $identifier)->orWhere('email', $identifier);
            });
        }
        $user = $query->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Create a token to be used for API calls
        $token = $user->createToken('admin')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
