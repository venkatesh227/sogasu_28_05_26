<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// AJAX handler to fetch PO items
if (isset($_GET['action']) && $_GET['action'] === 'get_po_items' && isset($_GET['po_id'])) {
    header('Content-Type: application/json');
    $po_id = intval($_GET['po_id']);

    try {
        $stmt = $pdo->prepare("
            SELECT 
                poi.id,
                poi.item_name,
                poi.sku,
                poi.category,
                poi.quantity,
                poi.unit,
                c.name AS category_name,
                por.received_quantity,
                por.cost,
                por.received_at
            FROM purchase_order_items poi
            LEFT JOIN inventory_categories c 
                ON c.code = poi.category AND c.is_deleted = 0
            LEFT JOIN purchase_order_receipts por 
                ON por.purchase_order_item_id = poi.id
            WHERE poi.purchase_order_id = ?
            ORDER BY poi.id ASC, por.received_at ASC
        ");
        $stmt->execute([$po_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'items' => $items
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// AJAX handler to cancel PO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_po' && isset($_POST['po_id'])) {
    header('Content-Type: application/json');
    $po_id = intval($_POST['po_id']);

    try {
        // Ensure it is pending before cancelling
        $stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$po_id]);
        $po = $stmt->fetch();

        if (!$po) {
            echo json_encode(['success' => false, 'message' => 'Purchase Order not found.']);
            exit;
        }

        if (!in_array($po['status'], ['Pending', 'Partially Received'])) {
            echo json_encode(['success' => false, 'message' => 'Only Pending or Partially Received orders can be cancelled.']);
            exit;
        }

        $update = $pdo->prepare("UPDATE purchase_orders SET status = 'Cancelled' WHERE id = ?");
        $update->execute([$po_id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch all POs joined with Supplier names
$stmt = $pdo->query("
    SELECT po.*, s.supplier_name, s.firm_name 
    FROM purchase_orders po
    LEFT JOIN suppliers s ON s.id = po.supplier_id
    WHERE po.is_deleted = 0
    ORDER BY po.id DESC
");
$orders = $stmt->fetchAll();

// Fetch metrics stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total_count,
        SUM(CASE WHEN status IN ('Pending','Partially Received') THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'Received' THEN 1 ELSE 0 END) AS received_count,
        COALESCE(SUM(total_amount), 0) AS total_value
    FROM purchase_orders 
    WHERE is_deleted = 0
")->fetch();

$pageTitle = "Purchase Orders - Sogasu";
$activePage = "purchase-orders";

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1rem;">

        <!-- Standard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Purchase Orders</h2>
                <p style="color: #64748b; margin-top: 0.25rem; margin-bottom: 0;">Raise and manage official purchase
                    orders to suppliers.</p>
            </div>
            <a href="add-purchase-order.php" class="btn btn-primary"
                style="background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s;">
                <i class="ri-add-line"></i> Raise Purchase Order
            </a>
        </div>

        <!-- Metrics Grid -->
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
            <!-- Total PO Value -->
            <div class="table-container metric-card"
                style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total
                        Ordered Value</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #3b82f6; margin-top: 0.5rem;">₹
                        <?= number_format($stats['total_value'], 2) ?>
                    </div>
                </div>
                <div
                    style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-money-rupee-circle-line"></i>
                </div>
            </div>

            <!-- Pending POs -->
            <div class="table-container metric-card"
                style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">
                        Pending Receipts</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #f59e0b; margin-top: 0.5rem;">
                        <?= intval($stats['pending_count']) ?>
                    </div>
                </div>
                <div
                    style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-time-line"></i>
                </div>
            </div>

            <!-- Received POs -->
            <div class="table-container metric-card"
                style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">
                        Received & Completed</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.5rem;">
                        <?= intval($stats['received_count']) ?>
                    </div>
                </div>
                <div
                    style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
            </div>

            <!-- Total PO Count -->
            <div class="table-container metric-card"
                style="padding: 1.25rem; margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total
                        POs Raised</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #8b5cf6; margin-top: 0.5rem;">
                        <?= intval($stats['total_count']) ?>
                    </div>
                </div>
                <div
                    style="width: 48px; height: 48px; border-radius: 12px; background: rgba(139, 92, 246, 0.1); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ri-file-list-3-line"></i>
                </div>
            </div>
        </div>

        <!-- Orders Table Container -->
        <div class="table-container" style="padding: 1.5rem;">
            <h3
                style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                PO Registry</h3>

            <div style="overflow-x: auto;">
                <table id="poTable" class="table">
                    <thead>
                        <tr>
                            <th>PO Details</th>
                            <th>Supplier Details</th>
                            <th>Raised Date</th>
                            <th>Expected Delivery</th>
                            <th>Total Cost</th>
                            <th>Status</th>
                            <th style="text-align: right;">Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $row): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #1e293b;">
                                        <?= htmlspecialchars($row['po_number']) ?>
                                    </div>
                                    <?php if ($row['status'] === 'Received' && !empty($row['invoice_no'])): ?>
                                        <div
                                            style="margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; font-size: 0.7rem; background: rgba(16, 185, 129, 0.08); color: #10b981; padding: 2px 6px; border-radius: 4px; font-weight: 600;">
                                            <i class="ri-file-text-line"></i>
                                            Inv: <?= htmlspecialchars($row['invoice_no']) ?>
                                            <?php if (!empty($row['invoice_file'])): ?>
                                                <a href="../uploads/invoices/<?= htmlspecialchars($row['invoice_file']) ?>"
                                                    target="_blank" title="View Invoice Copy"
                                                    style="color: #10b981; text-decoration: none; margin-left: 2px; display: inline-flex;">
                                                    <i class="ri-external-link-line" style="font-size: 0.75rem;"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #334155;">
                                        <?= htmlspecialchars($row['supplier_name']) ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                                        <?= htmlspecialchars($row['firm_name'] ?: 'N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #475569;">
                                        <?= date('d M Y', strtotime($row['order_date'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: #64748b;">
                                        <?= $row['delivery_date'] ? date('d M Y', strtotime($row['delivery_date'])) : '<span style="color:#94a3b8;">Not specified</span>' ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #4f46e5;">₹
                                        <?= number_format($row['total_amount'], 2) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status = $row['status'];
                                    $badgeStyle = '';
                                    if ($status === 'Pending') {
                                        $badgeStyle = 'background: rgba(245, 158, 11, 0.1); color: #f59e0b;';
                                    } elseif ($status === 'Partially Received') {
                                        $badgeStyle = 'background: rgba(59, 130, 246, 0.1); color: #3b82f6;';
                                    } elseif ($status === 'Received') {
                                        $badgeStyle = 'background: rgba(16, 185, 129, 0.1); color: #10b981;';
                                    } else {
                                        $badgeStyle = 'background: rgba(239, 68, 68, 0.1); color: #ef4444;';
                                    }
                                    ?>
                                    <span
                                        style="font-weight: 700; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.02em; <?= $badgeStyle ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div
                                        style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                        <button class="btn-icon-p"
                                            onclick="openDetailsModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['po_number'], ENT_QUOTES) ?>')"
                                            title="View Items Details" style="color: #4f46e5;">
                                            <i class="ri-eye-line"></i>
                                        </button>

                                        <?php if (in_array($status, ['Pending', 'Partially Received'])): ?>
                                            <a href="receive-po.php?id=<?= $row['id'] ?>" class="btn-icon-p"
                                                title="Receive Stock" style="color: #10b981;">
                                                <i class="ri-download-2-line"></i>
                                            </a>
                                            <button class="btn-icon-p"
                                                onclick="confirmCancel(<?= $row['id'] ?>, '<?= htmlspecialchars($row['po_number'], ENT_QUOTES) ?>')"
                                                title="Cancel Order" style="color: #ef4444;">
                                                <i class="ri-close-circle-line"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Details Modal -->
<div id="detailsModal" class="custom-modal" style="display: none;">
    <div class="custom-modal-card" style="max-width: 750px; width: 90%;">
        <div class="custom-modal-header" style="background: #4f46e5;">
            <h3 id="modalPoTitle" style="margin: 0; font-weight: 700; color: white;">PO Items Details</h3>
            <i class="ri-close-line" style="font-size: 1.5rem; cursor: pointer;" onclick="closeDetailsModal()"></i>
        </div>
        <div class="custom-modal-body" style="padding: 1.5rem;">
            <div style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                            <th style="padding: 10px 6px;">Item Description</th>
                            <th style="padding: 10px 6px;">Category</th>
                            <th style="padding: 10px 6px; text-align: right;">Ordered Qty</th>
                            <th style="padding: 10px 6px; text-align: right;">Received Qty</th>
                            <th style="padding: 10px 6px; text-align: right;">Unit Price</th>
                            <th style="padding: 10px 6px; text-align: right;">Received Total</th>
                            <th style="padding: 10px 6px; text-align: right;">Delivered At</th>
                        </tr>
                    </thead>
                    <tbody id="modalPoItemsBody">
                        <!-- Filled dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .btn-icon-p {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #f1f5f9;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        font-size: 1.1rem;
    }

    .btn-icon-p:hover {
        background: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border-color: #cbd5e1;
    }

    .metric-card {
        background: white;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px;
        transition: all 0.2s;
    }

    .metric-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        border-color: #cbd5e1 !important;
    }

    .custom-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(2px);
        transition: all 0.25s;
    }

    .custom-modal-card {
        background: #fff;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: scale(0.95);
        opacity: 0;
        transition: all 0.2s ease-out;
    }

    .custom-modal-header {
        color: #fff;
        padding: 1.25rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openDetailsModal(poId, poNumber) {
        const modal = document.getElementById('detailsModal');
        const card = modal.querySelector('.custom-modal-card');
        const tbody = document.getElementById('modalPoItemsBody');

        document.getElementById('modalPoTitle').textContent = 'PO Items: ' + poNumber;
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;"><i class="ri-loader-4-line ri-spin" style="font-size: 2rem;"></i></td></tr>';

        modal.style.display = 'flex';
        setTimeout(() => {
            card.style.transform = 'scale(1)';
            card.style.opacity = '1';
        }, 50);

        fetch('purchase-orders.php?action=get_po_items&po_id=' + poId)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    tbody.innerHTML = '';
                    data.items.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.style.borderBottom = '1px solid #f1f5f9';

                        const qty = parseFloat(item.quantity) || 0;
                        const recQty = parseFloat(item.received_quantity || 0);
                        const cost = parseFloat(item.cost || 0);
                        const total = recQty * cost;

                        let deliveredAt = 'N/A';
                        if (item.received_at) {
                            const dt = new Date(item.received_at);
                            deliveredAt =
                                dt.getDate().toString().padStart(2, '0') + '-' +
                                (dt.getMonth() + 1).toString().padStart(2, '0') + '-' +
                                dt.getFullYear() + ' ' +
                                dt.getHours().toString().padStart(2, '0') + ':' +
                                dt.getMinutes().toString().padStart(2, '0');
                        }
                        const categoryName = item.category_name || item.category || 'N/A';

                        tr.innerHTML = `
                            <td style="padding: 10px 6px;">
                                <div style="font-weight: 700; color: #1e293b;">${escapeHtml(item.item_name)}</div>
                                <div style="font-size: 0.72rem; color: #64748b; font-weight: 500;">SKU: ${escapeHtml(item.sku || 'N/A')}</div>
                            </td>
                            <td style="padding: 10px 6px;">
                                <span style="font-weight: 600; color: #475569; background: #f1f5f9; padding: 3px 8px; border-radius: 6px; font-size: 0.75rem;">${escapeHtml(categoryName)}</span>
                            </td>
                            <td style="padding: 10px 6px; text-align: right; font-weight: 700; color: #334155;">
                                ${qty} ${escapeHtml(item.unit)}
                            </td>
                            <td style="padding: 10px 6px; text-align: right; font-weight: 700; color: ${recQty > 0 ? '#10b981' : '#64748b'};">
                                ${recQty} ${escapeHtml(item.unit)}
                            </td>
                            <td style="padding: 10px 6px; text-align: right; font-weight: 700; color: #4f46e5;">
                                ₹ ${cost.toFixed(2)}
                            </td>
                            <td style="padding: 10px 6px; text-align: right; font-weight: 700; color: #0f172a;">
                                ₹ ${total.toFixed(2)}
                            </td>
                            <td style="padding: 10px 6px; font-weight: 600; color: #475569;">
                                ${deliveredAt}
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    if (data.items.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">No items in this purchase order.</td></tr>';
                    }
                } else {
                    closeDetailsModal();
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to fetch items.' });
                }
            })
            .catch(err => {
                closeDetailsModal();
                console.error(err);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network communication failed.' });
            });
    }

    function closeDetailsModal() {
        const modal = document.getElementById('detailsModal');
        const card = modal.querySelector('.custom-modal-card');

        card.style.transform = 'scale(0.95)';
        card.style.opacity = '0';
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }

    function confirmCancel(poId, poNumber) {
        Swal.fire({
            title: 'Cancel Purchase Order?',
            text: `Are you sure you want to cancel ${poNumber}? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Cancel it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Perform cancellation via AJAX post
                const formData = new FormData();
                formData.append('action', 'cancel_po');
                formData.append('po_id', poId);

                fetch('purchase-orders.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Cancelled!',
                                text: 'Purchase Order status has been updated to Cancelled.',
                                icon: 'success',
                                confirmButtonColor: '#4f46e5'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Failed to cancel the Purchase Order.',
                                icon: 'error',
                                confirmButtonColor: '#4f46e5'
                            });
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to communicate with server.' });
                    });
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    initializeDataTable('poTable', 'Purchase Orders');
</script>

<?php include 'includes/footer.php'; ?>