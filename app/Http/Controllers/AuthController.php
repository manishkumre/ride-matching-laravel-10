<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1️⃣ User create
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        // 2️⃣ Passenger role attach (DEFAULT)
        $passengerRole = Role::where('name', 'passenger')->first();
        $user->roles()->attach($passengerRole->id);

        // 3️⃣ Token generate
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function login(Request $request)
    {
        if (!Auth::attempt($request->only('email','password'))) {
            return response()->json(['error'=>'Invalid'], 401);
        }

        $token = $request->user()->createToken('api')->plainTextToken;
        return response()->json(['token'=>$token]);
    }
}

