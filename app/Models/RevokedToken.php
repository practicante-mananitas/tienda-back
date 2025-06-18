<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevokedToken extends Model
{
    public $timestamps = false;
    protected $fillable = ['token', 'revoked_at'];
}
