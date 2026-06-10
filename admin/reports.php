<?php
$pageTitle = "Reports - Sogasu";
$activePage = "reports";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1.5rem;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <div>
                <h2
                    style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem; letter-spacing: -0.02em;">
                    Business Intelligence</h1>
                    <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500;">Analyze your business
                        performance with advanced visual analytics.</p>
            </div>

            <div style="display:flex; align-items:center; gap:0.75rem;">
                <button style="
background:white;
border:1px solid #e2e8f0;
padding:10px 18px;
border-radius:8px;
font-weight:600;
color:#1e293b;
cursor:pointer;
"><i class="ri-calendar-line"></i> Select Period</button>
                <button style="
background:#4f46e5;
border:none;
padding:10px 18px;
border-radius:8px;
font-weight:600;
color:white;
cursor:pointer;
"></i> Download PDF Report</button>
            </div>
        </div>

        <!-- Premium KPI Widgets -->
        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1.25rem; margin-bottom:1.5rem;">

            <!-- Card 1 -->
            <div class="table-container" style="padding:1.25rem; border-radius:16px;">

                <div style="display:flex; justify-content:space-between; align-items:center;">

                    <div>
                        <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">
                            Net Profit
                        </div>

                        <div style="font-size:1.8rem; font-weight:800; margin-top:0.5rem; color:#4f46e5;">
                            ₹ 8,45,000
                        </div>
                    </div>

                    <div style="
            width:48px;
            height:48px;
            border-radius:12px;
            background:rgba(79,70,229,0.1);
            display:flex;
            align-items:center;
            justify-content:center;
            color:#4f46e5;
            font-size:1.4rem;
            ">
                        <i class="ri-line-chart-line"></i>
                    </div>

                </div>
            </div>

            <!-- Card 2 -->
            <div class="table-container" style="padding:1.25rem; border-radius:16px;">

                <div style="display:flex; justify-content:space-between; align-items:center;">

                    <div>
                        <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">
                            Customer Retention
                        </div>

                        <div style="font-size:1.8rem; font-weight:800; margin-top:0.5rem; color:#10b981;">
                            68.4%
                        </div>
                    </div>

                    <div style="
            width:48px;
            height:48px;
            border-radius:12px;
            background:rgba(16,185,129,0.1);
            display:flex;
            align-items:center;
            justify-content:center;
            color:#10b981;
            font-size:1.4rem;
            ">
                        <i class="ri-user-heart-line"></i>
                    </div>

                </div>
            </div>

            <!-- Card 3 -->
            <div class="table-container" style="padding:1.25rem; border-radius:16px;">

                <div style="display:flex; justify-content:space-between; align-items:center;">

                    <div>
                        <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">
                            Operating Costs
                        </div>

                        <div style="font-size:1.8rem; font-weight:800; margin-top:0.5rem; color:#f59e0b;">
                            ₹ 2,14,000
                        </div>
                    </div>

                    <div style="
            width:48px;
            height:48px;
            border-radius:12px;
            background:rgba(245,158,11,0.1);
            display:flex;
            align-items:center;
            justify-content:center;
            color:#f59e0b;
            font-size:1.4rem;
            ">
                        <i class="ri-hand-coin-line"></i>
                    </div>

                </div>
            </div>

            <!-- Card 4 -->
            <div class="table-container" style="padding:1.25rem; border-radius:16px;">

                <div style="display:flex; justify-content:space-between; align-items:center;">

                    <div>
                        <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">
                            Avg Order Value
                        </div>

                        <div style="font-size:1.8rem; font-weight:800; margin-top:0.5rem; color:#ef4444;">
                            ₹ 3,450
                        </div>
                    </div>

                    <div style="
            width:48px;
            height:48px;
            border-radius:12px;
            background:rgba(239,68,68,0.1);
            display:flex;
            align-items:center;
            justify-content:center;
            color:#ef4444;
            font-size:1.4rem;
            ">
                        <i class="ri-shopping-bag-3-line"></i>
                    </div>

                </div>
            </div>

        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- Clients Analytics Graph -->
            <div class="table-container" style="padding:1.5rem; border-radius:16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h3
                            style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem;">
                            Clients Analysis</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Comparison of new vs
                            repeated clients</p>
                    </div>
                    <select class="table-container" style="
padding:0.6rem 1rem;
border:1px solid #e2e8f0;
border-radius:8px;
background:white;
font-weight:600;
outline:none;
" font-size: 0.85rem; font-weight: 600; color: var(--text-dark); outline: none; border-color: #f1f5f9;">
                        <option>This Year</option>
                        <option>Last Year</option>
                    </select>
                </div>
                <div style="height: 350px;">
                    <canvas id="clientGraph"></canvas>
                </div>
            </div>

            <!-- Revenue vs Expenses Graph -->
            <div class="table-container" style="padding:1.5rem; border-radius:16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h3
                            style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem;">
                            Financial Trajectory</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Monthly revenue
                            against operational expenses</p>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <div
                            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; background: #f0fdf4; border-radius: 8px; font-size: 0.75rem; font-weight: 800; color: #16a34a;">
                            Revenue
                        </div>
                        <div
                            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; background: #fff1f2; border-radius: 8px; font-size: 0.75rem; font-weight: 800; color: #e11d48;">
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
<script>
    document.addEventListener('DOMContentLoaded', function () {
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