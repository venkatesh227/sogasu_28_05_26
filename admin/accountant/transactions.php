<?php
session_start();
$pageTitle = "Transactions - Accountant";
$activePage = "accountant-transactions";
include '../includes/header.php';
?>

<main class="main-content">
    <div style="padding: 1rem;">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Transactions Log</h2>
                <p class="text-muted">Detailed record of all financial movements</p>
            </div>
            <button class="btn btn-primary" onclick="window.location.href='add-transaction.php'">
                <i class="ri-add-line"></i> Add Transaction
            </button>
        </div>

        <div class="table-box" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Date</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Ref Type</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Ref ID</th>
                        <th style="padding: 1rem; text-align: left; color: #64748b; font-size: 0.85rem;">Description</th>
                        <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem;">Credit</th>
                        <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem;">Debit</th>
                        <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem;">Balance</th>
                        <th style="padding: 1rem; text-align: center; color: #64748b; font-size: 0.85rem;">Method</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem;">24 Feb 2026</td>
                        <td style="padding: 1rem;">Order</td>
                        <td style="padding: 1rem; font-family: monospace;">ORD-2458</td>
                        <td style="padding: 1rem;">Payment from Rekha</td>
                        <td style="padding: 1rem; text-align: right; color: #16a34a;">₹ 2,500</td>
                        <td style="padding: 1rem; text-align: right;">-</td>
                        <td style="padding: 1rem; text-align: right;">₹ 8,25,000</td>
                        <td style="padding: 1rem; text-align: center;">UPI</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
