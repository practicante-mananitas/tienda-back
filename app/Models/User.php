<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\VerifyApiEmail;
use App\Models\Cart;
use App\Models\Address;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'latitude',
        'longitude',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function address()
    {
        return $this->hasOne(Address::class);
    }

    public function loginActivities()
    {
        return $this->hasMany(LoginActivity::class);
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyApiEmail());
    }

    public function favorites()
    {
        return $this->belongsToMany(Product::class, 'user_favorites', 'user_id', 'product_id')->withTimestamps();
    }

}
