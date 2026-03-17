<?php
/**
 * Master Data CRUD — Organizational Units
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $pdo    = getConnection();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO tbl_units (unit_name) VALUES (:name)");
            $stmt->execute([':name' => trim($_POST['unit_name'] ?? '')]);
            echo json_encode(['status' => 'success', 'message' => 'Unit added.']);
        } elseif ($action === 'update') {
            $stmt = $pdo->prepare("UPDATE tbl_units SET unit_name=:name WHERE id=:id");
            $stmt->execute([':id' => (int)$_POST['id'], ':name' => trim($_POST['unit_name'] ?? '')]);
            echo json_encode(['status' => 'success', 'message' => 'Unit updated.']);
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM tbl_units WHERE id=:id");
            $stmt->execute([':id' => (int)$_POST['id']]);
            echo json_encode(['status' => 'success', 'message' => 'Unit deleted.']);
        } else {
            throw new InvalidArgumentException('Invalid action.');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $msg = str_contains($e->getMessage(), 'Duplicate entry') ? 'This unit already exists.' :
               (str_contains($e->getMessage(), 'foreign key') ? 'Cannot delete: this unit is in use.' : 'A database error occurred.');
        echo json_encode(['status' => 'error', 'message' => $msg]);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

$pageTitle  = 'Organizational Units';
$activeMenu = 'units';
try { $rows = getConnection()->query("SELECT * FROM tbl_units ORDER BY unit_name")->fetchAll(); } catch (PDOException $e) { $rows = []; }

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <p class="text-sm text-gray-500">Manage organizational units (e.g. PHO CLINIC, PESU).</p>
    <button onclick="openModal()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md whitespace-nowrap">
        <i class="fa-solid fa-plus text-xs"></i> Add Unit
    </button>
</div>

<div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
    <div class="px-6 py-6 overflow-x-auto">
        <table id="dataTable" class="w-full text-left">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-2">ID</th>
                    <th class="py-3 px-2">Unit Name</th>
                    <th class="py-3 px-2">Created</th>
                    <th class="py-3 px-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr class="border-b border-gray-50">
                    <td class="py-3 px-2 font-mono text-xs text-gray-400">#<?= (int)$r['id'] ?></td>
                    <td class="py-3 px-2 font-semibold text-gray-800"><?= e($r['unit_name']) ?></td>
                    <td class="py-3 px-2 text-xs text-gray-400"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
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
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-brand-50 px-6 py-4 border-b flex items-center justify-between">
            <h3 class="text-base font-semibold text-brand-800" id="modalTitle">Add Unit</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="modalForm" class="p-6 space-y-4">
            <input type="hidden" name="id" id="fId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Unit Name <span class="text-red-500">*</span></label>
                <input type="text" name="unit_name" id="fName" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition" placeholder="e.g. PHO CLINIC">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-100 transition">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md">Save</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
$(function(){ if($('#dataTable tbody tr').length) $('#dataTable').DataTable({pageLength:15, order:[[1,'asc']], columnDefs:[{orderable:false,targets:[3]}], language:{search:'',searchPlaceholder:'Search…'}}); });

function openModal(data) {
    document.getElementById('modalTitle').textContent = data ? 'Edit Unit' : 'Add Unit';
    document.getElementById('fId').value   = data ? data.id : '';
    document.getElementById('fName').value = data ? data.unit_name : '';
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
            await Swal.fire({icon:'success',title:'Done!',text:json.message,confirmButtonColor:'#4f46e5',timer:1500,showConfirmButton:false});
            location.reload();
        } else Swal.fire({icon:'error',title:'Error',text:json.message,confirmButtonColor:'#ef4444'});
    } catch { Swal.fire({icon:'error',title:'Network Error'}); }
});

function deleteRow(id) {
    Swal.fire({title:'Delete this unit?',text:'This cannot be undone.',icon:'warning',showCancelButton:true,confirmButtonText:'Delete',confirmButtonColor:'#ef4444',cancelButtonColor:'#6b7280',reverseButtons:true})
    .then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
        fetch(window.location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
            if(d.status==='success'){Swal.fire({icon:'success',title:'Deleted!',text:d.message,confirmButtonColor:'#4f46e5',timer:1500,showConfirmButton:false}).then(()=>location.reload());}
            else Swal.fire({icon:'error',title:'Error',text:d.message,confirmButtonColor:'#ef4444'});
        }).catch(()=>Swal.fire({icon:'error',title:'Network Error'}));
    });
}
</script>
