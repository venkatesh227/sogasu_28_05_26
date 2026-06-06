<?php
session_start();
require '../includes/db.php';
// Fetch logged in customer details
$customerId = $_SESSION['user_id'] ?? 0;
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
<?php

$timingStmt = $pdo->prepare("

    SELECT *
    FROM boutique_timing_settings
    ORDER BY effective_from ASC

");

$timingStmt->execute();

$timings = $timingStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

<div class="section-title">Pricing Details</div>

<div class="date-time-row">

    <div class="date-box">
        <label>Base Price</label>
        <input type="number"
            id="base_price"
            class="form-input"
            placeholder="Base Price"
            min="0"
            step="0.01"
            readonly>
    </div>

    <div class="time-box">
        <label>Extra Charges</label>
        <input type="number"
               id="extra_charges"
               class="form-input"
               placeholder="Enter Extra Charges"
               min="0"
               step="0.01"
               value="0">
    </div>

</div>

<div style="margin-bottom: 2rem;">
    <label>Total Amount</label>
    <input type="number"
           id="total_amount"
           class="form-input"
           readonly>
</div>

<button type="button" id="proceedBtn" class="btn-primary" style="width:100%; font-size:1.1rem; padding:1rem;">
    Proceed to Measurements
</button>
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
    window.addEventListener('DOMContentLoaded', function () {

    const selectedRadio =
        document.querySelector('input[name="service"]:checked');

    if (selectedRadio) {

        let categoryId = selectedRadio.value;

        fetch('get-subcategories.php?category_id=' + categoryId + '&t=' + new Date().getTime())
            .then(res => res.json())
            .then(data => {

                subCategoryDropdown.innerHTML =
                    `<option value="">Select Sub Category</option>`;

                if (!data || data.length === 0) {

                    subCategoryDropdown.innerHTML +=
                        `<option>No Sub Categories Available</option>`;

                    subCategoryDropdown.disabled = true;

                    return;
                }

                subCategoryDropdown.disabled = false;

                data.forEach(item => {

                    subCategoryDropdown.innerHTML += `
                        <option value="${item.id}" data-price="${item.base_price}">
                            ${item.name}
                        </option>
                    `;
                });
            });
    }
});
    cards.forEach(card => {
        card.addEventListener('click', () => {

            // UI selection
            cards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');

            const radio = card.querySelector('input[type="radio"]');
            radio.checked = true;
            document.getElementById('categoryError').innerText = '';

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
                        subCategoryDropdown.innerHTML += `
                            <option value="${item.id}" data-price="${item.base_price}">
                                ${item.name}
                            </option>
                        `;
                    });
                })
                .catch(err => console.error(err));
        });
    });
    const basePriceInput = document.getElementById('base_price');
const extraChargesInput = document.getElementById('extra_charges');
const totalAmountInput = document.getElementById('total_amount');

function calculateTotalAmount() {

    const basePrice = parseFloat(basePriceInput.value) || 0;
    const extraCharges = parseFloat(extraChargesInput.value) || 0;

    totalAmountInput.value = (basePrice + extraCharges).toFixed(2);
}

basePriceInput.addEventListener('input', calculateTotalAmount);
extraChargesInput.addEventListener('input', calculateTotalAmount);
subCategoryDropdown.addEventListener('change', function () {

    const selectedOption =
        this.options[this.selectedIndex];

    const basePrice =
        selectedOption.getAttribute('data-price') || 0;

    document.getElementById('base_price').value = basePrice;

    calculateTotalAmount();
});
    document.getElementById('proceedBtn')
