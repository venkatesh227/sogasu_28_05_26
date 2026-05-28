<?php
session_start();
$pageTitle = "Accountant Ledger - Sogasu";
$activePage = "accountant-ledger";
include '../includes/header.php';
?>

<main class="main-content">
    <div style="padding: 1rem;">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">General Ledger</h2>
                <p class="text-muted">Filtered financial records for audit</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <input type="date" class="form-control" style="width: 150px;">
                <select class="form-select" style="width: 150px;">
                    <option>All Types</option>
                    <option>Client Payment</option>
                    <option>Expense</option>
                </select>
                <button class="btn btn-primary">Filter</button>
            </div>
        </div>

        <div class="table-box" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Date</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Description</th>
                        <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem;">Credit</th>
                        <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem;">Debit</th>
                        <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem; font-size: 0.9rem;">24 Feb 2026</td>
                        <td style="padding: 1rem; font-size: 0.9rem;">Order Payment #ORD-2458</td>
                        <td style="padding: 1rem; text-align: right; color: #16a34a;">₹ 2,500</td>
                        <td style="padding: 1rem; text-align: right;">-</td>
                        <td style="padding: 1rem; text-align: right; font-weight: 600;">₹ 8,25,000</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem; font-size: 0.9rem;">24 Feb 2026</td>
                        <td style="padding: 1rem; font-size: 0.9rem;">Office Stationery Expense</td>
                        <td style="padding: 1rem; text-align: right;">-</td>
                        <td style="padding: 1rem; text-align: right; color: #dc2626;">₹ 350</td>
                        <td style="padding: 1rem; text-align: right; font-weight: 600;">₹ 8,22,500</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
