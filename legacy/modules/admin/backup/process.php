<?php
// modules/admin/backup/process.php
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function backup_is_ajax_request() {
    $xhr = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    if ($xhr === 'xmlhttprequest') return true;
    $fmt = strtolower(trim((string)($_REQUEST['format'] ?? '')));
    return ($fmt === 'json');
}

function backup_json_response($ok, array $payload = [], $http_code = 200) {
    http_response_code((int)$http_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => (bool)$ok], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function backup_redirect($ok_msg = '', $err_msg = '') {
    $url = '../../../index.php?page=admin-backup';
    if ($ok_msg !== '') {
        $url .= '&ok=' . urlencode($ok_msg);
    }
    if ($err_msg !== '') {
        $url .= '&err=' . urlencode($err_msg);
    }
    header('Location: ' . $url);
    exit;
}

function backup_process_assert_auth() {
    if (!is_logged_in() || !has_permission('admin_reset_db')) {
        if (backup_is_ajax_request()) {
            backup_json_response(false, ['message' => 'Akses ditolak.'], 403);
        }
        http_response_code(403); die('Akses ditolak.');
    }
}

function backup_process_assert_access() {
    backup_process_assert_auth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if (backup_is_ajax_request()) {
            backup_json_response(false, ['message' => 'Method tidak diizinkan.'], 405);
        }
        http_response_code(405);
        die('Method tidak diizinkan.');
    }
    $csrf = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        if (backup_is_ajax_request()) {
            backup_json_response(false, ['message' => 'Permintaan tidak valid (CSRF).'], 400);
        }
        http_response_code(400); die('Permintaan tidak valid (CSRF).');
    }
}

function backup_progress_token($raw) {
    $raw = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$raw);
    $len = strlen($raw);
    if ($len < 8 || $len > 64) return '';
    return $raw;
}

function backup_progress_file($token) {
    $token = backup_progress_token($token);
    if ($token === '') return '';
    if (function_exists('mms_abs_path')) {
        return mms_abs_path('database/backups/.restore_progress_' . $token . '.json');
    }
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mms_restore_progress_' . $token . '.json';
}

