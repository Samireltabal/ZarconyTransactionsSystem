<?php
  use Zarcony\Transactions\Controllers\TransactionController;
  use Zarcony\Transactions\Controllers\AdminController;
  Route::get('/', function() {
    return response()->json([
      'message' => 'transaction admin is responding'
    ], 200);
  });

  Route::post('/credit', [AdminController::class, 'credit_user']);
  Route::get('/list', [AdminController::class, 'list_transactions']);
  Route::get('/generate/{number}/{cycles}', [AdminController::class, 'generate_transactions']);
  Route::get('/report', [AdminController::class, 'report_data']);