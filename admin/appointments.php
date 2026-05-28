<?php
$pageTitle = "Appointments - Sogasu";
$activePage = "appointments";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between;">
             <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Appointments</h2>
                <p class="text-muted">Manage trials, consultations and measurements</p>
            </div>
            <button class="btn btn-primary" onclick="window.location.href='add-appointment.php'"><i class="ri-calendar-check-line"></i> New Appointment</button>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem;">
        
        <!-- Appointments List -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            
             <!-- Section Header -->
             <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                 <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b;">Upcoming Schedule</h3>
                 <div style="display: flex; gap: 0.5rem;">
                    <button class="btn-filter active">Upcoming</button>
                    <button class="btn-filter">History</button>
                </div>
             </div>

             <!-- Item 1 -->
            <div class="appointment-card">
                <div class="appointment-date-box">
                    <div class="month">FEB</div>
                    <div class="day">24</div>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                             <h4 class="appointment-title">Measurements - Mrs. Rashmi</h4>
                             <div class="appointment-meta">
                                <span><i class="ri-time-line"></i> 11:00 AM - 11:30 AM</span>
                                <span><i class="ri-user-line"></i> New Customer</span>
                             </div>
                        </div>
                        <span class="badge badge-blue">Measurements</span>
                    </div>
                </div>
                <button class="btn-icon-only"><i class="ri-more-2-fill"></i></button>
            </div>

            <!-- Item 2 -->
            <div class="appointment-card">
                <div class="appointment-date-box">
                    <div class="month">FEB</div>
                    <div class="day">24</div>
                </div>
                <div style="flex: 1;">
                     <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                             <h4 class="appointment-title">Trial - Lehenga (Sneha J)</h4>
                             <div class="appointment-meta">
                                <span><i class="ri-time-line"></i> 04:00 PM - 04:45 PM</span>
                                <span><i class="ri-t-shirt-line"></i> Order #ORD-2410</span>
                             </div>
                        </div>
                        <span class="badge badge-orange">Trial Session</span>
                    </div>
                </div>
                 <button class="btn-icon-only"><i class="ri-more-2-fill"></i></button>
            </div>

            <!-- Item 3 -->
            <div class="appointment-card">
                 <div class="appointment-date-box">
                    <div class="month">FEB</div>
                    <div class="day">25</div>
                </div>
                <div style="flex: 1;">
                     <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                             <h4 class="appointment-title">Delivery Pickup - Anjali M</h4>
                             <div class="appointment-meta">
                                <span><i class="ri-time-line"></i> 10:00 AM - 10:15 AM</span>
                                <span><i class="ri-check-double-line"></i> Ready for Delivery</span>
                             </div>
                        </div>
                        <span class="badge badge-green">Pickup</span>
                    </div>
                </div>
                 <button class="btn-icon-only"><i class="ri-more-2-fill"></i></button>
            </div>

        </div>

        <!-- Sidebar: Calendar & Stats -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Calendar Widget -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b;">April 2026</h3>
                    <div style="display: flex; gap: 0.5rem;">
                        <i class="ri-arrow-left-s-line" style="cursor: pointer;"></i>
                        <i class="ri-arrow-right-s-line" style="cursor: pointer;"></i>
                    </div>
                </div>
                <div class="calendar-grid">
                    <div class="day-name">S</div><div class="day-name">M</div><div class="day-name">T</div><div class="day-name">W</div><div class="day-name">T</div><div class="day-name">F</div><div class="day-name">S</div>
                    <!-- Empty slots -->
                    <div class="day empty"></div><div class="day empty"></div><div class="day empty"></div>
                    <?php for($d=1; $d<=30; $d++): ?>
                        <div class="day <?= ($d == 24) ? 'active' : '' ?> <?= (in_array($d, [24, 25, 28])) ? 'has-booking' : '' ?>">
                            <?= $d ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <div style="margin-top: 1rem; font-size: 0.75rem; color: #64748b;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <span style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></span> Booked Slots
                    </div>
                </div>
            </div>

             <!-- Today's Summary -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; text-align: center;">
                <h3 style="font-size: 0.9rem; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Today's Count</h3>
                <div style="font-size: 3.5rem; font-weight: 700; color: var(--primary); line-height: 1;">2</div>
                <div style="color: #475569; font-size: 0.9rem; margin-top: 0.5rem;">Appointments Scheduled</div>
            </div>

        </div>

    </div>

</main>

<style>
    .appointment-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1.25rem;
        display: flex;
        gap: 1.25rem;
        align-items: center;
        transition: box-shadow 0.2s;
    }
    .appointment-card:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .appointment-date-box {
        text-align: center;
        padding: 0.75rem 0.5rem;
        background: #f8fafc;
        border-radius: 8px;
        min-width: 70px;
        border: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .appointment-date-box .month {
        font-size: 0.75rem; 
        color: #64748b; 
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .appointment-date-box .day {
        font-size: 1.5rem; 
        font-weight: 700; 
        color: #1e293b; 
        line-height: 1;
    }
    .appointment-title {
        font-size: 1.05rem; 
        font-weight: 600; 
        color: #1e293b;
        margin-bottom: 0.35rem;
    }
    .appointment-meta {
        font-size: 0.85rem; 
        color: #64748b; 
        display: flex; 
        flex-direction: column; 
        gap: 0.25rem;
    }
    .appointment-meta span {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .badge-blue { background: #eef2ff; color: #4338ca; }
    .badge-orange { background: #fff7ed; color: #c2410c; }
    .badge-green { background: #f0fdf4; color: #15803d; }

    .btn-icon-only {
        background: transparent;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: background 0.2s;
        align-self: flex-start;
    }
    .btn-icon-only:hover {
        background: #f1f5f9;
        color: #475569;
    }
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
        text-align: center;
    }
    .day-name {
        font-size: 0.7rem;
        font-weight: 700;
        color: #94a3b8;
        padding-bottom: 0.5rem;
    }
    .day {
        font-size: 0.8rem;
        padding: 0.5rem 0;
        border-radius: 4px;
        color: #475569;
        cursor: pointer;
    }
    .day:hover { background: #f1f5f9; }
    .day.active { background: var(--primary); color: white; }
    .day.has-booking { font-weight: 700; color: var(--primary); text-decoration: underline; }
    .day.empty { cursor: default; }
</style>

<?php include 'includes/footer.php'; ?>
