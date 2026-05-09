<?php
$content = file_get_contents('d:/winco-ishad/winco-frondend/src/components/jsondata/indianpokergame.js');
$start = strpos($content, '[');
$end = strrpos($content, ']');
$json_content = substr($content, $start, $end - $start + 1);

// Log raw extracted length
echo "Raw length: " . strlen($json_content) . "\n";

// Remove comments
$json_content = preg_replace('!/\*.*?\*/!s', '', $json_content);
$json_content = preg_replace('!//.*?\n!', '', $json_content);

// Remove control characters except tab, newline, carriage return
$json_content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $json_content);

// Fix trailing commas
$json_content = preg_replace('/,\s*([\]\}])/', '$1', $json_content);

$data = json_decode($json_content, true);
if ($data === null) {
    echo "Error: " . json_last_error_msg() . "\n";
    // Check for weird characters
    $error_pos = json_last_error(); // Not the position unfortunately
    
    // Attempt to find where it fails by shrinking
    $len = strlen($json_content);
    for ($i = 100; $i < $len; $i += 100) {
        $test = substr($json_content, 0, $i);
        // Add closing bracket if needed to make it "closer" to valid
        $test_json = $test;
        if (substr(trim($test), -1) != '}') {
            $test_json .= '}]';
        } else {
            $test_json .= ']';
        }
        json_decode($test_json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "First failure around char $i: " . substr($json_content, $i-20, 40) . "\n";
            break;
        }
    }
} else {
    echo "Success! Found " . count($data) . " games.\n";
}
?>
