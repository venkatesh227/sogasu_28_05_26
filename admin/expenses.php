<?php
$pageTitle = "Expenses - Sogasu";
$activePage = "expenses";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="dashboard-container animate-fade-in" style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
            <div>
                <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.5rem; letter-spacing: -0.02em;">Expenses</h1>
                <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500;">Track and manage all business expenditures.</p>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button onclick="openCategoryModal()" class="btn-premium" style="background: white; color: var(--text-dark); border-color: #e2e8f0;"><i class="ri-folder-add-line"></i> Manage Categories</button>
                <button onclick="openExpenseModal()" class="btn-premium"><i class="ri-add-line"></i> Record New Expense</button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
            <!-- Expense Categories -->
            <div class="glass-card" style="padding: 0; overflow: hidden; border-radius: 24px;">
                <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-dark); margin: 0;">Expense Categories</h3>
                </div>
                <div style="padding: 1.5rem 2rem;">
                    <table class="premium-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="font-weight: 700; color: var(--text-dark);">Rent & Electricity</td>
                                <td style="text-align: right;">
                                    <button class="btn-icon-p"><i class="ri-pencil-line"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: 700; color: var(--text-dark);">Material Purchase</td>
                                <td style="text-align: right;">
                                    <button class="btn-icon-p"><i class="ri-pencil-line"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Monthly Summary -->
            <div class="glass-card" style="padding: 2rem; border-radius: 24px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-dark); margin-bottom: 1.5rem;">Monthly Summary (Feb)</h3>
                    
                    <div class="premium-stat-card card-expense" style="padding: 1.5rem; margin-bottom: 1.5rem; background: #fff1f2;">
                        <div class="stat-header">
                            <div class="stat-icon" style="background: white;"><i class="ri-hand-coin-line"></i></div>
                            <div class="stat-trend up" style="background: #e11d48; color: white;"><i class="ri-arrow-up-s-line"></i> High</div>
                        </div>
                        <div class="stat-label" style="color: #be123c;">Total Monthly Expenses</div>
                        <div class="stat-value" style="color: #9f1239;">₹ 45,800</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div style="padding: 1.25rem; background: #f0fdf4; border-radius: 16px; border: 1px solid #dcfce7;">
                        <p style="margin: 0; font-size: 0.75rem; color: #16a34a; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Paid</p>
                        <h3 style="margin: 0; color: #166534; font-weight: 800;">₹ 40,000</h3>
                    </div>
                    <div style="padding: 1.25rem; background: #fffbeb; border-radius: 16px; border: 1px solid #fef3c7;">
                        <p style="margin: 0; font-size: 0.75rem; color: #d97706; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Pending</p>
                        <h3 style="margin: 0; color: #92400e; font-weight: 800;">₹ 5,800</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="glass-card" style="padding: 0; overflow: hidden; border-radius: 24px;">
            <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin: 0;">Expense Transactions</h3>
            </div>
            
            <div style="padding: 1.5rem 2rem;">
                <table id="expenseTransactionsTable" class="premium-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Category</th>
                            <th>Method</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="font-weight: 600; color: var(--text-muted);">24 Feb 2026</td>
                            <td style="font-weight: 900; color: #e11d48; font-size: 1.1rem;">₹ 12,000</td>
                            <td style="font-weight: 700; color: var(--text-dark);">Shop Rent</td>
                            <td style="font-weight: 600; color: var(--text-muted);">Bank (HDFC)</td>
                            <td style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Feb Rent Paid - Receipt #88</td>
                            <td>
                                <span class="status-badge" style="background: #f0fdf4; color: #16a34a; border-color: #dcfce7;">Paid</span>
                            </td>
                            <td style="text-align: right;">
                                <button class="btn-icon-p"><i class="ri-more-2-fill"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Category Modal -->
<div id="categoryModal" class="premium-modal-overlay">
    <div class="glass-card premium-modal-content" style="max-width: 450px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="margin: 0; font-size: 1.5rem; font-weight: 800; color: var(--text-dark);">Add Category</h3>
            <button onclick="closeModal('categoryModal')" class="btn-icon-p"><i class="ri-close-line"></i></button>
        </div>
        
        <div style="margin-bottom: 2rem;">
            <label style="display: block; font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Category Name</label>
            <input type="text" placeholder="e.g. Marketing, Logistics" style="width: 100%; padding: 1.25rem; border: 1.5px solid #e2e8f0; border-radius: 16px; font-family: 'Outfit', sans-serif; font-size: 1rem; outline: none; background: #f8fafc; transition: all 0.2s;" onfocus="this.style.borderColor='var(--primary)'; this.style.background='white';">
        </div>
        
        <div style="display: flex; gap: 1rem;">
            <button onclick="closeModal('categoryModal')" class="btn-premium" style="flex: 1; background: #f1f5f9; color: var(--text-muted); border: none;">Cancel</button>
            <button class="btn-premium" style="flex: 1.5;">Save Category</button>
        </div>
    </div>
</div>

<!-- Expense Modal -->
<div id="expenseModal" class="premium-modal-overlay">
    <div class="glass-card premium-modal-content" style="max-width: 600px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="margin: 0; font-size: 1.5rem; font-weight: 800; color: var(--text-dark);">New Expense</h3>
            <button onclick="closeModal('expenseModal')" class="btn-icon-p"><i class="ri-close-line"></i></button>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Category</label>
                <select style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 14px; background: #f8fafc; outline: none; font-family: 'Outfit';">
                    <option>Select Category</option>
                    <option>Shop Rent</option>
                    <option>Material Purchase</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Payment Method</label>
                <select style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 14px; background: #f8fafc; outline: none; font-family: 'Outfit';">
                    <option>Cash</option>
                    <option>UPI</option>
                    <option>Bank Transfer</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Amount (₹)</label>
                <input type="number" placeholder="0.00" style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 14px; background: #f8fafc; outline: none;">
            </div>
            <div>
                <label style="display: block; font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Date</label>
                <input type="date" value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 14px; background: #f8fafc; outline: none; font-family: 'Outfit';">
            </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <label style="display: block; font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Remarks</label>
            <textarea rows="3" placeholder="Optional notes about this expense..." style="width: 100%; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 14px; background: #f8fafc; outline: none; resize: none; font-family: 'Outfit';"></textarea>
        </div>
        
        <div style="display: flex; gap: 1rem;">
            <button onclick="closeModal('expenseModal')" class="btn-premium" style="flex: 1; background: #f1f5f9; color: var(--text-muted); border: none;">Cancel</button>
            <button class="btn-premium" style="flex: 2;">Record Expense</button>
        </div>
    </div>
</div>

<style>
    .premium-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        animation: fadeIn 0.3s ease-out;
    }
    .premium-modal-content {
        width: 100%;
        padding: 2.5rem;
        animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .card-expense { --primary: #ef4444; --primary-light: #fee2e2; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
</style>

<script>
    function openCategoryModal() { document.getElementById('categoryModal').style.display = 'flex'; }
    function openExpenseModal() { document.getElementById('expenseModal').style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    
    // Close modal on click outside
    window.onclick = function(event) {
        if (event.target.classList.contains('premium-modal-overlay')) {
            event.target.style.display = 'none';
        }
    }

    $(document).ready(function() {
        initializeDataTable('expenseTransactionsTable', 'Expense Transactions');
    });
</script>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<?php include 'includes/footer.php'; ?>
