<?php
$pageTitle = "Order Placed - Sogasu";
include 'includes/header.php';
?>

<div class="container" style="display: flex; flex-direction: column; items-center; justify-content: center; min-height: 80vh; text-align: center;">
    
    <div style="background: #fdf2f8; width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; color: var(--primary);">
        <i class="ri-check-line" style="font-size: 4rem;"></i>
    </div>
    
    <h2 style="font-size: 2rem; font-weight: 700; color: var(--text-main); margin-bottom: 1rem;">Order Placed!</h2>
    
    <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 2rem; line-height: 1.6;">
        Your appointment has been booked successfully.<br>Order ID: #2468
    </p>

    <div style="background: var(--surface); padding: 1.5rem; border-radius: 16px; border: 1px solid var(--border); margin-bottom: 2rem; text-align: left;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span style="color: var(--text-muted);">Date</span>
            <span style="font-weight: 600;"><?php echo date('M d, Y'); ?></span>
        </div>
         <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span style="color: var(--text-muted);">Time</span>
            <span style="font-weight: 600;">10:00 AM</span>
        </div>
         <div style="display: flex; justify-content: space-between;">
            <span style="color: var(--text-muted);">Type</span>
            <span style="font-weight: 600;">Home Visit</span>
        </div>
    </div>

    <a href="dashboard.php" class="btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
        Go to Dashboard
    </a>

</div>

<?php include 'includes/bottom-nav.php'; ?>
