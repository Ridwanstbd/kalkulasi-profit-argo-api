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
                ->where('status', 'active')
                ->where('end_date', '>=', Carbon::now()->format('Y-m-d'))
                ->first();
                
            if ($activeSubscription) {
                if ($activeSubscription->subscription_plan_id == $planId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda sudah memiliki langganan aktif dengan paket yang sama'
                    ], 400);
                }
                
                $activeSubscription->status = 'cancelled';
                $activeSubscription->save();
            }
            
            $plan = SubscriptionPlan::find($planId);
            $startDate = Carbon::now();
            $endDate = Carbon::now()->addDays($plan->duration);
            
            if ($request->payment_status === 'paid') {
                $status = 'active';
            }
            
            $subscription = UserSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $planId, 
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status ?? 'inactive',
                'payment_status' => 'pending'
            ]);            
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Silakan melanjutkan ke pembayaran langganan',
                'data' => $subscription
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal berlangganan',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = JWTAuth::user();
        
        $subscription = UserSubscription::where('id', (int) $id)
            ->with('subscriptionPlan')
            ->first();
            
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Langganan tidak ditemukan'
            ], 404);
        }
        
        if ($subscription->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses'
            ], 403);
        }
        
        $isActive = $subscription->status === 'active' && 
                   Carbon::parse($subscription->end_date)->greaterThanOrEqualTo(Carbon::now());
        
        if (!$isActive) {
            $subscription->status = 'expired';
            $subscription->save();
        }
        
        return response()->json([
            'success' => true,
            'data' => $subscription
        ], 200);
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
                
                if ($request->payment_status === 'paid') {
                    $otherActiveSubscriptions = UserSubscription::where('user_id', $user->id)
                        ->where('id', '!=', $subscriptionId)
                        ->where('status', 'active')
                        ->get();
                    
                    foreach ($otherActiveSubscriptions as $otherSubscription) {
                        $otherSubscription->status = 'cancelled';
                        $otherSubscription->save();
                    }
                    
                    $subscription->status = 'active';
                } elseif ($request->payment_status === 'failed') {
                    $subscription->status = 'cancelled';
                } elseif ($request->payment_status === 'pending') {
                    $subscription->status = 'inactive';
                }
            }
            
            if ($request->has('status')) {
                $validator = Validator::make($request->all(), [
                    'status' => 'required|in:active,expired,cancelled,inactive',
                ]);
                
                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validasi gagal',
                        'errors' => $validator->errors()
                    ], 422);
                }
                
                if ($request->status === 'active' && $subscription->status !== 'active') {
                    $otherActiveSubscriptions = UserSubscription::where('user_id', $user->id)
                        ->where('id', '!=', $subscriptionId)
                        ->where('status', 'active')
                        ->get();
                    
                    foreach ($otherActiveSubscriptions as $otherSubscription) {
                        $otherSubscription->status = 'cancelled';
                        $otherSubscription->save();
                    }
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
            ], 500);
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
                'message' => 'Gagal membatalkan langganan',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
