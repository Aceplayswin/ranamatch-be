<?php
/**
 * Affiliate Settings Endpoint
 * Endpoint: GET|POST /affiliate/settings
 * Allows affiliates to view and update their profile details (name, phone, company, website).
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($jwt_user_type !== 'affiliate') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied. Affiliate role required."]);
    exit;
}

$affiliateId = (int)$jwt_user_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;

    $fullName = trim($data['full_name'] ?? $data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $companyName = trim($data['company_name'] ?? $data['company'] ?? '');
    $website = trim($data['website'] ?? '');

    $updateSql = "UPDATE affiliates SET full_name = ?, phone = ?, company_name = ?, website = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($stmt, "ssssi", $fullName, $phone, $companyName, $website, $affiliateId);

    if (mysqli_stmt_execute($stmt)) {
        // Fetch updated profile
        $fetchStmt = mysqli_prepare($conn, "SELECT id, affiliate_code, email, full_name, phone, company_name, website, tier, status, onboarding_completed FROM affiliates WHERE id = ?");
        mysqli_stmt_bind_param($fetchStmt, "i", $affiliateId);
        mysqli_stmt_execute($fetchStmt);
        $updated = mysqli_fetch_assoc(mysqli_stmt_get_result($fetchStmt));

        echo json_encode([
            "status" => "success",
            "message" => "Profile updated successfully.",
            "data" => [
                "id" => (int)$updated['id'],
                "affiliate_code" => $updated['affiliate_code'],
                "name" => $updated['full_name'],
                "full_name" => $updated['full_name'],
                "email" => $updated['email'],
                "phone" => $updated['phone'] ?? '',
                "company_name" => $updated['company_name'] ?? '',
                "company" => $updated['company_name'] ?? '',
                "website" => $updated['website'] ?? '',
                "tier" => $updated['tier'],
                "status" => $updated['status']
            ]
        ]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to update profile: " . mysqli_error($conn)]);
        exit;
    }
}

// GET profile
$fetchStmt = mysqli_prepare($conn, "SELECT id, affiliate_code, email, full_name, phone, company_name, website, tier, status, onboarding_completed FROM affiliates WHERE id = ?");
mysqli_stmt_bind_param($fetchStmt, "i", $affiliateId);
mysqli_stmt_execute($fetchStmt);
$aff = mysqli_fetch_assoc(mysqli_stmt_get_result($fetchStmt));

if (!$aff) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Affiliate not found."]);
    exit;
}

echo json_encode([
    "status" => "success",
    "data" => [
        "id" => (int)$aff['id'],
        "affiliate_code" => $aff['affiliate_code'],
        "name" => $aff['full_name'],
        "full_name" => $aff['full_name'],
        "email" => $aff['email'],
        "phone" => $aff['phone'] ?? '',
        "company_name" => $aff['company_name'] ?? '',
        "company" => $aff['company_name'] ?? '',
        "website" => $aff['website'] ?? '',
        "tier" => $aff['tier'],
        "status" => $aff['status'],
        "onboarding_completed" => (bool)$aff['onboarding_completed']
    ]
]);
