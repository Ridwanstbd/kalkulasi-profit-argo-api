<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class JWTAuthController extends Controller
{
    public function register(Request $request) {
        $validator = Validator::make($request->all(),[
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string|min:6'
        ]);
        
        if ($validator->fails()){
            return response()->json($validator->errors()->toJson(),400);
        }
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'password' => Hash::make($request->password),
            ]);
            $userRole = Role::where('name','user')->first();
            $user->roles()->attach($userRole);

            DB::commit();

            $token = JWTAuth::fromUser($user);
            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function login(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        try {
            $credentials = $request->only('email', 'password');
            $remember = $request->input('remember_me', false);
            if($request->remember_me) {
                config(['jwt.ttl' => 10080]);
            }          
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Login credentials are invalid'
                ], 401);
            }
            $user = JWTAuth::user();
            if ($remember) {
                $user->remember_token = hash('sha256', $token);
                $user->save();
            }
            $ttl = config('jwt.ttl');
           
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'is_admin' => $user->isAdmin()
                ],
                'authorization' => [
                    'token' => $token,
                    'type' => 'Bearer',
                    'expires_in' => $ttl * 60,
                    'remember_me' => (bool) $remember
                ]
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not create token',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function logout(Request $request) {
        try {
            $user = JWTAuth::user();
            if ($user) {
                $user->remember_token = null;
                $user->save();
            }
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
        } catch (JWTException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, the user cannot be logged out'
            ], 500);
        }
    }
    public function refresh(){
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            $user = JWTAuth::user();
            $ttl = config('jwt.ttl');

            if($user && $user->remember_token){
                $user->remember_token = hash('sha256', $token);
                $user->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'authorization' => [
                    'token' => $token,
                    'type' => 'Bearer',
                    'expires_in' => $ttl * 60
                ]
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not refresh token',
                'error' => $e->getMessage()
            ], 401);
        }
    }
    public function me(){
        try {
            $user = JWTAuth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'is_admin' => $user->isAdmin()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
