<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Log;

class Logger
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

        if(\Auth::user()) {
            if(\Auth::user()->role != 'admin') {
                $log = \Auth::user()->logs()->create([
                    'url'           => $request->path(),
                    'message'       => "Request method : " . $request->method(),
                    'ip_address'    => $request->ip(),
                    'agent'         => $request->header('user-agent'),
                    'user_id'       => \Auth::user()->id
                ]);
            }
        } else {
            $log = Log::create([
                'url'           => $request->path(),
                'message'       => "Request method : " . $request->method(),
                'ip_address'    => $request->ip(),
                'agent'         => $request->header('user-agent'),
                'user_id'       => null
            ]);
        }
        return $next($request);
    }
}
