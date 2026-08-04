<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');
echo "=== GIT DIAGNOSTIC SCRIPT 2 ===\n\n";

echo "Disable Functions: " . ini_get('disable_functions') . "\n\n";

function test_command($func, $cmd) {
    echo "Testing {$func}(): ";
    if (!function_exists($func)) {
        echo "NOT EXISTS\n";
        return false;
    }
    if (in_array($func, explode(', ', ini_get('disable_functions')))) {
        echo "DISABLED in ini\n";
        return false;
    }
    
    try {
        if ($func === 'exec') {
            $output = [];
            $code = -1;
            @exec($cmd, $output, $code);
            echo "SUCCESS, Code: {$code}, Output: " . implode(", ", $output) . "\n";
            return $code === 0;
        } elseif ($func === 'system') {
            ob_start();
            $code = -1;
            @system($cmd, $code);
            $out = ob_get_clean();
            echo "SUCCESS, Code: {$code}, Output: " . trim($out) . "\n";
            return $code === 0;
        } elseif ($func === 'passthru') {
            ob_start();
            $code = -1;
            @passthru($cmd, $code);
            $out = ob_get_clean();
            echo "SUCCESS, Code: {$code}, Output: " . trim($out) . "\n";
            return $code === 0;
        } elseif ($func === 'shell_exec') {
            $out = @shell_exec($cmd);
            echo "SUCCESS, Output: " . trim($out) . "\n";
            return true;
        }
    } catch (Exception $e) {
        echo "FAILED with exception: " . $e->getMessage() . "\n";
    }
    return false;
}

test_command('exec', 'git --version');
test_command('system', 'git --version');
test_command('passthru', 'git --version');
test_command('shell_exec', 'git --version');

echo "\n--- Attempting Git Pull via available execution function ---\n";
// Find which function works
$working_func = null;
foreach (['exec', 'system', 'passthru'] as $f) {
    if (function_exists($f) && !in_array($f, explode(', ', ini_get('disable_functions')))) {
        $working_func = $f;
        break;
    }
}

if ($working_func) {
    echo "Using {$working_func} to run 'git pull'...\n";
    chdir('..');
    echo "Current directory: " . getcwd() . "\n";
    // Force git to ignore SSL verification just in case
    test_command($working_func, 'git config http.sslVerify false');
    test_command($working_func, 'git pull origin main 2>&1');
} else {
    echo "No command execution functions are available. Cannot run git pull via PHP.\n";
}
