<?php
$pageTitle = "Gallery - Sogasu";
$activePage = "gallery";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1rem;">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Gallery</h2>
                <p class="text-muted">Explore and manage all media assets</p>
            </div>
            <button class="btn btn-primary">
                <i class="ri-upload-cloud-2-line"></i> Upload Media
            </button>
        </div>

        <div class="gallery-tabs" style="display: flex; gap: 2rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 2rem;">
            <button class="gallery-tab active" onclick="switchTab(this, 'client')">Client Images</button>
            <button class="gallery-tab" onclick="switchTab(this, 'product')">Product Images</button>
            <button class="gallery-tab" onclick="switchTab(this, 'reference')">Reference Images</button>
        </div>

        <div id="gallery-grid" class="gallery-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
            <!-- Images will be filtered/loaded here -->
            <?php for($i=1; $i<=8; $i++): ?>
            <div class="gallery-item">
                <img src="https://picsum.photos/400/400?random=<?php echo $i; ?>" alt="Gallery Image" style="width: 100%; border-radius: 8px;">
                <div class="gallery-info">
                    <span>Image_<?php echo $i; ?>.jpg</span>
                    <div style="display: flex; gap: 0.5rem;">
                        <i class="ri-download-line"></i>
                        <i class="ri-delete-bin-line"></i>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</main>

<style>
    .gallery-tab {
        background: none;
        border: none;
        padding: 0.75rem 0;
        font-size: 1rem;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        position: relative;
    }
    .gallery-tab.active {
        color: var(--primary);
    }
    .gallery-tab.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 2px;
        background: var(--primary);
    }

    .gallery-item {
        position: relative;
        overflow: hidden;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
        cursor: pointer;
    }
    .gallery-item:hover {
        transform: scale(1.02);
    }
    .gallery-info {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        background: linear-gradient(transparent, rgba(0,0,0,0.7));
        padding: 1rem;
        color: white;
        font-size: 0.8rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .gallery-item:hover .gallery-info {
        opacity: 1;
    }
</style>

<script>
    function switchTab(el, type) {
        document.querySelectorAll('.gallery-tab').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
        // Type logic here - in real app, fetch from server or filter JS object
        console.log("Switching to: " + type);
    }
</script>

<?php include 'includes/footer.php'; ?>
