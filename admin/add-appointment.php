<?php
session_start();
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    if (empty($_POST['customer_name'])) $errors['customer_name'] = "Customer Name is required";
    if (empty($_POST['date'])) $errors['date'] = "Date is required";
    if (empty($_POST['time'])) $errors['time'] = "Time is required";
    if (empty($_POST['type'])) $errors['type'] = "Type is required";

    if (empty($errors)) {
        try {
            $customer_name = $_POST['customer_name'];
            $phone = $_POST['phone'] ?? '';
            $date = $_POST['date'];
            $time = $_POST['time'];
            $type = $_POST['type'];
            $notes = $_POST['notes'] ?? '';

            // Assume insert into appointments table
            $stmt = $pdo->prepare("INSERT INTO appointments (customer_name, phone, date, time, type, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customer_name, $phone, $date, $time, $type, $notes]);

            echo "<script>alert('Appointment created successfully!'); window.location.href='appointments.php';</script>";
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

$pageTitle = "New Appointment - Sogasu";
$activePage = "appointments";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div>
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                 <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">New Appointment</h2>
                 <p class="text-muted">Schedule a trial, measurement, or consultation.</p>
            </div>
            <button class="btn" onclick="history.back()" style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
                <i class="ri-arrow-left-line"></i> Back
            </button>
        </div>
    </div>

    <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        
        <!-- Left Column: Appointment Details -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Schedule Details</h3>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Customer Name</label>
                    <div style="position: relative;">
                        <i class="ri-search-line" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                        <input type="text" class="form-control" style="padding-left: 2.5rem;" placeholder="Search existing customer..." name="customer_name">
                        <?php if (isset($errors['customer_name'])) echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['customer_name']}</div>"; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Customer Phone (Optional if selected above)</label>
                    <input type="tel" class="form-control" placeholder="+91" name="phone">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" name="date">
                        <?php if (isset($errors['date'])) echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['date']}</div>"; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time</label>
                        <input type="time" class="form-control" name="time">
                        <?php if (isset($errors['time'])) echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['time']}</div>"; ?>
                    </div>
                </div>

                 <div class="form-group">
                    <label class="form-label">Purpose / Type</label>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                         <label class="radio-card selected">
                            <input type="radio" name="type" checked>
                            <i class="ri-ruler-line"></i>
                            <span>Measurements</span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="type">
                            <i class="ri-t-shirt-line"></i>
                            <span>Trial</span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="type">
                            <i class="ri-discuss-line"></i>
                            <span>Consultation</span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="type">
                            <i class="ri-truck-line"></i>
                            <span>Delivery/Pickup</span>
                        </label>
                    </div>
                    <?php if (isset($errors['type'])) echo "<div style='color:red; font-size:0.9rem; margin-top:0.5rem;'>{$errors['type']}</div>"; ?>
                </div>

            </div>

             <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Additional Notes</h3>
                
                 <div class="form-group">
                    <label class="form-label">Message / Details</label>
                    <textarea class="form-control" rows="3" placeholder="Any specific instructions for this appointment..." name="notes"></textarea>
                </div>
            </div>

        </div>

        <!-- Right Column: Linking & Actions -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            
             <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Link to Order</h3>
                
                 <div class="form-group">
                     <label class="form-label">Order ID (Optional)</label>
                     <input type="text" class="form-control" placeholder="e.g. #ORD-2458">
                     <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Links this visit to a specific order</div>
                </div>

            </div>

              <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Status</h3>
                 <div class="form-group">
                     <label class="form-label">Appointment Status</label>
                     <select class="form-select">
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="tentative">Tentative</option>
                     </select>
                </div>
            </div>

             <!-- Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 8px;">
                 <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">Actions</h3>
                 <button class="btn btn-primary w-full" style="justify-content: center; width: 100%; margin-bottom: 1rem;">Create Appointment</button>
                 <button type="button" class="btn w-full" style="justify-content: center; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b;">Cancel</button>
            </div>

        </div>

    </form>
</main>

<style>
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #334155;
    }
    .form-control, .form-select {
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.95rem;
        width: 100%;
        outline: none;
        transition: border-color 0.2s;
        font-family: inherit;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
    }
    
    .radio-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        color: #64748b;
        text-align: center;
    }
    .radio-card i {
        font-size: 1.25rem;
    }
    .radio-card span {
        font-size: 0.75rem;
        font-weight: 500;
    }
    .radio-card input {
        display: none;
    }
    .radio-card:hover {
        background: #f8fafc;
        border-color: var(--primary);
        color: var(--primary);
    }
    .radio-card:has(input:checked), .radio-card.selected {
        background: #eef2ff;
        border-color: var(--primary);
        color: var(--primary);
        font-weight: 600;
    }
</style>

<script>
    // Simple script to toggle selected class for radio buttons styling
    const methods = document.querySelectorAll('.radio-card');
    methods.forEach(method => {
        method.addEventListener('click', () => {
             // Only remove from others if this is a radio group behavior
             const groupName = method.querySelector('input').name;
             document.querySelectorAll(`input[name="${groupName}"]`).forEach(input => {
                 input.closest('.radio-card').classList.remove('selected');
             });
             method.classList.add('selected');
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
