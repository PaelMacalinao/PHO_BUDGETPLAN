<?php
/**
 * Master Data CRUD — Account Codes
 */
require_once __DIR__ . '/../config.php';

// ── AJAX Handlers ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $pdo    = getConnection();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO tbl_account_codes (account_code, account_title, expense_class) VALUES (:code, :title, :class)");
            $stmt->execute([
                ':code'  => trim($_POST['account_code'] ?? ''),
                ':title' => trim($_POST['account_title'] ?? ''),
                ':class' => $_POST['expense_class'] ?? '',
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Account code added.']);
        } elseif ($action === 'update') {
            $stmt = $pdo->prepare("UPDATE tbl_account_codes SET account_code=:code, account_title=:title, expense_class=:class WHERE id=:id");
            $stmt->execute([
                ':id'    => (int)$_POST['id'],
                ':code'  => trim($_POST['account_code'] ?? ''),
                ':title' => trim($_POST['account_title'] ?? ''),
                ':class' => $_POST['expense_class'] ?? '',
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Account code updated.']);
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM tbl_account_codes WHERE id=:id");
            $stmt->execute([':id' => (int)$_POST['id']]);
            echo json_encode(['status' => 'success', 'message' => 'Account code deleted.']);
        } else {
            throw new InvalidArgumentException('Invalid action.');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $msg = str_contains($e->getMessage(), 'Duplicate entry') ? 'This account code already exists.' :
               (str_contains($e->getMessage(), 'foreign key') ? 'Cannot delete: this record is in use by budget proposals.' : 'A database error occurred.');
        echo json_encode(['status' => 'error', 'message' => $msg]);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Load data ────────────────────────────────────
$pageTitle  = 'Account Codes';
$activeMenu = 'account_codes';

try {
    $rows = getConnection()->query("SELECT * FROM tbl_account_codes ORDER BY account_code")->fetchAll();
} catch (PDOException $e) {
    $rows = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <p class="text-sm text-gray-500 mt-0.5">Manage account codes used in budget proposals.</p>
    </div>
    <button onclick="openModal()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md whitespace-nowrap">
        <i class="fa-solid fa-plus text-xs"></i> Add Account Code
    </button>
</div>

<div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
    <div class="px-6 py-6 overflow-x-auto">
        <table id="dataTable" class="w-full text-left" style="min-width:700px">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-2">ID</th>
                    <th class="py-3 px-2">Account Code</th>
                    <th class="py-3 px-2">Account Title</th>
                    <th class="py-3 px-2">Expense Class</th>
                    <th class="py-3 px-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr class="border-b border-gray-50">
                    <td class="py-3 px-2 font-mono text-xs text-gray-400">#<?= (int)$r['id'] ?></td>
                    <td class="py-3 px-2 font-semibold text-gray-800"><?= e($r['account_code']) ?></td>
                    <td class="py-3 px-2 text-gray-700"><?= e($r['account_title']) ?></td>
                    <td class="py-3 px-2">
                        <?php
                        $cls = match($r['expense_class']) {
                            'MOOE' => 'bg-blue-50 text-blue-700',
                            'CO'   => 'bg-orange-50 text-orange-700',
                            'PS'   => 'bg-purple-50 text-purple-700',
                            default => 'bg-gray-50 text-gray-700',
                        };
                        ?>
                        <span class="inline-block <?= $cls ?> text-xs font-medium px-2.5 py-1 rounded-full"><?= e($r['expense_class']) ?></span>
                    </td>
                    <td class="py-3 px-2 text-center whitespace-nowrap">
                        <button onclick='openModal(<?= json_encode($r) ?>)' class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium hover:bg-amber-100 transition"><i class="fa-solid fa-pen-to-square"></i></button>
                        <button onclick="deleteRow(<?= (int)$r['id'] ?>)" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-red-50 text-red-600 text-xs font-medium hover:bg-red-100 transition"><i class="fa-solid fa-trash-can"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="bg-brand-50 px-6 py-4 border-b flex items-center justify-between">
            <h3 class="text-base font-semibold text-brand-800" id="modalTitle">Add Account Code</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="modalForm" class="p-6 space-y-4">
            <input type="hidden" name="id" id="fId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Code <span class="text-red-500">*</span></label>
                <input type="text" name="account_code" id="fCode" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition" placeholder="e.g. 5-02-03-010">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Title <span class="text-red-500">*</span></label>
                <input type="text" name="account_title" id="fTitle" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition" placeholder="e.g. Office Supplies Expenses">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Expense Class <span class="text-red-500">*</span></label>
                <select name="expense_class" id="fClass" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    <option value="">— Select —</option>
                    <option value="MOOE">MOOE</option>
                    <option value="CO">CO</option>
                    <option value="PS">PS</option>
                </select>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-100 transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md" id="modalSubmitBtn">Save</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
$(function(){ if($('#dataTable tbody tr').length) $('#dataTable').DataTable({pageLength:15, order:[[1,'asc']], columnDefs:[{orderable:false,targets:[4]}], language:{search:'',searchPlaceholder:'Search…'}}); });

function openModal(data) {
    document.getElementById('modalTitle').textContent = data ? 'Edit Account Code' : 'Add Account Code';
    document.getElementById('fId').value    = data ? data.id : '';
    document.getElementById('fCode').value  = data ? data.account_code : '';
    document.getElementById('fTitle').value = data ? data.account_title : '';
    document.getElementById('fClass').value = data ? data.expense_class : '';
    document.getElementById('modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('modal').classList.add('hidden'); }

document.getElementById('modalForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', fd.get('id') ? 'update' : 'create');
    try {
        const res = await fetch(window.location.href, {method:'POST', body: fd});
        const json = await res.json();
        if (json.status === 'success') {
            await Swal.fire({icon:'success', title:'Done!', text:json.message, confirmButtonColor:'#4f46e5', timer:1500, showConfirmButton:false});
            location.reload();
        } else {
            Swal.fire({icon:'error', title:'Error', text:json.message, confirmButtonColor:'#ef4444'});
        }
    } catch { Swal.fire({icon:'error', title:'Network Error', text:'Could not reach the server.'}); }
});

function deleteRow(id) {
    Swal.fire({title:'Delete this record?',text:'This cannot be undone.',icon:'warning',showCancelButton:true,confirmButtonText:'Delete',confirmButtonColor:'#ef4444',cancelButtonColor:'#6b7280',reverseButtons:true})
    .then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
        fetch(window.location.href,{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
            if(data.status==='success'){Swal.fire({icon:'success',title:'Deleted!',text:data.message,confirmButtonColor:'#4f46e5',timer:1500,showConfirmButton:false}).then(()=>location.reload());}
            else Swal.fire({icon:'error',title:'Error',text:data.message,confirmButtonColor:'#ef4444'});
        }).catch(()=>Swal.fire({icon:'error',title:'Network Error'}));
    });
}
</script>
