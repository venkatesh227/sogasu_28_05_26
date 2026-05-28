<?php
session_start();
include '../includes/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE racks SET status=? WHERE id=?");
    echo json_encode([
        'success' => $stmt->execute([$_POST['status'], $_POST['id']])
    ]);
    exit;
}

$stmt = $pdo->query("SELECT * FROM racks ORDER BY rack_name ASC");
$racks = $stmt->fetchAll();

$pageTitle = "Racks & Storage - Sogasu";
$activePage = "racks";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        
        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; ">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Racks & Storage</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Manage garment storage locations and status</p>
            </div>
            <button class="btn btn-primary" onclick="window.location.href='add-rack.php'" style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; color: white;">
                <i class="ri-add-line"></i> Add New Rack
            </button>
        </div>

        <!-- Table Box -->
        <div class="table-container">
            <table id="racksTable" class="table">
                <thead>
                    <tr>
                        <th>Rack Name</th>
                        <th>Description</th>
                        <th style="text-align: center;">Barcode</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="rackTableBody">
                    <?php if (!empty($racks)): ?>
                        <?php foreach ($racks as $rack): ?>
                            <tr>
                                <td style="font-weight: 700; color: #1e293b;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 32px; height: 32px; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #64748b;">
                                            <i class="ri-stack-line"></i>
                                        </div>
                                        <?= htmlspecialchars($rack['rack_name']) ?>
                                    </div>
                                </td>
                                <td style="color: #64748b;">
                                    <?= htmlspecialchars($rack['description'] ?: 'No description') ?>
                                </td>
                                <td style="text-align: center;">
                                    <button class="btn-icon" title="Print Barcode" onclick="printBarcode('<?= $rack['id'] ?>', '<?= htmlspecialchars($rack['rack_name']) ?>')" style="border: none; background: transparent; color: #4338ca; cursor: pointer;">
                                        <i class="ri-barcode-line" style="font-size: 1.4rem;"></i>
                                    </button>
                                </td>
                                <td>
                                    <?php 
                                    $statusColor = match($rack['status']) {
                                        'Available' => '#10b981',
                                        'Occupied' => '#f59e0b',
                                        'Maintenance' => '#ef4444',
                                        default => '#64748b'
                                    };
                                    ?>
                                    <span style="background: <?= $statusColor ?>15; color: <?= $statusColor ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">
                                        <?= $rack['status'] ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                                        <a href="add-rack.php?id=<?= $rack['id'] ?>" style="color: #6366f1; font-size: 1.1rem;"><i class="ri-edit-line"></i></a>
                                        <a href="#" onclick="confirmDelete(<?= $rack['id'] ?>)" style="color: #ef4444; font-size: 1.1rem;"><i class="ri-delete-bin-line"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Barcode Modal -->
<div id="barcodeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; padding:1rem;">
    <div style="background: white; width:100%; max-width:400px; padding: 2rem; border-radius: 12px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 0.5rem; font-size: 1.25rem;">Rack Barcode</h3>
        <p id="modalRackName" style="color: #64748b; margin-bottom: 1.5rem; font-weight: 500;"></p>
        
        <div id="printArea" style="background: white; padding: 1rem; display: inline-block; border: 1px solid #e2e8f0; border-radius: 8px;">
            <svg id="barcode"></svg>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button class="btn btn-primary" onclick="doPrint()" style="flex: 1; justify-content: center;">
                <i class="ri-printer-line"></i> Print Barcode
            </button>
            <button class="btn" onclick="closeBarcodeModal()" style="flex: 1; justify-content: center; background: #f1f5f9; color: #475569;">
                Close
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This rack will be deleted permanently!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "delete-rack.php?id=" + id;
            }
        });
    }

    // Search functionality
    document.getElementById('rackSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#rackTableBody tr');
        rows.forEach(row => {
            let text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });

    function printBarcode(id, name) {
        document.getElementById('modalRackName').innerText = name;
        document.getElementById('barcodeModal').style.display = 'flex';
        
        // Generate Barcode
        JsBarcode("#barcode", "RACK-" + id, {
            format: "CODE128",
            lineColor: "#000",
            width: 2,
            height: 100,
            displayValue: true
        });
    }

    function closeBarcodeModal() {
        document.getElementById('barcodeModal').style.display = 'none';
    }

    function doPrint() {
        const printContent = document.getElementById('printArea').innerHTML;
        const originalContent = document.body.innerHTML;
        const rackName = document.getElementById('modalRackName').innerText;

        const printWindow = window.open('', '', 'height=400,width=600');
        printWindow.document.write('<html><head><title>Print Barcode</title>');
        printWindow.document.write('<style>body{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;margin:0;font-family:sans-serif;} .name{font-weight:bold;margin-bottom:10px;font-size:1.2rem;}</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<div class="name">' + rackName + '</div>');
        printWindow.document.write(printContent);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
</script>

<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>

initializeDataTable(
    'racksTable',
    'Racks'
);

</script>

<?php include 'includes/footer.php'; ?>
