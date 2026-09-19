<?php
require_once __DIR__ . '/JWTHandler.php';

header('Content-Type: text/plain');

echo "=== SPRINT 2: JWT AUTHENTICATION TEST ===\n\n";

$jwt = new JWTHandler();

// Test 1: Encode Payload
echo "1. Generating JWT Token for Agent #123... ";
$payload = [
    'user_id' => 123,
    'user_type' => 'agent',
    'user_code' => 'AGT-00123'
];
$token = $jwt->encode($payload);
echo "✅ DONE\nToken: " . substr($token, 0, 40) . "...\n\n";

// Test 2: Decode Valid Token
echo "2. Decoding Valid Token... ";
$decoded = $jwt->decode($token);
if ($decoded && $decoded['user_id'] === 123 && $decoded['user_type'] === 'agent') {
    echo "✅ SUCCESS\nDecoded User ID: " . $decoded['user_id'] . "\nRole: " . $decoded['user_type'] . "\nCode: " . $decoded['user_code'] . "\n\n";
} else {
    echo "❌ FAILED\n\n";
}

// Test 3: Decode Tampered Token
echo "3. Testing Tampered Token... ";
$tamperedToken = $token . "tamper";
$decodedTampered = $jwt->decode($tamperedToken);
if ($decodedTampered === null) {
    echo "✅ SUCCESS (Correctly rejected tampered token)\n\n";
} else {
    echo "❌ FAILED (Accepted tampered token)\n\n";
}

// Test 4: Decode Expired Token
echo "4. Testing Expired Token... ";
$expiredToken = $jwt->encode($payload, -10); // Expired 10 seconds ago
$decodedExpired = $jwt->decode($expiredToken);
if ($decodedExpired === null) {
    echo "✅ SUCCESS (Correctly rejected expired token)\n\n";
} else {
    echo "❌ FAILED (Accepted expired token)\n\n";
}

echo "=== SPRINT 2 TEST COMPLETE ===";
