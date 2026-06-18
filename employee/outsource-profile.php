<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$language = $_SESSION['language'] ?? 'en';

// Include translations
require_once __DIR__ . '/includes/translations.php';
$t = $translations[$language] ?? $translations['en'];

// Fetch employee data
$stmt = $pdo->prepare("SELECT e.*, u.email FROM employees e 
    INNER JOIN users u ON e.user_id = u.id 
    WHERE e.user_id = ?
    AND e.employee_type = 'outsource'
    AND e.is_deleted = 0");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee not found");
}
$stats = [
    'completed' => 0,
    'pending' => 0,
    'earnings' => 0
];

$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN order_status IN ('pending','accepted','approved','in progress') THEN 1 ELSE 0 END) AS pending,
        COALESCE(
            SUM(
                CASE 
                    WHEN order_status = 'completed' THEN total_amount 
                    ELSE 0 
                END
            ), 0
        ) AS earnings
    FROM outsource_orders
    WHERE assigned_employee_id = ?
    AND is_deleted = 0
");
$stmt->execute([$employee['id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $stats;
// Set language from DB if not in session
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = $employee['preferred_language'] ?? 'en';
    $language = $_SESSION['language'];
}
// Get notification count
$notifStmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications 
    WHERE employee_id = ? AND is_read = 0");
$notifStmt->execute([$employee['id']]);
$notifCount = $notifStmt->fetch()['count'];

// Handle notification status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'toggle_notification') {
        $current = $employee['notification_enabled'] ?? 1;
        $stmt = $pdo->prepare("UPDATE employees SET notification_enabled = ? WHERE id = ?");
        $stmt->execute([1 - $current, $employee['id']]);
        $employee['notification_enabled'] = 1 - $current;
    }

    if ($action === 'change_language') {
        $new_lang = $_POST['language'] ?? 'en';
        $_SESSION['language'] = $new_lang;
        $stmt = $pdo->prepare("UPDATE employees SET preferred_language = ? WHERE id = ?");
        $stmt->execute([$new_lang, $employee['id']]);
        $employee['preferred_language'] = $new_lang;
    }

    if ($action === 'update_profile') {
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';

        if (!empty($phone) && !empty($address)) {
            $stmt = $pdo->prepare("UPDATE employees SET phone = ?, address = ? WHERE id = ?");
            if ($stmt->execute([$phone, $address, $employee['id']])) {
                $employee['phone'] = $phone;
                $employee['address'] = $address;
                $success = $t['profile_updated'];
            }
        }
    }

    if ($action === 'change_password') {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($new_password) < 6) {
            $pwd_error = $t['password_error_length'];
        } elseif ($new_password !== $confirm_password) {
            $pwd_error = $t['password_error_match'];
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($updateStmt->execute([$hashed, $user_id])) {
                $_SESSION['success_message'] = $t['password_changed'];
                header("Location: outsource-profile.php");
                exit;
            }
        }
    }

    if ($action === 'upload_photo') {
        if (isset($_FILES['profile_photo'])) {
            $file = $_FILES['profile_photo'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = basename($file['name']);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $new_filename = 'emp_' . $employee['id'] . '_' . time() . '.' . $ext;
                    $upload_path = 'uploads/' . $new_filename;

                    if (!is_dir('uploads')) {
                        mkdir('uploads', 0755, true);
                    }

                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $stmt = $pdo->prepare("UPDATE employees SET profile_photo = ? WHERE id = ?");
                        $stmt->execute([$new_filename, $employee['id']]);
                        $employee['profile_photo'] = $new_filename;
                        $photo_success = $t['photo_uploaded'];
                    }
                }
            }
        }
    }
}

