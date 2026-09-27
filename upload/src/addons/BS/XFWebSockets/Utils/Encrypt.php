<?php

namespace BS\XFWebSockets\Utils;

class Encrypt
{
    public static function toAes256CbcBase64(string $data, string $key): string
    {
        $iv = openssl_random_pseudo_bytes(
            openssl_cipher_iv_length('aes-256-cbc'),
            $strong
        );
        if (! $iv || ! $strong) {
            throw new \RuntimeException('IV generation failed');
        }
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function fromAes256CbcBase64(string $data, string $key): string
    {
        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
}
