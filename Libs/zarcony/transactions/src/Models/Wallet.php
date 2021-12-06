<?php

namespace Zarcony\Transactions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Zarcony\Transactions\Models\Transaction;

class Wallet extends Model
{
    use HasFactory;

    protected $hidden = [
        'id',
        'created_at',
        'updated_at'
    ];
    
    protected $appends = ['LastHourTotalTransactions'];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
    public function rechargeBalance($value) {
        $this->balance = $this->balance + $value;
        return $this->save();
    }

    public function deductBalance($value) {
        if($this->balance < $value) {
            return false;
        }
        $this->balance = $this->balance - $value;
        return $this->save();
    }

    public function scopeUuid($query, $uuid) {
        return $query->where('user_id', '=', $uuid);
    }

    public function total_transactions () {
        return $this->hasMany(Transaction::class)->where('reciever_identifier','=','user_id')->orWhere('sender_identifier', '=', 'user_id');
    }
    public function recieved_transactions() {
        return $this->hasMany(Transaction::class, 'reciever_identifier', 'user_id');
    }

    public function sent_transactions() {
        return $this->hasMany(Transaction::class, 'sender_identifier', 'user_id');
    }

    public function getLastHourTotalTransactionsAttribute () {
        return $this
                ->sent_transactions()
                ->where('created_at', '>', \Carbon\Carbon::now()->subHour(1))
                ->whereHas('state', function($q) {
                    $q->where('state_name', '=', 'approved');
                })
                ->sum('amount');
    }
    public function getIsTrustedAttribute() {

    }
}
