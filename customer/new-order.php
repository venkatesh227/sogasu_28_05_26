<?php
$pageTitle = "Book Appointment - Sogasu";
$headerTitle = "New Request";
$activePage = "new-order";
$selectedCategoryId = $_GET['category_id'] ?? null;
include '../includes/db.php';

$query = "SELECT * FROM categories WHERE status = 'active' AND is_deleted = 0";
$stmt = $pdo->prepare($query);
$stmt->execute();
$result = $stmt->fetchAll();

$subCategories = [];
if ($selectedCategoryId) {
    $subQuery = "SELECT * FROM sub_categories 
                 WHERE category_id = :cat_id 
                 AND status = 'active' 
                 AND is_deleted = 0";

    $stmt = $pdo->prepare($subQuery);
    $stmt->execute(['cat_id' => $selectedCategoryId]);
    $subCategories = $stmt->fetchAll();
}
include 'includes/header.php';
?>

<div class="container">
    <div class="card">
        <div class="section-title">What would you like to stitch? Category</div>
        <div class="category-grid">
            <?php foreach ($result as $row) { ?>
                <label class="service-card <?php echo ($row['id'] == $selectedCategoryId) ? 'selected' : ''; ?>">
                    <input type="radio" name="service"
                        value="<?php echo $row['id']; ?>"
                        <?php echo ($row['id'] == $selectedCategoryId) ? 'checked' : ''; ?>>
                    <div class="icon-box">
                        <i class="<?php echo !empty($row['icon']) ? $row['icon'] : 'ri-folder-line'; ?>"></i>
                    </div>
                    <span><?php echo $row['category_name']; ?></span>
                </label>
            <?php } ?>
        </div>
        <div class="error" id="categoryError"></div>
        <div class="section-title">Sub Categories</div>
        <select id="subCategory" class="form-input">
            <option value="">Select Sub Category</option>
        </select>
        <div class="error" id="subCategoryError"></div>
        <div class="section-title">Preferred Visit Type</div>
        <div class="visit-type">
            <label class="visit-card">
                <input type="radio" name="visit_type" value="home" checked>
                <div class="visit-left">
                    <div class="visit-title">Home Visit</div>
                    <div class="visit-desc">Our tailor visits your home for measurements</div>
                </div>
                <div class="visit-price">₹ 100</div>
            </label>
            <label class="visit-card">
                <input type="radio" name="visit_type" value="store">
                <div class="visit-left">
                    <div class="visit-title">Store Visit</div>
                    <div class="visit-desc">Visit our boutique at MG Road</div>
                </div>
                <div class="visit-price free">Free</div>
            </label>
        </div>
        <div class="section-title">Select Date & Time</div>
        <div class="date-time-row">
            <!-- DATE -->
            <div class="date-box">
                <label>Date</label>
                <input type="date" id="dateInput" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                <div class="error" id="dateError"></div>
            </div>
            <!-- TIME -->
            <div class="time-box">
                <label>Time</label>
                <input type="time" id="timeInput" class="form-input">
                <div class="error" id="timeError"></div>
            </div>
        </div>
<<<<<<< Updated upstream

        <button class="btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;" id="proceedToMeasurementsBtn">
            Proceed to Measurements
        </button>
=======
        <button type="button" id="proceedBtn" class="btn-primary" style="width:100%; font-size:1.1rem; padding:1rem;">Proceed to Measurements</button>
>>>>>>> Stashed changes
    </div>
</div>
<style>
    .category-grid {
        margin-bottom: 0.5rem;
    }

    #categoryError {
        margin-bottom: 1rem;
    }

    #subCategory {
        margin-bottom: 0.25rem;
    }

    #subCategoryError {
        margin-bottom: 0.8rem;
    }

    .date-time-row {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .date-box,
    .time-box {
        flex: 1;
    }

    .date-box label,
    .time-box label {
        font-size: 0.8rem;
        color: var(--text-muted);
        display: block;
        margin-bottom: 0.5rem;
    }

    .visit-card {
        display: block;
        border: 1px solid var(--border);
        padding: 1rem;
        border-radius: 12px;
        cursor: pointer;
    }

    .visit-card input {
        margin-right: 10px;
    }

    .visit-card input:checked+.visit-content {
        border: 1px solid var(--primary);
        background: var(--primary-light);
        border-radius: 10px;
        padding: 10px;
    }

    .category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }

    .service-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.75rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .service-card input {
        display: none;
    }

    .service-card .icon-box {
        width: 40px;
        height: 40px;
        background: var(--background);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        font-size: 1.2rem;
        transition: all 0.2s;
    }

    .service-card span {
        font-weight: 500;
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .service-card.selected {
        border-color: var(--primary);
        background: var(--background);
    }

    .service-card.selected .icon-box {
        background: var(--primary);
        color: white;
    }

    .service-card.selected span {
        color: var(--primary);
        font-weight: 600;
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
    }

    .visit-type {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 2rem;
    }

    .visit-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1rem;
        cursor: pointer;
        transition: 0.2s;
        background: var(--surface);
        position: relative;
    }

    .visit-card input {
        margin-right: 10px;
        transform: scale(1.2);
    }

    .visit-left {
        flex: 1;
    }

    .visit-title {
        font-weight: 600;
        font-size: 1rem;
        color: var(--text-main);
    }

    .visit-desc {
        font-size: 0.8rem;
        color: var(--text-muted);
    }

    .visit-price {
        font-weight: 700;
        color: var(--primary);
    }

    .visit-price.free {
        color: var(--success);
    }

    .visit-card:has(input:checked) {
        border-color: var(--primary);
        background: var(--primary-light);
    }

    .visit-card:hover {
        border-color: var(--primary);
    }

    .section-title {
        margin-top: 1rem;
        /* gap above each section */
    }

    .error {
        color: red;
        font-size: 0.75rem;
        margin-top: 4px;
    }

    .input-error {
        border-color: red !important;
    }
