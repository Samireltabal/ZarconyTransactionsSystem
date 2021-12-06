<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'read_at',
        'message',
        'ip_address',
        'url',
        'agent',
        'user_id'
    ];

    public function loggable()
    {
        return $this->morphTo();
    }
}
