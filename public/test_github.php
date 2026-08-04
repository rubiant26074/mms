<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== GIT DIAGNOSTIC SCRIPT ===\n\n";

echo "Current User: " . shell_exec('whoami') . "\n";
echo "Git Version: " . shell_exec('git --version') . "\n";
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

$response = curl_exec($ch);
if ($response === false) {
    echo "Curl Error: " . curl_error($ch) . "\n";
} else {
    $info = curl_getinfo($ch);
    echo "HTTP Status Code: " . $info['http_code'] . "\n";
    echo "Response Length: " . strlen($response) . " bytes\n";
}

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "\n--- Verbose Curl Log ---\n" . $verboseLog . "\n";

echo "\n--- running git status & git fetch manually ---\n";
chdir('..');
echo "Git Path: " . getcwd() . "\n";
$output_status = shell_exec('git status 2>&1');
echo "Git Status:\n" . $output_status . "\n";

$output_fetch = shell_exec('git fetch origin 2>&1');
echo "Git Fetch Output:\n" . $output_fetch . "\n";

$output_remote = shell_exec('git remote -v 2>&1');
echo "Git Remotes:\n" . $output_remote . "\n";