// Get admin notifications
$notifStmt = $pdo->prepare("SELECT * FROM notifications 
    WHERE employee_id = ? ORDER BY created_at DESC LIMIT 5");
$notifStmt->execute([$employee['id']]);
$notifications = $notifStmt->fetchAll() ?? [];

$pageTitle = $t['page_title'];
$headerTitle = $t['header_title'];
$activePage = "profile";
include 'includes/outsource-header.php';
?>

<div class="container">
    <!-- Notifications Banner -->
    <?php if ($notifCount > 0): ?>
        <div
            style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <i class="ri-notification-badge-line" style="font-size: 1.5rem; color: #f59e0b;"></i>
                    <div>
                        <div style="font-weight: 600; color: var(--text-main);"><?php echo $t['admin_updates']; ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo $notifCount; ?>
                            <?php echo $t['new_updates']; ?>
                        </div>
                    </div>
                </div>
                <button onclick="toggleNotifications()"
                    style="background: transparent; border: none; color: var(--text-muted); cursor: pointer;">
                    <i class="ri-arrow-down-s-line"></i>
                </button>
            </div>
            <div id="notif-list" style="display: none; margin-top: 1rem; max-height: 200px; overflow-y: auto;">
                <?php foreach ($notifications as $notif): ?>
                    <div style="padding: 0.5rem; border-bottom: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.9rem;">
                        <strong><?php echo htmlspecialchars($notif['title'] ?? 'Update'); ?></strong>
                        <p style="margin-top: 0.25rem; color: var(--text-muted);">
                            <?php echo htmlspecialchars($notif['message'] ?? ''); ?>
                        </p>
                        <small style="color: #999;"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div
            style="background: #d1fae5; border-left: 4px solid #10b981; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; color: #065f46;">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="card"
        style="text-align: center; padding: 2rem 1rem; border: none; background: linear-gradient(135deg, var(--background), #fff);">
        <div style="position: relative; display: inline-block; margin-bottom: 1rem;">
            <img id="profileImg"
                src="<?php echo !empty($employee['profile_photo']) ? 'uploads/' . htmlspecialchars($employee['profile_photo']) : 'https://ui-avatars.com/api/?name=' . urlencode($employee['first_name'] . ' ' . $employee['last_name']) . '&background=fce7f3&color=db2777&size=128'; ?>"
                style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); object-fit: cover;">
            <button onclick="document.getElementById('photoInput').click()"
                style="position: absolute; bottom: 0; right: 0; background: var(--primary); color: white; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; cursor: pointer;">
                <i class="ri-camera-line" style="font-size: 1rem;"></i>
            </button>
            <form id="photoForm" style="display: none;">
                <input type="file" id="photoInput" name="profile_photo" accept="image/*" onchange="uploadPhoto(this)">
            </form>
        </div>
        <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
        </h2>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            <?php echo htmlspecialchars($employee['employee_type']); ?> • ID #<?php echo $employee['id']; ?>
        </p>
    </div>
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.75rem; margin-bottom:1rem;">
        <div class="card" style="text-align:center; padding:1rem;">
            <div style="font-size:1.4rem; font-weight:700; color:var(--primary);">
                <?= $stats['completed'] ?? 0 ?>
            </div>
            <div style="font-size:0.8rem; color:var(--text-muted);">Completed</div>
        </div>

        <div class="card" style="text-align:center; padding:1rem;">
            <div style="font-size:1.4rem; font-weight:700; color:var(--primary);">
                <?= $stats['pending'] ?? 0 ?>
            </div>
            <div style="font-size:0.8rem; color:var(--text-muted);">Pending</div>
        </div>

        <div class="card" style="text-align:center; padding:1rem;">
            <div style="font-size:1.4rem; font-weight:700; color:var(--primary);">
                ₹<?= $stats['earnings'] ?? 0 ?>
            </div>
            <div style="font-size:0.8rem; color:var(--text-muted);">Earned</div>
        </div>
    </div>

    <!-- Personal Info -->
    <div class="section-title"><?php echo $t['personal_details']; ?></div>
    <div class="card">
        <div
            style="padding: 0.75rem 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 1rem;">
            <div
                style="width: 36px; height: 36px; background: var(--background); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                <i class="ri-phone-line"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $t['mobile_number']; ?></div>
                <div style="font-size: 0.95rem; font-weight: 500;">+91
                    <?php echo htmlspecialchars($employee['phone'] ?? $t['not_set']); ?>
                </div>
            </div>
            <button onclick="openEditModal('phone')"
                style="background: transparent; border: none; color: var(--primary); font-size: 0.9rem; font-weight: 600; cursor: pointer;"><?php echo $t['edit']; ?></button>
        </div>
        <div style="padding: 0.75rem 0; display: flex; align-items: center; gap: 1rem;">
            <div
                style="width: 36px; height: 36px; background: var(--background); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                <i class="ri-map-pin-line"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $t['address']; ?></div>
                <div style="font-size: 0.95rem; font-weight: 500;">
                    <?php echo htmlspecialchars($employee['address'] ?? $t['not_set']); ?>
                </div>
            </div>
            <button onclick="openEditModal('address')"
                style="background: transparent; border: none; color: var(--primary); font-size: 0.9rem; font-weight: 600; cursor: pointer;"><?php echo $t['edit']; ?></button>
        </div>
    </div>

    <!-- App Settings -->
    <div class="section-title"><?php echo $t['app_settings']; ?></div>
    <div class="card" style="padding: 0;">
        <div
            style="padding: 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 1rem; align-items: center;">
                <i class="ri-notification-3-line" style="color: var(--text-muted); font-size: 1.25rem;"></i>
                <span style="font-size: 0.95rem; font-weight: 500;"><?php echo $t['notifications']; ?></span>
            </div>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="toggle_notification">
                <button type="submit"
                    class="toggle-switch <?php echo ($employee['notification_enabled'] ?? 1) ? 'active' : ''; ?>"
                    style="border: none; cursor: pointer;">
                    <div class="knob"></div>
                </button>
            </form>
        </div>
        <div style="padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 1rem; align-items: center;">
                <i class="ri-lock-password-line" style="color: var(--text-muted); font-size: 1.25rem;"></i>
                <span style="font-size: 0.95rem; font-weight: 500;"><?php echo $t['change_password']; ?></span>
            </div>
            <button onclick="openPasswordModal()"
                style="background: transparent; border: none; color: var(--text-muted); cursor: pointer;">
                <i class="ri-arrow-right-s-line"></i>
            </button>
        </div>
    </div>

    <!-- Logout -->
    <a href="../includes/logout.php" style="text-decoration: none;">
        <button
            style="width: 100%; background: #fff1f2; color: var(--danger); border: none; padding: 1rem; border-radius: 12px; font-weight: 600; font-size: 1rem; margin-top: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer;">
            <i class="ri-logout-box-line"></i> <?php echo $t['logout']; ?>
        </button>
    </a>

    <div style="text-align: center; margin-top: 2rem; color: var(--text-muted); font-size: 0.8rem;">
        <?php echo $t['app_version']; ?>
    </div>

