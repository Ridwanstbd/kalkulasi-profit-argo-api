<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = JWTAuth::user();
        
        $subscriptions = UserSubscription::where('user_id', $user->id)
            ->with('subscriptionPlan')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            DB::beginTransaction();
            $user = JWTAuth::user();
            $planId = $request->subscription_plan_id;
            $activeSubscription = UserSubscription::where('user_id', $user->id)
            ->where('status','active')
            ->where('end_date','>=',Carbon::now()->format('Y-m-d'))
            ->first();
            if ($activeSubscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah memiliki langganan aktif'
                ],400);
            }
            $plan = SubscriptionPlan::find($planId);
            $startDate = Carbon::now();
            $endDate = Carbon::now()->addDays($plan->duation);
            $subscription = UserSubscription::create([
                'user_id' => $user->id,
                'subcription_plan_id' => $planId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
                'payment_status' => 'pending'
            ]);            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil berlangganan',
                'data' => $subscription
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal berlangganan',
                'errors' => $e->getMessage()
            ],500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = JWTAuth::user();
        $subscriptionId = (int) $id;
        $subscription = UserSubscription::where('user_id',$subscriptionId)
        ->where('status','active')
        ->where('end_date','>=',Carbon::now()->format('Y-m-d'))
        ->with('subscriptionPlan')
        ->first();
        if ($subscription->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses'
            ], 403);
        }
        if (!$subscription){
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada langganan aktif'
            ],404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $subscription,
            
        ],200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            DB::beginTransaction();
            $subscriptionId = (int) $id;
            $subscription = UserSubscription::find($subscriptionId);
            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Langganan tidak ditemukan'
                ], 404);
            }
            $user = JWTAuth::user();
            if ($subscription->user_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses'
                ], 403);
            }
            if ($request->has('payment_status')) {
                $validator = Validator::make($request->all(), [
                    'payment_status' => 'required|in:pending,paid,failed,refunded',
                ]);
                
                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validasi gagal',
                        'errors' => $validator->errors()
                    ], 422);
                }
                
                $subscription->payment_status = $request->payment_status;
                
                // Jika pembayaran berhasil, status langganan menjadi aktif
                if ($request->payment_status === 'paid') {
                    $subscription->status = 'active';
                }
                
                // Jika pembayaran gagal, status langganan menjadi dibatalkan
                if ($request->payment_status === 'failed') {
                    $subscription->status = 'cancelled';
                }
            }
            if ($request->has('status')) {
                $validator = Validator::make($request->all(), [
                    'status' => 'required|in:active,expired,cancelled',
                ]);
                
                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validasi gagal',
                        'errors' => $validator->errors()
                    ], 422);
                }
                
                $subscription->status = $request->status;
            }
            
            $subscription->save();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Langganan berhasil diperbarui',
                'data' => $subscription
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui langganan',
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
            $subscriptionId = (int) $id;
            $subscription = UserSubscription::find($subscriptionId);
            
            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Langganan tidak ditemukan'
                ], 404);
            }
            $user = JWTAuth::user();
            if ($subscription->user_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses'
                ], 403);
            }
            $subscription->status = 'cancelled';
            $subscription->save();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Langganan berhasil dibatalkan'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal berlangganan',
                'errors' => $e->getMessage()
            ],500);
        }
    }
}
