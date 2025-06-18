<?php

return [

    'paths' => ['*'], // Aceptar todas las rutas

    'allowed_methods' => ['*'], // Permitir GET, POST, PUT, DELETE, etc.

    'allowed_origins' => ['http://localhost:4200', 'https://3dab-2806-104e-1b-54c1-6129-f572-3525-cd2d.ngrok-free.app',], // Permitir Angular local

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // Permitir cualquier encabezado

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
