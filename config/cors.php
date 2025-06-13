<?php

return [

    'paths' => ['*'], // Aceptar todas las rutas

    'allowed_methods' => ['*'], // Permitir GET, POST, PUT, DELETE, etc.

    'allowed_origins' => ['http://localhost:4200', 'https://bbd4-2806-104e-1b-9ac7-494a-2c49-d22-dd95.ngrok-free.app',], // Permitir Angular local

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // Permitir cualquier encabezado

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
