<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = JWTAuth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengguna tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error mengambil detail pengguna',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $idUser = JWTAuth::user()->id;
            if ($idUser != $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses tidak diizinkan'
                ], 403);
            }
            
            $user = User::findOrFail($id);
            
            $validationRules = [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            ];
            
            if ($request->has('current_password') || $request->has('password')) {
                $validationRules['current_password'] = 'required|string';
                $validationRules['password'] = ['required', 'confirmed', Password::min(8)];
                $validationRules['password_confirmation'] = 'required|string';
            }
            
            $validatedData = $request->validate($validationRules);

            if ($request->has('current_password')) {
                if (!Hash::check($validatedData['current_password'], $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Password lama tidak sesuai'
                    ], 422);
                }
                
                $user->password = Hash::make($validatedData['password']);
                unset($validatedData['current_password'], $validatedData['password'], $validatedData['password_confirmation']);
            }

            $user->fill($validatedData);
            $user->save();

            $message = $request->has('current_password') ? 'Profil dan password berhasil diperbarui' : 'Profil berhasil diperbarui';

            return response()->json([
                'success' => true,
                'message' => $message,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error memperbarui profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}