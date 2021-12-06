<?php

namespace Zarcony\Transactions\Exceptions;

use Exception;

class CcInSuffiecientException extends Exception
{
    public function render($request)
    {       
        return response()->json(
            [
                "error" => true, 
                "data" => $this->getMessage()
            ]
        , 435);       
    }

}
