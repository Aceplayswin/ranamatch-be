<?php
/**
 * Lightweight Pure PHP JWT Handler (HS256)
 * Zero external dependencies.
 */
class JWTHandler {
    private $secret;

    public function __construct($secret = null) {
        if ($secret === null) {
            // Fallback secret if not provided
            $this->secret = 'VELPLAY_JWT_SECRET_KEY_SECURE_2026_NINJA';
        } else {
            $this->secret = $secret;
        }
    }

    /**
     * Encode payload into JWT token
     * 
     * @param array $payload Key-value pairs to store in token
     * @param int $expirySeconds Expiration time in seconds (default 24 hours)
     * @return string Signed JWT token
     */
    public function encode(array $payload, int $expirySeconds = 86400): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $expirySeconds;
        
        $payloadJson = json_encode($payload);

        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payloadJson);

        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", $this->secret, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return "$base64Header.$base64Payload.$base64Signature";
    }

    /**
     * Decode and validate JWT token
     * 
     * @param string $token
     * @return array|null Returns decoded payload array if valid, or null if invalid/expired
     */
    public function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($base64Header, $base64Payload, $providedSignature) = $parts;

        // Recalculate signature
        $expectedSignature = hash_hmac('sha256', "$base64Header.$base64Payload", $this->secret, true);
        $expectedBase64Signature = $this->base64UrlEncode($expectedSignature);

        // Constant time comparison to prevent timing attacks
        if (!hash_equals($expectedBase64Signature, $providedSignature)) {
            return null; // Invalid signature
        }

        // Decode payload
        $payloadJson = $this->base64UrlDecode($base64Payload);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            return null; // Invalid JSON
        }

        // Check expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Expired token
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
