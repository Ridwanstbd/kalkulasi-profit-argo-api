<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminSubscriptionPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $plan = SubscriptionPlan::get();
            return response()->json([
                'success' => true,
                'message' => 'Paket berlangganan berhasil ditampilkan!',
                'data' => $plan     
            ],200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Paket berlangganan gagal ditampilkan!',
                'errors' => $e->getMessage()
            ],500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:subscription_plans',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'features' => 'nullable|json',
            'max_products' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        try {
            DB::beginTransaction();
            $plan = SubscriptionPlan::create($request->all());

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Paket berlangganan berhasil dibuat!',
                'data' => $plan
            ],201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Paket berlangganan gagal dibuat!',
                'errors' => $e->getMessage()
            ],500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $planId = (int) $id;
            $plan = SubscriptionPlan::find($planId);
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paket langganan tidak ditemukan'
                ], 404);
            }
    
            return response()->json([
                'success' => true,
                'message' => 'Paket berlangganan ditampilkan!',
                'data' => $plan,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Paket berlangganan gagal ditampilkan!',
                'errors' => $e->getMessage()
            ],500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:50|unique:subscription_plans,name,' . $id,
            'price' => 'nullable|numeric|min:0',
            'duration' => 'nullable|integer|min:1',
            'features' => 'nullable|json',
            'max_products' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        try {
            DB::beginTransaction();
            $planId = (int) $id;
            $plan = SubscriptionPlan::findOrFail($planId);
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paket langganan tidak ditemukan'
                ], 404);
            }
            $plan->update($request->all());
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Paket berlangganan diperbarui!',
                'data' => $plan
            ],200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Paket berlangganan gagal diperbarui!',
                'errors' => $e->getMessage()
            ],500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();
            $planId = (int) $id;
            $plan = SubscriptionPlan::find($planId);
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paket langganan tidak ditemukan'
                ], 404);
            }
            $plan->delete();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Subscription plan berhasil dihapus!',
            ],200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Paket berlangganan gagal diperbarui!',
                'errors' => $e->getMessage()
            ],500);
        }
    }
}
