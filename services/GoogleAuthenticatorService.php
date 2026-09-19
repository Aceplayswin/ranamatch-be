<?php
/**
 * Lightweight, dependency-free Google Authenticator (TOTP RFC 6238) Service for PHP
 */
class GoogleAuthenticatorService {
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random 16-character Base32 secret key
     */
    public static function generateSecret($length = 16) {
        $secret = '';
        $validChars = self::$base32Chars;
        for ($i = 0; $i < $length; $i++) {
            $secret .= $validChars[random_int(0, strlen($validChars) - 1)];
        }
        return $secret;
    }

    /**
     * Calculate 6-digit TOTP code for a given secret and time slice
     */
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);

        // Pack timeinto 8-byte big-endian binary string
        $time = pack('N*', 0) . pack('N*', $timeSlice);

        // Hash using HMAC-SHA1
        $hmac = hash_hmac('sha1', $time, $secretKey, true);

        // Dynamic truncation
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);

        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;

        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a 6-digit TOTP code with time drift tolerance
     */
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        if (empty($secret) || empty($code)) {
            return false;
        }

        $currentTimeSlice = floor(time() / 30);
        $code = trim($code);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate otpauth:// URI and QR Code URL
     */
    public static function getQrCodeUrl($accountName, $secret, $issuer = 'Velplay') {
        $otpauthUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($accountName),
            rawurlencode($secret),
            rawurlencode($issuer)
        );

        return [
            'otpauth_url' => $otpauthUrl,
            'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode($otpauthUrl)
        ];
    }

    /**
     * Decode Base32 string to binary
     */
    private static function base32Decode($secret) {
        if (empty($secret)) return '';
        $secret = strtoupper($secret);
        $base32chars = self::$base32Chars;
        $base32charsFlipped = array_flip(str_split($base32chars));

        $paddingCharCount = substr_count($secret, '=');
        $allowedPaddingCount = [6, 4, 3, 1, 0];
        if (!in_array($paddingCharCount, $allowedPaddingCount)) return false;

        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount == $allowedPaddingCount[$i] &&
                substr($secret, -($allowedPaddingCount[$i])) != str_repeat('=', $allowedPaddingCount[$i])) {
                return false;
            }
        }
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        for ($i = 0; $i < count($secret); $i += 8) {
            $x = '';
            if (!in_array($secret[$i], str_split($base32chars))) return false;
            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); $z++) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
            }
        }
        return $binaryString;
    }
}
