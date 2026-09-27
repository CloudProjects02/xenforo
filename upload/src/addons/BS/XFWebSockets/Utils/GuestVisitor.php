<?php

namespace BS\XFWebSockets\Utils;

class GuestVisitor
{
    public static function userId(string $ip): string
    {
        $config = \XF::config('websockets');
        $hash = $config['guest_ips_hash_method'] ?? 'md5';
        $pusherSecret = \XF::options()->bsXFWebSocketsPusherSecret;
        if (! $pusherSecret) {
            return md5($ip);
        }

        return match ($hash) {
            default => md5($ip),
            // allow to decrypt IP with pusher private key
            'aes_256_cbc_base64' => Encrypt::toAes256CbcBase64($ip, $pusherSecret),
        };
    }
}