</div>

<!-- Edit Profile Modal -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0;"><?php echo $t['edit_profile']; ?></h3>
            <button onclick="closeModal('editModal')"
                style="background: transparent; border: none; font-size: 1.5rem; cursor: pointer;">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div style="margin-bottom: 1rem;">
                <label
                    style="display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;"><?php echo $t['phone_number']; ?></label>
                <input type="tel" name="phone" id="editPhone"
                    value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label
                    style="display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;"><?php echo $t['address']; ?></label>
                <textarea name="address" id="editAddress"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; height: 80px; resize: vertical;"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
            </div>
            <button type="submit"
                style="width: 100%; background: var(--primary); color: white; border: none; padding: 0.75rem; border-radius: 8px; font-weight: 600; cursor: pointer;"><?php echo $t['save_changes']; ?></button>
        </form>
    </div>
</div>

<!-- Change Password Modal -->
<div id="passwordModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0;"><?php echo $t['change_password_title']; ?></h3>
            <button onclick="closeModal('passwordModal')"
                style="background: transparent; border: none; font-size: 1.5rem; cursor: pointer;">×</button>
        </div>
        <?php if (isset($pwd_error)): ?>
            <div
                style="background: #fee2e2; color: #991b1b; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
                <?php echo $pwd_error; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($pwd_success)): ?>
            <div
                style="background: #d1fae5; color: #065f46; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
                <?php echo $pwd_success; ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div style="margin-bottom: 1rem;">
                <label
                    style="display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;"><?php echo $t['employee_name']; ?></label>
                <input type="text"
                    value="<?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>"
                    disabled
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; background: var(--background);">
            </div>
            <div style="margin-bottom: 1rem;">
                <label
                    style="display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;"><?php echo $t['new_password']; ?></label>
                <input type="password" name="new_password" required
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label
                    style="display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;"><?php echo $t['confirm_password']; ?></label>
                <input type="password" name="confirm_password" required
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit;">
            </div>
            <button type="submit"
                style="width: 100%; background: var(--primary); color: white; border: none; padding: 0.75rem; border-radius: 8px; font-weight: 600; cursor: pointer;"><?php echo $t['change_password']; ?></button>
        </form>
    </div>
