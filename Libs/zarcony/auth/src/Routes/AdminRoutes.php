<?php
    use Zarcony\Auth\Controllers\AdminController;
    
    Route::get('/', function () {
        return response()->json([
            'message' => 'you are admin'
        ], 200);
    });

    Route::get('/accounts', [AdminController::class, 'list_accounts']);
    Route::get('/report', [AdminController::class, 'general_report']);
