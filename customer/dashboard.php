<?php
$pageTitle = "Home - Sogasu";
$headerTitle = "Sogasu";
$activePage = "dashboard";
include 'includes/header.php';
?>

<div class="container">
    
    <!-- Hero Banner -->
    <div style="background: linear-gradient(135deg, var(--primary), #f472b6); color: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; position: relative; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(219, 39, 119, 0.3);">
        <div style="position: relative; z-index: 10;">
            <div style="font-size: 0.9rem; margin-bottom: 0.5rem; opacity: 0.9;">New Collection</div>
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; line-height: 1.2;">Design Your Dream<br>Outfit Today</h2>
            <a href="new-order.php" style="background: white; color: var(--primary); padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-block;">Book Appointment</a>
        </div>
        <i class="ri-t-shirt-air-line" style="position: absolute; right: -20px; bottom: -20px; font-size: 8rem; opacity: 0.2; transform: rotate(-15deg);"></i>
    </div>

    <!-- Active Orders -->
    <div class="section-title">
        <span>Active Orders</span>
        <a href="my-orders.php" style="color: var(--primary); font-size: 0.85rem; text-decoration: none;">View All</a>
    </div>

    <div class="card">
        <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
            <div style="width: 60px; height: 60px; background: var(--background); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                <i class="ri-t-shirt-line" style="font-size: 1.5rem;"></i>
            </div>
            <div style="flex: 1;">
                 <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                    <div style="font-weight: 600; color: var(--text-main);">Silk Blouse</div>
                    <span class="badge progress">Stitching</span>
                 </div>
                 <div style="color: var(--text-muted); font-size: 0.85rem;">
                     Order #2458 • Expected Feb 15
                 </div>
            </div>
        </div>
        
        <!-- Progress Steps -->
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem;">
            <div style="flex: 1; height: 4px; background: var(--border); border-radius: 2px;">
                <div style="width: 100%; height: 100%; background: var(--success); border-radius: 2px;"></div>
            </div>
             <div style="flex: 1; height: 4px; background: var(--border); border-radius: 2px;">
                <div style="width: 100%; height: 100%; background: var(--success); border-radius: 2px;"></div>
            </div>
             <div style="flex: 1; height: 4px; background: var(--border); border-radius: 2px;">
                <div style="width: 50%; height: 100%; background: var(--primary); border-radius: 2px;"></div>
            </div>
             <div style="flex: 1; height: 4px; background: var(--border); border-radius: 2px;"></div>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">
            <span>Cut</span>
            <span>Design</span>
            <span style="color: var(--primary); font-weight: 600;">Stitch</span>
            <span>Finish</span>
        </div>
    </div>


    <!-- Services / Categories -->
    <div class="section-title">Explore Services</div>
    
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
        <div class="card" style="padding: 1rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
            <div style="width: 50px; height: 50px; background: #fdf2f8; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); margin-bottom: 0.25rem;">
                <i class="ri-women-line" style="font-size: 1.5rem;"></i>
            </div>
            <div style="font-weight: 600; font-size: 0.95rem;">Blouses</div>
        </div>
        <div class="card" style="padding: 1rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
             <div style="width: 50px; height: 50px; background: #fff7ed; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #c2410c; margin-bottom: 0.25rem;">
                <i class="ri-magic-line" style="font-size: 1.5rem;"></i>
            </div>
            <div style="font-weight: 600; font-size: 0.95rem;">Lehengas</div>
        </div>
        <div class="card" style="padding: 1rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
             <div style="width: 50px; height: 50px; background: #f0fdf4; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #15803d; margin-bottom: 0.25rem;">
                <i class="ri-scissors-2-line" style="font-size: 1.5rem;"></i>
            </div>
            <div style="font-weight: 600; font-size: 0.95rem;">Alterations</div>
        </div>
         <div class="card" style="padding: 1rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
             <div style="width: 50px; height: 50px; background: #eef2ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #4338ca; margin-bottom: 0.25rem;">
                <i class="ri-vip-diamond-line" style="font-size: 1.5rem;"></i>
            </div>
            <div style="font-weight: 600; font-size: 0.95rem;">Aari Work</div>
        </div>
    </div>

</div>

<?php include 'includes/bottom-nav.php'; ?>
