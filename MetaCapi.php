<?php
/**
 * ============================================================
 * Meta Conversions API (Server-Side) — Mahi Fashion House
 * ============================================================
 * এই ফাইলটি বর্তমান Browser Pixel (fbq) কে কোনোভাবে পরিবর্তন করে না।
 * এটি শুধু সার্ভার থেকে সমান্তরালভাবে (parallel) Meta Graph API-তে
 * একই ইভেন্ট, একই Event ID সহ পাঠায় — যাতে Meta উভয় পাশের ডেটা
 * Deduplicate করে একটি নির্ভরযোগ্য কনভার্সন হিসেবে গণনা করে।
 *
 * নিরাপত্তা:
 *  - Access Token ভুল/মেয়াদোত্তীর্ণ হলেও ওয়েবসাইট, চেকআউট বা অর্ডার
 *    কখনো বন্ধ হবে না — শুধু লগ ফাইলে এরর সংরক্ষণ হবে।
 *  - ইউজারকে রেসপন্স পাঠানোর পরে (fastcgi_finish_request থাকলে)
 *    ব্যাকগ্রাউন্ডে cURL কল হয়, তাই সাইট ধীর হয় না।
 * ============================================================
 */

define('META_CAPI_API_VERSION', 'v21.0');
define('META_CAPI_LOG_FILE', __DIR__ . '/logs/meta_capi_errors.log');

/* ------------------------------------------------------------
 * সেটিংস রিড হেল্পার
 * ------------------------------------------------------------ */

// Conversions API চালু আছে কিনা (অ্যাডমিন টগল)
function isMetaCapiEnabled() {
    return getSetting('meta_capi_enabled', '0') === '1';
}

function getMetaAccessToken() {
    return trim(getSetting('meta_access_token', ''));
}

function getMetaTestEventCode() {
    return trim(getSetting('meta_test_event_code', ''));
}

/* ------------------------------------------------------------
 * Event ID / Hashing হেল্পার
 * ------------------------------------------------------------ */

// Browser Pixel ও Server Event — উভয় জায়গায় ব্যবহারের জন্য ইউনিক Event ID
function generateMetaEventId($prefix = 'evt') {
    try {
        $rand = bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        $rand = uniqid('', true);
    }
    return $prefix . '_' . $rand;
}

// Advanced Matching এর জন্য SHA-256 (Meta-নির্ধারিত নিয়মে normalize করে হ্যাশ)
function metaHash($value) {
    $value = strtolower(trim((string)($value ?? '')));
    if ($value === '') return null;
    return hash('sha256', $value);
}

// বাংলাদেশি ফোন নম্বর কে দেশের কোডসহ ফরম্যাটে এনে হ্যাশ করা হয়
function metaHashPhone($phone) {
    $digits = preg_replace('/\D+/', '', (string)($phone ?? ''));
    if ($digits === '') return null;
    if (strlen($digits) === 11 && $digits[0] === '0') {
        $digits = '88' . $digits;           // 01XXXXXXXXX -> 8801XXXXXXXXX
    } elseif (strlen($digits) === 10) {
        $digits = '880' . $digits;          // 1XXXXXXXXX -> 8801XXXXXXXXX
    }
    return metaHash($digits);
}

function getClientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function getClientUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function getCurrentUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return $scheme . '://' . $host . $uri;
}

// _fbp / _fbc কুকি — থাকলে ম্যাচ কোয়ালিটি বাড়ায়; না থাকলে বাদ দেওয়া হয় (ঐচ্ছিক)
function getFbpFbc() {
    $fbp = $_COOKIE['_fbp'] ?? null;
    $fbc = $_COOKIE['_fbc'] ?? null;
    if (!$fbc && !empty($_GET['fbclid'])) {
        $fbclid = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_GET['fbclid']);
        if ($fbclid !== '') {
            $fbc = 'fb.1.' . round(microtime(true) * 1000) . '.' . $fbclid;
        }
    }
    return [$fbp, $fbc];
}

/* ------------------------------------------------------------
 * ইভেন্ট জমা রাখা (এই রিকোয়েস্টের জন্য) ও পাঠানো
 * ------------------------------------------------------------ */

$GLOBALS['__meta_capi_queue'] = [];
$GLOBALS['__meta_capi_shutdown_registered'] = false;

/**
 * একটি Conversions API ইভেন্ট সারিতে জমা রাখে। Browser Pixel-এ ব্যবহৃত
 * $eventId এখানেও একই দিতে হবে (Deduplication বাধ্যতামূলক)।
 *
 * $customData   -> value, currency, content_ids, content_type, num_items ইত্যাদি (array)
 * $rawUserData  -> ['email'=>, 'phone'=>, 'first_name'=>, 'last_name'=>] — raw মান, ফাংশনের ভেতরেই হ্যাশ হয়
 */
