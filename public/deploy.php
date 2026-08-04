<?php
// Simple deploy script for PT. Promindo Graha Cemerlang Utama
// URL: https://mms.promindolaser.com/deploy.php?key=promindo_secure_deploy_2026

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

header('Content-Type: text/plain; charset=utf-8');

$secret_key = 'promindo_secure_deploy_2026';
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die("Access Denied: Invalid Key");
}

echo "=== STARTING DEPLOYMENT ===\n\n";

$repo = 'rubiant26074/mms';
$branch = 'main';
$target_dir = realpath(__DIR__ . '/../');

// Read token dynamically from .git/config to prevent exposing it in source code
$configPath = $target_dir . '/.git/config';
$token = null;
if (file_exists($configPath)) {
    $content = file_get_contents($configPath);
    if (preg_match('/url\s*=\s*https:\/\/([^@]+)@github\.com/', $content, $matches)) {
        $token = $matches[1];
        echo "Authentication: Found token in .git/config (Masked: " . substr($token, 0, 8) . "...)\n";
    }
}

if (!$token) {
    die("Error: Could not find valid GitHub Token in .git/config URL. Please set URL using token.");
}

echo "Target Directory: {$target_dir}\n";
echo "GitHub Repo: {$repo} ({$branch})\n\n";

if (!class_exists('ZipArchive')) {
    die("Error: ZipArchive extension is not enabled on this PHP server. Contact hosting support.");
}

// 1. Download zip file from GitHub
$zip_url = "https://api.github.com/repos/{$repo}/zipball/{$branch}";
$temp_zip = sys_get_temp_dir() . '/deploy_' . uniqid() . '.zip';

echo "Downloading zipball from GitHub...\n";
$ch = curl_init($zip_url);
$fp = fopen($temp_zip, 'w+');

curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Deploy-Script');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: token {$token}"
]);

$success = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fp);

if (!$success || $http_code !== 200) {
    @unlink($temp_zip);
    die("Error downloading repository zipball (HTTP Code: {$http_code})");
}

echo "Download completed successfully. Saved to: {$temp_zip} (" . filesize($temp_zip) . " bytes)\n";

// 2. Extract zip file
echo "Extracting zipball...\n";
$zip = new ZipArchive();
$temp_extract_dir = sys_get_temp_dir() . '/extract_' . uniqid();
mkdir($temp_extract_dir, 0755, true);

if ($zip->open($temp_zip) === true) {
    $zip->extractTo($temp_extract_dir);
    $zip->close();
    echo "Extraction completed.\n";
} else {
    @unlink($temp_zip);
    die("Error opening zip file.");
}

@unlink($temp_zip);

// 3. Find the inner directory created by GitHub (format: username-repo-commit)
$subdirs = glob($temp_extract_dir . '/*', GLOB_ONLYDIR);
if (empty($subdirs)) {
    die("Error: No extracted files found.");
}
$source_dir = $subdirs[0];
echo "Source directory: {$source_dir}\n";

// 4. Recursively copy files to target directory
function copy_recursive($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                if ($file === '.git') continue;
                copy_recursive($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

echo "Deploying files to production directory...\n";
copy_recursive($source_dir, $target_dir);
echo "Files deployed.\n";

// 5. Clean up temporary extract directory
function delete_recursive($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!delete_recursive($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

echo "Cleaning up temporary files...\n";
delete_recursive($temp_extract_dir);
echo "Cleanup completed.\n";

echo "\n=== DEPLOYMENT SUCCESSFUL! ===\n";
