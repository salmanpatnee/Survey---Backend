<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $attributes = $request->validate([
            'name' => 'required|string', 
            'email' => 'required|email|string|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()]
        ]);

        User::create($attributes);

        return $this->login($request);

    }

    public function login(Request $request)
    {
        $attributes = $request->validate([
            'email' => 'required|email|string|exists:users,email',
            'password' => ['required'], 
            'remember' => 'boolean'
        ]);

        $remember = $attributes['remember'] ?? false;

        unset($attributes['remember']);

        if(!Auth::attempt($attributes, $remember)) {
            return response([
                'error' => 'The provided credentials do not match.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = Auth::user();
        $token = $user->createToken('main')->plainTextToken;

        return response([
            'user' => $user, 
            'token' => $token
        ]);
    }

    public function logout()
    {
        $user = Auth::user();

        $user->currentAccesstoken()->delete();

        return response([
            'success' => true
        ]);
    }
}
