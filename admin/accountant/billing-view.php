<?php
session_start();
$pageTitle = "Billing (View Only) - Accountant";
$activePage = "accountant-billing";
include '../includes/header.php';
?>

<main class="main-content">
    <div style="padding: 1rem;">
        <div class="page-header" style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Client Billing Overview</h2>
            <p class="text-muted">Read-only view of all client invoices</p>
        </div>

        <div class="table-box" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Order No</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Client</th>
                        <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem;">Total</th>
                        <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem;">Paid</th>
                        <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem;">Pending</th>
                        <th style="padding: 1rem; text-align: center; color: #64748b; font-size: 0.85rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem; font-weight: 600;">#ORD-2458</td>
                        <td style="padding: 1rem;">Mrs. Rekha</td>
                        <td style="padding: 1rem; text-align: right;">₹ 2,500</td>
                        <td style="padding: 1rem; text-align: right; color: #16a34a;">₹ 1,000</td>
                        <td style="padding: 1rem; text-align: right; color: #dc2626;">₹ 1,500</td>
                        <td style="padding: 1rem; text-align: center;"><span class="badge badge-warning">Pending</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<style>
    .badge { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .badge-warning { background: #fef3c7; color: #d97706; }
</style>

<?php include '../includes/footer.php'; ?>
