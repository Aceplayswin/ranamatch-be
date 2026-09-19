<?php
/**
 * Admin Affiliate List API Endpoint
 * Endpoint: GET /admin/affiliates/api_list.php
 * Returns paginated affiliate list + top summary stats.
 */

define("ACCESS_SECURITY", "true");
require_once __DIR__ . '/../../security/config.php';
require_once __DIR__ . '/../access_validate.php';
require_once __DIR__ . '/../../services/NotificationHelper.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() !== "true") {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin session unauthorized."]);
    exit;
}

// Handle POST actions (Status Update, Approval, Deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = (int)($_SESSION['admin_user_id'] ?? 1);
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
    $action = $input['action'] ?? '';
    $affId = (int)($input['affiliate_id'] ?? 0);

    if (!$affId) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing affiliate_id parameter."]);
        exit;
    }

    // Status change (Approve, Activate, Suspend, Close)
    if (isset($input['status']) || $action === 'approve') {
        $newStatus = strtolower(trim($input['status'] ?? 'active'));
        if ($action === 'approve' || $newStatus === 'approved') {
            $newStatus = 'active';
        }

        if (in_array($newStatus, ['active', 'pending', 'suspended', 'closed'])) {
            if ($action === 'approve') {
                $dealType = strtolower(trim($input['deal_type'] ?? 'revenue_share'));
                if ($dealType === 'revshare') $dealType = 'revenue_share';
                if (!in_array($dealType, ['revenue_share', 'cpa', 'hybrid'])) $dealType = 'revenue_share';
                $revsharePct = max(0, min(100, (float)($input['revshare_pct'] ?? 30)));
                $cpaAmount = max(0, (float)($input['cpa_amount'] ?? 0));
                $tier = strtolower(trim($input['tier'] ?? 'bronze'));
                if (!in_array($tier, ['bronze', 'silver', 'gold', 'platinum'])) $tier = 'bronze';

                $uStmt = mysqli_prepare($conn, "UPDATE affiliates SET status = ?, deal_type = ?, revshare_pct = ?, cpa_amount = ?, tier = ? WHERE id = ?");
                mysqli_stmt_bind_param($uStmt, "ssddsi", $newStatus, $dealType, $revsharePct, $cpaAmount, $tier, $affId);
            } else {
                $uStmt = mysqli_prepare($conn, "UPDATE affiliates SET status = ? WHERE id = ?");
                mysqli_stmt_bind_param($uStmt, "si", $newStatus, $affId);
            }
            if (mysqli_stmt_execute($uStmt)) {
                if ($action === 'approve') {
                    $audit = mysqli_prepare($conn, "INSERT INTO admin_audit_logs (admin_id, action_group, action_type, target_entity_id, target_entity_type, payload_after, ip_address) VALUES (?, 'AFFILIATE_APPLICATION', 'application.approved', ?, 'affiliate', ?, ?)");
                    $auditPayload = json_encode([
                        'deal_type' => $dealType,
                        'revshare_pct' => $revsharePct,
                        'cpa_amount' => $cpaAmount,
                        'tier' => $tier
                    ]);
                    $auditIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    mysqli_stmt_bind_param($audit, "iiss", $adminId, $affId, $auditPayload, $auditIp);
                    mysqli_stmt_execute($audit);
                }

                // Ensure approved affiliate has default tracking link
                if ($newStatus === 'active') {
                    $chk = mysqli_query($conn, "SELECT affiliate_code FROM affiliates WHERE id = $affId");
                    if ($chkRow = mysqli_fetch_assoc($chk)) {
                        $code = $chkRow['affiliate_code'];
                        $lChk = mysqli_query($conn, "SELECT id FROM affiliate_links WHERE affiliate_id = $affId");
                        if (mysqli_num_rows($lChk) === 0) {
                            $linkStmt = mysqli_prepare($conn, "INSERT INTO affiliate_links (affiliate_id, name, code, target_path) VALUES (?, 'Default Referral Link', ?, '/')");
                            mysqli_stmt_bind_param($linkStmt, "is", $affId, $code);
                            mysqli_stmt_execute($linkStmt);
                        }
                    }

                    sendAffiliateNotification(
                        $conn, 
                        $affId, 
                        'account', 
                        'Account Activated', 
                        'Your affiliate partner account is now active and approved for operations.', 
                        '/dashboard'
                    );
                } else if ($newStatus === 'suspended') {
                    sendAffiliateNotification(
                        $conn, 
                        $affId, 
                        'account', 
                        'Account Suspended', 
                        'Your affiliate partner account has been suspended by administration. Please contact partner support.', 
                        '/support'
                    );
                }

                echo json_encode([
                    "status" => "success", 
                    "message" => "Affiliate status successfully updated to " . ucfirst($newStatus) . "."
                ]);
                exit;
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Database update failed: " . mysqli_error($conn)]);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid status. Allowed: active, pending, suspended, closed."]);
            exit;
        }
    }

    // Delete affiliate
    if ($action === 'delete') {
        mysqli_query($conn, "DELETE FROM affiliate_links WHERE affiliate_id = $affId");
        mysqli_query($conn, "DELETE FROM affiliate_referrals WHERE affiliate_id = $affId");
        $dStmt = mysqli_prepare($conn, "DELETE FROM affiliates WHERE id = ?");
        mysqli_stmt_bind_param($dStmt, "i", $affId);
        if (mysqli_stmt_execute($dStmt)) {
            echo json_encode(["status" => "success", "message" => "Affiliate successfully deleted."]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database delete failed: " . mysqli_error($conn)]);
            exit;
        }
    }

    // Update Deal structure (deal_type, revshare_pct, cpa_amount, sub_override_pct)
    if ($action === 'update_deal') {
        $dealType = strtolower(trim($input['deal_type'] ?? 'revenue_share'));
        if ($dealType === 'revshare') {
            $dealType = 'revenue_share';
        }
        $revsharePct = floatval($input['revshare_pct'] ?? 35);
        $cpaAmount = floatval($input['cpa_amount'] ?? 0);
        $subOverridePct = floatval($input['sub_override_pct'] ?? 5.0);

        if (!in_array($dealType, ['revenue_share', 'cpa', 'hybrid'])) {
            $dealType = 'revenue_share';
        }

        $dealStmt = mysqli_prepare($conn, "UPDATE affiliates SET deal_type = ?, revshare_pct = ?, cpa_amount = ?, sub_override_pct = ? WHERE id = ?");
        mysqli_stmt_bind_param($dealStmt, "sdddi", $dealType, $revsharePct, $cpaAmount, $subOverridePct, $affId);
        if (mysqli_stmt_execute($dealStmt)) {
            echo json_encode([
                "status" => "success",
                "message" => "Affiliate deal structure updated successfully.",
                "data" => [
                    "deal_type" => $dealType,
                    "revshare_pct" => $revsharePct,
                    "cpa_amount" => $cpaAmount,
                    "sub_override_pct" => $subOverridePct
                ]
            ]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to update deal terms: " . mysqli_error($conn)]);
            exit;
        }
    }

    // Update Profile (full_name, company_name, phone, website, tier, status, parent_id)
    if ($action === 'update_profile') {
        $fullName = trim($input['full_name'] ?? '');
        $companyName = trim($input['company_name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $website = trim($input['website'] ?? '');
        $tier = strtolower(trim($input['tier'] ?? 'bronze'));
        $status = strtolower(trim($input['status'] ?? 'active'));
        $parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : -1;

        if (!in_array($tier, ['bronze', 'silver', 'gold', 'platinum'])) $tier = 'bronze';
        if (!in_array($status, ['active', 'pending', 'suspended', 'closed'])) $status = 'active';

        if ($parentId > 0 && $parentId !== $affId) {
            $profStmt = mysqli_prepare($conn, "UPDATE affiliates SET full_name = ?, company_name = ?, phone = ?, website = ?, tier = ?, status = ?, parent_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($profStmt, "ssssssii", $fullName, $companyName, $phone, $website, $tier, $status, $parentId, $affId);
        } elseif ($parentId === 0) {
            $profStmt = mysqli_prepare($conn, "UPDATE affiliates SET full_name = ?, company_name = ?, phone = ?, website = ?, tier = ?, status = ?, parent_id = NULL WHERE id = ?");
            mysqli_stmt_bind_param($profStmt, "ssssssi", $fullName, $companyName, $phone, $website, $tier, $status, $affId);
        } else {
            $profStmt = mysqli_prepare($conn, "UPDATE affiliates SET full_name = ?, company_name = ?, phone = ?, website = ?, tier = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($profStmt, "ssssssi", $fullName, $companyName, $phone, $website, $tier, $status, $affId);
        }

        if (mysqli_stmt_execute($profStmt)) {
            echo json_encode([
                "status" => "success",
                "message" => "Affiliate profile details updated successfully."
            ]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to update profile: " . mysqli_error($conn)]);
            exit;
        }
    }

    // Change Affiliate Password by Admin
    if ($action === 'change_password') {
        $chkStmt = mysqli_prepare($conn, "SELECT id, email, full_name, affiliate_code, status FROM affiliates WHERE id = ?");
        mysqli_stmt_bind_param($chkStmt, "i", $affId);
        mysqli_stmt_execute($chkStmt);
        $affData = mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt));

        if (!$affData) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Affiliate with ID {$affId} was not found in database."]);
            exit;
        }

        $newPassword = trim($input['new_password'] ?? '');
        if (strlen($newPassword) < 6) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters long."]);
            exit;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $passStmt = mysqli_prepare($conn, "UPDATE affiliates SET password_hash = ? WHERE id = ?");
        mysqli_stmt_bind_param($passStmt, "si", $passwordHash, $affId);
        $ok = mysqli_stmt_execute($passStmt);
        if ($ok) {
            echo json_encode([
                "status" => "success",
                "message" => "Password successfully updated for partner {$affData['email']} ({$affData['affiliate_code']}).",
                "data" => [
                    "id" => (int)$affData['id'],
                    "email" => $affData['email'],
                    "affiliate_code" => $affData['affiliate_code']
                ]
            ]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to update password: " . mysqli_error($conn)]);
            exit;
        }
    }

    // Save or Update Payout/Bank Method by Admin
    if ($action === 'save_payout_method') {
        $methodType = trim($input['method_type'] ?? 'bank_transfer');
        $details = $input['account_details'] ?? $input['details'] ?? [];
        $isPrimary = !empty($input['is_primary']) ? 1 : 1;

        if (is_array($details)) {
            $detailsJson = json_encode($details);
        } else {
            $detailsJson = (string)$details;
        }

        if (empty($detailsJson) || $detailsJson === '{}') {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Bank account details are required."]);
            exit;
        }

        if (!in_array($methodType, ['bank_transfer', 'UPI', 'crypto', 'ewallet'])) {
            $methodType = 'bank_transfer';
        }

        $methodId = (int)($input['method_id'] ?? 0);

        if ($methodId > 0) {
            $uStmt = mysqli_prepare($conn, "UPDATE affiliate_payout_methods SET method_type = ?, account_details = ?, is_primary = ? WHERE id = ? AND affiliate_id = ?");
            mysqli_stmt_bind_param($uStmt, "ssiii", $methodType, $detailsJson, $isPrimary, $methodId, $affId);
            mysqli_stmt_execute($uStmt);
        } else {
            if ($isPrimary === 1) {
                mysqli_query($conn, "UPDATE affiliate_payout_methods SET is_primary = 0 WHERE affiliate_id = $affId");
            }
            $iStmt = mysqli_prepare($conn, "INSERT INTO affiliate_payout_methods (affiliate_id, method_type, account_details, is_primary) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($iStmt, "issi", $affId, $methodType, $detailsJson, $isPrimary);
            mysqli_stmt_execute($iStmt);
            $methodId = mysqli_insert_id($conn);
        }

        echo json_encode([
            "status" => "success",
            "message" => "Bank / payout method saved successfully.",
            "data" => [
                "id" => $methodId,
                "method_type" => $methodType,
                "details" => json_decode($detailsJson, true)
            ]
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Unknown POST action."]);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

// 1. Fetch Summary Metrics
$sumSql = "SELECT 
    COUNT(CASE WHEN status IN ('approved','active') THEN 1 END) AS active_affiliates,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_affiliates,
    COALESCE(SUM(available_balance), 0) AS total_available_balance,
    COALESCE(SUM(pending_balance), 0) AS total_pending_balance,
    COALESCE(SUM(lifetime_earnings), 0) AS total_lifetime_earnings
FROM affiliates";
$sumRes = mysqli_query($conn, $sumSql);
$summary = mysqli_fetch_assoc($sumRes);

// Total clicks count
$clickRes = mysqli_query($conn, "SELECT COALESCE(SUM(clicks_count), 0) AS total_clicks FROM affiliate_links");
$summary['total_clicks'] = (int)mysqli_fetch_assoc($clickRes)['total_clicks'];

// Total referrals count
$refRes = mysqli_query($conn, "SELECT COUNT(*) AS total_referrals FROM affiliate_referrals");
$summary['total_referrals'] = (int)mysqli_fetch_assoc($refRes)['total_referrals'];

// 2. Build Filtered Query
$whereClause = "WHERE 1=1";
$params = [];
$typesStr = "";

if (!empty($statusFilter) && $statusFilter !== 'ALL') {
    $whereClause .= " AND a.status = ?";
    $params[] = $statusFilter;
    $typesStr .= "s";
}

if (!empty($search)) {
    $whereClause .= " AND (a.full_name LIKE ? OR a.affiliate_code LIKE ? OR a.email LIKE ? OR a.company_name LIKE ?)";
    $searchWild = "%$search%";
    $params[] = $searchWild;
    $params[] = $searchWild;
    $params[] = $searchWild;
    $params[] = $searchWild;
    $typesStr .= "ssss";
}

// Count total records
$countSql = "SELECT COUNT(*) AS total FROM affiliates a $whereClause";
if (!empty($params)) {
    $cStmt = mysqli_prepare($conn, $countSql);
    mysqli_stmt_bind_param($cStmt, $typesStr, ...$params);
    mysqli_stmt_execute($cStmt);
    $totalRecords = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['total'];
} else {
    $totalRecords = (int)mysqli_fetch_assoc(mysqli_query($conn, $countSql))['total'];
}

// Fetch paginated affiliates
$sql = "SELECT a.id, a.affiliate_code, a.email, a.full_name, a.company_name, a.phone, a.website, a.status, a.kyc_status, a.onboarding_completed, a.tier, a.deal_type, 
               a.revshare_pct, a.cpa_amount, a.sub_override_pct, a.available_balance, a.pending_balance, a.lifetime_earnings, a.created_at,
               pa.full_name AS parent_name, pa.affiliate_code AS parent_code,
               (SELECT COUNT(*) FROM affiliate_referrals r WHERE r.affiliate_id = a.id) AS total_referrals,
               (SELECT COALESCE(SUM(clicks_count), 0) FROM affiliate_links l WHERE l.affiliate_id = a.id) AS total_clicks,
               (SELECT COUNT(*) FROM affiliate_kyc_documents d WHERE d.affiliate_id = a.id AND d.status = 'pending') AS pending_kyc_count
        FROM affiliates a
        LEFT JOIN affiliates pa ON a.parent_id = pa.id
        $whereClause
        ORDER BY a.created_at DESC
        LIMIT ?, ?";

$params[] = $offset;
$params[] = $limit;
$typesStr .= "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $typesStr, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$affiliates = mysqli_fetch_all($res, MYSQLI_ASSOC);

$formattedAffiliates = array_map(function($r) {
    return [
        "id" => (int)$r['id'],
        "affiliate_code" => $r['affiliate_code'],
        "full_name" => $r['full_name'],
        "email" => $r['email'],
        "company_name" => $r['company_name'],
        "phone" => $r['phone'],
        "website" => $r['website'],
        "status" => $r['status'],
        "kyc_status" => $r['kyc_status'] ?? 'not_submitted',
        "onboarding_completed" => (int)($r['onboarding_completed'] ?? 0),
        "pending_kyc_count" => (int)($r['pending_kyc_count'] ?? 0),
        "tier" => $r['tier'],
        "deal_type" => $r['deal_type'],
        "revshare_pct" => (float)$r['revshare_pct'],
        "cpa_amount" => (float)$r['cpa_amount'],
        "sub_override_pct" => (float)$r['sub_override_pct'],
        "available_balance" => (float)$r['available_balance'],
        "pending_balance" => (float)$r['pending_balance'],
        "lifetime_earnings" => (float)$r['lifetime_earnings'],
        "parent_name" => $r['parent_name'] ?? null,
        "parent_code" => $r['parent_code'] ?? null,
        "total_referrals" => (int)$r['total_referrals'],
        "total_clicks" => (int)$r['total_clicks'],
        "created_at" => $r['created_at']
    ];
}, $affiliates);

echo json_encode([
    "status" => "success",
    "summary" => [
        "active_affiliates" => (int)$summary['active_affiliates'],
        "pending_affiliates" => (int)$summary['pending_affiliates'],
        "total_clicks" => (int)$summary['total_clicks'],
        "total_referrals" => (int)$summary['total_referrals'],
        "total_available_balance" => (float)$summary['total_available_balance'],
        "total_pending_balance" => (float)$summary['total_pending_balance'],
        "total_lifetime_earnings" => (float)$summary['total_lifetime_earnings']
    ],
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total_records" => $totalRecords,
        "total_pages" => ceil($totalRecords / $limit)
    ],
    "data" => $formattedAffiliates
]);
