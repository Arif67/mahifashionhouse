<?php
/**
 * Lightweight site visit tracker for admin analytics.
 * Safe no-op if site_visits table is missing.
 */
if (!function_exists('trackSiteVisit')) {
    function trackSiteVisit($page = '', $event = 'view') {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $page = substr(preg_replace('/[^a-zA-Z0-9_\-\/\.]/', '', (string)$page), 0, 100);
            if ($page === '') {
                $page = basename(parse_url($_SERVER['REQUEST_URI'] ?? 'index', PHP_URL_PATH) ?: 'index');
                $page = preg_replace('/\.php$/', '', $page) ?: 'index';
            }
            $event = substr(preg_replace('/[^a-zA-Z0-9_]/', '', (string)$event), 0, 20) ?: 'view';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $device = '';
            if (!empty($_COOKIE['mfh_device'])) {
                $device = preg_replace('/[^a-zA-Z0-9_]/', '', $_COOKIE['mfh_device']);
            } elseif (!empty($_SESSION['device_token'])) {
                $device = preg_replace('/[^a-zA-Z0-9_]/', '', $_SESSION['device_token']);
            }
            if ($device === '') {
                $device = bin2hex(random_bytes(16));
                $_SESSION['device_token'] = $device;
                @setcookie('mfh_device', $device, time() + 86400 * 365, '/');
            }
            query(
                "INSERT INTO site_visits (page, ip_address, device_token, event) VALUES (?, ?, ?, ?)",
                [$page, $ip, $device, $event]
            );
        } catch (Throwable $e) {
            // Never break the storefront for analytics
        }
    }
}

// Auto-track normal page GETs (skip AJAX / POST APIs)
if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && php_sapi_name() !== 'cli'
) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!in_array($script, ['track.php', 'check_order_block.php'], true)
        && strpos($script, 'admin') === false
        && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') === false
    ) {
        trackSiteVisit(pathinfo($script, PATHINFO_FILENAME) ?: 'index', 'view');
    }
}
