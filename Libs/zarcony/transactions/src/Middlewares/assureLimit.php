<?php

namespace Zarcony\Transactions\Middlewares;

use Closure;
use Illuminate\Http\Request;

class assureLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = \Auth::user();
        if( $user->wallet->LastHourTotalTransactions > config('transactions.limit_per_day') ) {
            return response()->json([
                'message' => 'limit exceded'
            ], 401);
        }
        if ($request->input('amount') + $user->wallet->LastHourTotalTransactions > config('transactions.limit_per_day')) {
            return response()->json([
                'message' => 'limit exceded'
            ], 401);
        }
        return $next($request);
    }
}
