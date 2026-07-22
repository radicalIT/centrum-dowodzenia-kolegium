<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            // Generowanie tokena Sanctum
            $token = $user->createToken('admin-token')->plainTextToken;
            
            return response()->json([
                'token' => $token,
                'user' => $user
            ]);
        }

        return response()->json(['message' => 'Nieprawidłowy adres e-mail lub hasło.'], 401);
    }

    public function logout(Request $request)
    {
        // Usuwanie obecnego tokena
        $request->user()->currentAccessToken()->delete();
        
        return response()->json(['message' => 'Pomyślnie wylogowano.']);
    }
}