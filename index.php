<?php
/**
 * PHO Budgeting System — Dashboard
 * Pivot-table style drill-down: Fund Source → Unit/Program → Individual Items
 * Step 1 modal (summary) → Step 2 modal (full record with computations)
 */
require_once __DIR__ . '/config.php';
requireLogin();

if (!canViewAllData()) {
    header('Location: staff_dashboard.php');
    exit;
}

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
$versionId  = getSelectedVersionId();
$versionName = getSelectedVersionName();

try {
    $pdo = getConnection();

    $stmtOverall = $pdo->prepare("
        SELECT COUNT(id) AS total_proposals, COALESCE(SUM(total_allocation), 0) AS grand_total
        FROM tbl_budget_proposals WHERE version_id = :vid
    ");
    $stmtOverall->execute([':vid' => $versionId]);
    $overallRow = $stmtOverall->fetch();

    $stmtDrill = $pdo->prepare("
        SELECT bp.*, fs.fund_name, un.unit_name,
               ac.account_code, ac.account_title, ac.expense_class,
               ind.indicator_description
        FROM   tbl_budget_proposals bp
        JOIN   tbl_fund_sources  fs  ON bp.fund_source_id = fs.id
        JOIN   tbl_units         un  ON bp.unit_id        = un.id
        JOIN   tbl_account_codes ac  ON bp.account_id     = ac.id
        JOIN   tbl_indicators    ind ON bp.indicator_id    = ind.id
        WHERE  bp.version_id = :vid
        ORDER BY fs.fund_name, un.unit_name, bp.ppa_description
    ");
    $stmtDrill->execute([':vid' => $versionId]);
    $drillRows = $stmtDrill->fetchAll();

} catch (PDOException $e) {
    error_log('Dashboard DB Error: ' . $e->getMessage());
    $overallRow = ['total_proposals' => 0, 'grand_total' => 0];
    $drillRows  = [];
    $dbError    = true;
}

$grandTotal     = (float)($overallRow['grand_total'] ?? 0);
$totalProposals = (int)($overallRow['total_proposals'] ?? 0);

$fundGroups = [];
foreach ($drillRows as $r) {
    $fund = (string)$r['fund_name'];
    $unit = (string)$r['unit_name'];
    if (!isset($fundGroups[$fund])) {
        $fundGroups[$fund] = ['total' => 0.0, 'count' => 0, 'units' => []];
    }
    if (!isset($fundGroups[$fund]['units'][$unit])) {
        $fundGroups[$fund]['units'][$unit] = ['total' => 0.0, 'count' => 0, 'items' => []];
    }
    $alloc = (float)$r['total_allocation'];
    $fundGroups[$fund]['total'] += $alloc;
    $fundGroups[$fund]['count']++;
    $fundGroups[$fund]['units'][$unit]['total'] += $alloc;
    $fundGroups[$fund]['units'][$unit]['count']++;
    $fundGroups[$fund]['units'][$unit]['items'][] = $r;
}

$fundOrder = ['General Fund', 'Special Project'];
$fundMeta  = [
    'General Fund'    => ['color' => '#0b4d26', 'bg' => '#ecf8f0', 'border' => '#bfe3c7', 'icon' => 'fa-landmark',        'badge' => 'bg-emerald-600'],
    'Special Project' => ['color' => '#a16207', 'bg' => '#fff7e3', 'border' => '#efd797', 'icon' => 'fa-diagram-project', 'badge' => 'bg-amber-600'],
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
.bpt{width:100%;border-collapse:separate;border-spacing:0}
.bpt th,.bpt td{text-align:left}
.bpt-header th{background:#1a3c2a;color:#fff;padding:.7rem 1rem;font-size:.74rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;position:sticky;top:0;z-index:2}
.bpt-header th:last-child{text-align:right}
.bpt-fund td{background:#f1f5f2;padding:.65rem 1rem;font-weight:800;font-size:.85rem;text-transform:uppercase;cursor:pointer;user-select:none;border-bottom:2px solid #d5e8db;transition:background .15s}
.bpt-fund:hover td{background:#e4efe7}
.bpt-fund td:last-child{text-align:right;font-family:'Segoe UI',system-ui,sans-serif}
.bpt-fund .fund-chevron{transition:transform .25s ease;display:inline-block;font-size:.6rem;margin-right:.5rem;color:#6b7280}
.bpt-fund .fund-chevron.open{transform:rotate(90deg)}
.bpt-unit td{padding:.52rem 1rem .52rem 2.2rem;font-size:.83rem;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;cursor:pointer;user-select:none;transition:background .15s}
.bpt-unit:hover td{background:#f9fafb}
.bpt-unit td:last-child{text-align:right;color:#065f46;font-family:'Segoe UI',system-ui,sans-serif}
.bpt-unit .unit-chevron{transition:transform .25s ease;display:inline-block;font-size:.55rem;margin-right:.4rem;color:#9ca3af}
.bpt-unit .unit-chevron.open{transform:rotate(90deg)}
.bpt-item td{padding:.46rem 1rem .46rem 3.4rem;font-size:.81rem;color:#4b5563;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .15s}
.bpt-item:hover td{background:#eff6ff}
.bpt-item td:last-child{text-align:right;color:#1e40af;font-weight:600;font-family:'Segoe UI',system-ui,sans-serif}
.bpt-grand td{background:#1a3c2a;color:#fff;padding:.7rem 1rem;font-weight:800;font-size:.9rem;letter-spacing:.03em}
.bpt-grand td:last-child{text-align:right;font-family:'Segoe UI',system-ui,sans-serif}
.bpt-hidden{display:none}
.modal-backdrop-custom{position:fixed;inset:0;z-index:60;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;padding:1rem}
.modal-backdrop-custom.active{display:flex}
.modal-panel{background:#fff;border-radius:1rem;box-shadow:0 25px 50px rgba(0,0,0,.15);width:100%;max-width:640px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;transform:scale(.95) translateY(10px);opacity:0;transition:all .25s ease}
.modal-backdrop-custom.active .modal-panel{transform:scale(1) translateY(0);opacity:1}
.modal-panel.expanded{max-width:960px}
.modal-body-scroll{overflow-y:auto;flex:1}
@media(max-width:640px){.bpt-unit td{padding-left:1.4rem}.bpt-item td{padding-left:2rem}}
</style>

<div style="width:min(100%,1400px);margin:0 auto">

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div><p class="text-sm text-gray-500">Program-level totals per fund source, plus the overall grand total for <?= e($versionName) ?>.</p></div>
    <?php if (canSubmitProposals()): ?>
    <a href="create.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md whitespace-nowrap">
        <i class="fa-solid fa-plus text-xs"></i> New Proposal
    </a>
    <?php endif; ?>
</div>

<?php if (isset($dbError)): ?>
<div class="bg-white rounded-2xl shadow-lg border border-gray-100 px-6 py-16 text-center">
    <div class="mx-auto w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mb-5"><i class="fa-solid fa-triangle-exclamation text-3xl text-red-300"></i></div>
    <h2 class="text-lg font-semibold text-gray-600 mb-2">Database Connection Error</h2>
    <p class="text-sm text-gray-400">Could not retrieve budget data. Please check your database configuration.</p>
</div>
<?php elseif ($totalProposals === 0): ?>
<div class="bg-white rounded-2xl shadow-lg border border-gray-100 px-6 py-16 text-center">
    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6"><i class="fa-solid fa-folder-open text-4xl text-gray-300"></i></div>
    <h2 class="text-xl font-semibold text-gray-600 mb-2">No Budget Proposals Yet</h2>
    <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto"><?= canSubmitProposals() ? 'Create your first proposal to see the financial overview.' : 'No proposals have been submitted for this budget version yet.' ?></p>
    <?php if (canSubmitProposals()): ?>
    <a href="create.php" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md"><i class="fa-solid fa-plus text-xs"></i> Create First Proposal</a>
    <?php endif; ?>
</div>
<?php else: ?>

<!-- ═══ Fund Source Summary Cards (above pivot table) ═══ -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
<?php foreach ($fundOrder as $fundName):
    $fg = $fundGroups[$fundName] ?? null;
    if (!$fg) continue;
    $fm = $fundMeta[$fundName] ?? $fundMeta['General Fund'];
?>
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden" style="border-left:5px solid <?= $fm['color'] ?>">
        <div class="px-5 py-3.5 flex items-center justify-between" style="background:<?= $fm['bg'] ?>;border-bottom:1px solid <?= $fm['border'] ?>">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid <?= $fm['icon'] ?>" style="color:<?= $fm['color'] ?>;font-size:.85rem"></i>
                <span class="text-[11px] font-bold uppercase tracking-wider" style="color:<?= $fm['color'] ?>">Fund Source</span>
            </div>
        </div>
        <div class="px-5 py-4 flex items-center justify-between">
            <span class="text-base font-bold text-gray-800"><?= e($fundName) ?></span>
            <span class="text-lg font-bold" style="color:<?= $fm['color'] ?>">₱<?= number_format($fg['total'], 2) ?></span>
        </div>
        <div class="px-5 pb-3.5">
            <span class="text-xs text-gray-400"><?= number_format($fg['count']) ?> PPA<?= $fg['count'] !== 1 ? 's' : '' ?></span>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- ═══ BUDGET PLAN TOTALS — Pivot Table ═══ -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-3.5 border-b border-gray-200 flex items-center gap-2.5" style="background:#ecf8f0">
        <div class="w-8 h-8 rounded-md flex items-center justify-center text-white text-xs" style="background:#0b4d26"><i class="fa-solid fa-table"></i></div>
        <div>
            <span class="block text-base font-bold" style="color:#0b4d26">BUDGET PLAN TOTALS</span>
            <span class="block text-[11px] text-gray-500">Program-level totals per fund source, plus the overall grand total for <?= e($versionName) ?>.</span>
        </div>
    </div>

    <div class="overflow-x-auto">
    <table class="bpt" id="budgetPivot">
        <thead>
            <tr class="bpt-header">
                <th style="width:70%">Row Labels</th>
                <th>Sum of Total <?= e($versionName) ?> Allocation / Appropriation</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fundOrder as $fundName):
            $fg = $fundGroups[$fundName] ?? null;
            if (!$fg) continue;
            $fm = $fundMeta[$fundName] ?? $fundMeta['General Fund'];
            $fundId = ($fundName === 'General Fund') ? 'gf' : 'sp';
        ?>
            <!-- FUND ROW -->
            <tr class="bpt-fund" data-fund="<?= $fundId ?>" onclick="toggleFund('<?= $fundId ?>')">
                <td>
                    <i class="fa-solid fa-chevron-right fund-chevron" id="chevron-<?= $fundId ?>"></i>
                    <i class="fa-solid <?= $fm['icon'] ?> mr-1" style="color:<?= $fm['color'] ?>;font-size:.7rem"></i>
                    <?= e(strtoupper($fundName)) ?>
                </td>
                <td><?= number_format($fg['total'], 2) ?></td>
            </tr>

            <?php foreach ($fg['units'] as $unitName => $unitData):
                $unitSlug = 'u' . crc32($fundId . $unitName);
            ?>
            <!-- UNIT ROW -->
            <tr class="bpt-unit bpt-hidden" data-parent="<?= $fundId ?>" data-unit="<?= $unitSlug ?>" onclick="toggleUnit('<?= $unitSlug ?>')">
                <td>
                    <i class="fa-solid fa-chevron-right unit-chevron" id="chevron-<?= $unitSlug ?>"></i>
                    <?= e(strtoupper($unitName)) ?>
                </td>
                <td><?= number_format($unitData['total'], 2) ?></td>
            </tr>

                <?php foreach ($unitData['items'] as $item): ?>
            <!-- ITEM ROW -->
            <tr class="bpt-item bpt-hidden" data-parent-unit="<?= $unitSlug ?>"
                onclick='openStep1(<?= json_encode($item, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_QUOT) ?>)'>
                <td>
                    <i class="fa-solid fa-file-lines text-gray-300 mr-1" style="font-size:.65rem"></i>
                    <?= e($item['ppa_description']) ?>
                </td>
                <td><?= number_format((float)$item['total_allocation'], 2) ?></td>
            </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>

            <!-- GRAND TOTAL -->
            <tr class="bpt-grand">
                <td><i class="fa-solid fa-calculator mr-1.5" style="font-size:.7rem"></i> Grand Total</td>
                <td><?= number_format($grandTotal, 2) ?></td>
            </tr>
        </tbody>
    </table>
    </div>
</div>

<?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════
     STEP 1 MODAL — Quick Summary
     ═══════════════════════════════════════════════ -->
<div id="step1Overlay" class="modal-backdrop-custom" onclick="if(event.target===this)closeStep1()">
    <div class="modal-panel" id="step1Panel">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between shrink-0" style="background:#f0faf3">
            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide"><i class="fa-solid fa-file-lines mr-2" style="color:#0b4d26"></i>Program Summary</h3>
            <button type="button" onclick="closeStep1()" class="w-8 h-8 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body-scroll p-5 sm:p-6 space-y-4">
            <div>
                <p id="s1Name" class="text-base font-bold text-gray-800"></p>
                <p id="s1Meta" class="text-xs text-gray-500 mt-1"></p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-center">
                    <span class="block text-[10px] uppercase tracking-wider text-emerald-600 font-bold">Total Allocation</span>
                    <span id="s1Total" class="block text-xl font-bold text-emerald-800 mt-1"></span>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
                    <span class="block text-[10px] uppercase tracking-wider text-blue-600 font-bold">Annual Target</span>
                    <span id="s1Target" class="block text-xl font-bold text-blue-800 mt-1"></span>
                </div>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold mb-1">Performance Indicator</p>
                <p id="s1Indicator" class="text-sm text-gray-700"></p>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold mb-1">Justification</p>
                <p id="s1Justification" class="text-sm text-gray-700 whitespace-pre-line"></p>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-200 flex items-center justify-end gap-3 shrink-0 bg-gray-50">
            <button type="button" onclick="closeStep1()" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-100 transition">Close</button>
            <button type="button" onclick="openStep2()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white text-sm font-bold shadow-md transition" style="background:#0b4d26">
                <i class="fa-solid fa-table-list text-xs"></i> View Full Record
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     STEP 2 MODAL — Full Record with Computations
     ═══════════════════════════════════════════════ -->
<div id="step2Overlay" class="modal-backdrop-custom" onclick="if(event.target===this)closeStep2()">
    <div class="modal-panel expanded" id="step2Panel">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between shrink-0" style="background:#eff6ff">
            <div class="flex items-center gap-2">
                <button type="button" onclick="backToStep1()" class="w-8 h-8 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition flex items-center justify-center" title="Back to Summary"><i class="fa-solid fa-arrow-left text-xs"></i></button>
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide"><i class="fa-solid fa-table-list mr-2 text-blue-600"></i>Full Budget Record</h3>
            </div>
            <button type="button" onclick="closeStep2()" class="w-8 h-8 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body-scroll p-5 sm:p-6 space-y-5">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <p id="s2Name" class="text-base font-bold text-gray-800"></p>
                    <p id="s2Meta" class="text-xs text-gray-500 mt-1"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span id="s2ExpBadge" class="inline-block text-[10px] font-bold px-2.5 py-1 rounded-full"></span>
                    <span id="s2FundBadge" class="inline-block bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2.5 py-1 rounded-full"></span>
                </div>
            </div>

            <!-- Indicator + Justification -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold mb-1"><i class="fa-solid fa-gauge-high mr-1"></i>Performance Indicator</p>
                    <p id="s2Indicator" class="text-sm text-gray-700"></p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold mb-1"><i class="fa-solid fa-file-pen mr-1"></i>Justification</p>
                    <p id="s2Justification" class="text-sm text-gray-700 whitespace-pre-line"></p>
                </div>
            </div>

            <!-- Quarterly Targets -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-3 py-2 bg-emerald-50 border-b border-emerald-200 text-xs font-bold text-emerald-700 uppercase tracking-wider"><i class="fa-solid fa-bullseye mr-1"></i>Quarterly Physical Targets</div>
                <div class="grid grid-cols-5 text-center text-sm divide-x divide-gray-100">
                    <div class="p-3"><span class="block text-[10px] text-gray-400 mb-1">Q1</span><span id="s2Q1" class="font-bold text-gray-700"></span></div>
                    <div class="p-3"><span class="block text-[10px] text-gray-400 mb-1">Q2</span><span id="s2Q2" class="font-bold text-gray-700"></span></div>
                    <div class="p-3"><span class="block text-[10px] text-gray-400 mb-1">Q3</span><span id="s2Q3" class="font-bold text-gray-700"></span></div>
                    <div class="p-3"><span class="block text-[10px] text-gray-400 mb-1">Q4</span><span id="s2Q4" class="font-bold text-gray-700"></span></div>
                    <div class="p-3 bg-emerald-50"><span class="block text-[10px] text-emerald-600 mb-1">Total</span><span id="s2TargetTotal" class="font-bold text-emerald-700"></span></div>
                </div>
            </div>

            <!-- Monthly Allocation -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-3 py-2 bg-blue-50 border-b border-blue-200 text-xs font-bold text-blue-700 uppercase tracking-wider"><i class="fa-solid fa-peso-sign mr-1"></i>Monthly Financial Allocation</div>
                <div class="overflow-x-auto">
                <table class="w-full text-xs" style="min-width:780px">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 uppercase text-center">
                            <th class="p-2 border-r border-gray-100 font-bold">Jan</th><th class="p-2 border-r border-gray-100 font-bold">Feb</th><th class="p-2 border-r border-gray-100 font-bold">Mar</th>
                            <th class="p-2 border-r border-gray-100 font-bold">Apr</th><th class="p-2 border-r border-gray-100 font-bold">May</th><th class="p-2 border-r border-gray-100 font-bold">Jun</th>
                            <th class="p-2 border-r border-gray-100 font-bold">Jul</th><th class="p-2 border-r border-gray-100 font-bold">Aug</th><th class="p-2 border-r border-gray-100 font-bold">Sep</th>
                            <th class="p-2 border-r border-gray-100 font-bold">Oct</th><th class="p-2 border-r border-gray-100 font-bold">Nov</th><th class="p-2 border-r border-gray-100 font-bold">Dec</th>
                            <th class="p-2 font-bold bg-blue-50 text-blue-700">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="text-center text-gray-700 font-semibold">
                            <td id="s2Jan" class="p-2 border-r border-gray-100"></td><td id="s2Feb" class="p-2 border-r border-gray-100"></td><td id="s2Mar" class="p-2 border-r border-gray-100"></td>
                            <td id="s2Apr" class="p-2 border-r border-gray-100"></td><td id="s2May" class="p-2 border-r border-gray-100"></td><td id="s2Jun" class="p-2 border-r border-gray-100"></td>
                            <td id="s2Jul" class="p-2 border-r border-gray-100"></td><td id="s2Aug" class="p-2 border-r border-gray-100"></td><td id="s2Sep" class="p-2 border-r border-gray-100"></td>
                            <td id="s2Oct" class="p-2 border-r border-gray-100"></td><td id="s2Nov" class="p-2 border-r border-gray-100"></td><td id="s2Dec" class="p-2 border-r border-gray-100"></td>
                            <td id="s2TotalAlloc" class="p-2 bg-blue-50 text-blue-700 font-bold"></td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Record meta -->
            <div class="flex items-center justify-between text-[11px] text-gray-400 pt-2 border-t border-gray-100">
                <span id="s2RecordId"></span>
                <span id="s2CreatedAt"></span>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-200 flex items-center justify-end gap-3 shrink-0 bg-gray-50">
            <button type="button" onclick="backToStep1()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-100 transition">
                <i class="fa-solid fa-arrow-left text-xs"></i> Back to Summary
            </button>
            <button type="button" onclick="closeStep2()" class="px-5 py-2.5 rounded-lg text-white text-sm font-bold shadow-md transition" style="background:#0b4d26">
                <i class="fa-solid fa-check mr-1"></i> Done
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
(function() {
    'use strict';

    // ══════════════════════════════════════════════
    // PIVOT TABLE: Collapsible fund → unit → item
    // ══════════════════════════════════════════════

    var fundOpen = {};
    var unitOpen = {};

    window.toggleFund = function(fundId) {
        var isOpen = !!fundOpen[fundId];
        fundOpen[fundId] = !isOpen;

        var chevron = document.getElementById('chevron-' + fundId);
        if (chevron) chevron.classList.toggle('open', !isOpen);

        document.querySelectorAll('[data-parent="' + fundId + '"]').forEach(function(row) {
            if (!isOpen) {
                row.classList.remove('bpt-hidden');
            } else {
                row.classList.add('bpt-hidden');
                var uid = row.dataset.unit;
                if (uid && unitOpen[uid]) {
                    unitOpen[uid] = false;
                    var uc = document.getElementById('chevron-' + uid);
                    if (uc) uc.classList.remove('open');
                    document.querySelectorAll('[data-parent-unit="' + uid + '"]').forEach(function(ir) {
                        ir.classList.add('bpt-hidden');
                    });
                }
            }
        });
    };

    window.toggleUnit = function(unitId) {
        event.stopPropagation();
        var isOpen = !!unitOpen[unitId];
        unitOpen[unitId] = !isOpen;

        var chevron = document.getElementById('chevron-' + unitId);
        if (chevron) chevron.classList.toggle('open', !isOpen);

        document.querySelectorAll('[data-parent-unit="' + unitId + '"]').forEach(function(row) {
            row.classList.toggle('bpt-hidden', isOpen);
        });
    };

    // ══════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════

    var currentData = null;
    function num(v) { return Number(v || 0); }
    function money(v) { return '₱ ' + num(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function intFmt(v) { return num(v).toLocaleString('en-PH'); }

    var expColors = {
        'MOOE': { bg: 'bg-blue-100',   text: 'text-blue-800' },
        'CO':   { bg: 'bg-orange-100', text: 'text-orange-800' },
        'PS':   { bg: 'bg-purple-100', text: 'text-purple-800' }
    };

    // ══════════════════════════════════════════════
    // STEP 1: Summary Modal
    // ══════════════════════════════════════════════

    var s1Overlay = document.getElementById('step1Overlay');
    var s1Panel   = document.getElementById('step1Panel');

    window.openStep1 = function(data) {
        event.stopPropagation();
        currentData = data;

        document.getElementById('s1Name').textContent = data.ppa_description || '';
        document.getElementById('s1Meta').textContent = [data.unit_name, data.fund_name, data.account_code + ' — ' + data.account_title, data.expense_class].filter(Boolean).join(' · ');
        document.getElementById('s1Total').textContent = money(data.total_allocation);
        document.getElementById('s1Target').textContent = intFmt(data.target_total);
        document.getElementById('s1Indicator').textContent = data.indicator_description || '—';
        document.getElementById('s1Justification').textContent = data.justification || '—';

        s1Overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeStep1 = function() {
        s1Overlay.classList.remove('active');
        document.body.style.overflow = '';
    };

    // ══════════════════════════════════════════════
    // STEP 2: Full Record Modal
    // ══════════════════════════════════════════════

    var s2Overlay = document.getElementById('step2Overlay');

    window.openStep2 = function() {
        if (!currentData) return;
        var d = currentData;

        closeStep1();

        setTimeout(function() {
            document.getElementById('s2Name').textContent = d.ppa_description || '';
            document.getElementById('s2Meta').textContent = [d.unit_name, d.account_code + ' — ' + d.account_title].filter(Boolean).join(' · ');

            var ec = expColors[d.expense_class] || expColors['MOOE'];
            var badge = document.getElementById('s2ExpBadge');
            badge.textContent = d.expense_class || '';
            badge.className = 'inline-block text-[10px] font-bold px-2.5 py-1 rounded-full ' + ec.bg + ' ' + ec.text;

            var fb = document.getElementById('s2FundBadge');
            fb.textContent = d.fund_name || '';

            document.getElementById('s2Indicator').textContent = d.indicator_description || '—';
            document.getElementById('s2Justification').textContent = d.justification || '—';

            document.getElementById('s2Q1').textContent = intFmt(d.q1_target);
            document.getElementById('s2Q2').textContent = intFmt(d.q2_target);
            document.getElementById('s2Q3').textContent = intFmt(d.q3_target);
            document.getElementById('s2Q4').textContent = intFmt(d.q4_target);
            document.getElementById('s2TargetTotal').textContent = intFmt(d.target_total);

            var months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
            months.forEach(function(m) {
                document.getElementById('s2' + m.charAt(0).toUpperCase() + m.slice(1)).textContent = money(d[m + '_amt']);
            });
            document.getElementById('s2TotalAlloc').textContent = money(d.total_allocation);

            document.getElementById('s2RecordId').textContent = 'Record #' + (d.id || '—');
            document.getElementById('s2CreatedAt').textContent = d.created_at ? 'Created: ' + d.created_at : '';

            s2Overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }, 200);
    };

    window.closeStep2 = function() {
        s2Overlay.classList.remove('active');
        document.body.style.overflow = '';
        currentData = null;
    };

    window.backToStep1 = function() {
        s2Overlay.classList.remove('active');
        if (currentData) {
            setTimeout(function() { openStep1(currentData); }, 150);
        }
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (s2Overlay.classList.contains('active')) closeStep2();
            else if (s1Overlay.classList.contains('active')) closeStep1();
        }
    });

})();
</script>
