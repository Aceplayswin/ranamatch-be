<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define("API_KEY", "48f8d4852883c8dcb3476492709d00c7dc72c710169bed4e0805fc62556577b6");
define("MERCHANT_ID", "MPTLIVE987654331");
define("ACCOUNT_ID", "z7VifXFxBvinkcs0WmCsxgsxhDfVAg");

date_default_timezone_set('Asia/Kolkata');

function logError($message) {
    file_put_contents(__DIR__ . '/payment_errors.log', "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
}

function makeApiRequest($url, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . API_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    function fetchRechargeHistory($conn, string $userId): array {
    $stmt = $conn->prepare("SELECT tbl_uniq_id, tbl_recharge_amount, tbl_recharge_mode, tbl_recharge_details, tbl_request_status, tbl_time_stamp FROM tblusersrecharge WHERE tbl_user_id = ? ORDER BY id DESC LIMIT 20");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        logError("cURL error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    logError("Response: $response");

    return ($httpCode === 200) ? json_decode($response, true) : null;
}

try {
    $amount = isset($_REQUEST['amount']) ? (float)$_REQUEST['amount'] : 200; // default 200 if not passed
    $userId = isset($_REQUEST['user_id']) ? $_REQUEST['user_id'] : 'testuser';

    if ($amount < 100) throw new Exception("Minimum amount is ₹100");
    if (!$userId) throw new Exception("Missing User ID");

    $orderId = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ") . rand(1000000000, 9999999999), 0, 18);

    $payload = [
        "merchant_id" => MERCHANT_ID,
        "acc_id" => ACCOUNT_ID,
        "amount" => $amount,
        "currency" => "INR",
        "order_id" => $orderId,
        "sub_pay_mode" => "qr_ap",
        "vpa" => "ddadadas@upi",
        "cust_name" => "Rohit Kumar",
        "cust_email" => "rohi22t@example.com",
        "cust_phone" => "8874897940",
        "callback_url" => "https://pay.winco.cc/gateapi/payments/gateways1/initialisation/callback.php",
        "redirect_url" => "https://winco.cc/transaction"
    ];

    $endpoint = "https://merchant.purntech.com/api/rpay/v1/pay-request";
    $response = makeApiRequest($endpoint, $payload);

    if (!$response) {
        throw new Exception("Failed to get response from payment gateway");
    }

} catch (Exception $e) {
    echo "<h3>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Payment Info</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap');

  body {
    font-family: 'Montserrat', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    margin: 0;
    padding: 40px 20px;
    display: flex;
    justify-content: center;
    min-height: 100vh;
  }

  .container {
    background: #1a1a2e;
    max-width: 720px;
    width: 100%;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    padding: 30px 40px;
    text-align: center;
  }

  pre {
    background: #222244;
    color: #cbd5e1;
    border-radius: 12px;
    padding: 20px;
    text-align: left;
    max-height: 280px;
    overflow-y: auto;
    box-shadow: inset 0 0 15px rgba(255,255,255,0.05);
    font-size: 14px;
  }

  a.btn, button.btn {
    display: inline-block;
    background: #6c63ff;
    color: white;
    font-weight: 600;
    border-radius: 50px;
    padding: 14px 30px;
    margin: 20px 10px 0 10px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background 0.3s ease;
    font-size: 16px;
  }

  a.btn:hover, button.btn:hover {
    background: #4b47c6;
  }

  .qr-code {
    margin-top: 30px;
  }

  .qr-code img {
    border-radius: 12px;
    box-shadow: 0 0 20px rgba(108,99,255,0.5);
    width: 200px;
    height: 200px;
    object-fit: contain;
  }
</style>
</head>
<body>

<div class="container">
    <pre><?php echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>

    <?php if (!empty($response['data']['qr_string'])): ?>
        <a href="<?php echo htmlspecialchars($response['data']['qr_string']); ?>" class="btn" target="_blank" rel="noopener noreferrer">Pay Using UPI App</a>
    <?php endif; ?>

    <?php if (!empty($response['data']['qr_code'])): ?>
        <div class="qr-code">
            <img id="qrImg" src="<?php echo htmlspecialchars($response['data']['qr_code']); ?>" alt="UPI QR Code" />
            <br />
            <button class="btn" onclick="downloadQR()">Download QR Code</button>
        </div>
    <?php endif; ?>
</div>

<script>
  function downloadQR() {
    const img = document.getElementById('qrImg');
    const url = img.src;
    const link = document.createElement('a');
    link.href = url;
    link.download = 'upi-qr-code.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }
</script>

</body>
</html>
