<?php
$pageTitle = "Reports - Sogasu";
$activePage = "reports";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="dashboard-container animate-fade-in" style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
            <div>
                <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.5rem; letter-spacing: -0.02em;">Business Intelligence</h1>
                <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500;">Analyze your business performance with advanced visual analytics.</p>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button class="btn-premium" style="background: white; color: var(--text-dark); border-color: #e2e8f0;"><i class="ri-calendar-line"></i> Select Period</button>
                <button class="btn-premium"><i class="ri-download-2-line"></i> Download PDF Report</button>
            </div>
        </div>

        <!-- Premium KPI Widgets -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2.5rem;">
            <div class="premium-stat-card card-income">
                <div class="stat-header">
                    <div class="stat-icon"><i class="ri-line-chart-line"></i></div>
                    <div class="stat-trend up"><i class="ri-arrow-up-s-line"></i> 12.5%</div>
                </div>
                <div class="stat-label">Net Profit (Annual)</div>
                <div class="stat-value">₹ 8,45,000</div>
                <div class="stat-footer">Growth vs previous year</div>
            </div>
            
            <div class="premium-stat-card card-primary">
                <div class="stat-header">
                    <div class="stat-icon"><i class="ri-user-heart-line"></i></div>
                    <div class="stat-trend up"><i class="ri-arrow-up-s-line"></i> 5%</div>
                </div>
                <div class="stat-label">Customer Retention</div>
                <div class="stat-value">68.4%</div>
                <div class="stat-footer">Based on 1,200+ clients</div>
            </div>
            
            <div class="premium-stat-card card-warning">
                <div class="stat-header">
                    <div class="stat-icon"><i class="ri-hand-coin-line"></i></div>
                    <div class="stat-trend down"><i class="ri-arrow-down-s-line"></i> 3.2%</div>
                </div>
                <div class="stat-label">Operating Costs</div>
                <div class="stat-value">₹ 2,14,000</div>
                <div class="stat-footer">Direct material & labor</div>
            </div>

            <div class="premium-stat-card card-expense">
                <div class="stat-header">
                    <div class="stat-icon"><i class="ri-shopping-bag-3-line"></i></div>
                    <div class="stat-trend up"><i class="ri-arrow-up-s-line"></i> 8.4%</div>
                </div>
                <div class="stat-label">Avg. Order Value</div>
                <div class="stat-value">₹ 3,450</div>
                <div class="stat-footer">Per transaction average</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Clients Analytics Graph -->
            <div class="glass-card" style="padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem;">Clients Analysis</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Comparison of new vs repeated clients</p>
                    </div>
                    <select class="glass-card" style="padding: 0.5rem 1rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); outline: none; border-color: #f1f5f9;">
                        <option>This Year</option>
                        <option>Last Year</option>
                    </select>
                </div>
                <div style="height: 350px;">
                    <canvas id="clientGraph"></canvas>
                </div>
            </div>

            <!-- Revenue vs Expenses Graph -->
            <div class="glass-card" style="padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem;">Financial Trajectory</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Monthly revenue against operational expenses</p>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; background: #f0fdf4; border-radius: 8px; font-size: 0.75rem; font-weight: 800; color: #16a34a;">
                            Revenue
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; background: #fff1f2; border-radius: 8px; font-size: 0.75rem; font-weight: 800; color: #e11d48;">
                            Expense
                        </div>
                    </div>
                </div>
                <div style="height: 350px;">
                    <canvas id="revenueGraph"></canvas>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    .card-income { --primary: #10b981; --primary-light: #d1fae5; }
    .card-primary { --primary: #4f46e5; --primary-light: #e0e7ff; }
    .card-warning { --primary: #f59e0b; --primary-light: #fef3c7; }
    .card-expense { --primary: #ef4444; --primary-light: #fee2e2; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Shared Chart Options
        const sharedOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1e293b',
                    bodyColor: '#64748b',
                    borderColor: '#f1f5f9',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true,
                    titleFont: { family: 'Outfit', size: 14, weight: 'bold' },
                    bodyFont: { family: 'Outfit', size: 13 }
                }
            },
            scales: {
                x: { 
                    grid: { display: false },
                    ticks: { font: { family: 'Outfit', size: 12, weight: '600' }, color: '#94a3b8' }
                },
                y: { 
                    grid: { color: '#f1f5f9', borderDash: [5, 5] },
                    ticks: { font: { family: 'Outfit', size: 12, weight: '600' }, color: '#94a3b8' }
                }
            }
        };

        // Client Graph
        const ctxClient = document.getElementById('clientGraph').getContext('2d');
        new Chart(ctxClient, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [
                    {
                        label: 'New Clients',
                        data: [45, 52, 48, 61, 55, 67],
                        backgroundColor: '#4f46e5',
                        borderRadius: 10,
                        barThickness: 15
                    },
                    {
                        label: 'Repeated Clients',
                        data: [20, 25, 30, 28, 35, 42],
                        backgroundColor: '#a5b4fc',
                        borderRadius: 10,
                        barThickness: 15
                    }
                ]
            },
            options: sharedOptions
        });

        // Revenue Graph
        const ctxRevenue = document.getElementById('revenueGraph').getContext('2d');
        const revenueGradient = ctxRevenue.createLinearGradient(0, 0, 0, 300);
        revenueGradient.addColorStop(0, 'rgba(16, 185, 129, 0.1)');
        revenueGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

        new Chart(ctxRevenue, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [
                    {
                        label: 'Revenue',
                        data: [120, 150, 140, 180, 210, 230, 250, 240, 260, 280, 300, 350],
                        borderColor: '#10b981',
                        borderWidth: 4,
                        pointBackgroundColor: 'white',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true,
                        backgroundColor: revenueGradient
                    },
                    {
                        label: 'Expenses',
                        data: [80, 90, 85, 110, 120, 130, 140, 135, 150, 160, 170, 190],
                        borderColor: '#ef4444',
                        borderWidth: 3,
                        borderDash: [5, 5],
                        pointRadius: 0,
                        tension: 0.4,
                        fill: false
                    }
                ]
            },
            options: sharedOptions
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
