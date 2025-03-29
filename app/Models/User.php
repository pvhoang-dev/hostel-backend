<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'phone_number',
        'hometown',
        'identity_card',
        'vehicle_plate',
        'status',
        'role_id',
        'avatar_url',
        'notification_preferences'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function housesCreated()
    {
        return $this->hasMany(House::class, 'created_by');
    }

    public function housesManaged()
    {
        return $this->hasMany(House::class, 'manager_id');
    }

    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            if (!$user->isForceDeleting()) {
                $admin = User::where('role_id', function ($query) {
                    $query->select('id')->from('roles')->where('code', 'admin');
                })->first();

                if ($admin) {
                    $user->housesCreated()->update(['created_by' => $admin->id]);
                    $user->housesManaged()->update(['managed_by' => $admin->id]);
                    $user->maintenanceRequests()->where('status', 'pending')->update(['user_id' => $admin->id]);
                }

                $user->notifications()->delete();
            }
        });
    }
}