</div>

<!-- Language Modal -->
<div id="languageModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0;"><?php echo $t['select_language']; ?></h3>
            <button onclick="closeModal('languageModal')"
                style="background: transparent; border: none; font-size: 1.5rem; cursor: pointer;">×</button>
        </div>
        <div id="languageOptions">
            <?php
            $languages = ['en' => 'English', 'hi' => 'Hindi', 'te' => 'Telugu'];
            foreach ($languages as $code => $name):
                ?>
                <label
                    style="display: flex; align-items: center; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 0.5rem; cursor: pointer;">
                    <input type="radio" name="language" value="<?php echo $code; ?>" <?php echo ($language === $code) ? 'checked' : ''; ?> style="margin-right: 0.75rem;" onchange="changeLanguage('<?php echo $code; ?>')">
                    <span><?php echo $name; ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div id="languageMessage"
            style="display: none; background: #d1fae5; color: #065f46; padding: 0.75rem; border-radius: 8px; margin-top: 1rem; font-size: 0.9rem;">
            Language changed successfully! Reloading page...
        </div>
    </div>
</div>

<style>
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: flex-end;
        z-index: 1000;
    }

    .modal-content {
        background: var(--surface);
        width: 100%;
        padding: 1.5rem;
        border-radius: 16px 16px 0 0;
        max-height: 80vh;
        overflow-y: auto;
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(100%);
        }

        to {
            transform: translateY(0);
        }
    }

    .toggle-switch {
        width: 44px;
        height: 24px;
        background: var(--border);
        border-radius: 99px;
        position: relative;
        cursor: pointer;
        transition: background 0.2s;
    }

    .toggle-switch.active {
        background: var(--primary);
    }

    .toggle-switch .knob {
        width: 20px;
        height: 20px;
        background: white;
        border-radius: 50%;
        position: absolute;
        top: 2px;
        left: 2px;
        transition: transform 0.2s;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .toggle-switch.active .knob {
        transform: translateX(20px);
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function openEditModal(type) {
        document.getElementById('editModal').style.display = 'flex';
    }

    function openPasswordModal() {
        document.getElementById('passwordModal').style.display = 'flex';
    }

    function openLanguageModal() {
        document.getElementById('languageModal').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function toggleNotifications() {
        const list = document.getElementById('notif-list');
        if (list.style.display === 'none') {
            list.style.display = 'block';
        } else {
            list.style.display = 'none';
        }
    }

    function changeLanguage(langCode) {
        // Send AJAX request to change language
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=change_language&language=' + langCode
        }).then(response => {
            if (response.ok) {
                // Show success message
                document.getElementById('languageMessage').style.display = 'block';
                document.getElementById('languageOptions').style.display = 'none';

                // Reload page after 1 second
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        });
    }

    function uploadPhoto(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];

            // Check file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('<?php echo addslashes($t['upload_valid_image']); ?>');
                return;
            }

            // Check file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('<?php echo addslashes($t['file_size_exceed']); ?>');
                return;
            }

            // Create FormData and submit
            const formData = new FormData();
            formData.append('profile_photo', file);
            formData.append('action', 'upload_photo');

            // Show preview
            const reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('profileImg').src = e.target.result;
            };
            reader.readAsDataURL(file);

            // Submit form
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }
    }

    // Close modal when clicking outside
    document.addEventListener('click', function (e) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
</script>
<?php if (isset($_SESSION['success_message'])): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= $_SESSION['success_message'] ?>',
    confirmButtonColor: '#db2777'
});
</script>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php include 'includes/outsource-bottom-nav.php'; ?>