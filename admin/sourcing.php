<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$pageTitle = "Sourcing - Sogasu";
$activePage = "sourcing";

// Fetch Sourcing Data
$stmt = $pdo->query("SELECT * FROM sourcing ORDER BY id DESC");
$sourcingList = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1rem;">
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Material Sourcing</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Manage procurement and raw material sourcing</p>
            </div>
            <button onclick="openSourcingModal()" class="btn btn-primary" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; color: white;">
                <i class="ri-add-line"></i> Add Sourcing
            </button>
        </div>

        <!-- Table Box -->
        <div class="table-container">
            <table id="sourcingTable" class="table">
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Files</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sourcingList as $row): 
                        switch($row['status']) {
                            case 'Pending': $statusColor = '#f59e0b'; break;
                            case 'In Progress': $statusColor = '#6366f1'; break;
                            case 'Completed': $statusColor = '#10b981'; break;
                            default: $statusColor = '#64748b'; break;
                        }
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem; font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td style="padding: 1rem; color: #475569;"><?= htmlspecialchars($row['product_name']) ?></td>
                        <td style="padding: 1rem;">
                            <span style="font-size: 0.75rem; background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 4px; font-weight: 600;">
                                <?= htmlspecialchars($row['source_type']) ?>
                            </span>
                        </td>
                        <td style="padding: 1rem; font-weight: 500;"><?= htmlspecialchars($row['quantity']) ?></td>
                        <td style="padding: 1rem; font-weight: 700; color: #1e293b;">₹<?= number_format($row['total_amount']) ?></td>
                        <td style="padding: 1rem;">
                            <span style="background: <?= $statusColor ?>15; color: <?= $statusColor ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                <?= htmlspecialchars($row['status']) ?>
                            </span>
                        </td>
                        <td style="padding: 1rem;">
                            <?php if(!empty($row['reference_image'])): ?>
                                <a href="../<?= htmlspecialchars($row['reference_image']) ?>" target="_blank" style="color: #4f46e5; text-decoration: none; margin-right: 8px;" title="View Reference Image"><i class="ri-image-line"></i></a>
                            <?php endif; ?>
                            <?php if(!empty($row['attachment_file'])): ?>
                                <a href="../<?= htmlspecialchars($row['attachment_file']) ?>" target="_blank" style="color: #10b981; text-decoration: none;" title="View Attachment"><i class="ri-attachment-line"></i></a>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; text-align: right;">
                            <button onclick='editSourcing(<?= json_encode($row) ?>)' style="border: none; background: #f8fafc; color: #6366f1; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">
                                <i class="ri-edit-line"></i> Edit
                            </button>
                            <button onclick="deleteSourcing(<?= $row['id'] ?>)" style="border: none; background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600; margin-left: 5px;">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- SOURCING MODAL -->
