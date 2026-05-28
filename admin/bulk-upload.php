<?php
session_start();
include '../includes/db.php';

// Handle Sample CSV Downloads
if (isset($_GET['download'])) {
    $type = $_GET['download'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_' . $type . '.csv"');
    $output = fopen('php://output', 'w');

    if ($type == 'categories') {
        fputcsv($output, ['category_name', 'description', 'status']);
        fputcsv($output, ['Bridal Wear', 'Heavy embroidery and wedding collections', 'active']);
        fputcsv($output, ['Daily Wear', 'Comfortable daily use outfits', 'active']);
    } elseif ($type == 'sub_categories') {
        fputcsv($output, ['category_name', 'sub_category_name', 'description', 'price', 'fabric', 'preparation_days', 'status']);
        fputcsv($output, ['Bridal Wear', 'Heavy Lehenga', 'Stitching for heavy wedding lehenga', '5500', 'Silk/Velvet', '15', 'active']);
        fputcsv($output, ['Daily Wear', 'Simple Kurti', 'Regular straight cut kurti', '450', 'Cotton', '3', 'active']);
    } elseif ($type == 'measurements') {
        fputcsv($output, ['sub_category_name', 'measurement_key']);
        fputcsv($output, ['Heavy Lehenga', 'Waist']);
        fputcsv($output, ['Heavy Lehenga', 'Length']);
        fputcsv($output, ['Simple Kurti', 'Chest']);
        fputcsv($output, ['Simple Kurti', 'Shoulder']);
    }
    fclose($output);
    exit();
}

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $type = $_POST['upload_type'];
    $file = $_FILES['csv_file']['tmp_name'];

    try {
        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            // Remove BOM if exists
            if (substr($header[0], 0, 3) == pack("CCC", 0xef, 0xbb, 0xbf)) {
                $header[0] = substr($header[0], 3);
            }
            
            $count = 0;
            $pdo->beginTransaction();

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if(count($header) !== count($data)) continue;
                $row = array_combine($header, $data);

                if ($type == 'categories') {
                    $stmt = $pdo->prepare("INSERT INTO categories (category_name, description, status) VALUES (?, ?, ?)");
                    $stmt->execute([$row['category_name'], $row['description'], $row['status']]);
                    $count++;
                } elseif ($type == 'sub_categories') {
                    // Find Category ID
                    $c_stmt = $pdo->prepare("SELECT id FROM categories WHERE category_name = ? AND is_deleted = 0");
                    $c_stmt->execute([$row['category_name']]);
                    $cat = $c_stmt->fetch();
                    if ($cat) {
                        $stmt = $pdo->prepare("INSERT INTO sub_categories (category_id, name, description, price, fabric, preparation_days, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$cat['id'], $row['sub_category_name'], $row['description'], $row['price'], $row['fabric'], $row['preparation_days'], $row['status']]);
                        $count++;
                    }
                } elseif ($type == 'measurements') {
                    // Find Sub Category ID
                    $sc_stmt = $pdo->prepare("SELECT id FROM sub_categories WHERE name = ? AND is_deleted = 0");
                    $sc_stmt->execute([$row['sub_category_name']]);
                    $scat = $sc_stmt->fetch();
                    if ($scat) {
                        $stmt = $pdo->prepare("INSERT INTO measurement_keys (sub_category_id, measurement_key) VALUES (?, ?)");
                        $stmt->execute([$scat['id'], $row['measurement_key']]);
                        $count++;
                    }
                }
            }
            $pdo->commit();
            fclose($handle);
            $success_msg = "Successfully uploaded $count records.";
        } else {
            $error_msg = "Failed to open CSV file.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error: " . $e->getMessage();
    }
}

$pageTitle = "Bulk Upload - Sogasu";
$activePage = "bulk-upload";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div >
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Bulk Upload Center</h2>
                <p style="color: #64748b; margin-top: 0.25rem;">Easily import categories, sub-categories, and measurement keys using CSV files.</p>
            </div>
            <button class="btn" onclick="history.back()"
                style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 1rem; color: #15803d; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="ri-checkbox-circle-line"></i> <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 1rem; color: #991b1b; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="ri-error-warning-line"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        
        <!-- Left Column: Upload Form -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Data Import</h3>
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">Upload Type <span style="color:red">*</span></label>
                        <select name="upload_type" required class="form-select">
                            <option value="categories">Categories</option>
                            <option value="sub_categories">Sub-Categories</option>
                            <option value="measurements">Measurement Keys</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">Select CSV File <span style="color:red">*</span></label>
                        <div style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 2rem; text-align: center; background: #f8fafc; transition: border-color 0.2s;" onmouseover="this.style.borderColor='#4f46e5'" onmouseout="this.style.borderColor='#cbd5e1'">
                            <i class="ri-file-upload-line" style="font-size: 2.5rem; color: #94a3b8; display: block; margin-bottom: 1rem;"></i>
                            <input type="file" name="csv_file" accept=".csv" required style="display: block; width: 100%; margin: 0 auto; cursor: pointer; color: #475569;">
                            <p style="font-size: 0.75rem; color: #64748b; margin-top: 1rem;">Supported format: .csv only</p>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-full" style="justify-content: center; width: 100%; padding: 0.75rem; font-size: 1rem; border-radius: 6px;">
                        <i class="ri-upload-cloud-2-line"></i> Start Import
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: Instructions & Samples -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Sample Templates</h3>
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem;">Please use these templates to ensure your data is formatted correctly before uploading.</p>
                
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <a href="?download=categories" class="sample-btn">
                        <span><i class="ri-folder-line" style="color: #4f46e5; margin-right: 0.5rem;"></i> Categories</span>
                        <i class="ri-download-2-line" style="color: #94a3b8;"></i>
                    </a>
                    <a href="?download=sub_categories" class="sample-btn">
                        <span><i class="ri-folders-line" style="color: #ec4899; margin-right: 0.5rem;"></i> Sub-Categories</span>
                        <i class="ri-download-2-line" style="color: #94a3b8;"></i>
                    </a>
                    <a href="?download=measurements" class="sample-btn">
                        <span><i class="ri-ruler-2-line" style="color: #f59e0b; margin-right: 0.5rem;"></i> Measurements</span>
                        <i class="ri-download-2-line" style="color: #94a3b8;"></i>
                    </a>
                </div>

                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0;">
                    <h5 style="font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 0.75rem;">Important Notes</h5>
                    <ul style="font-size: 0.8rem; color: #64748b; padding-left: 1.25rem; line-height: 1.6; margin: 0;">
                        <li>Do not change the header names in the CSV.</li>
                        <li>For Sub-Categories, the <strong>category_name</strong> must match an existing category in the system.</li>
                        <li>For Measurements, the <strong>sub_category_name</strong> must match an existing sub-category.</li>
                        <li>Status should be either 'active' or 'inactive'.</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</main>

<style>
    .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
    .form-label { font-size: 0.875rem; font-weight: 500; color: #334155; }
    .form-control, .form-select {
        padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; width: 100%; outline: none; transition: border-color 0.2s; font-family: inherit;
    }
    .form-control:focus, .form-select:focus { border-color: var(--primary); }
    
    .sample-btn {
        display: flex; align-items: center; justify-content: space-between; 
        padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; 
        border-radius: 6px; text-decoration: none; color: #1e293b; 
        font-size: 0.9rem; font-weight: 500; transition: all 0.2s;
    }
    .sample-btn:hover {
        border-color: #cbd5e1;
        background: #f1f5f9;
        transform: translateY(-1px);
    }
</style>

<?php include 'includes/footer.php'; ?>
