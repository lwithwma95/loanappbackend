<?php

return [
    'base_url'              => env('KEYCLOAK_BASE_URL', 'http://localhost:8080'),
    'realm'                 => env('KEYCLOAK_REALM', 'asrlm'),
    'frontend_client_id'    => env('KEYCLOAK_FRONTEND_CLIENT_ID', 'asrlm-frontend'),
    'backend_client_id'     => env('KEYCLOAK_BACKEND_CLIENT_ID', 'asrlm-backend'),
    'backend_client_secret' => env('KEYCLOAK_BACKEND_CLIENT_SECRET'),
];
