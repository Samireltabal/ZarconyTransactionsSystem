<?php

namespace SamirEltabal\Authsystem\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $table = 'password_resets';
	protected $fillable = ['email' , 'token', 'created_at'];
    public $timestamps = false;
    // protected $primaryKey = 'email';
    public function scopeToken($query, $value) {
        return $query->where('token', $value);
    }
    
    public function scopeActive($query) {
        return $query->where('created_at', '>', \Carbon\Carbon::now()->subHours(6));
    }
}