<div id="sourcingModal" class="custom-modal">
    <div class="custom-modal-card" style="width: 550px;">
        <div class="custom-modal-header" style="background: #4f46e5; color: white;">
            <h3 style="margin: 0;">Add New Sourcing</h3>
            <i class="ri-close-line" onclick="closeModal()" style="cursor: pointer;"></i>
        </div>
        <form id="sourcingForm" method="POST" action="save-sourcing.php" enctype="multipart/form-data">
            <div class="custom-modal-body" style="padding: 1.5rem;">
                <input type="hidden" name="id" id="sourcing_id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Customer Name</label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="product_name" id="product_name" class="form-control" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Source Type</label>
                    <select id="source_type_select" class="form-select" onchange="addSourceFields(this.value)">
                        <option value="">Select Category</option>
                        <?php
                        $quickNotes = $pdo->query("SELECT note_text FROM quick_notes WHERE status = 1 AND is_deleted = 0 ORDER BY note_text ASC")->fetchAll();
                        foreach($quickNotes as $note): ?>
                            <option value="<?= htmlspecialchars($note['note_text']) ?>"><?= htmlspecialchars($note['note_text']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="dynamicSourceFields" style="margin-bottom: 1rem;"></div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Status</label>
                    <select name="status" id="status" class="form-select" required>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label">Reference Image</label>
                        <input type="file" name="reference_image" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Attachments</label>
                        <input type="file" name="attachment_file" class="form-control">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; background: #4f46e5; border: none; color: white; border-radius: 8px; font-weight: 600; cursor: pointer;">Submit Sourcing</button>
            </div>
        </form>
    </div>
</div>

<style>
    .custom-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9999; justify-content: center; align-items: center; }
    .custom-modal-card { background: #fff; border-radius: 12px; overflow: hidden; }
    .custom-modal-header { padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
    .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569; font-size: 0.85rem; }
    .form-control, .form-select { width: 100%; height: 42px; border: 1px solid #dbe2ea; border-radius: 8px; padding: 0 12px; font-size: 0.9rem; outline: none; }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>
function openSourcingModal(){ 
    document.getElementById('sourcingForm').reset();
    document.getElementById('sourcing_id').value = '';
    document.getElementById('dynamicSourceFields').innerHTML = '';
    document.getElementById('sourcingModal').style.display = 'flex'; 
}
function closeModal(){ document.getElementById('sourcingModal').style.display = 'none'; }

function deleteSourcing(id) {
    if(confirm('Are you sure you want to delete this sourcing record?')) {
        window.location.href = `delete-sourcing.php?id=${id}`;
    }
}

function addSourceFields(type) {
    if(!type) return;
    const container = document.getElementById('dynamicSourceFields');
    const id = Date.now();
    const div = document.createElement('div');
    div.id = `source_${id}`;
    div.style.cssText = 'background: #f8fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 10px; margin-bottom: 0.75rem;';
    div.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
            <span style="font-weight:700; color:#4f46e5; font-size:0.85rem;">${type}</span>
            <i class="ri-delete-bin-line" onclick="this.parentElement.parentElement.remove()" style="color:#ef4444; cursor:pointer;"></i>
        </div>
        <input type="hidden" name="source_type[]" value="${type}">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div>
                <label class="form-label">Quantity</label>
                <input type="text" name="quantity[]" class="form-control" required>
            </div>
            <div>
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" name="total_amount[]" class="form-control" required>
            </div>
        </div>
    `;
    container.appendChild(div);
    document.getElementById('source_type_select').selectedIndex = 0;
}

function editSourcing(data) {
    openSourcingModal();
    document.getElementById('sourcing_id').value = data.id;
    document.getElementById('customer_name').value = data.customer_name;
    document.getElementById('product_name').value = data.product_name;
    document.getElementById('status').value = data.status;
    
    const container = document.getElementById('dynamicSourceFields');
    container.innerHTML = '';
    
    const types = data.source_type.split(',');
    const quantities = data.quantity.split(',');
    const amounts = data.total_amounts ? data.total_amounts.split(',') : [];
    
    types.forEach((type, i) => {
        const id = Date.now() + i;
        const div = document.createElement('div');
        div.id = `source_${id}`;
        div.style.cssText = 'background: #f8fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 10px; margin-bottom: 0.75rem;';
        div.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <span style="font-weight:700; color:#4f46e5; font-size:0.85rem;">${type.trim()}</span>
                <i class="ri-delete-bin-line" onclick="this.parentElement.parentElement.remove()" style="color:#ef4444; cursor:pointer;"></i>
            </div>
            <input type="hidden" name="source_type[]" value="${type.trim()}">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div>
                    <label class="form-label">Quantity</label>
                    <input type="text" name="quantity[]" value="${quantities[i]?.trim() || ''}" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" name="total_amount[]" value="${amounts[i]?.trim() || ''}" class="form-control" required>
                </div>
            </div>
        `;
        container.appendChild(div);
    });
}

$(document).ready(function() {
    initializeDataTable('sourcingTable', 'Sourcing Report');
});
</script>

<?php include 'includes/footer.php'; ?>
