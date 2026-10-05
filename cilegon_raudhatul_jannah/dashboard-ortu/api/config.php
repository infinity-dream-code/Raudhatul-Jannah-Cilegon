<?php

declare(strict_types=1);

/**
 * Konfigurasi API Smart Payment — dashboard orang tua.
 * Rahasia JWT hanya disimpan di server (jangan expose ke browser).
 */
return [
    'api_base' => 'http://mobile.smartpayment.co.id:8888/CilegonRaudhatulJannahForVPS/Token/',
    'jwt_secret' => '4ecfd4c24aee85b4b485f9d828aa1b7d',
    'timeout' => 30,
];
