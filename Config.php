<?php
// ============================================
// Mahi Fashion House - Database Configuration
// ============================================
// DB credentials will be set by user later

define('DB_HOST', 'localhost');
define('DB_NAME', 'mahifashionhouse');
define('DB_USER', 'admin');
define('DB_PASS', '123456');

define('SITE_NAME', 'Mahi Fashion House');
define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));
define('ADMIN_EMAIL', 'admin@mahifashion.com');

// Create database connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Helper function to execute queries with UTF-8 support
function query($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Fetch all rows
function fetchAll($sql, $params = []) {
    return query($sql, $params)->fetchAll();
}

// Fetch single row
function fetchOne($sql, $params = []) {
    return query($sql, $params)->fetch();
}

// Get setting value
function getSetting($key, $default = '') {
    try {
        $row = fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Format Bangladeshi Taka
function formatPrice($price) {
    return '৳ ' . number_format($price, 0, '.', ',');
}

// Sanitize input
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Generate slug
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    return strtolower($text);
}

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cart helper
function getCartCount() {
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        return array_sum(array_column($_SESSION['cart'], 'qty'));
    }
    return 0;
}

// Get all settings
function getAllSettings() {
    try {
        $rows = fetchAll("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

$settings = getAllSettings();

// Get site favicon
function getFavicon() {
    global $settings;
    if (!empty($settings['site_favicon'])) {
        return 'uploads/favicon/' . $settings['site_favicon'];
    }
    return '';
}

// Get site logo
function getLogo() {
    global $settings;
    if (!empty($settings['site_logo'])) {
        return 'uploads/favicon/' . $settings['site_logo'];
    }
    return '';
}

// Meta Pixel code from admin
function getMetaPixelCode() {
    $pixel = getSetting('meta_pixel_code', '');
    if (empty($pixel)) {
        $pixel = getSetting('meta_pixel_id', '');
    }
    if (empty($pixel)) {
        $pixel = getSetting('fb_pixel', '');
    }
    return trim($pixel);
}

// Google Tag / API code from admin
function getGoogleTagCode() {
    $tag = getSetting('google_tag_code', '');
    if (empty($tag)) {
        $tag = getSetting('google_tag_id', '');
    }
    if (empty($tag)) {
        $tag = getSetting('gtag_id', '');
    }
    if (empty($tag)) {
        $tag = getSetting('conversion_id', '');
    }
    return trim($tag);
}

// Get Facebook link
function getFacebookLink() {
    return getSetting('facebook_link', '#');
}

// Get WhatsApp number
function getWhatsAppNumber() {
    return getSetting('whatsapp_number', '+8801849518188');
}

// Get phone number
function getPhoneNumber() {
    return getSetting('phone_number', '01849518188');
}

// Exchange policy text
function getExchangePolicy() {
    return getSetting('exchange_policy', 'সাইজ বা অন্য যেকোনো সমস্যায় পাচ্ছেন ৩ দিনের মধ্যে এক্সচেঞ্জ সুবিধা।');
}

// Return policy text
function getReturnPolicy() {
    return getSetting('return_policy', 'রির্টান সুবিধা : কোনো কারণে পণ্য রিটার্ন করতে চাইলে, শুধুমাত্র কুরিয়ার চার্জ প্রযোজ্য হবে!');
}

include_once __DIR__ . '/MetaCapi.php';
include_once __DIR__ . '/track.php';