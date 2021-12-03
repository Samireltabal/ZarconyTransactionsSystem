<?php

namespace Zarcony\Auth;
use Illuminate\Support\Facades\Facade;

class ZarconyAuthFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'zarconyauth';
    }

    public static function ping () {
        return response()->json([
            'message' => 'Auth system is responding',
            'version' => config('ZarconyAuth.version')
        ], 200);
    }
}
