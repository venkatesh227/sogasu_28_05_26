<?php
// Database Connection Configuration
$host = 'localhost';
$dbname = 'sogasu-new';
$user = 'root';
$pass = '';

date_default_timezone_set('Asia/Kolkata');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("SET time_zone = '+05:30'");

} catch (PDOException $e) {
    die("Database connection failed. Please ensure the database '$dbname' is created. Error: " . $e->getMessage());
}

if (!function_exists('has_permission')) {
    function has_permission($permission_key) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Super admins bypass all permission checks
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
            return true;
        }
        
        $active_role = $_SESSION['active_role'] ?? '';
        if (!$active_role) {
            return false;
        }
        
        static $permissions_cache = null;
        if ($permissions_cache === null) {
            global $pdo;
            try {
                $stmt = $pdo->prepare("SELECT permission_key FROM role_permissions WHERE role_name = ?");
                $stmt->execute([$active_role]);
                $permissions_cache = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $e) {
                $permissions_cache = [];
            }
        }
        
        return in_array($permission_key, $permissions_cache);
    }
}
?>