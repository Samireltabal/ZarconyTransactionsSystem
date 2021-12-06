<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Zarcony\Auth\Traits\AuthenticableTrait;
use Spatie\MediaLibrary\HasMedia;
use Zarcony\Transactions\Traits\Payable;
use App\Models\Log;

class User extends Authenticatable implements MustVerifyEmail, HasMedia
{
    use HasFactory, AuthenticableTrait, Payable;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // protected $with = [
    //     'wallet'
    // ];

    // protected $appends = [
    //     'balance'
    // ];

    public function logs() {
        return $this->morphMany(Log::class, 'loggable');
    }
}
