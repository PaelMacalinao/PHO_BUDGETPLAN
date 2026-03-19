<?php
/**
 * PHO Budgeting System — Dashboard (High-Level Financial Overview)
 *
 * Level 1: Fund Source tabs  (General Fund | Special Project)
 * Level 2: Expense-class breakdown (PS / MOOE / CO)
 *          For Special Project: grouped first by project, then by class.
 */
require_once __DIR__ . '/config.php';
requireLogin();

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';

// ── OPTIMIZED AGGREGATION QUERIES ────────────────
try {
    $pdo = getConnection();

    $rows = $pdo->query("
        SELECT bp.id, bp.ppa_description, bp.target_total, bp.total_allocation, bp.created_at,
               pu.program_name,
               ac.account_code, ac.account_title, ac.expense_class,
               fs.fund_name,
               un.unit_name
        FROM   tbl_budget_proposals bp
        JOIN   tbl_programs_units pu ON bp.program_id     = pu.id
        JOIN   tbl_account_codes ac  ON bp.account_id     = ac.id
        JOIN   tbl_fund_sources  fs  ON bp.fund_source_id = fs.id
        JOIN   tbl_units         un  ON bp.unit_id        = un.id
        ORDER BY bp.created_at DESC
    ")->fetchAll();

    $programs    = $pdo->query("SELECT DISTINCT program_name FROM tbl_programs_units ORDER BY program_name")->fetchAll(PDO::FETCH_COLUMN);
    $fundSources = $pdo->query("SELECT DISTINCT fund_name FROM tbl_fund_sources ORDER BY fund_name")->fetchAll(PDO::FETCH_COLUMN);
    $unitNames   = $pdo->query("SELECT DISTINCT unit_name FROM tbl_units ORDER BY unit_name")->fetchAll(PDO::FETCH_COLUMN);
    $expClasses  = ['MOOE', 'CO', 'PS'];
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    $rows = [];
    $programs = $fundSources = $unitNames = $expClasses = [];
    $dbError = true;
}

$totalProposals  = count($rows);
$totalTargets    = array_sum(array_column($rows, 'target_total'));
$totalAllocation = array_sum(array_column($rows, 'total_allocation'));
$uniquePrograms  = count(array_unique(array_column($rows, 'program_name')));
$uniqueUnits     = count($unitNames);

// ── OUTSIDE THE LOOP: Header / Sidebar / Navbar ─
require_once __DIR__ . '/includes/header.php';
?>

<!-- ═══ Page-specific styles ═══ -->
<style>
.fund-tab{position:relative;background:#fff;border:2px solid #e5e7eb;border-radius:1rem;padding:1.25rem 1.5rem;cursor:pointer;transition:all .3s ease;text-align:left;width:100%;outline:none}
.fund-tab:hover{border-color:#d1d5db;box-shadow:0 4px 12px rgba(0,0,0,.06)}
.fund-tab.active{border-color:#0b4d26;box-shadow:0 4px 20px rgba(11,77,38,.12);background:linear-gradient(135deg,#f0faf3 0%,#fff 100%)}
.fund-tab.active::after{content:'';position:absolute;top:-2px;left:-2px;right:-2px;height:4px;background:#0b4d26;border-radius:1rem 1rem 0 0}
.fund-view{animation:viewFadeIn .35s ease}
@keyframes viewFadeIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.exp-card{transition:transform .2s ease,box-shadow .2s ease}
.exp-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.08)}
</style>

<!-- ═══ Page header row ═══ -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
        <p class="text-sm text-gray-500">High-Level Financial Overview &mdash; FY 2026</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
        <div class="bg-brand-100 text-brand-600 w-11 h-11 rounded-lg flex items-center justify-center"><i class="fa-solid fa-bullseye text-lg"></i></div>
        <div>
            <span class="block text-2xl font-bold text-gray-800"><?= number_format($totalTargets) ?></span>
            <span class="block text-xs text-gray-400">Total Targets</span>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
        <div class="bg-accent-50 text-accent-500 w-11 h-11 rounded-lg flex items-center justify-center"><i class="fa-solid fa-peso-sign text-lg"></i></div>
        <div>
            <span class="peso-amount text-2xl font-bold text-gray-800"><span class="peso-sign">₱</span> <?= number_format($totalAllocation, 2) ?></span>
            <span class="block text-xs text-gray-400">Total Allocation</span>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
        <div class="bg-accent-50 text-accent-600 w-11 h-11 rounded-lg flex items-center justify-center"><i class="fa-solid fa-sitemap text-lg"></i></div>
        <div>
            <span class="block text-2xl font-bold text-gray-800"><?= number_format($uniquePrograms) ?></span>
            <span class="block text-xs text-gray-400">Active Programs</span>
        </div>
        <div class="flex items-center gap-8">
            <div class="text-center">
                <span class="block text-2xl font-bold text-gray-800"><?= number_format($totalProposals) ?></span>
                <span class="block text-xs text-gray-400">Total Proposals</span>
            </div>
            <div class="text-center">
                <span class="block text-2xl font-bold text-gray-800">2</span>
                <span class="block text-xs text-gray-400">Fund Sources</span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     LEVEL 1 — FUND SOURCE TABS (Clickable Cards)
     ═══════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">

    <!-- Filter Bar -->
    <div class="px-6 pt-6 pb-2 flex flex-col lg:flex-row lg:items-end gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:flex lg:items-end gap-4 flex-1">
            <div>
                <label for="filterProgram" class="block text-xs font-medium text-gray-500 mb-1">Program (PPA)</label>
                <select id="filterProgram" class="w-full sm:w-64 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filterUnit" class="block text-xs font-medium text-gray-500 mb-1">Unit</label>
                <select id="filterUnit" class="w-full sm:w-52 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">All Units</option>
                    <?php foreach ($unitNames as $u): ?><option value="<?= e($u) ?>"><?= e($u) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </button>

    <!-- Special Project Tab -->
    <button type="button" id="tab-sp" class="fund-tab" onclick="switchFund('sp')">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-diagram-project text-xl"></i>
            </div>
            <div class="min-w-0">
                <span class="block text-base font-bold text-gray-800">Special Project / Locally Funded</span>
                <span class="peso-amount text-xl font-bold text-amber-700"><span class="peso-sign">₱</span> <?= number_format($spTotal, 2) ?></span>
                <span class="block text-xs text-gray-400 mt-0.5"><?= number_format($spCount) ?> proposal<?= $spCount !== 1 ? 's' : '' ?></span>
            </div>
        </div>
    </button>

</div>

<!-- ═══════════════════════════════════════════════════
     LEVEL 2 — GENERAL FUND: Expense Class Breakdown
     ═══════════════════════════════════════════════════ -->
<div id="view-gf" class="fund-view">

    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">
        <i class="fa-solid fa-landmark mr-1.5 text-emerald-500"></i> General Fund &mdash; Expense Class Breakdown
    </h3>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <?php foreach (['PS', 'MOOE', 'CO'] as $cls):
            $ci    = $classInfo[$cls];
            $total = $gfClasses[$cls]['total'];
            $count = $gfClasses[$cls]['count'];
            $pct   = $gfTotal > 0 ? round(($total / $gfTotal) * 100, 1) : 0;
        ?>
        <div class="exp-card bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="border-l-4 <?= $ci['border'] ?> p-5 sm:p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="<?= $ci['iconBg'] ?> <?= $ci['iconText'] ?> w-11 h-11 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid <?= $ci['icon'] ?> text-lg"></i>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-gray-800"><?= $cls ?></span>
                        <span class="block text-[11px] text-gray-400 leading-tight"><?= e($ci['label']) ?></span>
                    </div>
                </div>
                <div class="mb-4">
                    <span class="peso-amount text-2xl sm:text-3xl font-bold <?= $ci['text'] ?>"><span class="peso-sign">₱</span> <?= number_format($total, 2) ?></span>
                    <span class="block text-xs text-gray-400 mt-1"><?= number_format($count) ?> proposal<?= $count !== 1 ? 's' : '' ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-1 h-2.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full <?= $ci['barBg'] ?> rounded-full transition-all duration-700 ease-out" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="text-xs font-bold text-gray-500 w-12 text-right"><?= $pct ?>%</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($rows)): ?>
    <!-- Data Table (single instance) -->
    <div class="px-6 pb-6 pt-2 overflow-x-auto">
        <table id="proposalsTable" class="w-full text-left" style="min-width:1000px">
            <thead class="bg-custom-green text-white" style="background:#0b4d26">
                <tr>
                    <th class="py-3 px-2 text-white">ID</th>
                    <th class="py-3 px-2 text-white">PPA Description</th>
                    <th class="py-3 px-2 text-white">Program</th>
                    <th class="py-3 px-2 text-white">Unit</th>
                    <th class="py-3 px-2 text-white">Expense Class</th>
                    <th class="py-3 px-2 text-white">Fund Source</th>
                    <th class="py-3 px-2 text-right text-white">Target</th>
                    <th class="py-3 px-2 text-right text-white">Allocation</th>
                    <th class="py-3 px-2 text-center text-white">Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- ========================================
                     THE LOOP — Only table rows repeat here
                     ======================================== -->
                <?php foreach ($rows as $r): ?>
                <tr class="border-b border-gray-50">
                    <td class="py-3 px-2 font-mono text-xs text-gray-400">#<?= (int)$r['id'] ?></td>
                    <td class="py-3 px-2 font-medium text-gray-800 max-w-xs truncate"><?= e($r['ppa_description']) ?></td>
                    <td class="py-3 px-2"><span class="inline-block bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1 rounded-full"><?= e($r['program_name']) ?></span></td>
                    <td class="py-3 px-2"><span class="inline-block bg-violet-50 text-violet-700 text-xs font-medium px-2.5 py-1 rounded-full"><?= e($r['unit_name']) ?></span></td>
                    <td class="py-3 px-2"><span class="inline-block bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1 rounded-full"><?= e($r['expense_class']) ?></span></td>
                    <td class="py-3 px-2"><span class="inline-block bg-emerald-50 text-emerald-700 text-xs font-medium px-2.5 py-1 rounded-full"><?= e($r['fund_name']) ?></span></td>
                    <td class="py-3 px-2 text-right font-semibold text-gray-700"><?= number_format((int)$r['target_total']) ?></td>
                    <td class="py-3 px-2 text-right font-semibold text-emerald-700"><?= peso((float)$r['total_allocation']) ?></td>
                    <td class="py-3 px-2 text-center whitespace-nowrap">
                        <a href="view.php?id=<?= (int)$r['id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-brand-50 text-brand-700 text-xs font-medium hover:bg-brand-100 transition" title="View"><i class="fa-solid fa-eye"></i></a>
                        <a href="edit.php?id=<?= (int)$r['id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium hover:bg-amber-100 transition" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                        <button onclick="deleteProposal(<?= (int)$r['id'] ?>)" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-red-50 text-red-600 text-xs font-medium hover:bg-red-100 transition" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- SP Summary Footer -->
    <div class="mt-5 bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
        <span class="text-sm text-amber-700 font-semibold"><i class="fa-solid fa-calculator mr-1.5"></i> Special Project Total</span>
        <span class="peso-amount text-xl font-bold text-amber-800"><span class="peso-sign">₱</span> <?= number_format($spTotal, 2) ?></span>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- ═══════════════════════════════════════════════════
     JAVASCRIPT — Fund Source Tab Toggle
     ═══════════════════════════════════════════════════ -->
<script>
(function() {
    'use strict';
    if (!document.getElementById('proposalsTable')) return;
    if ($.fn.DataTable.isDataTable('#proposalsTable')) return;

    var table = $('#proposalsTable').DataTable({
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        order: [],
        language: {
            search: '', searchPlaceholder: 'Search proposals\u2026',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_\u2013_END_ of _TOTAL_',
            emptyTable: 'No matching proposals found.'
        },
        columnDefs: [
            { orderable: false, targets: [8] },
            { className: 'whitespace-nowrap', targets: '_all' }
        ]
    });

    function applyFilters() {
        var esc = $.fn.dataTable.util.escapeRegex;
        table.column(2).search($('#filterProgram').val() ? '^' + esc($('#filterProgram').val()) + '$' : '', true, false);
        table.column(3).search($('#filterUnit').val()    ? '^' + esc($('#filterUnit').val())    + '$' : '', true, false);
        table.column(4).search($('#filterExpense').val() ? '^' + esc($('#filterExpense').val()) + '$' : '', true, false);
        table.column(5).search($('#filterFund').val()    ? '^' + esc($('#filterFund').val())    + '$' : '', true, false);
        table.draw();
    }

    $('#filterProgram, #filterUnit, #filterFund, #filterExpense').on('change', applyFilters);
    $('#btnReset').on('click', function() {
        $('#filterProgram, #filterUnit, #filterFund, #filterExpense').val('');
        table.search('').columns().search('').draw();
    });

    window.deleteProposal = function(id) {
        Swal.fire({
            title: 'Delete Proposal #' + id + '?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            reverseButtons: true
        }).then(function(r) {
            if (!r.isConfirmed) return;
            fetch('delete_proposal.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + encodeURIComponent(id)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'success') {
                    Swal.fire({icon:'success', title:'Deleted!', text: data.message, confirmButtonColor:'#0b4d26'}).then(function() { location.reload(); });
                } else {
                    Swal.fire({icon:'error', title:'Error', text: data.message, confirmButtonColor:'#ef4444'});
                }
            })
            .catch(function() { Swal.fire({icon:'error', title:'Network Error', text:'Could not reach the server.'}); });
        });
    };
})();
</script>
