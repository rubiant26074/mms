<?php
// modules/hrd/payroll/form.php

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = $id ? true : false;

$data = [
    'payroll_code' => 'AUTO',
    'user_id' => '',
    'period_start' => date('Y-m-01'), // Awal bulan ini
    'period_end' => date('Y-m-t'),   // Akhir bulan ini
    'basic_salary' => 0,
    'allowance_total' => 0,
    'deduction_total' => 0,
    'net_salary' => 0,
    'total_attendance' => 0,
    'notes' => '',
    'status' => 'draft'
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM payrolls WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
}

// PROSES HITUNG ABSENSI (AJAX-like logic via reload or simple JS fetch preferred, but here we submit to calc)
// Untuk simplifikasi, kita hitung saat user diganti atau date diganti via JS. 
// Tapi di PHP native single file, kita buat input manual yang "terbantu" auto-fill.

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $p_start = $_POST['period_start'];
    $p_end = $_POST['period_end'];
    $basic = floatval(str_replace('.', '', $_POST['basic_salary']));
    $allow = floatval(str_replace('.', '', $_POST['allowance_total']));
    $deduct = floatval(str_replace('.', '', $_POST['deduction_total']));
    $attend = intval($_POST['total_attendance']);
    $notes = $_POST['notes'];
    
    $net = ($basic + $allow) - $deduct;

    try {
        $pdo->beginTransaction();

        if (!$is_edit) {
            $ym = date('ym');
            $stmt_no = $pdo->query("SELECT COUNT(*) FROM payrolls WHERE payroll_code LIKE 'PAY-$ym-%'");
            $count = $stmt_no->fetchColumn() + 1;
            $code = "PAY-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO payrolls (payroll_code, user_id, period_start, period_end, basic_salary, allowance_total, deduction_total, net_salary, total_attendance, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$code, $user_id, $p_start, $p_end, $basic, $allow, $deduct, $net, $attend, $notes, $_SESSION['user_id']]);
        } else {
            $sql = "UPDATE payrolls SET user_id=?, period_start=?, period_end=?, basic_salary=?, allowance_total=?, deduction_total=?, net_salary=?, total_attendance=?, notes=? WHERE id=?";
            $pdo->prepare($sql)->execute([$user_id, $p_start, $p_end, $basic, $allow, $deduct, $net, $attend, $notes, $id]);
        }

        $pdo->commit();
        echo "<script>alert('Data Gaji tersimpan!'); window.location='index.php?page=hrd-payroll';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Data Master Karyawan
$employees = $pdo->query("SELECT id, fullname, basic_salary FROM users WHERE role_id != 1 ORDER BY fullname ASC")->fetchAll();

render_header($is_edit ? "Edit Gaji" : "Buat Slip Gaji");
?>

<form method="POST">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Karyawan & Periode</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>No. Slip</label>
                        <input type="text" class="form-control fw-bold" value="<?= $data['payroll_code'] ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Pilih Karyawan <span class="text-danger">*</span></label>
                        <select name="user_id" id="empSelect" class="form-select" required onchange="getEmpInfo()">
                            <option value="">-- Pilih --</option>
                            <?php foreach($employees as $e): 
                                $selected = ($e['id'] == $data['user_id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $e['id'] ?>" data-basic="<?= $e['basic_salary'] ?>" <?= $selected ?>>
                                    <?= $e['fullname'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label>Dari Tanggal</label>
                            <input type="date" name="period_start" id="pStart" class="form-control" value="<?= $data['period_start'] ?>" onchange="calcAttendance()">
                        </div>
                        <div class="col-6 mb-3">
                            <label>Sampai Tanggal</label>
                            <input type="date" name="period_end" id="pEnd" class="form-control" value="<?= $data['period_end'] ?>" onchange="calcAttendance()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Jumlah Kehadiran (Hari)</label>
                        <div class="input-group">
                            <input type="number" name="total_attendance" id="totAttend" class="form-control" value="<?= $data['total_attendance'] ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="calcAttendance()">Hitung Ulang</button>
                        </div>
                        <small class="text-muted">Dihitung dari data Absensi (Hadir/Terlambat).</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-success text-white">Komponen Gaji</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Gaji Pokok (Basic)</label>
                        <input type="text" name="basic_salary" id="basicSal" class="form-control text-end fw-bold" value="<?= number_format($data['basic_salary'], 0, ',', '.') ?>" required onkeyup="formatRibuan(this); calcNet()">
                    </div>
                    <div class="mb-3">
                        <label>Total Tunjangan (+)</label>
                        <input type="text" name="allowance_total" id="allowance" class="form-control text-end text-success" value="<?= number_format($data['allowance_total'], 0, ',', '.') ?>" onkeyup="formatRibuan(this); calcNet()">
                    </div>
                    <div class="mb-3">
                        <label>Total Potongan (-)</label>
                        <input type="text" name="deduction_total" id="deduct" class="form-control text-end text-danger" value="<?= number_format($data['deduction_total'], 0, ',', '.') ?>" onkeyup="formatRibuan(this); calcNet()">
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="fw-bold">Gaji Bersih (Take Home Pay)</label>
                        <h3 class="text-end text-primary" id="netSalary">Rp <?= number_format($data['net_salary'], 0, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label>Catatan</label>
                <textarea name="notes" class="form-control" rows="2"><?= $data['notes'] ?></textarea>
            </div>

            <div class="d-flex justify-content-between">
                <a href="index.php?page=hrd-payroll" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Simpan Gaji</button>
            </div>
        </div>
    </div>
</form>

<script>
function formatRibuan(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(val);
}

function getEmpInfo() {
    const sel = document.getElementById('empSelect');
    const opt = sel.options[sel.selectedIndex];
    const basic = opt.getAttribute('data-basic') || 0;
    
    document.getElementById('basicSal').value = new Intl.NumberFormat('id-ID').format(basic);
    calcAttendance();
    calcNet();
}

function calcAttendance() {
    const uid = document.getElementById('empSelect').value;
    const start = document.getElementById('pStart').value;
    const end = document.getElementById('pEnd').value;

    if(uid && start && end) {
        document.getElementById('totAttend').placeholder = "Menghitung...";
        
        // Panggil Helper AJAX untuk hitung absensi
        fetch(`modules/hrd/payroll/get_attendance_count.php?uid=${uid}&start=${start}&end=${end}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('totAttend').value = data.count;
            });
    }
}

function calcNet() {
    let basic = parseFloat(document.getElementById('basicSal').value.replace(/\./g, '')) || 0;
    let allow = parseFloat(document.getElementById('allowance').value.replace(/\./g, '')) || 0;
    let deduct = parseFloat(document.getElementById('deduct').value.replace(/\./g, '')) || 0;
    
    let net = (basic + allow) - deduct;
    document.getElementById('netSalary').innerText = "Rp " + new Intl.NumberFormat('id-ID').format(net);
}
</script>

<?php render_footer(); ?>