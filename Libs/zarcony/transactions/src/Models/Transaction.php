<?php

namespace Zarcony\Transactions\Models;

use Zarcony\Transactions\Models\Wallet;
use Zarcony\Transactions\Models\TransactionState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    public static function boot() {
        parent::boot();
        self::creating(function ($model) {
            $model->transaction_identifier = (string) \Str::uuid();
        });
    }

    public function reciever() {
        return $this->belongsTo(Wallet::class, 'reciever_identifier', 'user_id');
    }

    public function sender() {
        return $this->belongsTo(Wallet::class, 'sender_identifier', 'user_id');
    }
    public function state() {
        return $this->belongsTo(TransactionState::class, 'state_id', 'id');
    }

    public function scopeUuid ($query, $value) {
        return $query->where('transaction_identifier', '=', $value);
    }
}
