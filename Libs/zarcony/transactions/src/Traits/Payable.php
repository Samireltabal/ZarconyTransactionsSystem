<?php 
  namespace Zarcony\Transactions\Traits;
  use Zarcony\Transactions\Models\Wallet;
  use Zarcony\Transactions\Models\Transaction;
  trait Payable {
   public static function booted() {
        parent::booted();
        self::created( function ($model) {
          $model->wallet()->create([]);
        });
     }
     public function wallet() {
        return $this->hasOne(Wallet::class, 'user_id', 'uuid');
     }

     public function getBalanceAttribute() {
       return $this->wallet->balance;
     }
  }