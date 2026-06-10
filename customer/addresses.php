<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$pageTitle = "My Addresses - Sogasu";
$headerTitle = "My Addresses";
$activePage = "profile";

// Fetch customer data from customers table (admin data)
$customerData = [];
$stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
$stmt->execute([$userId]);
$customer = $stmt->fetch();
if ($customer) {
    $customerData = $customer;
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Personal Information fields
    $personalData = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'secondary_phone' => trim($_POST['secondary_phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
    ];

    // Address Details fields
    $addressData = [
        'address' => trim($_POST['address'] ?? ''),
        'area' => trim($_POST['area'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
    ];

    // Validation
    $errors = [];

    if (empty($personalData['first_name'])) {
        $errors['first_name'] = "First name is required";
    }
    if (empty($personalData['phone'])) {
        $errors['phone'] = "Phone number is required";
    }
    if (empty($addressData['address'])) {
        $errors['address'] = "Address is required";
    }
    if (empty($addressData['city'])) {
        $errors['city'] = "City is required";
    }

    if (empty($errors)) {
        try {
            // Update customers table (syncs with admin)
            $stmt = $pdo->prepare("UPDATE customers SET
                first_name = ?, last_name = ?, phone = ?, secondary_phone = ?, email = ?,
                address = ?, area = ?, city = ?, updated_at = NOW()
                WHERE user_id = ?");

            $stmt->execute([
                $personalData['first_name'],
                $personalData['last_name'],
                $personalData['phone'],
                $personalData['secondary_phone'],
                $personalData['email'],
                $addressData['address'],
                $addressData['area'],
                $addressData['city'],
                $userId
            ]);

            // Also update users table for consistency
            $stmt = $pdo->prepare("UPDATE users SET
                username = ?, email = ?, mobile = ?, updated_at = NOW()
                WHERE id = ?");

            $stmt->execute([
                $personalData['first_name'] . ' ' . $personalData['last_name'],
                $personalData['email'],
                $personalData['phone'],
                $userId
            ]);

            $message = 'success|Information updated successfully!';
            $messageType = 'success';

            // Refresh customer data
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
            $stmt->execute([$userId]);
            $customer = $stmt->fetch();
            if ($customer) {
                $customerData = $customer;
            }

        } catch (Exception $e) {
            $message = 'error|Failed to update information. Please try again.';
            $messageType = 'error';
        }
    } else {
        $message = 'error|Please fill in all required fields.';
        $messageType = 'error';
    }
}

include 'includes/header.php';
?>

<div class="container">

    <div class="card" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <a href="profile.php" style="background: var(--background); border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); text-decoration: none; font-size: 1.2rem;">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main);">My Addresses</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Update your personal information and delivery address</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 1rem;">
            <?php echo str_replace('success|', '', str_replace('error|', '', $message)); ?>
        </div>
    <?php endif; ?>

    <form id="profileForm" method="POST">

        <!-- Personal Information Section -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="section-title">
                <span>Personal Information</span>
                <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">(Required)</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">

                <div>
                    <label class="input-label">First Name *</label>
                    <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($customerData['first_name'] ?? ''); ?>" required>
                </div>

                <div>
                    <label class="input-label">Last Name</label>
                    <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($customerData['last_name'] ?? ''); ?>">
                </div>

                <div>
                    <label class="input-label">Phone Number *</label>
                    <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($customerData['phone'] ?? ''); ?>" required>
                </div>

                <div>
                    <label class="input-label">Secondary Phone</label>
                    <input type="tel" name="secondary_phone" class="form-input" value="<?php echo htmlspecialchars($customerData['secondary_phone'] ?? ''); ?>">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="input-label">Email Address</label>
                    <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($customerData['email'] ?? ''); ?>">
                </div>

            </div>
        </div>

        <!-- Address Details Section -->
        <div class="card">
            <div class="section-title">
                <span>Address Details</span>
                <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">(Required)</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">

                <div>
                    <label class="input-label">Address *</label>
                    <textarea name="address" class="form-input" rows="3" required><?php echo htmlspecialchars($customerData['address'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="input-label">Area</label>
                    <input type="text" name="area" class="form-input" value="<?php echo htmlspecialchars($customerData['area'] ?? ''); ?>">
                </div>

                <div>
                    <label class="input-label">City *</label>
                    <input type="text" name="city" class="form-input" value="<?php echo htmlspecialchars($customerData['city'] ?? ''); ?>" required>
                </div>

            </div>
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" class="btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                <i class="ri-save-line"></i> Save Changes
            </button>
        </div>

    </form>

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

    .form-input[required] {
        border-color: #ef4444;
    }

    .form-input[required]:focus {
        border-color: var(--primary);
    }

    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('profileForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('addresses.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('success|')) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Information updated successfully!',
                confirmButtonColor: '#db2777'
            }).then(() => {
window.location.href = "http://localhost/sogasu_05_05/customer/profile.php";    
        });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.includes('error|') ? data.replace('error|', '') : 'Failed to update information',
                confirmButtonColor: '#db2777'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to update information',
            confirmButtonColor: '#db2777'
        });
    });
});
</script>

<?php include 'includes/bottom-nav.php'; ?>
                <i class="ri-save-line"></i> Save Address
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
    document.getElementById('addressForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        fetch('addresses.php', {
            method: 'POST',
            body: formData
        }).then(response => response.text()).then(data => {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Address saved successfully!',
                confirmButtonColor: '#db2777'
            }).then(() => {
                document.getElementById('addressForm').reset();
                setTimeout(() => window.location.reload(), 500);
            });
        }).catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Failed to save address',
                confirmButtonColor: '#db2777'
            });
        });
    });
</script>

<?php include 'includes/bottom-nav.php'; ?>
