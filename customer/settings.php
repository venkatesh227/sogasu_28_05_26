<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$pageTitle = "Settings - Sogasu";
$headerTitle = "Settings";
$activePage = "profile";

$savedData = [];
$stmt = $pdo->prepare("SELECT data FROM customer_profiles WHERE user_id = ? AND section_type = 'settings'");
$stmt->execute([$userId]);
$result = $stmt->fetch();
if ($result) {
    $savedData = json_decode($result['data'], true) ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'notifications_enabled' => isset($_POST['notifications_enabled']) ? '1' : '0',
        'order_updates' => isset($_POST['order_updates']) ? '1' : '0',
        'promotional' => isset($_POST['promotional']) ? '1' : '0',
        'language' => $_POST['language'] ?? 'English',
        'notification_method' => $_POST['notification_method'] ?? 'email',
        'privacy_level' => $_POST['privacy_level'] ?? 'public',
    ];

    $stmt = $pdo->prepare("INSERT INTO customer_profiles (user_id, section_type, data) VALUES (?, 'settings', ?) ON DUPLICATE KEY UPDATE data = ?, updated_at = NOW()");
    $stmt->execute([$userId, json_encode($formData), json_encode($formData)]);
    
    $savedData = $formData;
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully!']);
    exit();
}

include 'includes/header.php';
?>

<div class="container">
    
    <div class="card" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <a href="profile.php" style="background: var(--background); border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); text-decoration: none; font-size: 1.2rem;">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main);">Settings</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Manage your app preferences</p>
        </div>
    </div>

    <div class="card">
        <form id="settingsForm" method="POST">
            
            <!-- Notifications Section -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-notification-3-line"></i> Notifications
                </h3>
                
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="notifications_enabled" value="1" <?php echo ($savedData['notifications_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <span>Enable all notifications</span>
                    </label>

                    <label class="checkbox-label">
                        <input type="checkbox" name="order_updates" value="1" <?php echo ($savedData['order_updates'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <span>Order & delivery updates</span>
                    </label>

                    <label class="checkbox-label">
                        <input type="checkbox" name="promotional" value="1" <?php echo ($savedData['promotional'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <span>Promotional offers & deals</span>
                    </label>
                </div>
            </div>

            <!-- Preferences Section -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ri-settings-3-line"></i> Preferences
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="input-label">Language</label>
                        <select name="language" class="form-input">
                            <option value="English" <?php echo ($savedData['language'] ?? 'English') === 'English' ? 'selected' : ''; ?>>English</option>
                            <option value="Telugu" <?php echo ($savedData['language'] ?? '') === 'Telugu' ? 'selected' : ''; ?>>Telugu</option>
                            <option value="Hindi" <?php echo ($savedData['language'] ?? '') === 'Hindi' ? 'selected' : ''; ?>>Hindi</option>
                            <option value="Tamil" <?php echo ($savedData['language'] ?? '') === 'Tamil' ? 'selected' : ''; ?>>Tamil</option>
                            <option value="Kannada" <?php echo ($savedData['language'] ?? '') === 'Kannada' ? 'selected' : ''; ?>>Kannada</option>
                        </select>
                    </div>

                    <div>
                        <label class="input-label">Notification Method</label>
                        <select name="notification_method" class="form-input">
                            <option value="email" <?php echo ($savedData['notification_method'] ?? 'email') === 'email' ? 'selected' : ''; ?>>Email</option>
                            <option value="sms" <?php echo ($savedData['notification_method'] ?? '') === 'sms' ? 'selected' : ''; ?>>SMS</option>
                            <option value="whatsapp" <?php echo ($savedData['notification_method'] ?? '') === 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option>
                            <option value="push" <?php echo ($savedData['notification_method'] ?? '') === 'push' ? 'selected' : ''; ?>>Push Notification</option>
                        </select>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label class="input-label">Privacy Level</label>
                        <select name="privacy_level" class="form-input">
                            <option value="public" <?php echo ($savedData['privacy_level'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public - Share profile with others</option>
                            <option value="friends" <?php echo ($savedData['privacy_level'] ?? '') === 'friends' ? 'selected' : ''; ?>>Friends Only</option>
                            <option value="private" <?php echo ($savedData['privacy_level'] ?? '') === 'private' ? 'selected' : ''; ?>>Private - Keep data confidential</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                <i class="ri-save-line"></i> Save Settings
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

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        cursor: pointer;
        font-size: 0.95rem;
        color: var(--text-main);
    }

    .checkbox-label input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: var(--primary);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('settingsForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);

    fetch('settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Settings Saved!',
                text: 'Your preferences have been updated successfully.',
                confirmButtonColor: '#db2777'
            }).then(() => {
                window.location.href = "profile.php?section=settings"; // ✅ FIX
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to save settings',
            confirmButtonColor: '#db2777'
        });
    });
});
</script>

<?php include 'includes/bottom-nav.php'; ?>
