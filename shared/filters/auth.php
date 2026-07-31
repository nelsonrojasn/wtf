<?php
// shared/filters/auth.php

function auth(array $request): mixed
{
    $session = EncryptedCookie::get('wtf_session');

    if (!$session) {
        return json(['error' => 'No autorizado'], 401);
    }

    return true; // Continúa
}