.addEventListener('click', function () {

    const selectedCategory =
        document.querySelector('input[name="service"]:checked');

    const subCategory =
        document.getElementById('subCategory').value;

    const categoryError =
    document.getElementById('categoryError');

categoryError.innerText = '';

if (!selectedCategory) {

    categoryError.innerText =
        'Please select category';

    return;
}   

    const subCategoryError =
    document.getElementById('subCategoryError');

    subCategoryError.innerText = '';

    if (
        !subCategory &&
        !document.getElementById('subCategory').disabled
    ) {

        subCategoryError.innerText =
            'Please select sub category';

        document.getElementById('subCategory')
            .classList.add('input-error');

        return;
    } else {

        document.getElementById('subCategory')
            .classList.remove('input-error');
    }

    const visitType =
    document.querySelector('input[name="visit_type"]:checked').value;

    const appointmentDate =
        document.getElementById('dateInput').value;

    const appointmentTime =
    document.getElementById('timeInput').value;

    const timeError =
        document.getElementById('timeError');
    const basePrice =
    document.getElementById('base_price').value;

    const extraCharges =
    document.getElementById('extra_charges').value;

    const totalAmount =
    document.getElementById('total_amount').value;

    timeError.innerText = '';

    document.getElementById('timeInput')
        .classList.remove('input-error');

    if (!appointmentTime) {

        timeError.innerText =
            'Time is required';

        document.getElementById('timeInput')
            .classList.add('input-error');

        return;
    }

    const currentDateTime = new Date();

    const selectedDateTime = new Date(
        appointmentDate + 'T' + appointmentTime
    );
    /*
|--------------------------------------------------------------------------
| BLOCK SUNDAYS
|--------------------------------------------------------------------------
*/

const selectedDay =
    selectedDateTime.getDay();

if (selectedDay === 0) {

    Swal.fire({
        icon: 'warning',
        title: 'Booking Not Allowed',
        text: 'Appointments are not allowed on Sundays',
        confirmButtonColor: '#ef4444'
    });

    return;
}

/*
|--------------------------------------------------------------------------
| BLOCK HOLIDAYS
|--------------------------------------------------------------------------
*/

const holidays = <?php

$holidayStmt = $pdo->prepare("
    SELECT holiday_date
    FROM holidays
");

$holidayStmt->execute();

$holidayDates = $holidayStmt->fetchAll(
    PDO::FETCH_COLUMN
);

echo json_encode($holidayDates);

?>;

if (holidays.includes(appointmentDate)) {

    Swal.fire({
        icon: 'warning',
        title: 'Booking Not Allowed',
        text: 'Appointments are not allowed on holidays',
        confirmButtonColor: '#ef4444'
    });

    return;
}

    if (selectedDateTime < currentDateTime) {

        timeError.innerText =
            'Past date and time are not allowed';

        document.getElementById('timeInput')
            .classList.add('input-error');

        return;
    }
const boutiqueTimings =
<?php echo json_encode($timings); ?>;

let boutiqueStartTime = '';
let boutiqueEndTime = '';

for (let i = 0; i < boutiqueTimings.length; i++) {

    if (
        appointmentDate >=
        boutiqueTimings[i].effective_from
    ) {

        boutiqueStartTime =
        boutiqueTimings[i].start_time.slice(0, 5);

        boutiqueEndTime =
        boutiqueTimings[i].end_time.slice(0, 5);
    }
}

if (
    boutiqueStartTime &&
    boutiqueEndTime
) {

    const selectedMinutes =
    parseInt(appointmentTime.split(':')[0]) * 60 +
    parseInt(appointmentTime.split(':')[1]);

const startMinutes =
    parseInt(boutiqueStartTime.split(':')[0]) * 60 +
    parseInt(boutiqueStartTime.split(':')[1]);

const endMinutes =
    parseInt(boutiqueEndTime.split(':')[0]) * 60 +
    parseInt(boutiqueEndTime.split(':')[1]);

if (
    selectedMinutes < startMinutes ||
    selectedMinutes > endMinutes
) {

        timeError.innerText =
            'Appointments allowed only between ' +
            boutiqueStartTime +
            ' and ' +
            boutiqueEndTime;

        document.getElementById('timeInput')
            .classList.add('input-error');

        return;
    }
}


    window.location.href =
    'measurements.php?category_id=' +
    selectedCategory.value +
    '&sub_category_id=' +
    subCategory +
    '&visit_type=' +
    encodeURIComponent(visitType) +
    '&appointment_date=' +
    encodeURIComponent(appointmentDate) +
    '&appointment_time=' +
    encodeURIComponent(appointmentTime) +
    '&base_price=' +
    encodeURIComponent(basePrice) +
    '&extra_charges=' +
    encodeURIComponent(extraCharges) +
    '&total_amount=' +
    encodeURIComponent(totalAmount);
    });
</script>