function backup_progress_write($token, array $data) {
    $file = backup_progress_file($token);
    if ($file === '') return;
    $existing = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }
    $nowTs = microtime(true);
    $base = [
        'percent' => 0,
        'stage' => 'init',
        'message' => 'Menyiapkan proses restore...',
        'done' => false,
        'error' => false,
        'started_at' => date('c'),
        'started_at_ts' => $nowTs,
        'updated_at' => date('c'),
        'updated_at_ts' => $nowTs,
    ];
    $payload = array_merge($base, $existing, $data);
    $payload['percent'] = max(0, min(100, (int)round((float)($payload['percent'] ?? 0))));
    $payload['updated_at'] = date('c');
    $payload['updated_at_ts'] = $nowTs;
    if (!isset($payload['started_at_ts']) || !is_numeric($payload['started_at_ts'])) {
        $payload['started_at_ts'] = $nowTs;
    }
    if (empty($payload['started_at'])) {
        $payload['started_at'] = date('c', (int)$payload['started_at_ts']);
    }
    @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function backup_progress_read($token) {
    $file = backup_progress_file($token);
    if ($file === '' || !is_file($file)) return null;
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function backup_restore_error_log_path() {
    $rand = '';
    try {
        $rand = substr(bin2hex(random_bytes(3)), 0, 6);
    } catch (Throwable $e) {
        $rand = substr(md5((string)microtime(true)), 0, 6);
    }
    $filename = 'restore_error_' . date('Ymd_His') . '_' . $rand . '.log';
    if (function_exists('mms_abs_path')) {
        return mms_abs_path('database/backups/' . $filename);
    }
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
}

function backup_write_restore_error_log(Throwable $e, array $context = []) {
    $path = backup_restore_error_log_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $lines = [];
    $lines[] = 'MMS Restore Error Log';
    $lines[] = 'Generated: ' . date('c');
    $lines[] = str_repeat('=', 72);
    foreach ($context as $k => $v) {
        if (is_array($v) || is_object($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $lines[] = $k . ': ' . (string)$v;
    }
    $lines[] = str_repeat('-', 72);
    $lines[] = 'Error: ' . $e->getMessage();
    $lines[] = 'File: ' . $e->getFile() . ':' . $e->getLine();
    $lines[] = 'Trace:';
    $lines[] = $e->getTraceAsString();
    $lines[] = '';

    $ok = @file_put_contents($path, implode(PHP_EOL, $lines), LOCK_EX);
    if ($ok === false) {
        return '';
    }
    if (function_exists('mms_rel_path')) {
        return mms_rel_path($path);
    }
    return $path;
}

function backup_sql_strip_bom($sql) {
    $sql = (string)$sql;
    if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
        $sql = substr($sql, 3);
    }
    return $sql;
}

function backup_read_uploaded_sql(array $file) {
    if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload file restore gagal.');
    }
    $name = (string)($file['name'] ?? '');
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        throw new RuntimeException('File upload tidak ditemukan di temporary folder.');
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $is_gz = ($ext === 'gz') || preg_match('/\.sql\.gz$/i', $name);
    if (!$is_gz && $ext !== 'sql') {
        throw new RuntimeException('Format file harus .sql atau .sql.gz');
    }

    if ($is_gz) {
        if (!function_exists('gzopen')) {
            throw new RuntimeException('Server tidak mendukung file .gz (gzopen tidak tersedia).');
        }
        $h = @gzopen($tmp, 'rb');
        if (!$h) {
            throw new RuntimeException('Gagal membaca file gzip.');
        }
        $sql = '';
        while (!gzeof($h)) {
            $chunk = gzread($h, 8192);
            if ($chunk === false) break;
            $sql .= $chunk;
            if (strlen($sql) > 100 * 1024 * 1024) {
                gzclose($h);
                throw new RuntimeException('File restore terlalu besar (>100MB setelah dibuka).');
            }
        }
        gzclose($h);
    } else {
        $sql = @file_get_contents($tmp);
        if ($sql === false) {
            throw new RuntimeException('Gagal membaca file SQL.');
        }
        if (strlen($sql) > 100 * 1024 * 1024) {
            throw new RuntimeException('File restore terlalu besar (>100MB).');
        }
    }

    $sql = backup_sql_strip_bom($sql);
    if (trim($sql) === '') {
        throw new RuntimeException('File SQL kosong.');
    }

    return $sql;
}

function backup_split_sql_statements($sql) {
    $sql = (string)$sql;
    $len = strlen($sql);
    $statements = [];
    $buf = '';
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
                $buf .= $ch;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($ch === '-' && $next === '-' && (($i + 2 >= $len) || ctype_space($sql[$i + 2]))) {
                $inLineComment = true;
                $i++;
                continue;
            }
            if ($ch === '#') {
                $inLineComment = true;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($ch === "'" && !$inDouble && !$inBacktick) {
            $escaped = ($i > 0 && $sql[$i - 1] === '\\');
            if (!$escaped) $inSingle = !$inSingle;
            $buf .= $ch;
            continue;
        }
        if ($ch === '"' && !$inSingle && !$inBacktick) {
            $escaped = ($i > 0 && $sql[$i - 1] === '\\');
            if (!$escaped) $inDouble = !$inDouble;
            $buf .= $ch;
            continue;
        }
        if ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            $buf .= $ch;
            continue;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $stmt = trim($buf);
            if ($stmt !== '') $statements[] = $stmt;
            $buf = '';
            continue;
        }

        $buf .= $ch;
    }

    $tail = trim($buf);
    if ($tail !== '') $statements[] = $tail;

    return $statements;
}

