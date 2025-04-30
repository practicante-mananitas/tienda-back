<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class DisableCsrf extends Middleware
{
    protected $except = [
      
    ];
}
