<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');
echo "=== GIT DIAGNOSTIC SCRIPT ===\n\n";

if (function_exists('shell_exec') && !in_array('shell_exec', explode(', ', ini_get('disable_functions')))) {
    echo "Current User: " . @shell_exec('whoami') . "\n";
    echo "Git Version: " . @shell_exec('git --version') . "\n";
} else {
    echo "shell_exec() is DISABLED on this server.\n";
}
echo "Current Path: " . getcwd() . "\n\n";

// Try to read .git/config
$configPath = __DIR__ . '/../.git/config';
$token = null;
if (file_exists($configPath)) {
    $content = file_get_contents($configPath);
    if (preg_match('/url\s*=\s*https:\/\/([^@]+)@github\.com/', $content, $matches)) {
        $token = $matches[1];
        echo "Found token in .git/config: Masked(" . substr($token, 0, 8) . "...)\n";
    } else {
        echo "Could not find token in .git/config URL.\n";
    }
} else {
    echo ".git/config file not found at " . $configPath . "\n";
}

echo "\n--- testing github.com connection via curl ---\n";
if (!function_exists('curl_init')) {
    die("CURL is not enabled on this server!");
}

$ch = curl_init("https://api.github.com/repos/rubiant26074/mms");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Git-Diagnostic');
if ($token) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $token
    ]);
}
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
if ($response === false) {
    echo "Curl Error: " . curl_error($ch) . " (Code: " . curl_errno($ch) . ")\n";
} else {
    $info = curl_getinfo($ch);
    echo "HTTP Status Code: " . $info['http_code'] . "\n";
    echo "Response Length: " . strlen($response) . " bytes\n";
}

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "\n--- Verbose Curl Log ---\n" . $verboseLog . "\n";
