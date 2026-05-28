<?php
include '../includes/db.php';
$pageTitle = "Measurement Reference - Sogasu";
$activePage = "measurement";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Measurement Configuration</h2>
                <p class="text-muted">Configure default keys for garment types</p>
            </div>
            <button class="btn btn-primary" onclick="window.location.href='add-measurement-key.php'"><i class="ri-add-line"></i> Add New Key</button>
        </div>
    </div>
    <div id="error-box" style="display:none; background:#fef3c7; color:#92400e; padding:12px 16px; border:1px solid #fde68a; border-radius:6px;  font-size:0.9rem;">
    </div>
    <!-- Filter Section (Selection) -->
    <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;  display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 1.5rem; align-items: end;">
        <div class="form-group">
            <label class="form-label" style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem;">Select Category</label>
            <select class="form-select" id="cat-select">
                <option value="">Select</option>
                <?php
                $selectedCat = $_GET['cat'] ?? '';

                $cats = $pdo->query("SELECT id, category_name FROM categories WHERE status='active' AND is_deleted = 0")->fetchAll();
                foreach ($cats as $cat) {
                    $selected = ($selectedCat == $cat['id']) ? 'selected' : '';
                    echo "<option value='{$cat['id']}' $selected>{$cat['category_name']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <?php if (!empty($_GET['cat'])): $subCatId = $_GET['sub_cat'] ?? '';
                $stmt = $pdo->prepare("SELECT id, name FROM sub_categories WHERE category_id=? AND status='active' AND is_deleted = 0");
                $stmt->execute([$_GET['cat']]);
            ?>
            <label class="form-label" style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem;">Select Sub Category</label>
                <select class="form-select" id="sub-cat-select">
                    <option value="">Select Sub Category</option>
                    <?php while ($row = $stmt->fetch()): $selected = ($subCatId == $row['id']) ? 'selected' : ''; ?>
                        <option value="<?= $row['id'] ?>" <?= $selected ?>><?= $row['name'] ?></option>
                    <?php endwhile; ?>
                </select>
            <?php else: ?>
                <select class="form-select" id="sub-cat-select">
                    <option value="">Select Sub Category</option>
                </select>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <button class="btn" style="background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155;">Load Configuration</button>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">

        <!-- Measurement List for Selected Combination -->
        <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b;">Active Measurement Keys</h3>
                <span class="badge" style="background: #eef2ff; color: #4f46e5;">Blouse > Normal Blouse</span>
            </div>

            <div class="key-list">
                <?php
                if (!empty($_GET['sub_cat'])) {

                    $sub_cat_id = $_GET['sub_cat'];

                    $stmt = $pdo->prepare("
                        SELECT mm.id, mk.key_name 
                        FROM measurement_mapping mm
                        JOIN measurement_keys mk ON mk.id = mm.key_id
                        WHERE mm.sub_category_id = ?
                    ");
                    $stmt->execute([$sub_cat_id]);

                    while ($row = $stmt->fetch()) {
                ?>
                        <div class="key-item">
                            <div style="flex:1;">
                                <span><?= $row['key_name'] ?></span>
                            </div>

                            <button class="icon-btn text-danger delete-btn" data-id="<?= $row['id'] ?>">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                <?php }
                } ?>
            </div>
        </div>

        <!-- Available Keys Pool -->
        <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px; align-self: start;">
            <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Available Keys</h3>
            <div class="search-bar" style="margin-bottom: 1rem; width: 100%;">
                <i class="ri-search-line"></i>
                <input type="text" placeholder="Search keys...">
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 400px; overflow-y: auto;">
                <div>
                    <?php
                    $sub_cat_id = $_GET['sub_cat'] ?? 0;

                    $stmt = $pdo->prepare("
                        SELECT * FROM measurement_keys 
                        WHERE is_deleted = 0 
                        AND status='active' 
                        AND id NOT IN (
                            SELECT key_id FROM measurement_mapping WHERE sub_category_id=?
                        )
                    ");
                    $stmt->execute([$sub_cat_id]);
                    $stmt->execute([$sub_cat_id]);

                    $keys = $stmt->fetchAll();
                    foreach ($keys as $key) {
                        echo "<button class='btn-pool' data-id='{$key['id']}'> <i class='ri-add-line'></i> {$key['key_name']}</button>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    .key-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .key-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem 1rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        transition: all 0.2s;
    }

    .key-item:hover {
        border-color: #cbd5e1;
        background: #f1f5f9;
    }

    .icon-btn {
        border: none;
        background: transparent;
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 4px;
        transition: background 0.2s;
    }

    .icon-btn:hover {
        background: #e2e8f0;
    }

    .text-primary {
        color: var(--primary);
    }

    .text-danger {
        color: #ef4444;
    }

    .btn-pool {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        padding: 0.6rem 1rem;
        background: white;
        border: 1px dashed #cbd5e1;
        border-radius: 6px;
        cursor: pointer;
        color: #64748b;
        font-size: 0.9rem;
        text-align: left;
        transition: all 0.2s;
        margin-bottom: 8px;
    }

    .btn-pool:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #f0fdfa;
        /* Light tint match */
    }

    .form-control,
    .form-select {
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.95rem;
        width: 100%;
        outline: none;
    }

    .available-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        document.getElementById('cat-select').addEventListener('change', function() {
            let catId = this.value;

            fetch('get-subcategories.php?cat_id=' + catId)
                .then(res => res.text())
                .then(data => {
                    document.getElementById('sub-cat-select').innerHTML = data;
                });
        });

        document.querySelectorAll('.btn-pool').forEach(btn => {
            btn.addEventListener('click', function() {

                let keyId = this.dataset.id;
                let subCat = document.getElementById('sub-cat-select').value;
                let cat = document.getElementById('cat-select').value;

                let errorBox = document.getElementById('error-box');

                // reset
                errorBox.style.display = "none";

                if (!cat || !subCat) {

                    errorBox.innerText = "Please select Category and Sub Category";
                    errorBox.style.display = "block";

                    return;
                }

                fetch('add-mapping.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `key_id=${keyId}&sub_cat=${subCat}&cat=${cat}`
                }).then(() => location.reload());

            });
        });

        document.getElementById('sub-cat-select').addEventListener('change', function() {
            let sub = this.value;
            let cat = document.getElementById('cat-select').value;

            if (sub) {
                window.location.href = "?cat=" + cat + "&sub_cat=" + sub;
            }
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {

                let id = this.dataset.id;

                fetch('delete-mapping.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${id}`
                }).then(() => location.reload());
            });
        });

    });
</script>

<?php include 'includes/footer.php'; ?>