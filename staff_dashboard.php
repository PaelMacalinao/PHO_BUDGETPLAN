<?php
/**
 * PHO Budgeting System — Staff Dashboard
 * Same layout as the main dashboard but with View-only actions.
 */
require_once __DIR__ . '/config.php';
requireLogin();

if (canViewAllData()) {
    header('Location: index.php');
    exit;
}

$pageTitle  = 'My Submissions';
$activeMenu = 'dashboard';

$versionId = getSelectedVersionId();
try {
    $pdo = getConnection();
    $access = buildProposalAccessFilter('bp');
    $stmtRows = $pdo->prepare("
        SELECT bp.id, bp.ppa_description, bp.target_total, bp.total_allocation, bp.created_at,
               ac.account_code, ac.account_title, ac.expense_class,
               fs.fund_name,
               un.unit_name
        FROM   tbl_budget_proposals bp
        JOIN   tbl_account_codes ac  ON bp.account_id     = ac.id
        JOIN   tbl_fund_sources  fs  ON bp.fund_source_id = fs.id
        JOIN   tbl_units         un  ON bp.unit_id        = un.id
        WHERE  bp.version_id = :vid{$access['sql']}
        ORDER BY bp.created_at DESC
    ");
    $stmtRows->execute([':vid' => $versionId] + $access['params']);
    $rows = $stmtRows->fetchAll();

    $fundSources = array_values(array_unique(array_map(static fn($row) => $row['fund_name'], $rows)));
    sort($fundSources);
    $unitNames   = array_values(array_unique(array_map(static fn($row) => $row['unit_name'], $rows)));
    sort($unitNames);
    $expClasses  = ['MOOE', 'CO', 'PS'];
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    $rows = [];
    $fundSources = $unitNames = $expClasses = [];
    $dbError = true;
}

$totalProposals  = count($rows);
$totalTargets    = array_sum(array_column($rows, 'target_total'));
$totalAllocation = array_sum(array_column($rows, 'total_allocation'));
$uniqueUnits     = count($unitNames);

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">My Submitted Proposals</h2>
        <p class="text-sm text-gray-500 mt-1">Review the forms you submitted for <?= e(getSelectedVersionName()) ?>. Only your own submissions are shown here.</p>
    </div>
    <a href="create.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md whitespace-nowrap">
        <i class="fa-solid fa-plus text-xs"></i> New Proposal
    </a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
        <div class="bg-brand-100 text-brand-600 w-11 h-11 rounded-lg flex items-center justify-center"><i class="fa-solid fa-file-lines text-lg"></i></div>
        <div>
            <span class="block text-2xl font-bold text-gray-800"><?= number_format($totalProposals) ?></span>
            <span class="block text-xs text-gray-400">Total Proposals</span>
        </div>
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
        <div class="bg-accent-50 text-accent-600 w-11 h-11 rounded-lg flex items-center justify-center"><i class="fa-solid fa-coins text-lg"></i></div>
        <div>
            <span class="block text-2xl font-bold text-gray-800"><?= count($fundSources) ?></span>
            <span class="block text-xs text-gray-400">Fund Sources</span>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
        <div class="bg-brand-100 text-brand-700 w-11 h-11 rounded-lg flex items-center justify-center"><i class="fa-solid fa-building text-lg"></i></div>
        <div>
            <span class="block text-2xl font-bold text-gray-800"><?= number_format($uniqueUnits) ?></span>
            <span class="block text-xs text-gray-400">Active Units</span>
        </div>
    </div>
</div>

<!-- Table Card -->
<div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">

    <!-- Filter Bar -->
    <div class="px-6 pt-6 pb-2 flex flex-col lg:flex-row lg:items-end gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:flex lg:items-end gap-4 flex-1">
            <div>
                <label for="filterUnit" class="block text-xs font-medium text-gray-500 mb-1">Unit</label>
                <select id="filterUnit" class="w-full sm:w-52 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">All Units</option>
                    <?php foreach ($unitNames as $u): ?><option value="<?= e($u) ?>"><?= e($u) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filterFund" class="block text-xs font-medium text-gray-500 mb-1">Fund Source</label>
                <select id="filterFund" class="w-full sm:w-52 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">All Funds</option>
                    <?php foreach ($fundSources as $f): ?><option value="<?= e($f) ?>"><?= e($f) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filterExpense" class="block text-xs font-medium text-gray-500 mb-1">Expense Class</label>
                <select id="filterExpense" class="w-full sm:w-40 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">All</option>
                    <?php foreach ($expClasses as $ec): ?><option value="<?= e($ec) ?>"><?= e($ec) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="flex items-end gap-2 lg:ml-auto">
            <button id="btnReset" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand-600 transition px-3 py-2"><i class="fa-solid fa-rotate-left text-xs"></i> Reset</button>
        </div>
    </div>

    <?php if (!empty($rows)): ?>
    <div class="px-6 pb-6 pt-2 overflow-x-auto">
        <table id="proposalsTable" class="w-full text-left" style="min-width:900px">
            <thead class="bg-custom-green text-white" style="background:#0b4d26">
                <tr>
                    <th class="py-3 px-2 text-white">ID</th>
                    <th class="py-3 px-2 text-white">PPA Description</th>
                    <th class="py-3 px-2 text-white">Unit</th>
                    <th class="py-3 px-2 text-white">Expense Class</th>
                    <th class="py-3 px-2 text-white">Fund Source</th>
                    <th class="py-3 px-2 text-white">Submitted</th>
                    <th class="py-3 px-2 text-right text-white">Target</th>
                    <th class="py-3 px-2 text-right text-white">Allocation</th>
                    <th class="py-3 px-2 text-center text-white">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr class="border-b border-gray-50">
                    <td class="py-3 px-2 font-mono text-xs text-gray-400">#<?= (int)$r['id'] ?></td>
                    <td class="py-3 px-2 font-medium text-gray-800 max-w-xs truncate"><?= e($r['ppa_description']) ?></td>
                    <td class="py-3 px-2"><span class="inline-block bg-violet-50 text-violet-700 text-xs font-medium px-2.5 py-1 rounded-full"><?= e($r['unit_name']) ?></span></td>
                    <td class="py-3 px-2"><span class="inline-block bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1 rounded-full"><?= e($r['expense_class']) ?></span></td>
                    <td class="py-3 px-2"><span class="inline-block bg-emerald-50 text-emerald-700 text-xs font-medium px-2.5 py-1 rounded-full"><?= e($r['fund_name']) ?></span></td>
                    <td class="py-3 px-2 text-sm text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                    <td class="py-3 px-2 text-right font-semibold text-gray-700"><?= number_format((int)$r['target_total']) ?></td>
                    <td class="py-3 px-2 text-right font-semibold text-emerald-700"><?= peso((float)$r['total_allocation']) ?></td>
                    <td class="py-3 px-2 text-center whitespace-nowrap">
                        <a href="view.php?id=<?= (int)$r['id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-brand-50 text-brand-700 text-xs font-medium hover:bg-brand-100 transition" title="View"><i class="fa-solid fa-eye"></i> View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <div class="px-6 py-20 text-center">
        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6"><i class="fa-solid fa-folder-open text-4xl text-gray-300"></i></div>
        <h2 class="text-xl font-semibold text-gray-600 mb-2">No Submitted Proposals Yet</h2>
        <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto">Your review dashboard will show the forms you submit here. Start by creating your first proposal.</p>
        <a href="create.php" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md"><i class="fa-solid fa-plus text-xs"></i> Create First Proposal</a>
        <?php if (isset($dbError)): ?><p class="mt-4 text-xs text-red-400"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Could not connect to the database.</p><?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

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
        table.column(2).search($('#filterUnit').val()    ? '^' + esc($('#filterUnit').val())    + '$' : '', true, false);
        table.column(3).search($('#filterExpense').val() ? '^' + esc($('#filterExpense').val()) + '$' : '', true, false);
        table.column(4).search($('#filterFund').val()    ? '^' + esc($('#filterFund').val())    + '$' : '', true, false);
        table.draw();
    }

    $('#filterUnit, #filterFund, #filterExpense').on('change', applyFilters);
    $('#btnReset').on('click', function() {
        $('#filterUnit, #filterFund, #filterExpense').val('');
        table.search('').columns().search('').draw();
    });
})();
</script>
