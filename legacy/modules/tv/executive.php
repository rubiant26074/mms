<?php
// modules/tv/executive.php
$comp = function_exists('get_company_profile') ? get_company_profile() : [];
$company_name = (string)($comp['company_name'] ?? 'MMS EXECUTIVE TV');
$logo_path = (string)($comp['logo_path'] ?? '');
$logo_abs = ($logo_path !== '' && function_exists('mms_abs_path')) ? mms_abs_path($logo_path) : $logo_path;
$logo_url = ($logo_path !== '' && function_exists('mms_asset_url')) ? mms_asset_url($logo_path, true) : $logo_path;
$running_text = trim((string)($comp['running_text'] ?? ''));
if ($running_text === '') $running_text = 'EXECUTIVE TV DASHBOARD MMS - MONITOR OVERALL BISNIS - SALES, PURCHASING, PRODUKSI, WAREHOUSE, FINANCE';
if (function_exists('mms_ensure_sales_orders_fulfillment_source_column')) mms_ensure_sales_orders_fulfillment_source_column($pdo);
$logo_html = '<i class="bi bi-bar-chart-line-fill" style="font-size:2.6rem;color:#67e8f9"></i>';
if (!empty($logo_abs) && is_file($logo_abs) && !empty($logo_url)) {
    $logo_html = '<img src="'.htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8').'" alt="Logo" style="height:66px;width:auto;background:rgba(255,255,255,.95);padding:6px 8px;border-radius:10px">';
}
if (!function_exists('tvxS')) {
    function tvxS(PDO $pdo, $sql, $p = [], $d = 0) { try { $st = $pdo->prepare($sql); $st->execute($p); $v = $st->fetchColumn(); return ($v === false || $v === null) ? $d : $v; } catch (Throwable $e) { return $d; } }
}
if (!function_exists('tvxR')) {
    function tvxR(PDO $pdo, $sql, $p = []) { try { $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) { return []; } }
}
if (!function_exists('tvxRp')) {
    function tvxRp($n) { $n = (float)$n; $a = abs($n); if ($a >= 1000000000) return 'Rp '.number_format($n/1000000000,1,',','.').' M'; if ($a >= 1000000) return 'Rp '.number_format($n/1000000,1,',','.').' Jt'; return 'Rp '.number_format($n,0,',','.'); }
}
if (!function_exists('tvxNo')) {
    function tvxNo($n) { $n = (float)$n; $a = abs($n); if ($a >= 1000000) return number_format($n/1000000,1,',','.').'M'; if ($a >= 1000) return number_format($n/1000,1,',','.').'K'; return number_format($n,0,',','.'); }
}
$today = date('Y-m-d'); $m1 = date('Y-m-01'); $m2 = date('Y-m-01', strtotime('+1 month'));
$k = [];
$k['so_booking'] = (float)tvxS($pdo, "SELECT SUM(grand_total) FROM sales_orders WHERE status NOT IN ('cancelled','rejected') AND so_date>=? AND so_date<?", [$m1,$m2], 0);
$k['so_active'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM sales_orders WHERE status IN ('confirmed','in_production','delivered')", [], 0);
$k['so_fg'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM sales_orders WHERE COALESCE(fulfillment_source,'spk')='fg_stock' AND status NOT IN ('completed','cancelled','rejected')", [], 0);
$k['inv_month'] = (float)tvxS($pdo, "SELECT SUM(grand_total) FROM invoices WHERE status!='cancelled' AND invoice_date>=? AND invoice_date<?", [$m1,$m2], 0);
$k['col_month'] = (float)tvxS($pdo, "SELECT SUM(amount) FROM invoice_payments WHERE payment_date>=? AND payment_date<?", [$m1,$m2], 0);
$k['ar_out'] = (float)tvxS($pdo, "SELECT SUM(GREATEST(grand_total-paid_amount,0)) FROM invoices WHERE status IN ('unpaid','partial')", [], 0);
$k['ap_out'] = (float)tvxS($pdo, "SELECT SUM(GREATEST(grand_total-paid_amount,0)) FROM supplier_bills WHERE status IN ('unpaid','partial')", [], 0);
$k['spk_active'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM spk WHERE status IN ('released','in_production')", [], 0);
$k['spk_wait'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM spk WHERE status IN ('waiting_eng','preliminary','waiting_mgr','final')", [], 0);
$k['prod_run'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM production_assignments WHERE status='in_progress'", [], 0);
$k['prod_out_today'] = (float)tvxS($pdo, "SELECT SUM(qty_good) FROM production_assignments WHERE status='completed' AND DATE(COALESCE(end_time, created_at))=CURDATE()", [], 0);
$k['qc_ng7'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM qc_production WHERE status='ng' AND qc_date>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)", [], 0);
$k['pr_open'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM purchase_requests WHERE status IN ('submitted','approved','partial')", [], 0);
$k['po_open'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM purchase_orders WHERE status IN ('submitted','approved_pm','approved_finance','sent')", [], 0);
$k['po_month'] = (float)tvxS($pdo, "SELECT SUM(grand_total) FROM purchase_orders WHERE status!='cancelled' AND po_date>=? AND po_date<?", [$m1,$m2], 0);
$k['dn_today'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM delivery_notes WHERE dn_date=CURDATE()", [], 0);
$k['low_stock'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM items WHERE COALESCE(min_stock,0)>0 AND COALESCE(current_stock,0)>0 AND current_stock<=min_stock", [], 0);
$k['neg_stock'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM items WHERE COALESCE(current_stock,0)<0", [], 0);
$k['near_exp'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM warehouse_batches WHERE COALESCE(is_active,1)=1 AND COALESCE(qty_available,0)>0 AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)", [], 0);
$k['cash_in'] = (float)tvxS($pdo, "SELECT SUM(amount) FROM finance_cash_expenses WHERE transaction_type='income' AND status='posted' AND expense_date>=? AND expense_date<?", [$m1,$m2], 0);
$k['cash_out'] = (float)tvxS($pdo, "SELECT SUM(amount) FROM finance_cash_expenses WHERE transaction_type='expense' AND status='posted' AND expense_date>=? AND expense_date<?", [$m1,$m2], 0);
$k['ar_overdue'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid','partial') AND due_date<CURDATE()", [], 0);
$k['so_overdue'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM sales_orders WHERE status NOT IN ('completed','cancelled','rejected') AND delivery_date IS NOT NULL AND delivery_date<CURDATE()", [], 0);
$k['po_overdue'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM purchase_orders WHERE status IN ('sent','approved_finance','approved_pm') AND delivery_date<CURDATE()", [], 0);
$k['machine_down'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM machines WHERE status='broken'", [], 0);
$k['machine_maint'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM machines WHERE status='maintenance'", [], 0);
$k['machine_run'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM machines WHERE status='active'", [], 0);
$k['machine_total'] = (int)tvxS($pdo, "SELECT COUNT(*) FROM machines", [], 0);
$pulse = [
    'SO'=>(int)tvxS($pdo,"SELECT COUNT(*) FROM sales_orders WHERE so_date=CURDATE()"),
    'QT'=>(int)tvxS($pdo,"SELECT COUNT(*) FROM quotations WHERE quote_date=CURDATE()"),
    'INV'=>(int)tvxS($pdo,"SELECT COUNT(*) FROM invoices WHERE invoice_date=CURDATE()"),
    'AR RCPT'=>(int)tvxS($pdo,"SELECT COUNT(*) FROM invoice_payments WHERE payment_date=CURDATE()"),
    'PR'=>(int)tvxS($pdo,"SELECT COUNT(*) FROM purchase_requests WHERE pr_date=CURDATE()"),
    'PO'=>(int)tvxS($pdo,"SELECT COUNT(*) FROM purchase_orders WHERE po_date=CURDATE()"),
    'SJ'=>(int)$k['dn_today']
];
$keys = []; $labels = []; for ($i=5; $i>=0; $i--) { $t = strtotime("-$i month"); $keys[] = date('Y-m',$t); $labels[] = date('M y',$t); }
$serSO = array_fill_keys($keys, 0.0); $serINV = array_fill_keys($keys, 0.0); $serCOL = array_fill_keys($keys, 0.0);
foreach (tvxR($pdo, "SELECT DATE_FORMAT(so_date,'%Y-%m') ym, SUM(grand_total) t FROM sales_orders WHERE status NOT IN ('cancelled','rejected') AND so_date>=DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(so_date,'%Y-%m')") as $r) if (isset($serSO[$r['ym']])) $serSO[$r['ym']] = (float)$r['t'];
foreach (tvxR($pdo, "SELECT DATE_FORMAT(invoice_date,'%Y-%m') ym, SUM(grand_total) t FROM invoices WHERE status!='cancelled' AND invoice_date>=DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(invoice_date,'%Y-%m')") as $r) if (isset($serINV[$r['ym']])) $serINV[$r['ym']] = (float)$r['t'];
foreach (tvxR($pdo, "SELECT DATE_FORMAT(payment_date,'%Y-%m') ym, SUM(amount) t FROM invoice_payments WHERE payment_date>=DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(payment_date,'%Y-%m')") as $r) if (isset($serCOL[$r['ym']])) $serCOL[$r['ym']] = (float)$r['t'];
$soDist = tvxR($pdo, "SELECT status, COUNT(*) cnt FROM sales_orders GROUP BY status");
$spkDist = tvxR($pdo, "SELECT status, COUNT(*) cnt FROM spk GROUP BY status");
$recentSO = tvxR($pdo, "SELECT so_number, so_date, status, COALESCE(fulfillment_source,'spk') fulfillment_source, grand_total FROM sales_orders ORDER BY id DESC LIMIT 8");
$arDue = tvxR($pdo, "SELECT invoice_number, due_date, (grand_total-paid_amount) os FROM invoices WHERE status IN ('unpaid','partial') ORDER BY due_date ASC LIMIT 8");
$opsAlerts = [];
$opsAlerts[] = ['SO Overdue Delivery', (int)$k['so_overdue'], 'danger'];
$opsAlerts[] = ['AR Overdue', (int)$k['ar_overdue'], 'danger'];
$opsAlerts[] = ['PO Overdue', (int)$k['po_overdue'], 'warn'];
$opsAlerts[] = ['SPK Waiting Queue', (int)$k['spk_wait'], 'warn'];
$opsAlerts[] = ['QC NG (7 Hari)', (int)$k['qc_ng7'], 'danger'];
$opsAlerts[] = ['Low Stock', (int)$k['low_stock'], 'warn'];
$opsAlerts[] = ['Stock Minus', (int)$k['neg_stock'], 'danger'];
$opsAlerts[] = ['Near Expiry 30 Hari', (int)$k['near_exp'], 'warn'];
$j = static fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>TV Executive - MMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"><script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{--bg1:#071426;--bg2:#0c1f3b;--panel:rgba(14,27,49,.78);--line:rgba(148,163,184,.14);--ink:#ecf4ff;--mut:#a5b8cd;--c1:#38bdf8;--c2:#22c55e;--c3:#f59e0b;--c4:#a855f7;--danger:#ef4444}
*{box-sizing:border-box}body{margin:0;padding:12px 12px 68px;color:var(--ink);font-family:'Segoe UI',Tahoma,sans-serif;overflow:hidden;background:radial-gradient(circle at 10% -5%,rgba(56,189,248,.18),transparent 38%),radial-gradient(circle at 100% 0,rgba(34,197,94,.12),transparent 34%),linear-gradient(180deg,var(--bg1),var(--bg2))}
.cardx{background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:0 14px 28px rgba(2,8,23,.28);backdrop-filter:blur(8px)}
.top{display:grid;grid-template-columns:1.25fr .95fr;gap:10px;margin-bottom:10px}.headA,.headB{padding:12px 14px}.headA{display:flex;align-items:center;gap:12px;min-height:88px}.ttl{font-weight:800;font-size:clamp(1.2rem,1.5vw,1.8rem);line-height:1.06;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0}.sub{color:var(--mut);font-size:.9rem}.live{display:inline-block;width:9px;height:9px;background:#ef4444;border-radius:50%;animation:b 1s infinite;margin:0 7px}@keyframes b{50%{opacity:.25}}
.headB{display:grid;grid-template-columns:1fr 1fr;gap:8px}.clock{padding:8px 10px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.03)}.tm{font-size:1.8rem;font-weight:800;line-height:1}.dt{color:var(--mut);font-size:.85rem;margin-top:4px}.chip{padding:8px 10px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.02)}.chip .l{font-size:.68rem;color:var(--mut);text-transform:uppercase;letter-spacing:.08em}.chip .v{font-size:1rem;font-weight:700}
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:10px}.k{padding:11px 12px;min-height:90px;position:relative;overflow:hidden}.k:after{content:'';position:absolute;right:-24px;top:-35px;width:110px;height:110px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.14),transparent 70%)}.kl{font-size:.72rem;color:#c7d8ea;text-transform:uppercase;letter-spacing:.08em}.kv{font-size:1.45rem;font-weight:800;line-height:1.1}.km{font-size:.8rem;color:#d6e5f6;margin-top:6px}.t1{background:linear-gradient(135deg,rgba(59,130,246,.2),rgba(56,189,248,.14))}.t2{background:linear-gradient(135deg,rgba(20,184,166,.18),rgba(34,197,94,.14))}.t3{background:linear-gradient(135deg,rgba(168,85,247,.16),rgba(99,102,241,.14))}.t4{background:linear-gradient(135deg,rgba(245,158,11,.15),rgba(249,115,22,.14))}
.main{display:grid;grid-template-columns:1.45fr .95fr;gap:10px;height:calc(100vh - 233px)}.colL,.colR{display:grid;gap:10px;min-height:0}.colL{grid-template-rows:1.05fr .95fr}.colR{grid-template-rows:.8fr 1fr}.box{padding:12px 13px;display:flex;flex-direction:column;min-height:0}.hd{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px}.hd h4{margin:0;font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#dbeafe}.hd small{color:var(--mut)}.cv{position:relative;flex:1;min-height:140px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;min-height:0}.mini{padding:9px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.02);display:flex;flex-direction:column}.mini h5{margin:0 0 7px 0;font-size:.76rem;color:#dbeafe;text-transform:uppercase;letter-spacing:.08em}.mini .cv{min-height:110px}
.tbl{width:100%;border-collapse:collapse;font-size:.8rem}.tbl th,.tbl td{padding:5px 4px;border-bottom:1px solid rgba(148,163,184,.12)}.tbl th{font-size:.66rem;text-transform:uppercase;letter-spacing:.08em;color:#b9cae0}.tbl td{color:#edf5ff}.r{text-align:right}.nw{white-space:nowrap}.pill{display:inline-block;padding:.15rem .45rem;border-radius:999px;font-size:.66rem;border:1px solid rgba(148,163,184,.22);background:rgba(255,255,255,.04)}.fg{color:#fcd34d;border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.12)}.spk{color:#bfdbfe;border-color:rgba(59,130,246,.3);background:rgba(59,130,246,.12)}
.alerts{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:8px}.al{padding:8px 9px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.025)}.al .n{font-size:1.05rem;font-weight:800;line-height:1}.al .t{font-size:.7rem;color:#d6e3f2;margin-top:5px;line-height:1.15}.al.warn .n{color:var(--c3)}.al.danger .n{color:var(--danger)}.al.ok .n{color:var(--c2)}
.ft{position:fixed;left:0;right:0;bottom:0;background:rgba(0,0,0,.9);color:#facc15;border-top:2px solid #facc15;padding:5px 0;z-index:999}.ft marquee{font-family:'Courier New',monospace;font-weight:700;font-size:.96rem}
@media (max-width:1400px){body{overflow:auto}.top,.main{grid-template-columns:1fr;height:auto}.headB{grid-template-columns:1fr 1fr}.kpis{grid-template-columns:repeat(2,1fr)}.colL,.colR{grid-template-rows:auto}.alerts{grid-template-columns:repeat(2,1fr)}}
</style></head><body>
<div class="top"><div class="cardx headA"><div><?=$logo_html?></div><div style="min-width:0;flex:1"><h1 class="ttl"><?=htmlspecialchars($company_name,ENT_QUOTES,'UTF-8')?></h1><div class="sub">TV Executive Dashboard - Overall Business <span class="live"></span> Realtime Snapshot</div></div></div><div class="cardx headB"><div class="clock"><div id="clk" class="tm">--:--:--</div><div id="dte" class="dt">-</div><div class="sub" style="font-size:.75rem;margin-top:4px">Auto refresh 180s</div></div><div class="grid2" style="grid-template-columns:1fr 1fr;gap:8px"><div class="chip"><div class="l">Machine</div><div class="v"><?=$k['machine_run']?>/<?=$k['machine_total']?></div></div><div class="chip"><div class="l">Down</div><div class="v" style="color:<?=$k['machine_down']>0?'#fca5a5':'#86efac'?>"><?=$k['machine_down']?></div></div><div class="chip"><div class="l">PR Open</div><div class="v"><?=$k['pr_open']?></div></div><div class="chip"><div class="l">PO Open</div><div class="v"><?=$k['po_open']?></div></div></div></div></div>
<div class="kpis">
<div class="cardx k t1"><div class="kl">Sales Booking Bulan Ini</div><div class="kv"><?=tvxRp($k['so_booking'])?></div><div class="km">SO aktif <?=$k['so_active']?> | FG Stock <?=$k['so_fg']?></div></div>
<div class="cardx k t2"><div class="kl">Invoicing & Collection</div><div class="kv"><?=tvxRp($k['inv_month'])?></div><div class="km">Collection <?=tvxRp($k['col_month'])?> | AR <?=tvxRp($k['ar_out'])?></div></div>
<div class="cardx k t3"><div class="kl">Produksi Berjalan</div><div class="kv"><?=$k['spk_active']?> SPK</div><div class="km">Task running <?=$k['prod_run']?> | Output today <?=tvxNo($k['prod_out_today'])?></div></div>
<div class="cardx k t4"><div class="kl">Warehouse & Delivery</div><div class="kv"><?=$k['dn_today']?> SJ Today</div><div class="km">Low stock <?=$k['low_stock']?> | Near expiry <?=$k['near_exp']?></div></div>
<div class="cardx k t2"><div class="kl">AR / AP Outstanding</div><div class="kv"><?=tvxRp($k['ar_out']+$k['ap_out'])?></div><div class="km">AR <?=tvxRp($k['ar_out'])?> | AP <?=tvxRp($k['ap_out'])?></div></div>
<div class="cardx k t1"><div class="kl">Purchasing Bulan Ini</div><div class="kv"><?=tvxRp($k['po_month'])?></div><div class="km">PR open <?=$k['pr_open']?> | PO open <?=$k['po_open']?></div></div>
<div class="cardx k t3"><div class="kl">SPK & QC Queue</div><div class="kv"><?=$k['spk_wait']?> Waiting</div><div class="km">QC NG 7 hari <?=$k['qc_ng7']?> | SPK aktif <?=$k['spk_active']?></div></div>
<div class="cardx k t4"><div class="kl">Cash / Kas Bulan Ini</div><div class="kv"><?=tvxRp($k['cash_in']-$k['cash_out'])?></div><div class="km">In <?=tvxRp($k['cash_in'])?> | Out <?=tvxRp($k['cash_out'])?></div></div>
</div>
<div class="main">
<div class="colL">
<div class="cardx box"><div class="hd"><h4><i class="bi bi-graph-up-arrow me-2"></i>Trend 6 Bulan (SO / Invoice / Collection)</h4><small>Overall Finansial</small></div><div class="cv"><canvas id="cTrend"></canvas></div></div>
<div class="cardx box"><div class="hd"><h4><i class="bi bi-speedometer2 me-2"></i>Executive Radar</h4><small>Alert lintas modul</small></div><div class="alerts"><?php foreach($opsAlerts as $a): ?><div class="al <?=htmlspecialchars($a[2],ENT_QUOTES,'UTF-8')?>"><div class="n"><?=number_format((int)$a[1],0,',','.')?></div><div class="t"><?=htmlspecialchars($a[0],ENT_QUOTES,'UTF-8')?></div></div><?php endforeach; ?></div><div class="grid2" style="flex:1;min-height:0"><div class="mini"><h5>Daily Pulse Today</h5><div class="cv"><canvas id="cPulse"></canvas></div></div><div class="mini"><h5>Status SO</h5><div class="cv"><canvas id="cSO"></canvas></div></div></div></div>
</div>
<div class="colR">
<div class="cardx box"><div class="hd"><h4><i class="bi bi-pie-chart me-2"></i>Status SPK & Machine Health</h4><small>Produksi</small></div><div class="grid2" style="flex:1;min-height:0"><div class="mini"><h5>Status SPK</h5><div class="cv"><canvas id="cSPK"></canvas></div></div><div class="mini"><h5>Machine Health</h5><div class="cv"><canvas id="cMach"></canvas></div></div></div></div>
<div class="cardx box"><div class="hd"><h4><i class="bi bi-table me-2"></i>Recent SO + AR Due</h4><small>Sales / Finance</small></div><div class="grid2" style="flex:1;min-height:0"><div class="mini"><h5>Recent Sales Orders</h5><table class="tbl"><thead><tr><th>SO</th><th>Status</th><th>Fulfill</th><th class="r">Total</th></tr></thead><tbody><?php if(empty($recentSO)): ?><tr><td colspan="4">Tidak ada data</td></tr><?php else: foreach($recentSO as $r): $ff=(($r['fulfillment_source']??'spk')==='fg_stock')?'fg_stock':'spk'; ?><tr><td class="nw"><strong><?=htmlspecialchars($r['so_number'],ENT_QUOTES,'UTF-8')?></strong><div style="color:#a5b8cd;font-size:.66rem"><?=date('d/m',strtotime((string)$r['so_date']))?></div></td><td class="nw"><?=htmlspecialchars(strtoupper(str_replace('_',' ',(string)$r['status'])),ENT_QUOTES,'UTF-8')?></td><td class="nw"><span class="pill <?=$ff==='fg_stock'?'fg':'spk'?>"><?=$ff==='fg_stock'?'FG':'SPK'?></span></td><td class="r"><?=tvxRp($r['grand_total']??0)?></td></tr><?php endforeach; endif; ?></tbody></table></div><div class="mini"><h5>AR Due / Overdue</h5><table class="tbl"><thead><tr><th>Invoice</th><th>Due</th><th class="r">OS</th></tr></thead><tbody><?php if(empty($arDue)): ?><tr><td colspan="3">Tidak ada AR outstanding</td></tr><?php else: foreach($arDue as $r): ?><tr><td><?=htmlspecialchars($r['invoice_number'],ENT_QUOTES,'UTF-8')?></td><td class="nw" style="color:<?=strtotime((string)$r['due_date'])<strtotime($today)?'#fca5a5':'#cbd5e1'?>"><?=date('d/m',strtotime((string)$r['due_date']))?></td><td class="r"><?=tvxRp($r['os']??0)?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div>
</div>
</div>
<div class="ft"><marquee behavior="scroll" direction="left" scrollamount="9"><i class="bi bi-broadcast me-2"></i> <?=htmlspecialchars(strtoupper($running_text),ENT_QUOTES,'UTF-8')?> | SO Active <?=$k['so_active']?> | SPK Active <?=$k['spk_active']?> | AR Overdue <?=$k['ar_overdue']?> | Low Stock <?=$k['low_stock']?> | Near Expiry <?=$k['near_exp']?></marquee></div>
<script>
const M=<?= $j(array_values($labels)) ?>, SOv=<?= $j(array_values($serSO)) ?>, IVv=<?= $j(array_values($serINV)) ?>, CLv=<?= $j(array_values($serCOL)) ?>;
const pulseL=<?= $j(array_keys($pulse)) ?>, pulseV=<?= $j(array_values($pulse)) ?>, soDist=<?= $j($soDist) ?>, spkDist=<?= $j($spkDist) ?>;
const machineVals=[<?= (int)$k['machine_run'] ?>,<?= (int)$k['machine_maint'] ?>,<?= (int)$k['machine_down'] ?>];
const tickColor='#bcd0e5', gridColor='rgba(148,163,184,.12)';
const rup=(n)=>'Rp '+(Number(n||0)/1e6).toLocaleString('id-ID',{maximumFractionDigits:1})+' Jt';
new Chart(document.getElementById('cTrend'),{type:'line',data:{labels:M,datasets:[{label:'SO',data:SOv,borderColor:'#38bdf8',borderWidth:3,tension:.35,pointRadius:2},{label:'Invoice',data:IVv,borderColor:'#22c55e',borderWidth:3,tension:.35,pointRadius:2},{label:'Collection',data:CLv,borderColor:'#f59e0b',borderWidth:3,tension:.35,pointRadius:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#eaf2ff'}},tooltip:{callbacks:{label:(c)=>`${c.dataset.label}: ${rup(c.raw)}`}}},scales:{x:{grid:{display:false},ticks:{color:tickColor}},y:{grid:{color:gridColor},ticks:{color:tickColor,callback:(v)=>(v/1e6).toLocaleString('id-ID')+' Jt'}}}}});
new Chart(document.getElementById('cPulse'),{type:'bar',data:{labels:pulseL,datasets:[{data:pulseV,backgroundColor:['#38bdf8','#3b82f6','#22c55e','#14b8a6','#f59e0b','#a855f7','#fb7185'],borderRadius:7}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{color:tickColor,font:{size:10}}},y:{grid:{color:gridColor},ticks:{color:tickColor}}}}});
function donut(el,rows,fallback){const map={'draft':'#94a3b8','waiting_approval':'#f59e0b','confirmed':'#3b82f6','in_production':'#06b6d4','delivered':'#14b8a6','completed':'#22c55e','cancelled':'#ef4444','rejected':'#dc2626','waiting_eng':'#f59e0b','preliminary':'#a855f7','waiting_mgr':'#f97316','final':'#c084fc','released':'#38bdf8','closed':'#16a34a'};let labels=[],vals=[],colors=[];(rows||[]).forEach(r=>{const s=String(r.status||'unknown');labels.push(s.replaceAll('_',' ').toUpperCase());vals.push(Number(r.cnt||0));colors.push(map[s]||'#64748b');});if(!labels.length){labels=[fallback];vals=[1];colors=['#334155'];}new Chart(document.getElementById(el),{type:'doughnut',data:{labels,datasets:[{data:vals,backgroundColor:colors,borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#eaf2ff',boxWidth:10,font:{size:10}}}},cutout:'60%'}});} 
donut('cSO',soDist,'SO'); donut('cSPK',spkDist,'SPK');
new Chart(document.getElementById('cMach'),{type:'doughnut',data:{labels:['Running','Maint','Down'],datasets:[{data:machineVals,backgroundColor:['#22c55e','#f59e0b','#ef4444'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#eaf2ff',boxWidth:10}}},cutout:'62%'}});
(()=>{const T=document.getElementById('clk'),D=document.getElementById('dte'),H=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'],B=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'],p=n=>String(n).padStart(2,'0');const f=()=>{const n=new Date();T.textContent=`${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;D.textContent=`${H[n.getDay()]}, ${p(n.getDate())} ${B[n.getMonth()]} ${n.getFullYear()}`;};f();setInterval(f,1000);})();
setTimeout(()=>location.reload(),180000);
</script></body></html>
