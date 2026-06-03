<?php
/**
 * PHO Budgeting System — Admin Dashboard
 * 3-Layer Drill-Down Budget Overview with Role-Based UI.
 */
require_once __DIR__ . '/config.php';
requireLogin();

// ── Role toggle via GET ─────────────────────────
if (isset($_GET['set_role']) && in_array($_GET['set_role'], ['admin', 'staff'], true)) {
    setUserRole($_GET['set_role']);
    header('Location: admin_dashboard.php');
    exit;
}

$role    = getUserRole();
$isAdmin = isAdmin();

// ── AJAX: Update proposal ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo    = getConnection();
        $editId = (int)($_POST['id'] ?? 0);
        if ($editId < 1) throw new InvalidArgumentException('Invalid ID.');

        $data = [];
        foreach (['ppa_description', 'justification'] as $f) {
            $data[$f] = trim($_POST[$f] ?? '');
            if ($data[$f] === '') throw new InvalidArgumentException("'" . str_replace('_', ' ', $f) . "' is required.");
        }
        foreach (['account_id', 'fund_source_id', 'indicator_id', 'unit_id'] as $f) {
            $data[$f] = (int)($_POST[$f] ?? 0);
            if ($data[$f] < 1) throw new InvalidArgumentException("Please select a valid " . str_replace('_id', '', $f) . ".");
        }

        $quarters = ['q1_target', 'q2_target', 'q3_target', 'q4_target'];
        $tt = 0;
        foreach ($quarters as $q) { $data[$q] = max(0, (int)($_POST[$q] ?? 0)); $tt += $data[$q]; }
        $data['target_total'] = $tt;

        $months = ['jan_amt','feb_amt','mar_amt','apr_amt','may_amt','jun_amt','jul_amt','aug_amt','sep_amt','oct_amt','nov_amt','dec_amt'];
        $at = 0;
        foreach ($months as $m) { $data[$m] = max(0, round((float)($_POST[$m] ?? 0), 2)); $at += $data[$m]; }
        $data['total_allocation'] = round($at, 2);

        $sql = "UPDATE tbl_budget_proposals SET
                    ppa_description=:ppa_description, account_id=:account_id,
                    fund_source_id=:fund_source_id, indicator_id=:indicator_id, unit_id=:unit_id,
                    q1_target=:q1_target, q2_target=:q2_target, q3_target=:q3_target, q4_target=:q4_target, target_total=:target_total,
                    jan_amt=:jan_amt, feb_amt=:feb_amt, mar_amt=:mar_amt, apr_amt=:apr_amt, may_amt=:may_amt, jun_amt=:jun_amt,
                    jul_amt=:jul_amt, aug_amt=:aug_amt, sep_amt=:sep_amt, oct_amt=:oct_amt, nov_amt=:nov_amt, dec_amt=:dec_amt,
                    total_allocation=:total_allocation, justification=:justification
                WHERE id=:id";
        $stmt = $pdo->prepare($sql);
        $params = [':id' => $editId];
        foreach ($data as $k => $v) $params[":$k"] = $v;
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'message' => 'Proposal updated successfully.']);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (PDOException $e) {
        error_log('Update Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
    }
    exit;
}

