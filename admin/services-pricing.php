<?php
$pageTitle = "Services & Pricing - Sogasu";
$activePage = "services";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                 <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Services & Pricing</h2>
                 <p class="text-muted">Manage standard rates for tailoring services</p>
            </div>
            <button class="btn btn-primary" onclick="window.location.href='add-service.php'"><i class="ri-add-line"></i> Add New Service</button>
        </div>
    </div>

    <!-- Stats -->
    <div class="cards-grid" style="grid-template-columns: repeat(4, 1fr); ">
        <div class="count-card card-blue">
            <h3>Total Services</h3>
            <div class="value"><?php 
                include_once '../includes/db.php';
                echo $pdo->query("SELECT COUNT(*) FROM services WHERE is_deleted=0")->fetchColumn(); 
            ?></div>
            <div class="trend up"><i class="ri-list-check"></i> Active</div>
        </div>
        <div class="count-card card-purple">
            <h3>Categories</h3>
            <div class="value"><?php echo $pdo->query("SELECT COUNT(*) FROM categories WHERE is_deleted=0 AND status='active'")->fetchColumn(); ?></div>
            <div class="trend up"><i class="ri-function-line"></i> Types</div>
        </div>
        <div class="count-card card-green">
            <h3>Avg. Order Value</h3>
            <div class="value">₹ 1,850</div>
            <div class="trend up"><i class="ri-money-rupee-circle-line"></i> Est.</div>
        </div>
        <div class="count-card card-orange">
            <h3>Pending Revisions</h3>
            <div class="value">2</div>
            <div class="trend down"><i class="ri-price-tag-3-line"></i> Updates</div>
        </div>
    </div>

    <!-- Pricing Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; align-items: start;">
        <?php
        $cats = $pdo->query("SELECT * FROM categories WHERE status='active' AND is_deleted=0")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cats as $cat):
            $stmt = $pdo->prepare("SELECT * FROM services WHERE category_id = ? AND is_deleted=0 ORDER BY id ASC");
            $stmt->execute([$cat['id']]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($services) > 0):
        ?>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
            <div style="padding: 1rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="<?= !empty($cat['icon']) ? htmlspecialchars($cat['icon']) : 'ri-function-line' ?>" style="color: var(--primary);"></i> <?= htmlspecialchars($cat['category_name']) ?>
                </h3>
                <button class="btn-icon-only"><i class="ri-more-2-fill"></i></button>
            </div>
            <div>
                <?php foreach ($services as $srv): ?>
                 <div class="price-row">
                    <span class="service-name"><?= htmlspecialchars($srv['service_name']) ?></span>
                    <span class="service-price" style="display: flex; align-items: center; gap: 1.5rem;">
                        <span>
                            <?php 
                            if ($srv['price_type'] == 'starting') echo 'Starting ₹ ';
                            elseif ($srv['price_type'] == 'fixed') echo '₹ ';
                            else echo '₹ ';
                            echo number_format($srv['base_price'], 2);
                            if ($srv['price_type'] == 'variable') echo ' /hr';
                            ?>
                        </span>
                        <div class="service-actions" style="display: flex; gap: 0.25rem;">
                            <a href="add-service.php?id=<?= $srv['id'] ?>" class="btn-icon-only" style="color: #6366f1; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;" title="Edit"><i class="ri-pencil-line"></i></a>
                            <button onclick="confirmDelete(<?= $srv['id'] ?>)" class="btn-icon-only" style="color: #ef4444; display: inline-flex; align-items: center; justify-content: center;" title="Delete"><i class="ri-delete-bin-line"></i></button>
                        </div>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; endforeach; ?>
        
        <?php 
        $totalServices = $pdo->query("SELECT COUNT(*) FROM services WHERE is_deleted=0")->fetchColumn();
        if ($totalServices == 0): 
        ?>
            <div style="grid-column: 1 / -1; background: white; padding: 3rem; text-align: center; border: 1px solid #e2e8f0; border-radius: 8px;">
                <i class="ri-price-tag-3-line" style="font-size: 3rem; color: #94a3b8; margin-bottom: 1rem; display: inline-block;"></i>
                <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.5rem;">No Services Added Yet</h3>
                <p style="color: #64748b; margin-bottom: 1.5rem;">Click the "Add New Service" button to start building your pricing list.</p>
                <button class="btn btn-primary" onclick="window.location.href='add-service.php'"><i class="ri-add-line"></i> Add New Service</button>
            </div>
        <?php endif; ?>
    </div>

</main>

<style>
    .price-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
    }
    .price-row:last-child {
        border-bottom: none;
    }
    .price-row:hover {
        background: #f8fafc;
    }
    .service-name {
        font-weight: 500;
        color: #334155;
        font-size: 0.95rem;
    }
    .service-price {
        font-weight: 600;
        color: #0f172a;
        font-size: 0.95rem;
    }
    .btn-icon-only {
        background: transparent;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 0.25rem;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .btn-icon-only:hover {
        background: #e2e8f0;
        color: #475569;
    }
</style>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This service will be removed!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "delete-service.php?id=" + id;
            }
        });
    }
</script>
