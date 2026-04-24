<?php
include '../security/constants.php';
$SERVER_URL = $_SERVER['SERVER_NAME'];

if($SERVER_URL==""){
    echo "Server URL error";
    return;
}

function downloadApk($url, $app_name) {
    // Check if the URL is valid
    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
        die('Invalid URL');
    }

    // Initialize a cURL session
    $ch = curl_init($url);

    // Check if the URL is valid
    if ($ch === FALSE) {
        die('Invalid URL');
    }

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Execute the cURL session
    $fileContent = curl_exec($ch);

    // Check for errors
    if ($fileContent === FALSE) {
        die('cURL Error: ' . curl_error($ch));
    }

    // Get HTTP response code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Check if the request was successful
    if ($httpCode != 200) {
        die('HTTP Error: ' . $httpCode);
    }

    // Close the cURL session
    curl_close($ch);

    // Set headers to trigger a download in the user's browser
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="'.$app_name.'.apk"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($fileContent));

    // Output the file content
    echo $fileContent;
}

// download file
$FILE_LOCATION = 'https://'.$SERVER_URL."/storage/app.apk";
downloadApk($FILE_LOCATION, $APP_NAME);
?>