</style>
<?php include 'includes/bottom-nav.php'; ?>
<script>
    const cards = document.querySelectorAll('.service-card');
    const subCategoryDropdown = document.getElementById('subCategory');

    cards.forEach(card => {
        card.addEventListener('click', () => {

            // UI selection
            cards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');

            const radio = card.querySelector('input[type="radio"]');
            radio.checked = true;

            let categoryId = radio.value;

            // AJAX CALL
            fetch('get-subcategories.php?category_id=' + categoryId + '&t=' + new Date().getTime())
                .then(res => res.json())
                .then(data => {

                    // reset dropdown
                    subCategoryDropdown.innerHTML = `<option value="">Select Sub Category</option>`;

                    if (!data || data.length === 0) {
                        subCategoryDropdown.innerHTML += `<option>No Sub Categories Available</option>`;
                        subCategoryDropdown.disabled = true;
                        return;
                    }
                    subCategoryDropdown.disabled = false;
                    data.forEach(item => {
                        subCategoryDropdown.innerHTML += `<option value="${item.id}">${item.name}</option>`;
                    });
                })
                .catch(err => console.error(err));
        });
    });
<<<<<<< Updated upstream
    document.getElementById('proceedToMeasurementsBtn')
    .addEventListener('click', function () {

        const selectedCategory =
            document.querySelector('input[name="service"]:checked');

        const subCategory =
            document.getElementById('subCategory').value;

        if (!selectedCategory) {

            alert('Please select category');
            return;
        }

        if (!subCategory) {

            alert('Please select sub category');
            return;
        }

        window.location.href =
            'measurements.php?category_id=' +
            selectedCategory.value +
            '&subcategory_id=' +
            subCategory;
=======
    const dateInput = document.getElementById('dateInput');
    const timeInput = document.getElementById('timeInput');

    const today = new Date().toISOString().split('T')[0];
    dateInput.setAttribute('min', today);

    // initial set
    setTimeRestriction();

    dateInput.addEventListener('change', setTimeRestriction);
    timeInput.addEventListener('change', validateTime);

    function setTimeRestriction() {
        const selectedDate = dateInput.value;
        const now = new Date();

        now.setSeconds(0, 0);

        let hours = now.getHours().toString().padStart(2, '0');
        let minutes = now.getMinutes().toString().padStart(2, '0');
        let currentTime = `${hours}:${minutes}`;

        if (selectedDate === today) {
            timeInput.min = currentTime;

            if (!timeInput.value || timeInput.value < currentTime) {
                timeInput.value = currentTime;
            }
        } else {
            timeInput.removeAttribute('min');
        }
    }

    function validateTime() {
        const selectedDate = dateInput.value;
        const selectedTime = timeInput.value;

        if (!selectedDate || !selectedTime) return;

        const now = new Date();
        const selected = new Date(`${selectedDate}T${selectedTime}`);
        now.setSeconds(0, 0);

        if (selectedDate === today && selected < now) {
            timeInput.classList.add('input-error');
            document.getElementById('timeError').innerText = "Please select a future time";
        } else {
            timeInput.classList.remove('input-error');
            document.getElementById('timeError').innerText = "";
        }
    }
    document.getElementById('proceedBtn').addEventListener('click', function() {

        let isValid = true;
        let category = document.querySelector('input[name="service"]:checked')?.value;
        const subCategoryEl = document.getElementById('subCategory');
        let subCategory = subCategoryEl.value;
        const dateInput = document.getElementById('dateInput');
        const timeInput = document.getElementById('timeInput');
        // clear errors
        document.querySelectorAll('.error').forEach(el => el.innerText = "");
        document.querySelectorAll('.form-input').forEach(el => el.classList.remove('input-error'));

        // CATEGORY VALIDATION
        if (!category) {
            isValid = false;
            document.getElementById('categoryError').innerText = "Please select a category";
        }

        // SUB CATEGORY
        if (!subCategory || subCategoryEl.disabled) {
            isValid = false;
            subCategoryEl.classList.add('input-error');
            document.getElementById('subCategoryError').innerText = "Please select a sub category";
        }

        // DATE
        if (!dateInput.value) {
            isValid = false;
            dateInput.classList.add('input-error');
            document.getElementById('dateError').innerText = "Please select a date";
        }

        // TIME
        if (!timeInput.value) {
            isValid = false;
            timeInput.classList.add('input-error');
            document.getElementById('timeError').innerText = "Please select a time";
        }

        // PAST TIME CHECK
        const today = new Date().toISOString().split('T')[0];
        const now = new Date();

        if (dateInput.value && timeInput.value) {
            const selected = new Date(`${dateInput.value}T${timeInput.value}`);
            const now = new Date();
            now.setSeconds(0, 0); 

            if (dateInput.value === today && selected < now) {
                isValid = false;
                timeInput.classList.add('input-error');
                document.getElementById('timeError').innerText = "Please select a future time";
            }
        }

        // STOP
        if (!isValid) {
            document.querySelector('.input-error')?.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            return;
        }

        // SUCCESS
        if (!isValid) return;

        // SUCCESS REDIRECT
        fetch('store-order-session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                category_id: category,
                sub_category_id: subCategory,
                visit_type: document.querySelector('input[name="visit_type"]:checked').value,
                date: dateInput.value,
                time: timeInput.value
            })
        }).then(() => {
            window.location.href = `measurements.php?category_id=${category}&sub_category_id=${subCategory}`;
        });
>>>>>>> Stashed changes
    });
</script>