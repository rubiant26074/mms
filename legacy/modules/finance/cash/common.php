<?php
if (!function_exists('fin_cash_ensure_schema')) {
    function fin_cash_ensure_schema(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS finance_cash_expenses (
            id INT NOT NULL AUTO_INCREMENT,
            expense_number VARCHAR(30) NOT NULL,
            transaction_type ENUM('expense','income') NOT NULL DEFAULT 'expense',
            coa_id INT NULL,
            cash_coa_id INT NULL,
            expense_date DATE NOT NULL,
            category VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            payment_method ENUM('Cash','Transfer Bank','E-Wallet','Lainnya') NOT NULL DEFAULT 'Cash',
            reference_no VARCHAR(80) NULL,
            vendor_name VARCHAR(120) NULL,
            status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
            notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_expense_number (expense_number),
            KEY idx_expense_date (expense_date),
            KEY idx_expense_status (status),
            KEY idx_category (category),
            KEY idx_transaction_type (transaction_type),
            KEY idx_coa_id (coa_id),
            KEY idx_cash_coa_id (cash_coa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Backward compatibility untuk database lama.
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM finance_cash_expenses LIKE 'transaction_type'");
            $has = $stmt && $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$has) {
                $pdo->exec("ALTER TABLE finance_cash_expenses
                            ADD COLUMN transaction_type ENUM('expense','income') NOT NULL DEFAULT 'expense' AFTER expense_number");
            }
        } catch (Exception $e) {
            // ignore
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM finance_cash_expenses LIKE 'coa_id'");
            $has = $stmt && $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$has) {
                $pdo->exec("ALTER TABLE finance_cash_expenses
                            ADD COLUMN coa_id INT NULL AFTER transaction_type");
            }
        } catch (Exception $e) {
            // ignore
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM finance_cash_expenses LIKE 'cash_coa_id'");
            $has = $stmt && $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$has) {
                $pdo->exec("ALTER TABLE finance_cash_expenses
                            ADD COLUMN cash_coa_id INT NULL AFTER coa_id");
            }
        } catch (Exception $e) {
            // ignore
        }

        try {
            $stmt = $pdo->query("SHOW INDEX FROM finance_cash_expenses WHERE Key_name = 'idx_transaction_type'");
            $has = $stmt && $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$has) {
                $pdo->exec("ALTER TABLE finance_cash_expenses ADD INDEX idx_transaction_type (transaction_type)");
            }
        } catch (Exception $e) {
            // ignore
        }

        try {
            $stmt = $pdo->query("SHOW INDEX FROM finance_cash_expenses WHERE Key_name = 'idx_coa_id'");
            $has = $stmt && $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$has) {
                $pdo->exec("ALTER TABLE finance_cash_expenses ADD INDEX idx_coa_id (coa_id)");
            }
        } catch (Exception $e) {
            // ignore
        }

        try {
            $stmt = $pdo->query("SHOW INDEX FROM finance_cash_expenses WHERE Key_name = 'idx_cash_coa_id'");
            $has = $stmt && $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$has) {
                $pdo->exec("ALTER TABLE finance_cash_expenses ADD INDEX idx_cash_coa_id (cash_coa_id)");
            }
        } catch (Exception $e) {
            // ignore
        }
    }
}

if (!function_exists('fin_cash_generate_number')) {
    function fin_cash_generate_number(PDO $pdo, string $expense_date, string $transaction_type = 'expense'): string {
        $ym = date('ym', strtotime($expense_date ?: date('Y-m-d')));
        $transaction_type = strtolower(trim((string)$transaction_type));
        $prefix = ($transaction_type === 'income') ? 'CASHIN' : 'CASHOUT';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM finance_cash_expenses WHERE expense_number LIKE ?");
        $stmt->execute(["$prefix-$ym-%"]);
        $seq = (int)$stmt->fetchColumn() + 1;
        return $prefix . "-$ym-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('fin_cash_expense_coa_id')) {
    function fin_cash_expense_coa_id(PDO $pdo): ?int {
        try {
            $stmt = $pdo->query("SELECT id FROM coa WHERE account_type = 'expense' AND (is_active = 1 OR is_active IS NULL) ORDER BY account_code ASC LIMIT 1");
            $id = $stmt ? $stmt->fetchColumn() : null;
            return $id ? (int)$id : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('fin_cash_revenue_coa_id')) {
    function fin_cash_revenue_coa_id(PDO $pdo): ?int {
        try {
            $stmt = $pdo->query("SELECT id FROM coa WHERE account_type = 'revenue' AND (is_active = 1 OR is_active IS NULL) ORDER BY account_code ASC LIMIT 1");
            $id = $stmt ? $stmt->fetchColumn() : null;
            return $id ? (int)$id : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('fin_cash_default_cash_coa_id')) {
    function fin_cash_default_cash_coa_id(PDO $pdo, string $payment_method = 'Cash'): ?int {
        $account_code = ((string)$payment_method === 'Cash') ? '1-1001' : '1-1002';
        $id = function_exists('get_coa_id') ? get_coa_id($account_code) : null;
        if ($id) return (int)$id;

        try {
            $stmt = $pdo->query("SELECT id FROM coa WHERE account_type = 'asset' AND (is_active = 1 OR is_active IS NULL) ORDER BY account_code ASC LIMIT 1");
            $fallback = $stmt ? $stmt->fetchColumn() : null;
            return $fallback ? (int)$fallback : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('fin_cash_is_coa_type_valid')) {
    function fin_cash_is_coa_type_valid(PDO $pdo, int $coa_id, array $allowed_types): bool {
        if ($coa_id <= 0 || empty($allowed_types)) return false;
        $allowed_types = array_values(array_filter(array_map('strval', $allowed_types)));
        if (empty($allowed_types)) return false;
        $placeholders = implode(',', array_fill(0, count($allowed_types), '?'));
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM coa WHERE id = ? AND account_type IN ($placeholders) AND (is_active = 1 OR is_active IS NULL)");
            $stmt->execute(array_merge([$coa_id], $allowed_types));
            return ((int)$stmt->fetchColumn() > 0);
        } catch (Exception $e) {
            return false;
        }
    }
}
