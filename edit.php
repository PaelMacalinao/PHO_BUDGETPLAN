<?php
/**
 * PHO Budgeting System - Edit Budget Proposal
 * Same wizard layout as create.php but pre-populated with existing data.
 */
require_once __DIR__ . '/config.php';
requireLogin();

if (!isAdmin() && !canSubmitProposals()) {
    header('Location: staff_dashboard.php');
    exit;
}

$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$row = null;

try {
    $pdo = getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');

        $editId = (int)($_POST['id'] ?? 0);
        if ($editId < 1 || !canAccessProposal($pdo, $editId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'You are not authorized to edit this proposal.']);
            exit;
        }

        $data = [];

        foreach (['ppa_description', 'justification'] as $f) {
            $data[$f] = trim($_POST[$f] ?? '');
            if ($data[$f] === '') {
                throw new InvalidArgumentException("'{$f}' is required.");
            }
        }

        foreach (['account_id', 'fund_source_id', 'unit_id'] as $f) {
            $data[$f] = (int)($_POST[$f] ?? 0);
            if ($data[$f] < 1) {
                throw new InvalidArgumentException("Please select a valid option for '{$f}'.");
            }
        }
        $data['indicator_id'] = resolveIndicatorId($pdo, trim($_POST['indicator_text'] ?? ''));

        $quarters = ['q1_target', 'q2_target', 'q3_target', 'q4_target'];
        $tt = 0;
        foreach ($quarters as $q) {
            $data[$q] = max(0, (int)($_POST[$q] ?? 0));
            $tt += $data[$q];
        }
        $data['target_total'] = $tt;

        $months = ['jan_amt', 'feb_amt', 'mar_amt', 'apr_amt', 'may_amt', 'jun_amt', 'jul_amt', 'aug_amt', 'sep_amt', 'oct_amt', 'nov_amt', 'dec_amt'];
        $at = 0;
        foreach ($months as $m) {
            $data[$m] = max(0, round((float)($_POST[$m] ?? 0), 2));
            $at += $data[$m];
        }
        $submittedTotal = round((float)($_POST['total_allocation'] ?? 0), 2);
        $data['total_allocation'] = $submittedTotal > 0 ? $submittedTotal : round($at, 2);

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
        foreach ($data as $k => $v) {
            $params[":$k"] = $v;
        }
        $stmt->execute($params);

        echo json_encode(['status' => 'success', 'message' => 'Proposal updated!', 'id' => $editId]);
        exit;
    }

    if (!$id || !canAccessProposal($pdo, $id)) {
        header('Location: ' . (canViewAllData() ? 'index.php' : 'staff_dashboard.php'));
        exit;
    }
    $stmt = $pdo->prepare("SELECT bp.*, ind.indicator_description FROM tbl_budget_proposals bp LEFT JOIN tbl_indicators ind ON bp.indicator_id = ind.id WHERE bp.id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: ' . (canViewAllData() ? 'index.php' : 'staff_dashboard.php'));
        exit;
    }

    $accounts = $pdo->query("SELECT id, account_code, account_title, expense_class FROM tbl_account_codes ORDER BY account_code")->fetchAll();
    $fundSrcs = $pdo->query("SELECT id, fund_name FROM tbl_fund_sources ORDER BY fund_name")->fetchAll();
    $units = $pdo->query("SELECT id, unit_name, fund_source_id FROM tbl_units ORDER BY unit_name")->fetchAll();
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    $row = null;
}

$unitsByFund = [];
if (!empty($units)) {
    foreach ($units as $u) {
        $fsid = (int)$u['fund_source_id'];
        $unitsByFund[$fsid][] = ['id' => (int)$u['id'], 'name' => $u['unit_name']];
    }
}

$accountsByExpense = [];
$selectedExpenseClass = '';
foreach ($accounts as $a) {
    $expense = trim((string)$a['expense_class']);
    $accountsByExpense[$expense][] = [
        'id' => (int)$a['id'],
        'code' => $a['account_code'],
        'title' => $a['account_title'],
    ];
    if ((int)$a['id'] === (int)$row['account_id']) {
        $selectedExpenseClass = $expense;
    }
}

$pageTitle  = 'Edit Proposal #' . (int)$id;
$activeMenu = 'dashboard';
require_once __DIR__ . '/includes/header.php';

