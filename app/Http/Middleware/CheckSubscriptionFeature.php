<?php

namespace App\Http\Middleware;

use App\Models\UserSubscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckSubscriptionFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $feature): Response
    {
        $user = JWTAuth::user();
        if ($user->hasRole('admin')) {
            return $next($request);
        }
        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>=', Carbon::now()->format('Y-m-d'))
            ->with('subscriptionPlan')
            ->first();

        // Jika tidak ada langganan aktif
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki langganan aktif. Silakan berlangganan untuk mengakses fitur ini.'
            ], 403);
        }

        $features = json_decode($subscription->subscriptionPlan->features, true);
        $hasAccess = isset($features[$feature]) && $features[$feature] === true;

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini tidak tersedia dalam paket langganan Anda. Silakan upgrade langganan Anda.'
            ], 403);
        }

        return $next($request);
    }
}
