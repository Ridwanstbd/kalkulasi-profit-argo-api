<?php

namespace App\Http\Middleware;

use App\Models\Material;
use App\Models\Product;
use App\Models\UserSubscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckResourceLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $resourceType): Response
    {
        if ($request->isMethod('get') || $request->isMethod('put') || $request->isMethod('delete')) {
            return $next($request);
        }
        $user = JWTAuth::user();
        if ($user->hasRole('admin')) {
            return $next($request);
        }
        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>=', Carbon::now()->format('Y-m-d'))
            ->with('subscriptionPlan')
            ->first();
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki langganan aktif. Silakan berlangganan untuk menambah ' . 
                ($resourceType == 'product' ? 'produk' : 'bahan') . '.'
            ], 403);
        }
        if ($resourceType == 'product') {
            $maxCount = $subscription->subscriptionPlan->max_products;
            $currentCount = Product::where('user_id', $user->id)->count();
            $resourceLabel = 'produk';
        } 
        if ($maxCount != -1 && $currentCount >= $maxCount) {
            return response()->json([
                'success' => false,
                'message' => "Anda telah mencapai batas jumlah $resourceLabel dalam paket langganan Anda. Silakan upgrade langganan untuk menambah lebih banyak $resourceLabel."
            ], 403);
        }
        return $next($request);
    }
}
