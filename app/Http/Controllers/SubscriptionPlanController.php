<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $plans = SubscriptionPlan::all();
        if ($plans->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Paket langganan belum tersedia'
            ], 200);
        }
        return response()->json([
            'success' => true,
            'data' => $plans
        ], 200);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $plan = SubscriptionPlan::find($id);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Paket langganan tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $plan
        ], 200);
    }

}
