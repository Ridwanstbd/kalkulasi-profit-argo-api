<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ManageUserSubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $status = $request->input('status');
            $perPage = $request->input('per_page',10);
            $query = UserSubscription::with(['user','subscriptionPlan']);
            if ($status){
                $query->where('status',$status);
            }
            $subscriptions = $query->orderBy('created_at','desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Pelanggan berhasil ditampilkan!',
                'data' => $subscriptions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pelanggan gagal ditampilkan!',
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
        $userId = (int) $id;
        $user = User::find($userId);
            if(!$user){
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ]);
            }
            $subscriptions = UserSubscription::where('user_id',$userId)->with('subscriptionPlan')->orderBy('created_at','desc')->get();
            return response()->json([
                'success' => true,
                'message' => 'Pelanggan berhasil ditampilkan!',
                'data' => [
                    'user' => $user,
                    'subscriptions' => $subscriptions 
                ]

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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:active,expired,cancelled',
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()], 422);
        }
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
            
            $subscription->payment_status = $request->payment_status;
            
            if ($request->payment_status === 'paid' && $subscription->status !== 'active') {
                $subscription->status = 'active';
            }
            
            if ($request->payment_status === 'failed' && $subscription->status !== 'cancelled') {
                $subscription->status = 'cancelled';
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Status pembayaran berhasil diperbarui',
                'data' => $subscription
            ],200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Paket pelanggan gagal diperbarui!',
                'errors' => $e->getMessage()
            ],500);
        }
    }
}
