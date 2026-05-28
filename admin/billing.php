<?php
$pageTitle = "Billing - Sogasu";
$activePage = "billing";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1rem;">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Billing & Invoices</h2>
                <p class="text-muted">Manage client bills and payments</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button class="btn" style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                    <i class="ri-download-line"></i> Export PDF
                </button>
                <button class="btn btn-primary">
                    <i class="ri-add-line"></i> Create New Bill
                </button>
            </div>
        </div>

        <div class="table-box" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
            <div style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                <div class="search-bar" style="width: 300px; position: relative;">
                    <i class="ri-search-line" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                    <input type="text" class="form-control" style="padding-left: 2.5rem;" placeholder="Search by Order No or Client Name...">
                </div>
                <select class="form-select" style="width: 150px;">
                    <option>All Status</option>
                    <option>Pending</option>
                    <option>Received</option>
                    <option>Closed</option>
                    <option>Cancelled</option>
                </select>
            </div>

<table id="billingTable" style="width:100%; border-collapse:collapse;">
                    <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Order No</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Client Name</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Due Date</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Advance Paid</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Pending</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Total Payable (incl. GST)</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Status</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem; font-weight: 600; color: #4f46e5;">#ORD-2458</td>
                        <td style="padding: 1rem; font-size: 0.9rem; color: #1e293b;">Mrs. Rekha</td>
                        <td style="padding: 1rem; font-size: 0.9rem; color: #64748b;">30 Apr 2026</td>
                        <td style="padding: 1rem; font-size: 0.9rem; color: #16a34a;">₹ 1,000</td>
                        <td style="padding: 1rem; font-size: 0.9rem; color: #dc2626; font-weight: 600;">₹ 1,500</td>
                        <td style="padding: 1rem; font-size: 1rem; font-weight: 700; color: #1e293b;">₹ 2,500</td>
                        <td style="padding: 1rem;"><span class="badge badge-warning">Pending</span></td>
                        <td style="padding: 1rem;">
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn-icon" title="View Bill"><i class="ri-eye-line"></i></button>
                                <button class="btn-icon" title="Download PDF"><i class="ri-file-pdf-line"></i></button>
                                <button class="btn-icon" title="Receive Payment"><i class="ri-money-rupee-circle-line"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem; font-weight: 600; color: #4f46e5;">#ORD-2457</td>
                        <td style="padding: 1rem; font-size: 0.9rem; color: #1e293b;">Ms. Kavya</td>
                        <td style="padding: 1rem; font-size: 0.9rem; color: #64748b;">25 Apr 2026</td>
                        <td style="padding: 1rem; font-size: 0.9rem; color: #16a34a;">₹ 5,000</td>
                        <td style="padding: 1rem; font-size: 0.9rem; color: #16a34a; font-weight: 600;">₹ 0</td>
                        <td style="padding: 1rem; font-size: 1rem; font-weight: 700; color: #1e293b;">₹ 5,000</td>
                        <td style="padding: 1rem;"><span class="badge badge-success">Received</span></td>
                        <td style="padding: 1rem;">
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn-icon"><i class="ri-eye-line"></i></button>
                                <button class="btn-icon"><i class="ri-file-pdf-line"></i></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<style>
    .badge {
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .badge-warning { background: #fef3c7; color: #d97706; }
    .badge-success { background: #dcfce7; color: #16a34a; }
    .badge-danger { background: #fee2e2; color: #dc2626; }
    .badge-secondary { background: #f1f5f9; color: #64748b; }
    
    .btn-icon {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 6px;
        border-radius: 6px;
        cursor: pointer;
        color: #64748b;
        transition: all 0.2s;
    }
    .btn-icon:hover {
        background: #eef2ff;
        color: #4f46e5;
        border-color: #c7d2fe;
    }
</style>
<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>

initializeDataTable(
    'billingTable',
    'Billing & Invoices'
);

</script>
<?php include 'includes/footer.php'; ?>
