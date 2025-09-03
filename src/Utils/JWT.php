<?php

namespace App\Utils;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    private static $secret_key;
    private static $algorithm = 'HS256';
    private static $expiration;

    public static function init()
    {
        self::$secret_key = $_ENV['JWT_SECRET'];
        self::$expiration = $_ENV['JWT_EXPIRATION'] ?? 86400;
    }

    public static function encode($payload)
    {
        self::init();
        $payload['iat'] = time();
        $payload['exp'] = time() + self::$expiration;
        return FirebaseJWT::encode($payload, self::$secret_key, self::$algorithm);
    }

    public static function decode($token)
    {
        self::init();
        try {
            return FirebaseJWT::decode($token, new Key(self::$secret_key, self::$algorithm));
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function validate($token)
    {
        return self::decode($token) !== false;
    }
}
