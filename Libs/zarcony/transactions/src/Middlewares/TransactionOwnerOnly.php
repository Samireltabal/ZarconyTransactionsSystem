<?php

namespace Zarcony\Transactions\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Zarcony\Transactions\Models\Transaction;
class TransactionOwnerOnly
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
        $user_uuid = \Auth::user()->uuid;
        $transaction = Transaction::Uuid($request->route()->parameter('uuid'))->first();
        
        if ($user_uuid == $transaction->reciever_identifier || $user_uuid == $transaction->sender_identifier ) {
            return $next($request);
        }
        return response()->json([
            'message' => 'unauthorised access'
        ], 401);

    }
}
