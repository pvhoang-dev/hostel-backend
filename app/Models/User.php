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
        'password',
        'phone_number',
        'hometown',
        'identity_card',
        'vehicle_plate',
        'status',
        'role_id',
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

    public function senderRequests()
    {
        return $this->hasMany(Request::class, 'sender_id');
    }

    public function recipientRequests()
    {
        return $this->hasMany(Request::class, 'recipient_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function contracts()
    {
        return $this->belongsToMany(Contract::class, 'contract_users', 'user_id', 'contract_id');
    }

    /**
     * Get the room that the tenant is currently assigned to through active contract
     */
    public function room()
    {
        return $this->hasOneThrough(
            Room::class,
            Contract::class,
            'id', // Khóa ngoại của bảng trung gian (contract_id trong contract_users)
            'id', // Khóa chính của bảng đích (id trong rooms)
            null, // Khóa local là null vì sẽ được xác định trong phương thức first()
            'room_id' // Khóa ngoại trên bảng trung gian liên kết đến bảng đích
        )->whereHas('contracts', function ($query) {
            $query->whereHas('tenants', function ($q) {
                $q->where('users.id', $this->id);
            })->where('status', 'active');
        })->first();
    }

    /**
     * Get the house that the tenant is currently living in (through active contract)
     * or the house that the manager is managing
     */
    public function house()
    {
        // Nếu là manager, lấy nhà từ quan hệ housesManaged
        if ($this->role && $this->role->code === 'manager') {
            return $this->housesManaged()->first();
        }

        // Nếu là tenant, lấy nhà từ phòng trong hợp đồng đang hoạt động
        return $this->hasManyThrough(
            House::class,
            Room::class,
            'id',
            'id',
            null,
            'house_id'
        )->whereHas('rooms', function ($query) {
            $query->whereHas('contracts', function ($q) {
                $q->whereHas('tenants', function ($q2) {
                    $q2->where('users.id', $this->id);
                })->where('status', 'active');
            });
        })->first();
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
                    $user->housesManaged()->update(['manager_id' => $admin->id]);
                    $user->senderRequests()->update(['sender_id' => $admin->id]);
                    $user->recipientRequests()->update(['recipient_id' => $admin->id]);
                }

                $user->notifications()->delete();
            }
        });
    }
}
