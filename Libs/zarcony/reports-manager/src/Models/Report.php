<?php

namespace Zarcony\ReportsManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'from',
        'to',
        'report_body',
        'ui_module',
        'type',
        'ready',
        'user_id'
    ];

    protected $hidden = [
        'report_body'
    ];

    protected $with = [
        'user'
    ];

    protected $casts = [
        'report_body' => 'array'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->identifier = (string) Str::uuid();
        });
    }

    public function user() {
        return $this->belongsTo('\App\Models\User', 'user_id', 'id');
    }

    public function scopeUuid($query, $uuid) {
        return $query->where('identifier', '=', $uuid);
    }
    
}
