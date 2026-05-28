<?php
session_start();
$pageTitle = "Expenses (View Only) - Accountant";
$activePage = "accountant-expenses";
include '../includes/header.php';
?>

<main class="main-content">
    <div style="padding: 1rem;">
        <div class="page-header" style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Business Expenses</h2>
            <p class="text-muted">Read-only view of all expenditures</p>
        </div>

        <div class="table-box" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Date</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Category</th>
                        <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem;">Amount</th>
                        <th style="padding: 1rem; text-align: center; color: #64748b; font-size: 0.85rem;">Method</th>
                        <th style="padding: 1rem; text-align: center; color: #64748b; font-size: 0.85rem;">Status</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem;">24 Feb 2026</td>
                        <td style="padding: 1rem;">Shop Rent</td>
                        <td style="padding: 1rem; text-align: right; font-weight: 600;">₹ 12,000</td>
                        <td style="padding: 1rem; text-align: center;">Bank Transfer</td>
                        <td style="padding: 1rem; text-align: center;"><span class="badge badge-success">Paid</span></td>
                        <td style="padding: 1rem; color: #64748b; font-size: 0.85rem;">Feb Rent</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<style>
    .badge { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .badge-success { background: #dcfce7; color: #16a34a; }
</style>

<?php include '../includes/footer.php'; ?>
