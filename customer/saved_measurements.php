<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$selectedCategoryId = $_GET['category_id'] ?? '';
$selectedSubCategoryId = $_GET['subcategory_id'] ?? '';
$pageTitle = "Enter Measurements - Sogasu";
$headerTitle = "Measurements";
$activePage = "new-order";

// Fetch categories and sub-categories
$categories = $pdo->query("SELECT id, category_name FROM categories WHERE status='active' ORDER BY category_name")->fetchAll();
$measurementFields = [];

if (!empty($selectedSubCategoryId)) {

    $stmt = $pdo->prepare("
    SELECT 
        mk.id,
        mk.key_name,
        mk.input_type
    FROM measurement_mapping mm
    INNER JOIN measurement_keys mk 
        ON mk.id = mm.key_id
    WHERE mm.sub_category_id = ?
    AND mk.status = 'active'
    ORDER BY mk.key_name ASC
");

    $stmt->execute([$selectedSubCategoryId]);

    $measurementFields = $stmt->fetchAll();
}

$savedData = [];
if (
    !empty($selectedCategoryId)
    &&
    !empty($selectedSubCategoryId)
) {

    $stmt = $pdo->prepare("
    SELECT data
    FROM customer_profiles
    WHERE user_id = ?
    AND section_type = 'measurements'
    AND JSON_EXTRACT(data, '$.category_id') = ?
    AND JSON_EXTRACT(data, '$.sub_category_id') = ?
    LIMIT 1
");

    $stmt->execute([
    $userId,
    $selectedCategoryId,
    $selectedSubCategoryId
]);

    $measurement = $stmt->fetch();

    if ($measurement) {

        $savedData = json_decode(
            $measurement['data'],
            true
        ) ?? [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCategoryId = $_POST['category_id'] ?? '';
    $selectedSubCategoryId = $_POST['sub_category_id'] ?? '';

    $stmt = $pdo->prepare("
    SELECT 
        mk.id,
        mk.key_name,
        mk.input_type
    FROM measurement_mapping mm
    INNER JOIN measurement_keys mk 
        ON mk.id = mm.key_id
    WHERE mm.sub_category_id = ?
    AND mk.status = 'active'
    ORDER BY mk.key_name ASC
");

    $stmt->execute([$selectedSubCategoryId]);

    $measurementFields = $stmt->fetchAll();
    $formData = [];

    $formData['category_id'] = $_POST['category_id'] ?? '';
    $formData['sub_category_id'] = $_POST['sub_category_id'] ?? '';
    foreach ($measurementFields as $field) {

        $key = trim($field['key_name']);

        $fieldName = str_replace(' ', '_', $key);

        if ($field['input_type'] == 'checkbox') {

            $formData[$key] =
                isset($_POST[$fieldName]) ? 1 : 0;

        } else {

            $formData[$key] =
                $_POST[$fieldName] ?? '';
        }
    }
    $stmt = $pdo->prepare("
    SELECT id
    FROM customer_profiles
    WHERE user_id = ?
    AND section_type = 'measurements'
    AND JSON_EXTRACT(data, '$.category_id') = ?
    AND JSON_EXTRACT(data, '$.sub_category_id') = ?
    LIMIT 1
");

    $stmt->execute([
    $userId,
    $formData['category_id'],
    $formData['sub_category_id']
]);

    $existingProfile = $stmt->fetch();

    if ($existingProfile) {

        $existingId = $existingProfile['id'];

        $stmt = $pdo->prepare("
        UPDATE customer_profiles
        SET data = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

        $stmt->execute([
            json_encode($formData),
            $existingId
        ]);

        $savedData = $formData;

    } else {

        $existingId = null;

        $stmt = $pdo->prepare("
        INSERT INTO customer_profiles
        (
            user_id,
            section_type,
            data,
            created_at
        )
        VALUES (?, 'measurements', ?, NOW())
    ");

        $stmt->execute([
            $userId,
            json_encode($formData)
        ]);

        $savedData = $formData;
    }
    header('Content-Type: application/json');
    $responseMessage = $existingId
        ? 'Measurements updated successfully!'
        : 'Measurements saved successfully!';

    $responseTitle = $existingId
        ? 'Updated!'
        : 'Saved!';

    echo json_encode([
        'success' => true,
        'title' => $responseTitle,
        'message' => $responseMessage
    ]);

    exit();
}
// FETCH MEASUREMENTS BASED ON SUB CATEGORY

include 'includes/header.php';
?>

<div class="container">

    <div class="card" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <a href="profile.php"
            style="background: var(--background); border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); text-decoration: none; font-size: 1.2rem;">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main);">Saved Measurements</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Store your body measurements for tailoring</p>
        </div>
    </div>

    <div class="card">
        <form id="measurementsForm" method="POST">
            <input type="hidden" name="measurement_exists" id="measurementExists" value="0">
            <!-- Category Selection Section -->
            <div style="margin-bottom: 2rem;">
                <h3
                    style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 2px solid var(--primary); padding-bottom: 0.75rem;">
                    <i class="ri-shirt-line"></i> Select Category & Sub-Category
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="input-label">Category</label>
                        <select name="category_id" id="categorySelect" class="form-input" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php
                                   echo (
                                       $selectedCategoryId == $category['id']
                                       ||
                                       ($savedData['category_id'] ?? '') == $category['id']
                                   )
                                       ? 'selected'
                                       : '';
                                   ?>>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="input-label">Sub-Category</label>
                        <select name="sub_category_id" id="subCategorySelect" class="form-input" required>
                            <option value="">Select Sub-Category</option>
                            <?php if (!empty($selectedCategoryId) || !empty($savedData['category_id'])): ?>
                                <?php
                                $subCategories = $pdo->prepare("SELECT id, name FROM sub_categories WHERE category_id = ? AND status = 'active' AND is_deleted = 0 ORDER BY name");
                                $subCategories->execute([
                                    $selectedCategoryId ?: $savedData['category_id']
                                ]);
                                $subs = $subCategories->fetchAll();
                                foreach ($subs as $sub):
                                    ?>
                                    <option value="<?php echo $sub['id']; ?>" <?php
                                       echo (
                                           $selectedSubCategoryId == $sub['id']
                                           ||
                                           ($savedData['sub_category_id'] ?? '') == $sub['id']
                                       )
                                           ? 'selected'
                                           : '';
                                       ?>>
                                        <?php echo htmlspecialchars($sub['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="section-title">
                <span>Body Measurements</span>
                <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">
                    (in inches)
                </span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">

                <?php foreach ($measurementFields as $field): ?>

                    <?php
                    $fieldKey = trim($field['key_name']);

                    $fieldName = str_replace(' ', '_', $fieldKey);

                    $savedValue =
                        $savedData[$fieldKey]
                        ?? $savedData[$fieldName]
                        ?? '';
                    ?>

                    <div>

                        <label class="input-label">
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $field['key_name']))) ?>
                        </label>

                        <input type="text" name="<?= htmlspecialchars($fieldName) ?>" class="form-input"
                            value="<?= htmlspecialchars($savedValue) ?>"
                            placeholder="Enter <?= ucwords(str_replace('_', ' ', $field['key_name'])) ?>">

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="section-title">Additional Notes</div>
            <textarea name="additional_notes" class="form-input" rows="4"
                placeholder="Any specific requirements regarding fit, design or style..."
                style="margin-bottom: 2rem;"><?php echo htmlspecialchars($savedData['additional_notes'] ?? ''); ?></textarea>

            <button type="submit" class="btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                <i class="ri-save-line"></i>
                <span id="measurementButtonText">Save Measurements</span>
            </button>
        </form>

    </div>

</div>

<style>
    .input-label {
        font-size: 0.85rem;
        color: var(--text-muted);
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    .form-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 1rem;
        font-family: inherit;
        background: var(--background);
        outline: none;
    }

    .form-input:focus {
        border-color: var(--primary);
        background: white;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

    document.getElementById('categorySelect').addEventListener('change', function () {

        const categoryId = this.value;

        window.location.href =
            'saved_measurements.php?category_id=' + categoryId;
    });

    document.getElementById('subCategorySelect').addEventListener('change', function () {

        const categoryId =
            document.getElementById('categorySelect').value;

        const subCategoryId = this.value;

        window.location.href =
            'saved_measurements.php?category_id='
            + categoryId
            + '&subcategory_id='
            + subCategoryId;
    });

    document.getElementById('measurementsForm')
        .addEventListener('submit', function (e) {

            e.preventDefault();

            const formData = new FormData(this);

            fetch('saved_measurements.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {

                    if (data.success) {

                        Swal.fire({
                            icon: 'success',
                            title: data.title,
                            text: data.message,
                            confirmButtonColor: '#db2777'
                        }).then(() => {

                            window.location.href =
                                "profile.php?section=measurements";
                        });
                    }
                })
                .catch(() => {

                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Failed to save measurements',
                        confirmButtonColor: '#db2777'
                    });
                });
        });

</script>

<?php include 'includes/bottom-nav.php'; ?>