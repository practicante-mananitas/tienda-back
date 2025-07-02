<?php

return [

    'paths' => ['*'], // Aceptar todas las rutas

    'allowed_methods' => ['*'], // Permitir GET, POST, PUT, DELETE, etc.

    'allowed_origins' => ['http://localhost:4200', 'https://67ea-2806-104e-1b-1397-e520-a22b-241c-274a.ngrok-free.app',], // Permitir Angular local

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // Permitir cualquier encabezado

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
