<?php
// modules/ppic/spk/service.php

if (!function_exists('ppic_spk_has_column')) {
    function ppic_spk_has_column(PDO $pdo, string $table, string $column): bool {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $sql = "SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $column]);
        $cache[$key] = (bool)$stmt->fetchColumn();
        return $cache[$key];
    }
}

if (!function_exists('ppic_spk_material_select_expr')) {
    function ppic_spk_material_select_expr(PDO $pdo): string {
        if (ppic_spk_has_column($pdo, 'sales_order_items', 'material_manual')) {
            return "COALESCE(soi.material_manual, i.description, '')";
        }
        return "COALESCE(i.description, '')";
    }
}

if (!function_exists('ppic_spk_lock_name')) {
    function ppic_spk_lock_name(string $ym): string {
        return 'mms.spk.number.' . $ym;
    }
}

if (!function_exists('ppic_spk_acquire_lock')) {
    function ppic_spk_acquire_lock(PDO $pdo, string $lockName, int $timeoutSec = 10): void {
        $stmt = $pdo->prepare("SELECT GET_LOCK(?, ?)");
        $stmt->execute([$lockName, $timeoutSec]);
        $ok = (int)$stmt->fetchColumn();
        if ($ok !== 1) {
            throw new Exception("Sistem sedang sibuk membuat nomor SPK. Coba lagi.");
        }
    }
}

if (!function_exists('ppic_spk_release_lock')) {
    function ppic_spk_release_lock(PDO $pdo, string $lockName): void {
        try {
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?)");
            $stmt->execute([$lockName]);
        } catch (Throwable $e) {
            // ignore
        }
    }
}

