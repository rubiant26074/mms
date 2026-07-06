<?php
// config/Approval.php

class Approval {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Mencatat Approval Digital
     */
    public function signDocument($module, $docId, $userId, $role, $action, $notes = '') {
        try {
            $this->pdo->beginTransaction();

            // 1. Generate Digital Signature (Unik)
            // Kombinasi: Module + ID + User + Timestamp + Salt
            $salt = "MMS_SECRET_KEY_2026"; 
            $signatureRaw = $module . $docId . $userId . time() . $salt;
            $signatureHash = hash('sha256', $signatureRaw);

            // 2. Insert ke Log
            $stmt = $this->pdo->prepare("
                INSERT INTO approval_logs 
                (module, doc_id, approver_id, role_at_time, action, notes, digital_signature) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$module, $docId, $userId, $role, $action, $notes, $signatureHash]);

            // 3. Update Status Dokumen Utama (Logika Dinamis berdasarkan Module)
            $this->updateDocumentStatus($module, $docId, $action);

            $this->pdo->commit();
            return ['status' => true, 'message' => 'Dokumen berhasil ditandatangani secara digital.'];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Logika Perpindahan Status (State Machine Sederhana)
     */
    private function updateDocumentStatus($module, $docId, $action) {
        if ($action == 'REJECTED') {
            $table = $this->getTableName($module);
            $sql = "UPDATE $table SET status = 'Rejected', current_approval_level = 0 WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$docId]);
            return;
        }

        // Jika APPROVED, naikkan level atau Finalize
        if ($module == 'PR') {
            // Contoh Flow PR: Staff Input -> Manager Approve (Final)
            // Bisa dikembangkan: Staff -> Manager -> Direktur
            $table = 'purchase_requests';
            
            // Cek level sekarang
            $stmt = $this->pdo->prepare("SELECT current_approval_level FROM $table WHERE id = ?");
            $stmt->execute([$docId]);
            $currentLevel = $stmt->fetchColumn();

            if ($currentLevel == 1) {
                // Naik ke level Manager (misal) atau langsung Approved jika flow pendek
                $newStatus = 'Approved'; 
                $newLevel = 2; // Completed
            } else {
                $newStatus = 'Approved';
                $newLevel = $currentLevel; 
            }

            $sql = "UPDATE $table SET status = ?, current_approval_level = ?, last_approval_date = NOW() WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$newStatus, $newLevel, $docId]);
        }
        
        // Tambahkan logic module lain di sini nanti
    }

    private function getTableName($module) {
        $map = [
            'PR' => 'purchase_requests',
            'PO' => 'purchase_orders'
        ];
        return $map[$module] ?? null;
    }

    /**
     * Mengambil History Approval untuk Tampilan UI
     */
    public function getHistory($module, $docId) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.fullname 
            FROM approval_logs a
            JOIN users u ON a.approver_id = u.id
            WHERE a.module = ? AND a.doc_id = ?
            ORDER BY a.created_at ASC
        ");
        $stmt->execute([$module, $docId]);
        return $stmt->fetchAll();
    }
}
?>
