<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoginActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'login_at',
        'last_activity',  // 👈 necesario para detectar sesiones activas
        'logout_at',      // 👈 necesario para saber si la cerraron
        'location',
        'token',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
