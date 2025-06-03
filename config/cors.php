<?php

return [

    'paths' => ['*'], // Aceptar todas las rutas

    'allowed_methods' => ['*'], // Permitir GET, POST, PUT, DELETE, etc.

    'allowed_origins' => ['http://localhost:4200', 'https://8c4f-148-230-223-134.ngrok-free.app',], // Permitir Angular local

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // Permitir cualquier encabezado

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
