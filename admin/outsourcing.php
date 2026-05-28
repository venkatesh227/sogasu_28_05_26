<?php
$pageTitle = "Outsourcing - Sogasu";
$activePage = "outsourcing";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding:1rem;">

        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h2 style="color:#1e293b;">Outsourcing</h2>

            <div style="display:flex; gap:0.5rem;">
                <input type="text" placeholder="Search..." class="form-control" style="width:200px;">
                <button class="btn btn-primary"><i class="ri-search-line"></i></button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-box">
<table id="outsourcingTable" style="width:100%;">
                    <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Product (Material Design)</th>
                        <th>Employee</th>
                        <th>Given Date</th>
                        <th>Expected Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php
                $data = [
                    ['order' => '#ORD-2458', 'product' => 'Silk Blouse (Aari Work)', 'emp' => 'Vibisha', 'given' => '31/03/2026', 'expected' => '15/04/2026', 'status' => 'Given'],
                    ['order' => '#ORD-2457', 'product' => 'Lehenga (Mirror Work)', 'emp' => 'Tarunika', 'given' => '30/03/2026', 'expected' => '11/04/2026', 'status' => 'Given']
                ];

                foreach($data as $d){
                ?>
                    <tr>
                        <td><?= $d['order'] ?></td>
                        <td><?= $d['product'] ?></td>
                        <td><?= $d['emp'] ?></td>
                        <td><?= $d['given'] ?></td>
                        <td><?= $d['expected'] ?></td>
                        <td><span class="badge"><?= $d['status'] ?></span></td>
                        <td>
                            <button class="btn btn-sm" style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe;"
                                onclick="openEdit(
                                   '<?= $d['order'] ?>',
                                   '<?= $d['emp'] ?>',
                                   '<?= $d['given'] ?>',
                                   '<?= $d['expected'] ?>',
                                   '<?= $d['status'] ?>'
                                )">
                                <i class="ri-pencil-line"></i> Edit
                            </button>
                        </td>
                    </tr>
                <?php } ?>

                </tbody>
            </table>
        </div>

    </div>
</main>

<!-- Edit Card Modal -->
<div id="editModal" class="modal">
    <div class="modal-card" style="width: 400px; padding: 0; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
        
        <div style="background: var(--primary); color: white; padding: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem;">Edit Outsourcing Details</h3>
            <i class="ri-close-line" style="cursor: pointer; font-size: 1.5rem;" onclick="closeModal()"></i>
        </div>

        <div style="padding: 1.5rem;">
            <div class="form-group">
                <label class="form-label" style="font-weight: 600; color: #475569;">Outsourcing Employee</label>
                <input type="text" id="emp" class="form-control" placeholder="Employee Name">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; color: #475569;">Given Date</label>
                    <input type="date" id="given" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; color: #475569;">Expected Date</label>
                    <input type="date" id="expected" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-weight: 600; color: #475569;">Status</label>
                <select id="status" class="form-select">
                    <option value="Given">Given</option>
                    <option value="Completed">Completed</option>
                    <option value="Pending">Pending</option>
                    <option value="Delayed">Delayed</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-weight: 600; color: #475569;">Reference Image</label>
                <div style="border: 2px dashed #e2e8f0; padding: 1rem; border-radius: 8px; text-align: center; background: #f8fafc; position: relative;">
                    <i class="ri-image-add-line" style="font-size: 1.5rem; color: #94a3b8; display: block; margin-bottom: 0.25rem;"></i>
                    <span style="font-size: 0.75rem; color: #64748b;">Click to upload reference</span>
                    <input type="file" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button class="btn" style="background: white; border: 1px solid #e2e8f0; color: #64748b; padding: 0.5rem 1rem;" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" style="padding: 0.5rem 1.5rem;" onclick="saveChanges()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<style>

.table-box{
    background:white;
    border:1px solid #e2e8f0;
    padding:1rem;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:0.8rem;
    font-size:0.85rem;
}

th{
    color:#64748b;
}

.badge{
    background:#e0f2fe;
    color:#0284c7;
    padding:3px 8px;
    border-radius:6px;
}

.edit-icon{
    cursor:pointer;
    color:#4f46e5;
}

/* Modal */
.modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.3);
    justify-content:center;
    align-items:center;
}

.modal-card{
    background:white;
    padding:1.5rem;
    width:350px;
    border-radius:10px;
}

.form-group{
    margin-bottom:0.8rem;
}

.form-group input,
.form-group select{
    width:100%;
    padding:0.5rem;
    border:1px solid #cbd5e1;
    border-radius:6px;
}

</style>

<script>

function openEdit(order, emp, given, expected, status){
    document.getElementById('editModal').style.display='flex';

    document.getElementById('emp').value = emp;
    document.getElementById('given').value = formatDate(given);
    document.getElementById('expected').value = formatDate(expected);
    document.getElementById('status').value = status;
}

function closeModal(){
    document.getElementById('editModal').style.display='none';
}

function saveChanges() {
    alert('Changes saved successfully!');
    closeModal();
}

// convert dd/mm/yyyy → yyyy-mm-dd
function formatDate(date){
    if(!date) return '';
    let parts = date.split('/');
    if(parts.length !== 3) return date;
    return parts[2]+'-'+parts[1]+'-'+parts[0];
}

</script>
<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
initializeDataTable('outsourcingTable', 'Outsourcing');
</script>
<?php include 'includes/footer.php'; ?>