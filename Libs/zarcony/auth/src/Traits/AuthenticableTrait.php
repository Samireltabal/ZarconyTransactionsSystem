<?php
    namespace Zarcony\Auth\Traits;
    use Spatie\Permission\Traits\HasRoles;
    use Laravel\Passport\HasApiTokens;
    use Hash;
    use Str;
    use Illuminate\Notifications\Notifiable;
    use Zarcony\Auth\Notifications\PasswordResetRequested;

    trait AuthenticableTrait {
        use HasApiTokens, Notifiable, HasRoles;
        public $guard_name = 'api';

        public function __construct(array $attributes = []) {
            $this->fillable[] = 'uuid';
            $this->fillable[] = 'phone';
            $this->with[] = 'notifications';
            $this->with[] = 'unreadNotifications';
            $this->appends[] = 'role';
            $this->hidden[] = 'roles';
            parent::__construct($attributes);
        }

        public static function boot()
        {
            parent::boot();
            self::creating(function ($model) {
                $model->uuid = (string) Str::uuid();
            });
        }

        public function sendPasswordResetNotification($token) {
            $this->notify(new PasswordResetRequested($token));
        }

        public function scopeEmail($query, $value) {
            return $query->where('email', $value);
        }
        public function setPasswordAttribute($value){
            $this->attributes['password'] = Hash::make($value);
        }

        public function otp() {
            return $this->hasOne('Zarcony\Auth\Models\Otp','user_id');
        }

        public function getRoleAttribute() {
            if (!$this->roles->count()) {
                return 'n/a';
            }
            return $this->roles[0] ? $this->roles[0]->name : 'n/a';
        }

        public function scopeUuid($query, $uuid) {
            return $query->where('uuid', '=', $uuid);
        }
    }