function queueMetaCapiEvent($eventName, $eventId, $customData = [], $rawUserData = [], $eventSourceUrl = null) {
    try {
        if (!isMetaCapiEnabled()) return;
        if (!getMetaPixelCode() || !getMetaAccessToken()) return;

        [$fbp, $fbc] = getFbpFbc();

        $userData = [
            'client_ip_address' => getClientIp(),
            'client_user_agent' => getClientUserAgent(),
        ];
        if ($fbp) $userData['fbp'] = $fbp;
        if ($fbc) $userData['fbc'] = $fbc;

        if (!empty($rawUserData['email'])) {
            $h = metaHash($rawUserData['email']);
            if ($h) $userData['em'] = [$h];
        }
        if (!empty($rawUserData['phone'])) {
            $h = metaHashPhone($rawUserData['phone']);
            if ($h) $userData['ph'] = [$h];
        }
        if (!empty($rawUserData['first_name'])) {
            $h = metaHash($rawUserData['first_name']);
            if ($h) $userData['fn'] = [$h];
        }
        if (!empty($rawUserData['last_name'])) {
            $h = metaHash($rawUserData['last_name']);
            if ($h) $userData['ln'] = [$h];
        }

        $event = [
            'event_name'       => $eventName,
            'event_time'       => time(),
            'event_id'         => $eventId,
            'action_source'    => 'website',
            'event_source_url' => $eventSourceUrl ?: getCurrentUrl(),
            'user_data'        => $userData,
        ];
        if (!empty($customData)) {
            $event['custom_data'] = $customData;
        }

        $GLOBALS['__meta_capi_queue'][] = $event;

        if (!$GLOBALS['__meta_capi_shutdown_registered']) {
            $GLOBALS['__meta_capi_shutdown_registered'] = true;
            register_shutdown_function('flushMetaCapiQueue');
        }
    } catch (Throwable $e) {
        logMetaCapiError('queueMetaCapiEvent exception: ' . $e->getMessage());
    }
}

// পেজ রেসপন্স ইউজারকে পাঠানোর পরে (সম্ভব হলে) জমা হওয়া ইভেন্টগুলো Meta-তে পাঠায়
function flushMetaCapiQueue() {
    try {
        $queue = $GLOBALS['__meta_capi_queue'] ?? [];
        if (empty($queue)) return;
        $GLOBALS['__meta_capi_queue'] = [];

        // ইউজারের রেসপন্স আটকে না রেখে আগেই পাঠিয়ে দেওয়ার চেষ্টা — সাইট ধীর হবে না
        if (function_exists('fastcgi_finish_request')) {
            try { fastcgi_finish_request(); } catch (Throwable $e) {}
        } else {
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) { @ob_end_flush(); }
            }
            @flush();
        }

        sendMetaCapiBatch($queue);
    } catch (Throwable $e) {
        logMetaCapiError('flushMetaCapiQueue exception: ' . $e->getMessage());
    }
}

// cURL দিয়ে ব্যাচ ইভেন্ট Meta Graph API-তে পাঠায় (টাইমআউট কম রাখা হয়েছে)
function sendMetaCapiBatch($events) {
    $pixelId = getMetaPixelCode();
    $token = getMetaAccessToken();
    if (!$pixelId || !$token || empty($events)) return;

    $payload = ['data' => $events];
    $testCode = getMetaTestEventCode();
    if (!empty($testCode)) {
        $payload['test_event_code'] = $testCode; // থাকলেই শুধু পাঠানো হবে
    }

    $url = 'https://graph.facebook.com/' . META_CAPI_API_VERSION . '/' . rawurlencode($pixelId) . '/events?access_token=' . urlencode($token);

    try {
        if (!function_exists('curl_init')) {
            logMetaCapiError('cURL is not available on this server.');
            return;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlErrNo || $httpCode < 200 || $httpCode >= 300) {
            logMetaCapiError('Meta CAPI request failed', [
                'http_code'  => $httpCode,
                'curl_error' => $curlError,
                'response'   => $response,
                'events'     => array_column($events, 'event_name'),
            ]);
        }
    } catch (Throwable $e) {
        logMetaCapiError('Meta CAPI exception: ' . $e->getMessage(), [
            'events' => array_column($events, 'event_name'),
        ]);
    }
}

// এরর লগ — কখনোই এক্সসেপশন থ্রো করে না, সাইট থামায় না
function logMetaCapiError($message, $context = []) {
    try {
        $dir = dirname(META_CAPI_LOG_FILE);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        @file_put_contents(META_CAPI_LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        @error_log('Meta CAPI: ' . $message);
    }
}
