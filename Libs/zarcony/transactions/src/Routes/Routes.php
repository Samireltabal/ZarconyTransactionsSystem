<?php
  use Zarcony\Transactions\Controllers\TransactionController;

  Route::get('/', function() {
    return response()->json([
      'message' => 'transaction is responding'
    ], 200);
  });

  Route::get('wallet', [TransactionController::class, 'my_wallet']);
  Route::get('list', [TransactionController::class, 'my_transactions']);
  Route::post('user/search', [TransactionController::class, 'user_autocomplete']);
  Route::post('/pay', [TransactionController::class, 'send_money']);
  Route::post('/checkout', [TransactionController::class, 'checkout']);
  Route::get('/show/{uuid}', [TransactionController::class, 'show_transaction']);