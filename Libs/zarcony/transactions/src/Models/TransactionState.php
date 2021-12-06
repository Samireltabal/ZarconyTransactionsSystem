<?php

namespace Zarcony\Transactions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionState extends Model
{
    use HasFactory;

    protected $fillable = [
        'state_name',
        'color_code'
    ];
    
}
