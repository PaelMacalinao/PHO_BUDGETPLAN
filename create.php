<?php
/**
 * PHO Budgeting System — Create Budget Proposal
 * 4-step wizard with auto-computed totals.
 */
require_once __DIR__ . '/config.php';

// ── Handle POST (AJAX) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $pdo  = getConnection();
        $data = [];

        $textFields = ['ppa_description', 'justification'];
        foreach ($textFields as $f) {
            $data[$f] = trim($_POST[$f] ?? '');
            if ($data[$f] === '') throw new InvalidArgumentException("'{$f}' is required.");
        }

        $fkFields = ['program_id', 'account_id', 'fund_source_id', 'indicator_id', 'unit_id'];
        foreach ($fkFields as $f) {
            $data[$f] = (int)($_POST[$f] ?? 0);
            if ($data[$f] < 1) throw new InvalidArgumentException("Please select a valid option for '{$f}'.");
        }

        $quarters = ['q1_target', 'q2_target', 'q3_target', 'q4_target'];
        $targetTotal = 0;
        foreach ($quarters as $q) {
            $data[$q] = max(0, (int)($_POST[$q] ?? 0));
            $targetTotal += $data[$q];
        }
        $data['target_total'] = $targetTotal;

        $months = ['jan_amt','feb_amt','mar_amt','apr_amt','may_amt','jun_amt','jul_amt','aug_amt','sep_amt','oct_amt','nov_amt','dec_amt'];
        $allocTotal = 0;
        foreach ($months as $m) {
            $data[$m] = max(0, round((float)($_POST[$m] ?? 0), 2));
            $allocTotal += $data[$m];
        }
        $data['total_allocation'] = round($allocTotal, 2);

        $sql = "INSERT INTO tbl_budget_proposals
                (ppa_description, program_id, account_id, fund_source_id, indicator_id,
                 q1_target, q2_target, q3_target, q4_target, target_total,
                 jan_amt, feb_amt, mar_amt, apr_amt, may_amt, jun_amt,
                 jul_amt, aug_amt, sep_amt, oct_amt, nov_amt, dec_amt, total_allocation,
                 justification)
                VALUES
                (:ppa_description, :program_id, :account_id, :fund_source_id, :indicator_id,
                 :q1_target, :q2_target, :q3_target, :q4_target, :target_total,
                 :jan_amt, :feb_amt, :mar_amt, :apr_amt, :may_amt, :jun_amt,
                 :jul_amt, :aug_amt, :sep_amt, :oct_amt, :nov_amt, :dec_amt, :total_allocation,
                 :justification)";

        $stmt = $pdo->prepare($sql);
        $params = [];
        foreach ($data as $k => $v) $params[":$k"] = $v;
        $stmt->execute($params);

        echo json_encode(['status' => 'success', 'message' => 'Budget proposal saved!', 'id' => $pdo->lastInsertId()]);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (PDOException $e) {
        error_log('DB Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
    }
    exit;
}

// ── Load reference data for dropdowns ────────────
$pageTitle  = 'New Budget Proposal';
$activeMenu = 'create';