if (!function_exists('ppic_spk_next_number')) {
    function ppic_spk_next_number(PDO $pdo, string $ym): string {
        $stmt = $pdo->prepare("SELECT spk_number FROM spk WHERE spk_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(["SPK-$ym-%"]);
        $last = (string)$stmt->fetchColumn();
        $seq = 1;
        if ($last !== '' && preg_match('/^SPK-\d{4}-(\d+)$/', $last, $m)) {
            $seq = ((int)$m[1]) + 1;
        }
        return "SPK-$ym-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('ppic_spk_ensure_pk_auto_increment')) {
    function ppic_spk_ensure_pk_auto_increment(PDO $pdo, string $table, string $column = 'id'): void {
        try {
            $stmt = $pdo->prepare(
                "SELECT COLUMN_TYPE, EXTRA, IS_NULLABLE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                 LIMIT 1"
            );
            $stmt->execute([$table, $column]);
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$col) {
                return;
            }
            $extra = strtolower((string)($col['EXTRA'] ?? ''));
            if (strpos($extra, 'auto_increment') !== false) {
                return;
            }

            $colType = (string)$col['COLUMN_TYPE'];
            $nullable = strtoupper((string)$col['IS_NULLABLE']) === 'YES';
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';

            $sql = "ALTER TABLE `$table` MODIFY COLUMN `$column` $colType $nullSql AUTO_INCREMENT";
            $pdo->exec($sql);

            $next = (int)$pdo->query("SELECT COALESCE(MAX(`$column`), 0) + 1 FROM `$table`")->fetchColumn();
            if ($next < 1) {
                $next = 1;
            }
            $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = " . $next);
        } catch (Throwable $e) {
            // ignore, best effort hardening for legacy DB
        }
    }
}

if (!function_exists('ppic_spk_ensure_schema')) {
    function ppic_spk_ensure_schema(PDO $pdo): void {
        ppic_spk_ensure_pk_auto_increment($pdo, 'spk', 'id');
        ppic_spk_ensure_pk_auto_increment($pdo, 'spk_materials', 'id');
        ppic_spk_ensure_pk_auto_increment($pdo, 'purchase_requests', 'id');
        ppic_spk_ensure_pk_auto_increment($pdo, 'purchase_request_items', 'id');
    }
}

if (!function_exists('ppic_spk_save')) {
    function ppic_spk_save(PDO $pdo, array $payload): array {
        ppic_spk_ensure_schema($pdo);

        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        $isEdit = $id > 0;
        $soId = isset($payload['sales_order_id']) ? (int)$payload['sales_order_id'] : 0;
        $spkDate = (string)($payload['spk_date'] ?? date('Y-m-d'));
        $deadlineDate = (string)($payload['deadline_date'] ?? date('Y-m-d'));
        $priority = trim((string)($payload['priority'] ?? 'normal'));
        $notes = (string)($payload['notes'] ?? '');
        $selectedProcesses = $payload['processes'] ?? [];
        $requiredProcesses = is_array($selectedProcesses) ? implode(',', $selectedProcesses) : '';
        $matIds = is_array($payload['mat_id'] ?? null) ? $payload['mat_id'] : [];
        $matQtys = is_array($payload['mat_qty'] ?? null) ? $payload['mat_qty'] : [];
        $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;

        if ($soId <= 0) {
            throw new Exception("Pilih Sales Order terlebih dahulu.");
        }
        if ($userId <= 0) {
            throw new Exception("Session user tidak valid. Silakan login ulang.");
        }

        $ownTx = !$pdo->inTransaction();
        $lockName = '';
        $spkNumber = '';
        $spkId = $id;

        try {
            if ($ownTx) {
                $pdo->beginTransaction();
            }

            if (!$isEdit) {
                $ym = date('ym', strtotime($spkDate ?: date('Y-m-d')));
                $lockName = ppic_spk_lock_name($ym);
                ppic_spk_acquire_lock($pdo, $lockName, 10);

                $spkNumber = ppic_spk_next_number($pdo, $ym);

                $sql = "INSERT INTO spk
                        (spk_number, sales_order_id, spk_date, deadline_date, priority, status, notes, required_processes, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, 'preliminary', ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$spkNumber, $soId, $spkDate, $deadlineDate, $priority, $notes, $requiredProcesses, $userId]);
                $spkId = (int)$pdo->lastInsertId();

                $pdo->prepare("UPDATE sales_orders SET status='in_production' WHERE id=?")->execute([$soId]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT spk_number FROM spk WHERE id = ? LIMIT 1"
                );
                $stmt->execute([$spkId]);
                $spkNumber = (string)$stmt->fetchColumn();
                if ($spkNumber === '') {
                    throw new Exception("Data SPK tidak ditemukan.");
                }

                $sql = "UPDATE spk
                        SET sales_order_id=?, spk_date=?, deadline_date=?, priority=?, notes=?, required_processes=?, updated_at=NOW()
                        WHERE id=?";
                $pdo->prepare($sql)->execute([$soId, $spkDate, $deadlineDate, $priority, $notes, $requiredProcesses, $spkId]);
                $pdo->prepare("DELETE FROM spk_materials WHERE spk_id=?")->execute([$spkId]);
            }

            if (!empty($matIds)) {
                $stmtMat = $pdo->prepare("INSERT INTO spk_materials (spk_id, item_id, qty_required) VALUES (?, ?, ?)");
                foreach ($matIds as $idx => $rawMatId) {
                    $matId = (int)$rawMatId;
                    $qty = isset($matQtys[$idx]) ? (float)$matQtys[$idx] : 0;
                    if ($matId > 0 && $qty > 0) {
                        $stmtMat->execute([$spkId, $matId, $qty]);
                    }
                }
            }

            if ($ownTx) {
                $pdo->commit();
            }

            if ($lockName !== '') {
                ppic_spk_release_lock($pdo, $lockName);
            }

            return [
                'spk_id' => $spkId,
                'spk_number' => $spkNumber,
                'is_edit' => $isEdit,
            ];
        } catch (Throwable $e) {
            if ($lockName !== '') {
                ppic_spk_release_lock($pdo, $lockName);
            }
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

