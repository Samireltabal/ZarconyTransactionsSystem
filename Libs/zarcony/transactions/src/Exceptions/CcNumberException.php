<?php

namespace Zarcony\Transactions\Exceptions;

use Exception;

class CcNumberException extends Exception
{
    public function render($request)
    {       
        return response()->json(
            [
                "error" => true, 
                "data" => $this->getMessage()
            ]
        , 433);       
    }

}
