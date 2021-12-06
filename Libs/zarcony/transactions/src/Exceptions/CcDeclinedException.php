<?php

namespace Zarcony\Transactions\Exceptions;

use Exception;

class CcDeclinedException extends Exception
{
    public function render($request)
    {       
        return response()->json(
            [
                "error" => true, 
                "data" => $this->getMessage()
            ]
        , 434);       
    }

}