function backup_drop_all_tables(PDO $pdo) {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (!$tables) return 0;

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $count = 0;
    foreach ($tables as $table) {
        $table = (string)$table;
        if ($table === '') continue;
        $safe = str_replace('`', '``', $table);
        $pdo->exec("DROP TABLE IF EXISTS `{$safe}`");
        $count++;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    return $count;
}

function backup_restore_sql(PDO $pdo, $sql, $wipe_before_restore = true, $progress_cb = null) {
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    $sql = backup_sql_strip_bom($sql);
    if (is_callable($progress_cb)) {
        $progress_cb(20, 'Memisahkan statement SQL...');
    }
    $stmts = backup_split_sql_statements($sql);
    if (empty($stmts)) {
        throw new RuntimeException('Tidak ada statement SQL yang bisa diproses.');
    }
    $total_stmt = count($stmts);
    if (is_callable($progress_cb)) {
        $progress_cb(30, 'Statement SQL ditemukan: ' . $total_stmt, ['total_statements' => $total_stmt]);
    }

    $dropped_tables = 0;
    if ($wipe_before_restore) {
        if (is_callable($progress_cb)) {
            $progress_cb(35, 'Mengosongkan database (drop table) ...');
        }
        $dropped_tables = backup_drop_all_tables($pdo);
    }
    if (is_callable($progress_cb)) {
        $progress_cb(40, 'Mulai restore statement SQL ...', ['dropped_tables' => $dropped_tables]);
    }

    $executed = 0;
    $skipped = 0;
    $processed = 0;
    $last_progress_update = microtime(true);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    foreach ($stmts as $stmt) {
        $trim = trim($stmt);
        if ($trim === '') { $skipped++; $processed++; continue; }

        // Abaikan statement yang bisa mengacaukan restore ke DB aktif saat ini.
        if (preg_match('/^(USE|CREATE\s+DATABASE|DROP\s+DATABASE)\b/i', $trim)) {
            $skipped++;
            $processed++;
            continue;
        }
        if (preg_match('/^DELIMITER\b/i', $trim)) {
            $skipped++;
            $processed++;
            continue;
        }

        try {
            $pdo->exec($trim);
            $executed++;
            $processed++;
        } catch (Throwable $e) {
            $preview = preg_replace('/\s+/', ' ', substr($trim, 0, 180));
            throw new RuntimeException('Restore gagal pada statement ke-' . ($executed + 1) . ': ' . $e->getMessage() . ' | SQL: ' . $preview);
        }

        if (is_callable($progress_cb)) {
            $now = microtime(true);
            if ($processed === $total_stmt || ($processed % 25 === 0) || (($now - $last_progress_update) >= 0.5)) {
                $pct = 40 + (($processed / max(1, $total_stmt)) * 58); // 40..98
                $progress_cb($pct, 'Restore berjalan: ' . $processed . '/' . $total_stmt . ' statement', [
                    'processed' => $processed,
                    'executed' => $executed,
                    'skipped' => $skipped,
                    'total_statements' => $total_stmt,
                ]);
                $last_progress_update = $now;
            }
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    if (is_callable($progress_cb)) {
        $progress_cb(100, 'Restore selesai.', [
            'processed' => $processed,
            'executed' => $executed,
            'skipped' => $skipped,
            'total_statements' => $total_stmt,
            'dropped_tables' => $dropped_tables,
            'done' => true,
        ]);
    }
    return ['executed' => $executed, 'skipped' => $skipped, 'dropped_tables' => $dropped_tables, 'total_statements' => $total_stmt];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'progress')) {
    backup_process_assert_auth();
    $token = backup_progress_token($_GET['token'] ?? '');
    if ($token === '') {
        backup_json_response(false, ['message' => 'Token progress tidak valid.'], 400);
    }
    $progress = backup_progress_read($token);
    if ($progress === null) {
        backup_json_response(true, [
            'progress' => [
                'percent' => 0,
                'stage' => 'waiting',
                'message' => 'Menunggu proses restore dimulai...',
                'done' => false,
                'error' => false,
                'started_at_ts' => null,
                'updated_at_ts' => microtime(true),
            ]
        ]);
    }
    backup_json_response(true, ['progress' => $progress]);
}

backup_process_assert_access();

if (isset($_POST['backup'])) {
    // Konfigurasi Nama File
    $filename = 'backup_mms_' . date('Y-m-d_H-i-s') . '.sql';

    // Header untuk Download
    header('Content-Type: application/octet-stream');
    header('Content-Transfer-Encoding: Binary');
    header('Content-disposition: attachment; filename="' . $filename . '"');

    // Header SQL File
    echo "-- MMS Database Backup\n";
    echo "-- Date: " . date('d M Y H:i:s') . "\n";
    echo "-- User: " . ($_SESSION['fullname'] ?? 'Unknown') . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    try {
        $tables = [];
        $stmt = $pdo->query('SHOW TABLES');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        foreach ($tables as $table) {
            $safeTable = str_replace('`', '``', (string)$table);

            // Struktur Tabel (DROP + CREATE)
            echo "DROP TABLE IF EXISTS `{$safeTable}`;\n";
            $row2 = $pdo->query("SHOW CREATE TABLE `{$safeTable}`")->fetch(PDO::FETCH_NUM);
            echo ($row2[1] ?? '') . ";\n\n";

            // Isi Data (INSERT INTO)
            $stmt_data = $pdo->query("SELECT * FROM `{$safeTable}`");
            while ($row_data = $stmt_data->fetch(PDO::FETCH_ASSOC)) {
                $keys = array_map(static fn($k) => '`' . str_replace('`', '``', (string)$k) . '`', array_keys($row_data));
                $values = array_map(static function ($v) {
                    if ($v === null) return 'NULL';
                    return "'" . addslashes((string)$v) . "'";
                }, array_values($row_data));

                echo "INSERT INTO `{$safeTable}` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
            echo "\n";
        }

        echo "SET FOREIGN_KEY_CHECKS=1;\n";
    } catch (Throwable $e) {
        echo "-- Error saat backup: " . $e->getMessage() . "\n";
    }
    exit;
}

if (isset($_POST['restore'])) {
    $is_ajax = backup_is_ajax_request();
    $progress_token = backup_progress_token($_POST['restore_token'] ?? '');
    $uploaded_name = '';
    try {
        if (empty($_POST['confirm_restore'])) {
            throw new RuntimeException('Konfirmasi restore wajib dicentang.');
        }
        if (trim((string)($_POST['confirm_text'] ?? '')) !== 'RESTORE') {
            throw new RuntimeException('Konfirmasi teks restore harus \"RESTORE\".');
        }
        if (!isset($_FILES['restore_file']) || !is_array($_FILES['restore_file'])) {
            throw new RuntimeException('File restore belum dipilih.');
        }

        $wipe = !empty($_POST['wipe_before_restore']);
        $uploaded_name = (string)($_FILES['restore_file']['name'] ?? 'backup.sql');
        if ($progress_token !== '') {
            backup_progress_write($progress_token, [
                'percent' => 5,
                'stage' => 'upload_received',
                'message' => 'File restore diterima server. Membaca file...',
                'done' => false,
                'error' => false,
                'file_name' => $uploaded_name,
            ]);
        }

        // Lepas lock session agar polling progress dari browser lain/request lain tidak terblokir.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $sql = backup_read_uploaded_sql($_FILES['restore_file']);
        if ($progress_token !== '') {
            backup_progress_write($progress_token, [
                'percent' => 15,
                'stage' => 'file_loaded',
                'message' => 'File SQL berhasil dibaca. Menyiapkan restore...',
                'done' => false,
                'error' => false,
                'file_name' => $uploaded_name,
            ]);
        }
        $res = backup_restore_sql($pdo, $sql, $wipe, function ($percent, $message, $extra = []) use ($progress_token, $uploaded_name) {
            if ($progress_token === '') return;
            backup_progress_write($progress_token, array_merge([
                'percent' => (int)$percent,
                'stage' => 'restoring',
                'message' => (string)$message,
                'done' => false,
                'error' => false,
                'file_name' => $uploaded_name,
            ], is_array($extra) ? $extra : []));
        });

        if ($progress_token !== '') {
            backup_progress_write($progress_token, [
                'percent' => 100,
                'stage' => 'completed',
                'message' => 'Restore selesai.',
                'done' => true,
                'error' => false,
                'file_name' => $uploaded_name,
                'result' => $res,
            ]);
        }

        $okMsg = 'Restore berhasil dari file ' . $uploaded_name . ' | Statement dieksekusi: ' . (int)$res['executed'] . ' | Dilewati: ' . (int)$res['skipped'] . ($wipe ? ' | Tabel dihapus dulu: ' . (int)$res['dropped_tables'] : '');
        if ($is_ajax) {
            backup_json_response(true, ['message' => $okMsg, 'result' => $res]);
        }
        backup_redirect($okMsg, '');
    } catch (Throwable $e) {
        $logPath = backup_write_restore_error_log($e, [
            'user' => (string)($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'unknown'),
            'restore_file' => $uploaded_name !== '' ? $uploaded_name : (string)($_FILES['restore_file']['name'] ?? ''),
            'progress_token' => $progress_token,
            'request_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'progress_last' => ($progress_token !== '' ? backup_progress_read($progress_token) : null),
        ]);
        if ($progress_token !== '') {
            backup_progress_write($progress_token, [
                'percent' => 100,
                'stage' => 'failed',
                'message' => $e->getMessage(),
                'done' => true,
                'error' => true,
                'error_log' => $logPath,
            ]);
        }
        $errMsg = $e->getMessage();
        if ($logPath !== '') {
            $errMsg .= ' | Log: ' . $logPath;
        }
        if ($is_ajax) {
            backup_json_response(false, ['message' => $errMsg, 'error_log' => $logPath], 500);
        }
        backup_redirect('', $errMsg);
    }
}

backup_redirect('', 'Aksi backup/restore tidak dikenali.');
