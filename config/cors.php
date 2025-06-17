<?php

return [

    'paths' => ['*'], // Aceptar todas las rutas

    'allowed_methods' => ['*'], // Permitir GET, POST, PUT, DELETE, etc.

    'allowed_origins' => ['http://localhost:4200', 'https://7cf3-2806-104e-1b-9c1d-ed18-1d47-dda9-4060.ngrok-free.app',], // Permitir Angular local

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // Permitir cualquier encabezado

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