try {
    $pdo        = getConnection();
    $programs   = $pdo->query("SELECT id, program_name FROM tbl_programs_units ORDER BY program_name")->fetchAll();
    $accounts   = $pdo->query("SELECT id, account_code, account_title, expense_class FROM tbl_account_codes ORDER BY account_code")->fetchAll();
    $fundSrcs   = $pdo->query("SELECT id, fund_name FROM tbl_fund_sources ORDER BY fund_name")->fetchAll();
    $indicators = $pdo->query("SELECT id, indicator_description FROM tbl_indicators ORDER BY id")->fetchAll();
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    $programs = $accounts = $fundSrcs = $indicators = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .tab-panel{display:none;animation:fadeSlide .35s ease}
    .tab-panel.active{display:block}
    @keyframes fadeSlide{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    .step-dot{transition:all .3s ease}
    .step-dot.active{background:#4f46e5;color:#fff;box-shadow:0 0 0 4px rgba(79,70,229,.25)}
    .step-dot.done{background:#10b981;color:#fff}
    .step-line.done{background:#10b981}
</style>

<!-- Page heading -->
<div class="mb-8 text-center">
    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800">2026 Consolidated Budget Proposal</h2>
    <p class="mt-1 text-sm text-gray-500">Complete the form below. Fields with <span class="text-red-500">*</span> are required.</p>
</div>

<!-- Step Indicator -->
<div class="flex items-center justify-center mb-10 select-none" id="stepIndicator"></div>

<!-- Form Card -->
<form id="budgetForm" novalidate autocomplete="off" class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">

    <!-- ─── TAB 1: Program & Account Details ─── -->
    <div class="tab-panel active p-6 sm:p-10" data-step="1">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-6">
            <span class="bg-brand-100 text-brand-700 w-8 h-8 rounded-lg flex items-center justify-center text-sm"><i class="fa-solid fa-folder-open"></i></span>
            Program &amp; Account Details
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">PPA Description <span class="text-red-500">*</span></label>
                <textarea name="ppa_description" rows="2" required placeholder="Program / Project / Activity description"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition resize-none"></textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Program / Unit <span class="text-red-500">*</span></label>
                <select name="program_id" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">— Select Program —</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['program_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Code <span class="text-red-500">*</span></label>
                <select name="account_id" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">— Select Account —</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= e($a['account_code']) ?> — <?= e($a['account_title']) ?> (<?= e($a['expense_class']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Fund Source <span class="text-red-500">*</span></label>
                <select name="fund_source_id" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">— Select Fund Source —</option>
                    <?php foreach ($fundSrcs as $f): ?>
                        <option value="<?= (int)$f['id'] ?>"><?= e($f['fund_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Performance Indicator <span class="text-red-500">*</span></label>
                <select name="indicator_id" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">— Select Indicator —</option>
                    <?php foreach ($indicators as $ind): ?>
                        <option value="<?= (int)$ind['id'] ?>"><?= e($ind['indicator_description']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- ─── TAB 2: Physical Targets ─── -->
    <div class="tab-panel p-6 sm:p-10" data-step="2">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-6">
            <span class="bg-emerald-100 text-emerald-700 w-8 h-8 rounded-lg flex items-center justify-center text-sm"><i class="fa-solid fa-bullseye"></i></span>
            Physical Targets
        </h2>
        <p class="text-sm text-gray-500 mb-6">Enter quarterly physical targets. The total is auto-computed.</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <?php foreach (['q1_target'=>'Q1','q2_target'=>'Q2','q3_target'=>'Q3','q4_target'=>'Q4'] as $name => $label): ?>
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 text-center">
                <span class="block text-xs font-medium text-gray-400 mb-2"><?= $label ?> Target</span>
                <input type="number" name="<?= $name ?>" min="0" value="0"
                       class="quarter-input w-full text-center text-lg font-semibold border border-gray-300 rounded-lg py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition" />
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

    <!-- ─── TAB 3: Financial Allocation ─── -->
    <div class="tab-panel p-6 sm:p-10" data-step="3">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-6">
            <span class="bg-amber-100 text-amber-700 w-8 h-8 rounded-lg flex items-center justify-center text-sm"><i class="fa-solid fa-coins"></i></span>
            Financial Allocation
        </h2>
        <p class="text-sm text-gray-500 mb-6">Enter the monthly budget allocation in pesos. The total is auto-computed.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">
            <?php
            $monthLabels = [
                'jan_amt'=>'January','feb_amt'=>'February','mar_amt'=>'March',
                'apr_amt'=>'April','may_amt'=>'May','jun_amt'=>'June',
                'jul_amt'=>'July','aug_amt'=>'August','sep_amt'=>'September',
                'oct_amt'=>'October','nov_amt'=>'November','dec_amt'=>'December',
            ];
            foreach ($monthLabels as $key => $label): ?>
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                <span class="block text-xs font-medium text-gray-400 mb-2"><?= $label ?></span>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                    <input type="number" name="<?= $key ?>" min="0" step="0.01" value="0.00"
                           class="month-input w-full pl-7 pr-2 text-right text-sm font-medium border border-gray-300 rounded-lg py-2 focus:outline-none focus:ring-2 focus:ring-amber-500 transition" />
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="bg-amber-500 text-white w-10 h-10 rounded-full flex items-center justify-center"><i class="fa-solid fa-peso-sign"></i></div>
                <div>
                    <span class="block text-xs text-amber-600 font-medium">Total Annual Allocation</span>
                    <span class="block text-2xl font-bold text-amber-800" id="totalAllocation">₱ 0.00</span>
                </div>
            </div>
            <span class="text-xs text-amber-500 italic">Auto-computed</span>
        </div>
    </div>

    <!-- ─── TAB 4: Justification ─── -->
    <div class="tab-panel p-6 sm:p-10" data-step="4">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-6">
            <span class="bg-violet-100 text-violet-700 w-8 h-8 rounded-lg flex items-center justify-center text-sm"><i class="fa-solid fa-file-pen"></i></span>
            Justification
        </h2>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Justification / Remarks <span class="text-red-500">*</span></label>
            <textarea name="justification" rows="6" required placeholder="Provide justification for this budget proposal"
                class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition resize-none"></textarea>
        </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="bg-gray-50 border-t border-gray-100 px-6 sm:px-10 py-5 flex items-center justify-between">
        <button type="button" id="btnPrev" class="hidden inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-600 hover:bg-gray-100 transition shadow-sm">
            <i class="fa-solid fa-arrow-left text-xs"></i> Previous
        </button>
        <div class="flex-1"></div>
        <button type="button" id="btnNext" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition shadow-md">
            Next <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
        <button type="submit" id="btnSubmit" class="hidden inline-flex items-center gap-2 px-7 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition shadow-md">
            <i class="fa-solid fa-paper-plane text-xs"></i> Submit Proposal
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
(() => {
    'use strict';

    const STEPS = [
        { label: 'Details',       icon: 'fa-folder-open' },
        { label: 'Targets',       icon: 'fa-bullseye' },
        { label: 'Allocation',    icon: 'fa-coins' },
        { label: 'Justification', icon: 'fa-file-pen' },
    ];

    let currentStep = 1;
    const totalSteps = STEPS.length;

    const form          = document.getElementById('budgetForm');
    const panels        = document.querySelectorAll('.tab-panel');
    const btnPrev       = document.getElementById('btnPrev');
    const btnNext       = document.getElementById('btnNext');
    const btnSubmit     = document.getElementById('btnSubmit');
    const indicator     = document.getElementById('stepIndicator');
    const quarterInputs = document.querySelectorAll('.quarter-input');
    const monthInputs   = document.querySelectorAll('.month-input');

    // ── Step indicator ───────────────────────────
    function buildIndicator() {
        let html = '';
        STEPS.forEach((s, i) => {
            const num = i + 1;
            if (i > 0) html += `<div class="step-line w-8 sm:w-16 h-1 rounded bg-gray-200 mx-1" data-line="${num}"></div>`;
            html += `
                <div class="flex flex-col items-center gap-1 cursor-pointer" data-goto="${num}">
                    <div class="step-dot w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold border-2 border-gray-200 bg-white text-gray-400" data-dot="${num}">
                        <i class="fa-solid ${s.icon}"></i>
                    </div>
                    <span class="text-[10px] sm:text-xs font-medium text-gray-400" data-label="${num}">${s.label}</span>
                </div>`;
        });
        indicator.innerHTML = html;
        indicator.querySelectorAll('[data-goto]').forEach(el => {
            el.addEventListener('click', () => {
                const t = parseInt(el.dataset.goto);
                if (t < currentStep) goTo(t);
            });
        });
    }

    function updateIndicator() {
        for (let i = 1; i <= totalSteps; i++) {
            const dot   = indicator.querySelector(`[data-dot="${i}"]`);
            const label = indicator.querySelector(`[data-label="${i}"]`);
            const line  = indicator.querySelector(`[data-line="${i}"]`);
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
            if (line) { line.className = 'step-line w-8 sm:w-16 h-1 rounded mx-1 ' + (i <= currentStep ? 'done bg-emerald-500' : 'bg-gray-200'); }
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
        const panel    = document.querySelector(`.tab-panel[data-step="${step}"]`);
        const required = panel.querySelectorAll('[required]');
        let valid = true;
        required.forEach(el => {
            el.classList.remove('border-red-400', 'ring-2', 'ring-red-200');
            if (!el.value.trim()) {
                el.classList.add('border-red-400', 'ring-2', 'ring-red-200');
                valid = false;
            }
        });
        if (!valid) Swal.fire({ icon:'warning', title:'Missing Information', text:'Please fill in all required fields.', confirmButtonColor:'#4f46e5' });
        return valid;
    }

    btnNext.addEventListener('click', () => { if (validateStep(currentStep) && currentStep < totalSteps) goTo(currentStep + 1); });
    btnPrev.addEventListener('click', () => { if (currentStep > 1) goTo(currentStep - 1); });

    // ── Auto-compute: Physical Targets ───────────
    function computeTargets() {
        let total = 0;
        quarterInputs.forEach(inp => { total += parseInt(inp.value) || 0; });
        document.getElementById('totalTarget').textContent = total.toLocaleString();
    }
    quarterInputs.forEach(inp => inp.addEventListener('input', computeTargets));

    // ── Auto-compute: Financial Allocation ───────
    function computeAllocation() {
        let total = 0;
        monthInputs.forEach(inp => { total += parseFloat(inp.value) || 0; });
        document.getElementById('totalAllocation').textContent = '₱ ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    monthInputs.forEach(inp => inp.addEventListener('input', computeAllocation));

    // ── Form submission ──────────────────────────
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateStep(currentStep)) return;

        const conf = await Swal.fire({
            title: 'Submit Budget Proposal?',
            text: 'Please review all details before submitting.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-paper-plane"></i> Yes, Submit',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
        });
        if (!conf.isConfirmed) return;

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';

        try {
            const res  = await fetch(window.location.href, { method: 'POST', body: new FormData(form) });
            const json = await res.json();
            if (json.status === 'success') {
                await Swal.fire({ icon:'success', title:'Proposal Saved!', html:`Reference ID: <strong>#${json.id}</strong>`, confirmButtonColor:'#059669' });
                window.location.href = 'index.php';
            } else {
                Swal.fire({ icon:'error', title:'Validation Error', text: json.message, confirmButtonColor:'#ef4444' });
            }
        } catch {
            Swal.fire({ icon:'error', title:'Network Error', text:'Could not reach the server.', confirmButtonColor:'#ef4444' });
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-paper-plane text-xs"></i> Submit Proposal';
        }
    });

    form.querySelectorAll('[required]').forEach(el => {
        el.addEventListener('focus', () => el.classList.remove('border-red-400', 'ring-2', 'ring-red-200'));
    });

    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            if (currentStep < totalSteps) btnNext.click();
        }
    });

    buildIndicator();
    updateIndicator();
    computeTargets();
    computeAllocation();
})();
</script>
