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


// Auto-generate Indian-style customer name, phone, and email
$firstNames = ['Aarav', 'Vivaan', 'Aditya', 'Krishna', 'Ishaan', 'Anaya', 'Diya', 'Riya', 'Kavya', 'Meera'];
$lastNames = ['Sharma', 'Verma', 'Kumar', 'Singh', 'Patel', 'Reddy', 'Nair', 'Joshi', 'Choudhary', 'Mishra'];

$first = $firstNames[array_rand($firstNames)];
$last = $lastNames[array_rand($lastNames)];

$uniqueId = substr(uniqid(), -5); // small unique ID based on microtime

$cust_name = "$first $last$uniqueId"; // e.g. Aarav Sharmah12f3
$cust_phone = "9" . rand(100000000, 999999999);
$cust_email = strtolower($first . $uniqueId . '@example.com'); // e.g. aaravh12f3@example.com

    $payload = [
    "merchant_id" => MERCHANT_ID,
    "acc_id" => ACCOUNT_ID,
    "amount" => $amount,
    "currency" => "INR",
    "order_id" => $orderId,
    "sub_pay_mode" => "qr_ap",
    "vpa" => "ddadadas@upi",
    "cust_name" => $cust_name,
    "cust_email" => $cust_email,
    "cust_phone" => $cust_phone,
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

  .note {
    margin-top: 30px;
    font-size: 16px;
    color: #cbd5e1;
    font-weight: 500;
  }
</style>
</head>
<body>

<div class="container">
  

    <?php if (!empty($response['data']['qr_code'])): ?>
        <div class="qr-code">
            <img id="qrImg" src="<?php echo htmlspecialchars($response['data']['qr_code']); ?>" alt="UPI QR Code" />
            <br />
            <button class="btn" onclick="downloadQR()">Download QR Code</button>
        </div>
    <?php endif; ?>
    
      <?php if (!empty($response['data']['qr_string'])): ?>
        <a href="<?php echo htmlspecialchars($response['data']['qr_string']); ?>" class="btn" target="_blank" rel="noopener noreferrer">Pay Using UPI App</a>
    <?php endif; ?>

    <div class="note">
        After successful payment, the amount will automatically be added to your account.
    </div>
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
