<?php
// shared/filters/auth.php

function auth(array $request): bool {
    $session = EncryptedCookie::get('wtf_session');
    
    if (!$session) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        return false; // Interrumpe el flujo antes del Handler
    }

    return true; // Continúa
}