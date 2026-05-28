<?php
session_start();
$pageTitle = "Add Transaction - Accountant";
$activePage = "accountant-transactions";
include '../includes/header.php';
?>

<main class="main-content">
    <div style="padding: 1rem;">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Add Manual Transaction</h2>
                <p class="text-muted">Record ledger entries manually</p>
            </div>
            <button class="btn" onclick="history.back()" style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>

        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; max-width: 600px; margin: 0 auto;">
            <form>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select class="form-select">
                            <option value="credit">Credit (IN)</option>
                            <option value="debit">Debit (OUT)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label">Amount</label>
                        <input type="number" class="form-control" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select">
                            <option>Cash</option>
                            <option>UPI</option>
                            <option>Bank Transfer</option>
                            <option>Card</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Reference (Order # / Bill #)</label>
                    <input type="text" class="form-control" placeholder="e.g. #ORD-2458">
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label class="form-label">Remarks / Description</label>
                    <textarea class="form-control" rows="3" placeholder="Describe the transaction..."></textarea>
                </div>

                <button class="btn btn-primary" style="width: 100%; justify-content: center; font-size: 1rem; padding: 0.8rem;">
                    Save Transaction Entry
                </button>
            </form>
        </div>
    </div>
</main>

<style>
    .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
    .form-label { font-size: 0.9rem; font-weight: 600; color: #475569; }
</style>

<?php include '../includes/footer.php'; ?>
