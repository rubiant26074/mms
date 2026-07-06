<?php
$id = isset($_GET['id']) ? $_GET['id'] : null;
if ($id) {
    $stmt = $pdo->prepare("DELETE FROM payrolls WHERE id = ? AND status = 'draft'");
    $stmt->execute([$id]);
    echo "<script>window.location='index.php?page=hrd-payroll';</script>";
}
?>