// ── AJAX: Delete proposal ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$isAdmin) { http_response_code(403); echo json_encode(['status' => 'error', 'message' => 'Access denied.']); exit; }
    try {
        $pdo  = getConnection();
        $stmt = $pdo->prepare("DELETE FROM tbl_budget_proposals WHERE id = :id");
        $stmt->execute([':id' => (int)($_POST['id'] ?? 0)]);
        echo json_encode(['status' => 'success', 'message' => 'Proposal deleted.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
    }
    exit;
}

$versionId = getSelectedVersionId();
try {
    $pdo = getConnection();

    $stmtRows = $pdo->prepare("
        SELECT bp.*,
               ac.account_code, ac.account_title, ac.expense_class,
               fs.fund_name,
               ind.indicator_description,
               un.unit_name
        FROM   tbl_budget_proposals bp
        JOIN   tbl_account_codes  ac  ON bp.account_id     = ac.id
        JOIN   tbl_fund_sources   fs  ON bp.fund_source_id = fs.id
        JOIN   tbl_indicators     ind ON bp.indicator_id   = ind.id
        JOIN   tbl_units          un  ON bp.unit_id        = un.id
        WHERE  bp.version_id = :vid
        ORDER BY ac.expense_class, ac.account_code, bp.ppa_description
    ");
    $stmtRows->execute([':vid' => $versionId]);
    $rows = $stmtRows->fetchAll();
    $accounts   = $pdo->query("SELECT id, account_code, account_title, expense_class FROM tbl_account_codes ORDER BY account_code")->fetchAll();
    $fundSrcs   = $pdo->query("SELECT id, fund_name FROM tbl_fund_sources ORDER BY fund_name")->fetchAll();
    $indicators = $pdo->query("SELECT id, indicator_description FROM tbl_indicators ORDER BY id")->fetchAll();
    $units      = $pdo->query("SELECT id, unit_name FROM tbl_units ORDER BY unit_name")->fetchAll();
    $users      = $isAdmin ? $pdo->query("SELECT id, fullname, username, role, created_at FROM tbl_users ORDER BY created_at DESC, id DESC")->fetchAll() : [];
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    $rows = $accounts = $fundSrcs = $indicators = $units = $users = [];
}

// ── Group rows by account_id → Layer 1 ──────────
$grouped = [];
$grandTotal = 0;
$grandTarget = 0;
$classTotals = ['MOOE' => 0, 'CO' => 0, 'PS' => 0];
$fundTotals = [];

foreach ($rows as $r) {
    $aid = (int)$r['account_id'];
    if (!isset($grouped[$aid])) {
        $grouped[$aid] = [
            'account_code'   => $r['account_code'],
            'account_title'  => $r['account_title'],
            'expense_class'  => $r['expense_class'],
            'total_alloc'    => 0,
            'total_target'   => 0,
            'count'          => 0,
            'proposals'      => [],
        ];
    }
    $alloc = (float)$r['total_allocation'];
    $grouped[$aid]['total_alloc']  += $alloc;
    $grouped[$aid]['total_target'] += (int)$r['target_total'];
    $grouped[$aid]['count']++;
    $grouped[$aid]['proposals'][]   = $r;
    $grandTotal += $alloc;
    $grandTarget += (int)$r['target_total'];
    $classTotals[$r['expense_class']] = ($classTotals[$r['expense_class']] ?? 0) + $alloc;
    $fundTotals[$r['fund_name']] = ($fundTotals[$r['fund_name']] ?? 0) + $alloc;
}

$pageTitle  = 'Budget Overview';
$activeMenu = 'admin_dashboard';
require_once __DIR__ . '/includes/header.php';

$monthKeys  = ['jan_amt','feb_amt','mar_amt','apr_amt','may_amt','jun_amt','jul_amt','aug_amt','sep_amt','oct_amt','nov_amt','dec_amt'];
$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$expBadge   = ['MOOE' => 'bg-blue-100 text-blue-800', 'CO' => 'bg-orange-100 text-orange-800', 'PS' => 'bg-purple-100 text-purple-800'];
?>

<style>
.layer-content{max-height:0;overflow:hidden;transition:max-height .4s cubic-bezier(.4,0,.2,1)}
    .layer-content.open{overflow:visible}
    .toggle-icon{transition:transform .3s ease;font-size:.7rem}
    .toggle-icon.rotated{transform:rotate(90deg)}
    .layer1-header:hover{background:rgba(11,77,38,.04)}
    .layer2-header:hover{background:rgba(16,185,129,.04)}
    .layer1-header,.layer2-header{cursor:pointer;user-select:none;-webkit-tap-highlight-color:transparent}
    .account-group.filtered-out,.ppa-entry.filtered-out{display:none!important}
    .field-error{border-color:#ef4444!important;box-shadow:0 0 0 3px rgba(239,68,68,.15)!important}
    .field-error-msg{color:#ef4444;font-size:.75rem;margin-top:.25rem;display:none}
    .field-error-msg.visible{display:block}
    /* ── Layer 3 compact tables ─────────────────── */
    .layer3-panel{font-size:.8125rem}
    .layer3-panel .l3-section{margin-bottom:.75rem}
    .layer3-panel .l3-section:last-child{margin-bottom:0}
    .layer3-panel .l3-label{display:flex;align-items:center;gap:.35rem;font-size:.65rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#9ca3af;margin-bottom:.35rem}
    .layer3-panel .l3-label i{font-size:.6rem}
    .layer3-panel .l3-text{font-size:.8125rem;color:#374151;line-height:1.45}
    .layer3-panel .l3-box{background:#fff;border:1px solid #e5e7eb;border-radius:.5rem;overflow:hidden}
    .layer3-panel .l3-meta-grid{display:grid;grid-template-columns:1fr;gap:.5rem}
    @media(min-width:640px){.layer3-panel .l3-meta-grid{grid-template-columns:1fr 1fr}}
    .layer3-panel .l3-meta-cell{padding:.6rem .75rem;border-left:3px solid #d5f0dc}
    .layer3-panel table{width:100%;border-collapse:collapse}
    .layer3-panel th{font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;background:#f9fafb;border-bottom:2px solid #e5e7eb;padding:.4rem .5rem;text-align:center;white-space:nowrap}
    .layer3-panel td{font-size:.75rem;font-weight:600;color:#374151;padding:.45rem .5rem;text-align:center;white-space:nowrap;border-right:1px solid #f3f4f6}
    .layer3-panel td:last-child{border-right:none}
    .layer3-panel .td-total{background:#ecfdf5;color:#065f46;font-weight:800;border-left:2px solid #6ee7b7}
    .layer3-panel .td-total-alloc{background:#eff6ff;color:#1e40af;font-weight:800;border-left:2px solid #93c5fd}
    .layer3-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:.5rem;border:1px solid #e5e7eb}
    .layer3-scroll::-webkit-scrollbar{height:5px}
    .layer3-scroll::-webkit-scrollbar-track{background:#f3f4f6}
    .layer3-scroll::-webkit-scrollbar-thumb{background:#a8e0b9;border-radius:999px}
    .layer3-scroll::-webkit-scrollbar-thumb:hover{background:#a5b4fc}
    
    @media(max-width:640px){
        .layer3-grid{grid-template-columns:repeat(3,1fr)!important}
        .summary-cards{grid-template-columns:repeat(2,1fr)!important}
    }
</style>

<!-- ═══ Overview Header ═══ -->
<div class="mb-6">
    <p class="text-sm text-gray-500">3-Layer drill-down view of all budget proposals grouped by Account Code.</p>
</div>

<!-- ═══ Summary Cards ═══ --><div class="summary-cards grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 sm:p-5 flex items-center gap-3">
        <div class="bg-brand-100 text-brand-600 w-10 h-10 rounded-lg flex items-center justify-center shrink-0"><i class="fa-solid fa-peso-sign text-lg"></i></div>
        <div class="min-w-0">
            <span class="peso-amount text-lg sm:text-xl font-bold text-gray-800 truncate"><span class="peso-sign">&#8369;</span> <?= number_format($grandTotal, 2) ?></span>
            <span class="block text-[10px] sm:text-xs text-gray-400">Total Allocation</span>
        </div>
    </div>
    <?php foreach ([['MOOE','Maintenance & Other','blue','fa-wrench'], ['CO','Capital Outlay','orange','fa-building'], ['PS','Personal Services','purple','fa-users']] as [$cls,$lbl,$clr,$ico]): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 sm:p-5 flex items-center gap-3">
        <div class="bg-<?= $clr ?>-100 text-<?= $clr ?>-600 w-10 h-10 rounded-lg flex items-center justify-center shrink-0"><i class="fa-solid <?= $ico ?> text-lg"></i></div>
        <div class="min-w-0">
            <span class="peso-amount text-lg sm:text-xl font-bold text-gray-800 truncate"><span class="peso-sign">&#8369;</span> <?= number_format(($classTotals[$cls] ?? 0), 2) ?></span>
            <span class="block text-[10px] sm:text-xs text-gray-400"><?= $cls ?> - <?= $lbl ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-6 flex flex-col sm:flex-row sm:items-end gap-3">
    <div class="flex-1">
        <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Expense Class</label>
        <select id="fExpense" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-brand-500 transition">
            <option value="">All Classes</option>
            <option value="MOOE">MOOE</option><option value="CO">CO</option><option value="PS">PS</option>
        </select>
    </div>
    <div class="flex-1">
        <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Fund Source</label>
        <select id="fFund" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-brand-500 transition">
            <option value="">All Funds</option>
            <?php foreach ($fundSrcs as $f): ?><option value="<?= e($f['fund_name']) ?>"><?= e($f['fund_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="flex-1">
        <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Unit</label>
        <select id="fUnit" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-brand-500 transition">
            <option value="">All Units</option>
            <?php foreach ($units as $u): ?><option value="<?= e($u['unit_name']) ?>"><?= e($u['unit_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <button onclick="resetFilters()" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand-600 transition px-3 py-2 shrink-0">
        <i class="fa-solid fa-rotate-left text-xs"></i> Reset
    </button>
</div>

<!-- 3-Layer Accordion -->
<div id="accordionRoot" class="space-y-3">
<?php if (empty($grouped)): ?>
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 px-6 py-16 text-center">
        <div class="mx-auto w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-5"><i class="fa-solid fa-folder-open text-3xl text-gray-300"></i></div>
        <h2 class="text-lg font-semibold text-gray-600 mb-2">No Budget Proposals Found</h2>
        <p class="text-sm text-gray-400 mb-5">Create your first proposal to see it here.</p>
        <a href="create.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md"><i class="fa-solid fa-plus text-xs"></i> New Proposal</a>
    </div>
<?php endif; ?>

<?php foreach ($grouped as $aid => $grp): ?>
<!-- ═══ LAYER 1: Account Group ═══ -->
<div class="account-group bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" data-expense="<?= e($grp['expense_class']) ?>">

    <!-- Layer 1 Header -->
    <div class="layer1-header flex items-center justify-between px-4 sm:px-5 py-4 gap-3" onclick="toggleLayer(this)">
        <div class="flex items-center gap-3 min-w-0">
            <i class="fa-solid fa-chevron-right toggle-icon text-gray-400"></i>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-xs text-gray-500"><?= e($grp['account_code']) ?></span>
                    <span class="inline-block <?= $expBadge[$grp['expense_class']] ?? 'bg-gray-100 text-gray-600' ?> text-[10px] font-bold px-2 py-0.5 rounded-full"><?= e($grp['expense_class']) ?></span>
                </div>
                <span class="block text-sm sm:text-base font-semibold text-gray-800 truncate"><?= e($grp['account_title']) ?></span>
            </div>
        </div>
        <div class="text-right shrink-0">
            <span class="block text-sm sm:text-base font-bold text-brand-700"><?= peso($grp['total_alloc']) ?></span>
            <span class="block text-[10px] text-gray-400"><?= $grp['count'] ?> PPA<?= $grp['count'] > 1 ? 's' : '' ?></span>
        </div>
    </div>

    <!-- Layer 1 Content -->
    <div class="layer-content">
        <div class="border-t border-gray-100">
        <?php foreach ($grp['proposals'] as $pi => $p): ?>

            <!-- ═══ LAYER 2: PPA Entry ═══ -->
            <div class="ppa-entry border-b border-gray-50 last:border-b-0" data-fund="<?= e($p['fund_name']) ?>" data-unit="<?= e($p['unit_name']) ?>" data-alloc="<?= (float)$p['total_allocation'] ?>">

                <!-- Layer 2 Header -->
                <div class="layer2-header flex items-center justify-between px-4 sm:px-5 pl-8 sm:pl-10 py-3.5 gap-3" onclick="toggleLayer(this)">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fa-solid fa-chevron-right toggle-icon text-emerald-400"></i>
                        <div class="min-w-0">
                            <span class="block text-sm font-medium text-gray-800 truncate"><?= e($p['ppa_description']) ?></span>
                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                <span class="text-[10px] text-violet-500"><i class="fa-solid fa-building mr-0.5"></i><?= e($p['unit_name']) ?></span>
                                <span class="text-[10px] text-gray-400"><i class="fa-solid fa-wallet mr-0.5"></i><?= e($p['fund_name']) ?></span>
                            </div>
                        </div>
                    </div>
                    <span class="text-sm font-bold text-emerald-700 shrink-0 whitespace-nowrap"><?= peso((float)$p['total_allocation']) ?></span>
                </div>

                <!-- Layer 2 Content → Layer 3 Detail -->
                <div class="layer-content">
                    <div class="bg-gray-50/70 px-4 sm:px-6 py-5 ml-8 sm:ml-10 mr-2 sm:mr-4 mb-3 rounded-xl border border-gray-200/80">

                        <!-- Layer 3 (Compact layout) -->
                        <div class="layer3-panel">

                            <!-- SECTION 1: Indicator + Justification (side-by-side on desktop) -->
                            <div class="l3-section">
                                <div class="l3-box">
                                    <div class="l3-meta-grid">
                                        <div class="l3-meta-cell">
                                            <div class="l3-label"><i class="fa-solid fa-gauge-high"></i> Performance Indicator</div>
                                            <div class="l3-text"><?= e($p['indicator_description']) ?></div>
                                        </div>
                                        <div class="l3-meta-cell" style="border-left-color:#fde68a">
                                            <div class="l3-label"><i class="fa-solid fa-file-lines"></i> Justification / Narrative</div>
                                            <div class="l3-text" style="white-space:pre-line"><?= e($p['justification']) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 2: Physical Targets (5-column table) -->
                            <div class="l3-section">
                                <div class="l3-label"><i class="fa-solid fa-bullseye"></i> Physical Targets</div>
                                <div class="layer3-scroll">
                                    <table style="min-width:360px">
                                        <thead><tr><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Total Target</th></tr></thead>
                                        <tbody><tr>
                                            <td><?= number_format((int)$p['q1_target']) ?></td>
                                            <td><?= number_format((int)$p['q2_target']) ?></td>
                                            <td><?= number_format((int)$p['q3_target']) ?></td>
                                            <td><?= number_format((int)$p['q4_target']) ?></td>
                                            <td class="td-total"><?= number_format((int)$p['target_total']) ?></td>
                                        </tr></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- SECTION 3: Financial Allocation (13-column table) -->
                            <div class="l3-section">
                                <div class="l3-label"><i class="fa-solid fa-peso-sign"></i> Financial Allocation</div>
                                <div class="layer3-scroll">
                                    <table style="min-width:900px">
                                        <thead><tr>
                                            <?php for ($mi = 0; $mi < 12; $mi++): ?><th><?= $monthNames[$mi] ?></th><?php endfor; ?>
                                            <th>Total</th>
                                        </tr></thead>
                                        <tbody><tr>
                                            <?php for ($mi = 0; $mi < 12; $mi++): ?><td><?= number_format((float)$p[$monthKeys[$mi]], 2) ?></td><?php endfor; ?>
                                            <td class="td-total-alloc"><?= number_format((float)$p['total_allocation'], 2) ?></td>
                                        </tr></tbody>
                                    </table>
                                </div>
                            </div>

                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 pt-3 mt-1 border-t border-gray-200">
                            <button onclick='openEditModal(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_TAG) ?>)' class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 text-white text-xs font-semibold hover:bg-amber-600 transition shadow-sm">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                            <a href="view.php?id=<?= (int)$p['id'] ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-brand-500 text-white text-xs font-semibold hover:bg-brand-600 transition shadow-sm">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <?php if ($isAdmin): ?>
                            <button onclick="deleteProposal(<?= (int)$p['id'] ?>)" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-red-500 text-white text-xs font-semibold hover:bg-red-600 transition shadow-sm admin-only-btn">
                                <i class="fa-solid fa-trash-can"></i> Delete
                            </button>
                            <?php endif; ?>
                            <span class="ml-auto text-[10px] text-gray-400">#<?= (int)$p['id'] ?> · <?= date('M j, Y', strtotime($p['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

            </div><!-- /ppa-entry -->
        <?php endforeach; ?>
        </div>
    </div>

</div><!-- /account-group -->
<?php endforeach; ?>
</div><!-- /accordionRoot -->

<!-- ═══════════════════════════════════════════════════
     EDIT MODAL (Slide-up panel)
     ═══════════════════════════════════════════════════ -->
<div id="editOverlay" class="fixed inset-0 z-[60] bg-black/50 hidden flex items-end sm:items-center justify-center" onclick="if(event.target===this)closeEditModal()">
    <div id="editPanel" class="bg-white w-full sm:max-w-2xl sm:rounded-2xl rounded-t-2xl shadow-2xl max-h-[92vh] flex flex-col transform translate-y-full sm:translate-y-0 sm:scale-95 opacity-0 transition-all duration-300">

        <!-- Modal Header -->
        <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-200 shrink-0 bg-amber-50 sm:rounded-t-2xl">
            <h3 class="text-base font-bold text-amber-900"><i class="fa-solid fa-pen-to-square mr-2"></i>Edit Budget Proposal</h3>
            <button onclick="closeEditModal()" class="w-8 h-8 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Modal Body (scrollable) -->
        <form id="editForm" novalidate class="flex-1 overflow-y-auto px-5 sm:px-6 py-5 space-y-5">
            <input type="hidden" name="_action" value="update">
            <input type="hidden" name="id" id="eId">

            <!-- PPA Description -->
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">PPA Description <span class="text-red-500">*</span></label>
                <textarea name="ppa_description" id="ePpa" rows="2" required class="ef w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition resize-none"></textarea>
                <p class="field-error-msg" data-for="ePpa">PPA Description is required.</p>
            </div>

            <!-- Dropdowns Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Unit <span class="text-red-500">*</span></label>
                    <select name="unit_id" id="eUnit" required class="ef w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-brand-500 transition">
                        <option value="">— Select —</option>
                        <?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['unit_name']) ?></option><?php endforeach; ?>
                    </select>
                    <p class="field-error-msg" data-for="eUnit">Unit is required.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Account Code <span class="text-red-500">*</span></label>
                    <select name="account_id" id="eAccount" required class="ef w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-brand-500 transition">
                        <option value="">— Select —</option>
                        <?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['account_code']) ?> — <?= e($a['account_title']) ?></option><?php endforeach; ?>
                    </select>
                    <p class="field-error-msg" data-for="eAccount">Account Code is required.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Fund Source <span class="text-red-500">*</span></label>
                    <select name="fund_source_id" id="eFund" required class="ef w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-brand-500 transition">
                        <option value="">— Select —</option>
                        <?php foreach ($fundSrcs as $f): ?><option value="<?= (int)$f['id'] ?>"><?= e($f['fund_name']) ?></option><?php endforeach; ?>
                    </select>
                    <p class="field-error-msg" data-for="eFund">Fund Source is required.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Indicator <span class="text-red-500">*</span></label>
                    <select name="indicator_id" id="eIndicator" required class="ef w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-brand-500 transition">
                        <option value="">— Select —</option>
                        <?php foreach ($indicators as $ind): ?><option value="<?= (int)$ind['id'] ?>"><?= e($ind['indicator_description']) ?></option><?php endforeach; ?>
                    </select>
                    <p class="field-error-msg" data-for="eIndicator">Indicator is required.</p>
                </div>
            </div>

            <!-- Quarterly Targets -->
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Quarterly Targets</label>
                <div class="grid grid-cols-5 gap-2">
                    <?php foreach (['q1_target'=>'Q1','q2_target'=>'Q2','q3_target'=>'Q3','q4_target'=>'Q4'] as $qk => $ql): ?>
                    <div class="text-center">
                        <span class="block text-[10px] text-gray-400 mb-1"><?= $ql ?></span>
                        <input type="number" name="<?= $qk ?>" id="e_<?= $qk ?>" min="0" value="0" class="eq-input w-full text-center text-sm font-semibold border border-gray-300 rounded-lg py-2 focus:ring-2 focus:ring-emerald-500 transition">
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center">
                        <span class="block text-[10px] text-emerald-600 font-bold mb-1">Total</span>
                        <div class="w-full text-center text-sm font-bold py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800" id="eTargetTotal">0</div>
                    </div>
                </div>
            </div>

            <!-- Monthly Allocation -->
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Monthly Allocation (₱)</label>
                <div class="layer3-grid grid gap-2" style="grid-template-columns:repeat(6,1fr)">
                    <?php for ($mi = 0; $mi < 12; $mi++): ?>
                    <div class="text-center">
                        <span class="block text-[10px] text-gray-400 mb-1"><?= $monthNames[$mi] ?></span>
                        <input type="number" name="<?= $monthKeys[$mi] ?>" id="e_<?= $monthKeys[$mi] ?>" min="0" step="0.01" value="0" class="em-input w-full text-center text-xs font-medium border border-gray-300 rounded-lg py-2 focus:ring-2 focus:ring-amber-500 transition">
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="mt-2 bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-center justify-between">
                    <span class="text-xs text-amber-600 font-semibold">Total Annual Allocation</span>
                    <span class="text-base font-bold text-amber-800" id="eAllocTotal">₱ 0.00</span>
                </div>
            </div>

            <!-- Justification -->
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Justification <span class="text-red-500">*</span></label>
                <textarea name="justification" id="eJustification" rows="3" required class="ef w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 transition resize-none"></textarea>
                <p class="field-error-msg" data-for="eJustification">Justification is required.</p>
            </div>
        </form>

        <!-- Modal Footer -->
        <div class="flex items-center justify-end gap-3 px-5 sm:px-6 py-4 border-t border-gray-200 shrink-0 bg-gray-50 sm:rounded-b-2xl">
            <button type="button" onclick="closeEditModal()" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-100 transition">Cancel</button>
            <button type="button" id="editSaveBtn" onclick="submitEditForm()" class="px-6 py-2.5 rounded-lg bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition shadow-md">
                <i class="fa-solid fa-floppy-disk mr-1.5"></i>Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════ -->
<script>
(() => {
'use strict';

// ══════════════════════════════════════════════════
// ACCORDION: Smooth expand/collapse with nested support
// ══════════════════════════════════════════════════
window.toggleLayer = function(header) {
    const content = header.nextElementSibling;
    const icon    = header.querySelector('.toggle-icon');
    const isOpen  = content.classList.contains('open');

    if (isOpen) {
        // Closing: set explicit height first, then animate to 0
        content.style.maxHeight = content.scrollHeight + 'px';
        content.offsetHeight; // force reflow
        content.style.maxHeight = '0';
        content.classList.remove('open');
        icon.classList.remove('rotated');
    } else {
        // Opening: animate from 0 to scrollHeight, then remove constraint
        content.style.maxHeight = content.scrollHeight + 'px';
        content.classList.add('open');
        icon.classList.add('rotated');
        content.addEventListener('transitionend', function handler() {
            if (content.classList.contains('open')) {
                content.style.maxHeight = 'none';
            }
            content.removeEventListener('transitionend', handler);
        });
    }
};

// ══════════════════════════════════════════════════
// FILTERS
// ══════════════════════════════════════════════════
const fExpense = document.getElementById('fExpense');
const fFund    = document.getElementById('fFund');
const fUnit    = document.getElementById('fUnit');

function applyFilters() {
    const expVal  = fExpense.value;
    const fundVal = fFund.value;
    const unitVal = fUnit.value;

    document.querySelectorAll('.account-group').forEach(group => {
        const groupExp = group.dataset.expense;
        if (expVal && groupExp !== expVal) {
            group.classList.add('filtered-out');
            return;
        }

        let visibleCount = 0;
        group.querySelectorAll('.ppa-entry').forEach(ppa => {
            const matchFund = !fundVal || ppa.dataset.fund === fundVal;
            const matchUnit = !unitVal || ppa.dataset.unit === unitVal;
            if (matchFund && matchUnit) {
                ppa.classList.remove('filtered-out');
                visibleCount++;
            } else {
                ppa.classList.add('filtered-out');
            }
        });

        group.classList.toggle('filtered-out', visibleCount === 0);
    });
}

fExpense.addEventListener('change', applyFilters);
fFund.addEventListener('change', applyFilters);
fUnit.addEventListener('change', applyFilters);

// Pre-set filters from URL query parameters (e.g. ?expense=MOOE&fund=General+Fund&unit=...)
(function() {
    const params = new URLSearchParams(window.location.search);
    let changed = false;
    if (params.get('expense')) { fExpense.value = params.get('expense'); changed = true; }
    if (params.get('fund'))    { fFund.value    = params.get('fund');    changed = true; }
    if (params.get('unit'))    { fUnit.value    = params.get('unit');    changed = true; }
    if (changed) applyFilters();
})();

window.resetFilters = function() {
    fExpense.value = fFund.value = fUnit.value = '';
    applyFilters();
    if (window.history.replaceState) {
        window.history.replaceState({}, '', window.location.pathname);
    }
};

if (trackerClock) {
    const updateClock = () => {
        const now = new Date();
        trackerClock.textContent = now.toLocaleTimeString('en-GB', { hour12: false });
    };
    updateClock();
    setInterval(updateClock, 1000);
}

// ══════════════════════════════════════════════════
// EDIT MODAL
// ══════════════════════════════════════════════════
const overlay = document.getElementById('editOverlay');
const panel   = document.getElementById('editPanel');
const form    = document.getElementById('editForm');

window.openEditModal = function(data) {
    // Clear previous errors
    form.querySelectorAll('.field-error').forEach(el => el.classList.remove('field-error'));
    form.querySelectorAll('.field-error-msg').forEach(el => el.classList.remove('visible'));

    // Populate fields
    document.getElementById('eId').value            = data.id;
    document.getElementById('ePpa').value           = data.ppa_description;
    document.getElementById('eUnit').value          = data.unit_id;
    document.getElementById('eAccount').value       = data.account_id;
    document.getElementById('eFund').value          = data.fund_source_id;
    document.getElementById('eIndicator').value     = data.indicator_id;
    document.getElementById('eJustification').value = data.justification;

    ['q1_target','q2_target','q3_target','q4_target'].forEach(k => {
        document.getElementById('e_' + k).value = data[k];
    });
    <?php for ($i = 0; $i < 12; $i++): ?>
    document.getElementById('e_<?= $monthKeys[$i] ?>').value = parseFloat(data.<?= $monthKeys[$i] ?>).toFixed(2);
    <?php endfor; ?>

    computeEditTargets();
    computeEditAlloc();

    // Show modal with animation
    overlay.classList.remove('hidden');
    requestAnimationFrame(() => {
        panel.classList.remove('translate-y-full', 'sm:scale-95', 'opacity-0');
        panel.classList.add('translate-y-0', 'sm:scale-100', 'opacity-100');
    });
    document.body.style.overflow = 'hidden';
};

window.closeEditModal = function() {
    panel.classList.add('translate-y-full', 'sm:scale-95', 'opacity-0');
    panel.classList.remove('translate-y-0', 'sm:scale-100', 'opacity-100');
    setTimeout(() => {
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }, 300);
};

// ── Real-time calculations in modal ──────────────
function computeEditTargets() {
    let t = 0;
    document.querySelectorAll('.eq-input').forEach(i => { t += parseInt(i.value) || 0; });
    document.getElementById('eTargetTotal').textContent = t.toLocaleString();
}
function computeEditAlloc() {
    let t = 0;
    document.querySelectorAll('.em-input').forEach(i => { t += parseFloat(i.value) || 0; });
    document.getElementById('eAllocTotal').textContent = '₱ ' + t.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}
document.querySelectorAll('.eq-input').forEach(i => i.addEventListener('input', computeEditTargets));
document.querySelectorAll('.em-input').forEach(i => i.addEventListener('input', computeEditAlloc));

// ── Strict form validation with field focus ──────
window.submitEditForm = function() {
    // Clear all errors first
    form.querySelectorAll('.field-error').forEach(el => el.classList.remove('field-error'));
    form.querySelectorAll('.field-error-msg.visible').forEach(el => el.classList.remove('visible'));

    const required = [
        { id: 'ePpa',          label: 'PPA Description' },
        { id: 'eUnit',         label: 'Unit' },
        { id: 'eAccount',      label: 'Account Code' },
        { id: 'eFund',         label: 'Fund Source' },
        { id: 'eIndicator',    label: 'Indicator' },
        { id: 'eJustification', label: 'Justification' },
    ];

    let firstError = null;

    required.forEach(({ id }) => {
        const el = document.getElementById(id);
        if (!el.value.trim()) {
            el.classList.add('field-error');
            const msg = form.querySelector(`.field-error-msg[data-for="${id}"]`);
            if (msg) msg.classList.add('visible');
            if (!firstError) firstError = el;
        }
    });

    if (firstError) {
        // Scroll the modal body to the error field and focus it
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => firstError.focus(), 350);
        Swal.fire({
            icon: 'warning',
            title: 'Incomplete Form',
            text: 'Please fill in all required fields highlighted in red.',
            confirmButtonColor: '#f59e0b',
            toast: true,
            position: 'top-end',
            timer: 3000,
            showConfirmButton: false,
        });
        return;
    }

    // Submit via AJAX
    const saveBtn = document.getElementById('editSaveBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>Saving…';

    const fd = new FormData(form);

    fetch(window.location.pathname, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            closeEditModal();
            Swal.fire({ icon:'success', title:'Updated!', text:data.message, confirmButtonColor:'#0b4d26', timer:1500, showConfirmButton:false })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon:'error', title:'Error', text:data.message, confirmButtonColor:'#ef4444' });
        }
    })
    .catch(() => Swal.fire({ icon:'error', title:'Network Error', text:'Could not reach the server.' }))
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1.5"></i>Save Changes';
    });
};

// Clear error state on focus
form.querySelectorAll('.ef').forEach(el => {
    el.addEventListener('focus', () => {
        el.classList.remove('field-error');
        const msg = form.querySelector(`.field-error-msg[data-for="${el.id}"]`);
        if (msg) msg.classList.remove('visible');
    });
});

// ══════════════════════════════════════════════════
// DELETE (Admin only)
// ══════════════════════════════════════════════════
window.deleteProposal = function(id) {
    Swal.fire({
        title: 'Delete Proposal #' + id + '?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-trash-can"></i> Yes, delete',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
    }).then(result => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('_action', 'delete');
        fd.append('id', id);
        fetch(window.location.pathname, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({ icon:'success', title:'Deleted!', text:data.message, confirmButtonColor:'#0b4d26', timer:1500, showConfirmButton:false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text:data.message, confirmButtonColor:'#ef4444' });
            }
        })
        .catch(() => Swal.fire({ icon:'error', title:'Network Error' }));
    });
};

// ── Keyboard: Escape closes modal ────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !overlay.classList.contains('hidden')) closeEditModal();
});

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>