if (!$row) {
    echo '<div class="text-center py-20"><h2 class="text-xl font-semibold text-gray-600">Proposal Not Found</h2><a href="index.php" class="mt-4 inline-block text-brand-600 hover:underline">Back to Dashboard</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div class="mb-8 text-center">
    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800">Edit Budget Proposal <span class="text-brand-600">#<?= (int)$row['id'] ?></span></h2>
    <p class="mt-1 text-sm text-gray-500">Modify the details below and save your changes.</p>
</div>

<div class="flex items-center justify-center mb-10 select-none" id="stepIndicator"></div>

<form id="budgetForm" novalidate autocomplete="off" class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

    <div class="tab-panel active p-6 sm:p-10" data-step="1">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-6">
            <span class="bg-brand-100 text-brand-700 w-8 h-8 rounded-lg flex items-center justify-center text-sm"><i class="fa-solid fa-folder-open"></i></span>
            Program &amp; Account Details
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">PPA Description <span class="text-red-500">*</span></label>
                <textarea name="ppa_description" rows="2" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition resize-none"><?= e($row['ppa_description']) ?></textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Fund Source <span class="text-red-500">*</span>
                    <span id="fundSourceWarning" class="ml-2 text-amber-600 text-xs font-normal" style="display:none"><i class="fa-solid fa-triangle-exclamation"></i> Step 1: Select Fund Source to proceed</span>
                </label>
                <select name="fund_source_id" id="fundSourceSelect" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">Select Fund Source</option>
                    <?php foreach ($fundSrcs as $f): ?>
                        <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === (int)$row['fund_source_id'] ? 'selected' : '' ?>><?= e($f['fund_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Unit <span class="text-red-500">*</span></label>
                <select name="unit_id" id="unitSelect" required class="lockable w-full border border-gray-300 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition" data-preselect="<?= (int)$row['unit_id'] ?>">
                    <option value="">Select Unit</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Expense Class <span class="text-red-500">*</span></label>
                <select id="expenseClassSelect" required class="lockable w-full border border-gray-300 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">Select Expense Class</option>
                    <option value="MOOE" <?= $selectedExpenseClass === 'MOOE' ? 'selected' : '' ?>>MOOE - Maintenance &amp; Other</option>
                    <option value="CO" <?= $selectedExpenseClass === 'CO' ? 'selected' : '' ?>>CO - Capital Outlay</option>
                    <option value="PS" <?= $selectedExpenseClass === 'PS' ? 'selected' : '' ?>>PS - Personal Services</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Code <span class="text-red-500">*</span></label>
                <select name="account_id" id="accountSelect" required class="lockable w-full border border-gray-300 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">Select Account</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Performance Indicator</label>
                <input type="text" name="indicator_text" value="<?= e($row['indicator_description'] ?? '') ?>" placeholder="Enter performance indicator" class="lockable w-full border border-gray-300 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
            </div>
        </div>
    </div>

    <div class="tab-panel p-6 sm:p-10" data-step="2">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-6">
            <span class="bg-emerald-100 text-emerald-700 w-8 h-8 rounded-lg flex items-center justify-center text-sm"><i class="fa-solid fa-bullseye"></i></span>
            Physical Targets
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <?php foreach (['q1_target' => 'Q1', 'q2_target' => 'Q2', 'q3_target' => 'Q3', 'q4_target' => 'Q4'] as $name => $label): ?>
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 text-center">
                <span class="block text-xs font-medium text-gray-400 mb-2"><?= $label ?> Target</span>
                <input type="number" name="<?= $name ?>" min="0" value="<?= (int)$row[$name] ?>" class="quarter-input w-full text-center text-lg font-semibold border border-gray-300 rounded-lg py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
            </div>
            <?php endforeach; ?>
        </div>
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="bg-emerald-500 text-white w-10 h-10 rounded-full flex items-center justify-center"><i class="fa-solid fa-calculator"></i></div>
                <div>
                    <span class="block text-xs text-emerald-600 font-medium">Annual Physical Target</span>
                    <span class="block text-2xl font-bold text-emerald-800" id="totalTarget">0</span>
                </div>
            </div>
            <span class="text-xs text-emerald-500 italic">Auto-computed</span>
        </div>
    </div>

    <div class="tab-panel p-6 sm:p-10" data-step="3">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-6">
            <span class="bg-amber-100 text-amber-700 w-8 h-8 rounded-lg flex items-center justify-center text-sm"><i class="fa-solid fa-coins"></i></span>
            Financial Allocation
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">
            <?php
            $monthLabels = [
                'jan_amt' => 'January', 'feb_amt' => 'February', 'mar_amt' => 'March',
                'apr_amt' => 'April', 'may_amt' => 'May', 'jun_amt' => 'June',
                'jul_amt' => 'July', 'aug_amt' => 'August', 'sep_amt' => 'September',
                'oct_amt' => 'October', 'nov_amt' => 'November', 'dec_amt' => 'December',
            ];
            foreach ($monthLabels as $key => $label): ?>
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                <span class="block text-xs font-medium text-gray-400 mb-2"><?= $label ?></span>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">&#8369;</span>
                    <input type="number" name="<?= $key ?>" min="0" step="0.01" value="<?= number_format((float)$row[$key], 2, '.', '') ?>" class="month-input w-full pl-7 pr-2 text-right text-sm font-medium border border-gray-300 rounded-lg py-2 focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="bg-amber-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm"><i class="fa-solid fa-peso-sign"></i></div>
                    <span class="text-sm font-semibold text-amber-700">Total Annual Allocation / Appropriation</span>
                </div>
                <span class="text-xs text-amber-400 italic" id="allocMode">Auto-computed or Manual Entry</span>
            </div>
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text">&#8369;</span>
                    <input type="number" name="total_allocation" id="totalAllocation" min="0" step="0.01" value="<?= number_format((float)$row['total_allocation'], 2, '.', '') ?>" placeholder="0.00" class="form-control text-success">
                </div>
                <small id="amountInWords" class="text-muted mt-1 d-block"></small>
            </div>
        </div>
    </div>

    <div class="tab-panel p-6 sm:p-10" data-step="4">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-6">
            <span class="bg-violet-100 text-violet-700 w-8 h-8 rounded-lg flex items-center justify-center text-sm"><i class="fa-solid fa-file-pen"></i></span>
            Justification
        </h2>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Justification / Remarks <span class="text-red-500">*</span></label>
            <textarea name="justification" rows="6" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition resize-none"><?= e($row['justification']) ?></textarea>
        </div>
    </div>

    <div class="bg-gray-50 border-t border-gray-100 px-6 sm:px-10 py-5 flex items-center justify-between">
        <button type="button" id="btnPrev" class="hidden inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-600 hover:bg-gray-100 transition shadow-sm">
            <i class="fa-solid fa-arrow-left text-xs"></i> Previous
        </button>
        <div class="flex-1"></div>
        <button type="button" id="btnNext" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition shadow-md">
            Next <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
        <button type="submit" id="btnSubmit" class="hidden inline-flex items-center gap-2 px-7 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition shadow-md">
            <i class="fa-solid fa-floppy-disk text-xs"></i> Save Changes
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
const unitsByFund = <?= json_encode($unitsByFund, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const accountsByExpense = <?= json_encode($accountsByExpense, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<script>
(() => {
    'use strict';

    const STEPS = [
        { label: 'Details', icon: 'fa-folder-open' },
        { label: 'Targets', icon: 'fa-bullseye' },
        { label: 'Allocation', icon: 'fa-coins' },
        { label: 'Justification', icon: 'fa-file-pen' },
    ];
    let currentStep = 1;
    const totalSteps = STEPS.length;
    const form = document.getElementById('budgetForm');
    const panels = document.querySelectorAll('.tab-panel');
    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    const btnSubmit = document.getElementById('btnSubmit');
    const indicator = document.getElementById('stepIndicator');
    const quarterInputs = document.querySelectorAll('.quarter-input');
    const monthInputs = document.querySelectorAll('.month-input');

    const fundSourceSelect = document.getElementById('fundSourceSelect');
    const unitSelect = document.getElementById('unitSelect');
    const expenseClassSelect = document.getElementById('expenseClassSelect');
    const accountSelect = document.getElementById('accountSelect');
    const fundSourceWarning = document.getElementById('fundSourceWarning');
    const lockableFields = form.querySelectorAll('.lockable, .quarter-input, .month-input, #totalAllocation, [name="justification"]');
    const preselectedUnit = parseInt(unitSelect.dataset.preselect, 10) || 0;
    const preselectedAccount = <?= (int)$row['account_id'] ?>;

    function setFormLocked(locked) {
        lockableFields.forEach(el => el.disabled = locked);
        btnNext.disabled = locked;
        fundSourceWarning.style.display = locked ? '' : 'none';
    }

    function populateUnits(fundSourceId, selectId = 0) {
        const list = unitsByFund[fundSourceId] || [];
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
        list.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.name;
            if (selectId && u.id === selectId) {
                opt.selected = true;
            }
            unitSelect.appendChild(opt);
        });
    }

    function populateAccounts(expenseClass, selectedId = 0) {
        const list = accountsByExpense[expenseClass] || [];
        accountSelect.innerHTML = '<option value="">Select Account</option>';
        list.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a.id;
            opt.textContent = `${a.code} - ${a.title}`;
            if (selectedId && a.id === selectedId) {
                opt.selected = true;
            }
            accountSelect.appendChild(opt);
        });
    }

    fundSourceSelect.addEventListener('change', function() {
        const val = this.value;
        if (!val) {
            setFormLocked(true);
            populateUnits(0);
            populateAccounts('', 0);
            expenseClassSelect.value = '';
        } else {
            setFormLocked(false);
            populateUnits(val);
            populateAccounts(expenseClassSelect.value, accountSelect.value ? parseInt(accountSelect.value, 10) : 0);
        }
    });

    expenseClassSelect.addEventListener('change', function() {
        populateAccounts(this.value, 0);
    });

    if (fundSourceSelect.value) {
        populateUnits(fundSourceSelect.value, preselectedUnit);
        populateAccounts(expenseClassSelect.value, preselectedAccount);
    } else {
        setFormLocked(true);
    }

    function buildIndicator() {
        let html = '';
        STEPS.forEach((s, i) => {
            const num = i + 1;
            if (i > 0) {
                html += `<div class="step-line w-8 sm:w-16 h-1 rounded bg-gray-200 mx-1" data-line="${num}"></div>`;
            }
            html += `<div class="flex flex-col items-center gap-1 cursor-pointer" data-goto="${num}">
                <div class="step-dot w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold border-2 border-gray-200 bg-white text-gray-400" data-dot="${num}"><i class="fa-solid ${s.icon}"></i></div>
                <span class="text-[10px] sm:text-xs font-medium text-gray-400" data-label="${num}">${s.label}</span></div>`;
        });
        indicator.innerHTML = html;
        indicator.querySelectorAll('[data-goto]').forEach(el => {
            el.addEventListener('click', () => {
                const t = parseInt(el.dataset.goto, 10);
                if (t < currentStep) {
                    goTo(t);
                }
            });
        });
    }

    function updateIndicator() {
        for (let i = 1; i <= totalSteps; i++) {
            const dot = indicator.querySelector(`[data-dot="${i}"]`);
            const label = indicator.querySelector(`[data-label="${i}"]`);
            const line = indicator.querySelector(`[data-line="${i}"]`);
            dot.className = 'step-dot w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold border-2';
            label.className = 'text-[10px] sm:text-xs font-medium';
            if (i < currentStep) {
                dot.classList.add('done');
                label.classList.add('text-emerald-600');
            } else if (i === currentStep) {
                dot.classList.add('active');
                label.classList.add('text-brand-600');
            } else {
                dot.classList.add('border-gray-200', 'bg-white', 'text-gray-400');
                label.classList.add('text-gray-400');
            }
            if (line) {
                line.className = 'step-line w-8 sm:w-16 h-1 rounded mx-1 ' + (i <= currentStep ? 'done bg-emerald-500' : 'bg-gray-200');
            }
        }
    }

    function goTo(step) {
        currentStep = step;
        panels.forEach(p => p.classList.remove('active'));
        document.querySelector(`.tab-panel[data-step="${step}"]`).classList.add('active');
        btnPrev.classList.toggle('hidden', step === 1);
        btnNext.classList.toggle('hidden', step === totalSteps);
        btnSubmit.classList.toggle('hidden', step !== totalSteps);
        updateIndicator();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(step) {
        const panel = document.querySelector(`.tab-panel[data-step="${step}"]`);
        const required = panel.querySelectorAll('[required]');
        let valid = true;
        required.forEach(el => {
            el.classList.remove('border-red-400', 'ring-2', 'ring-red-200');
            if (!el.value.trim()) {
                el.classList.add('border-red-400', 'ring-2', 'ring-red-200');
                valid = false;
            }
        });
        if (!valid) {
            Swal.fire({ icon: 'warning', title: 'Missing Information', text: 'Please fill in all required fields.', confirmButtonColor: '#0b4d26' });
        }
        return valid;
    }

    btnNext.addEventListener('click', () => {
        if (validateStep(currentStep) && currentStep < totalSteps) {
            goTo(currentStep + 1);
        }
    });
    btnPrev.addEventListener('click', () => {
        if (currentStep > 1) {
            goTo(currentStep - 1);
        }
    });

    function computeTargets() {
        let t = 0;
        quarterInputs.forEach(i => {
            t += parseInt(i.value, 10) || 0;
        });
        document.getElementById('totalTarget').textContent = t.toLocaleString();
    }
    quarterInputs.forEach(i => i.addEventListener('input', computeTargets));

    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const scales = ['', 'Thousand', 'Million', 'Billion', 'Trillion'];

    function chunkToWords(n) {
        if (n === 0) return '';
        let s = '';
        if (n >= 100) {
            s += ones[Math.floor(n / 100)] + ' Hundred';
            n %= 100;
            if (n) s += ' ';
        }
        if (n >= 20) {
            s += tens[Math.floor(n / 10)];
            n %= 10;
            if (n) s += '-';
        }
        if (n > 0) s += ones[n];
        return s;
    }

    function numberToWords(num) {
        if (num === 0) return 'Zero';
        if (num < 0) return 'Negative ' + numberToWords(-num);
        let parts = [];
        let scaleIdx = 0;
        let n = Math.floor(num);
        while (n > 0) {
            const chunk = n % 1000;
            if (chunk > 0) {
                parts.unshift(chunkToWords(chunk) + (scales[scaleIdx] ? ' ' + scales[scaleIdx] : ''));
            }
            n = Math.floor(n / 1000);
            scaleIdx++;
        }
        return parts.join(' ') || 'Zero';
    }

    function pesoWords(value) {
        const num = parseFloat(value) || 0;
        if (num === 0) return 'Zero Pesos';
        const abs = Math.abs(num);
        const pesos = Math.floor(abs);
        const centavos = Math.round((abs - pesos) * 100);
        let result = (num < 0 ? 'Negative ' : '') + numberToWords(pesos) + ' Peso' + (pesos !== 1 ? 's' : '');
        if (centavos > 0) {
            result += ' and ' + numberToWords(centavos) + ' Centavo' + (centavos !== 1 ? 's' : '');
        }
        return result;
    }

    const totalAllocInput = document.getElementById('totalAllocation');
    const allocModeLabel = document.getElementById('allocMode');
    const amountInWordsEl = document.getElementById('amountInWords');
    let manualAllocEdit = false;

    function updateAmountInWords() {
        if (amountInWordsEl) {
            amountInWordsEl.textContent = pesoWords(totalAllocInput.value);
        }
    }

    function computeAllocation() {
        if (manualAllocEdit) return;
        let t = 0;
        monthInputs.forEach(i => {
            t += parseFloat(i.value) || 0;
        });
        totalAllocInput.value = t.toFixed(2);
        updateAmountInWords();
    }

    monthInputs.forEach(i => i.addEventListener('input', () => {
        manualAllocEdit = false;
        if (allocModeLabel) {
            allocModeLabel.textContent = 'Auto-computed';
        }
        computeAllocation();
    }));

    totalAllocInput.addEventListener('input', () => {
        manualAllocEdit = true;
        if (allocModeLabel) {
            allocModeLabel.textContent = 'Manual Entry';
        }
        updateAmountInWords();
    });

    totalAllocInput.addEventListener('focus', () => {
        totalAllocInput.select();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateStep(currentStep)) return;
        const conf = await Swal.fire({
            title: 'Save Changes?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk"></i> Save',
            confirmButtonColor: '#0b4d26',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
        });
        if (!conf.isConfirmed) return;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        try {
            const res = await fetch(window.location.href, { method: 'POST', body: new FormData(form) });
            const json = await res.json();
            if (json.status === 'success') {
                await Swal.fire({ icon: 'success', title: 'Updated!', text: json.message, confirmButtonColor: '#0b4d26' });
                window.location.href = 'view.php?id=' + json.id;
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.message, confirmButtonColor: '#ef4444' });
            }
        } catch {
            Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.' });
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-floppy-disk text-xs"></i> Save Changes';
        }
    });

    form.querySelectorAll('[required]').forEach(el => {
        el.addEventListener('focus', () => el.classList.remove('border-red-400', 'ring-2', 'ring-red-200'));
    });

    buildIndicator();
    updateIndicator();
    computeTargets();
    computeAllocation();
    updateAmountInWords();
})();
</script>
