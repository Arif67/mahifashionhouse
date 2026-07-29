<?php
require_once '../Config.php';

/*
 * ============================================================
 * STEADFAST COURIER INTEGRATION
 * ============================================================
 */

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $orderId = intval($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        try {
            query("UPDATE orders SET status = ? WHERE id = ?", [$status, $orderId]);
            $message = 'অর্ডার স্ট্যাটাস আপডেট হয়েছে!';
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }
    if ($_POST['action'] === 'update_customer') {
        $orderId = intval($_POST['order_id'] ?? 0);
        $newName = clean($_POST['customer_name'] ?? '');
        $newPhone = clean($_POST['customer_phone'] ?? '');
        $newAddress = clean($_POST['customer_address'] ?? '');
        $newDistrict = clean($_POST['customer_district'] ?? '');
        $newDivision = clean($_POST['customer_division'] ?? '');
        try {
            query("UPDATE orders SET customer_name = ?, customer_phone = ?, customer_address = ?, customer_district = ?, customer_division = ? WHERE id = ?", [
                $newName, $newPhone, $newAddress, $newDistrict, $newDivision, $orderId
            ]);
            $message = 'কাস্টমার তথ্য আপডেট ও সিংক হয়েছে!';
            header('Location: orders.php?view=' . $orderId . '&updated=1');
            exit;
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }
    if ($_POST['action'] === 'delete') {
        $orderId = intval($_POST['order_id'] ?? 0);
        try {
            query("DELETE FROM order_items WHERE order_id = ?", [$orderId]);
            query("DELETE FROM orders WHERE id = ?", [$orderId]);
            $message = 'অর্ডার মুছে ফেলা হয়েছে!';
            header('Location: orders.php?deleted=1');
            exit;
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }
    if ($_POST['action'] === 'update_items') {
        $orderId = intval($_POST['order_id'] ?? 0);
        $itemPrices = $_POST['item_price'] ?? [];
        $newShipping = isset($_POST['shipping_cost']) ? floatval($_POST['shipping_cost']) : 0;
        try {
            $newSubtotal = 0;
            foreach ($itemPrices as $itemId => $price) {
                $itemId = intval($itemId);
                $price = floatval($price);
                $itm = fetchOne("SELECT quantity FROM order_items WHERE id = ? AND order_id = ?", [$itemId, $orderId]);
                if ($itm) {
                    $sub = $price * intval($itm['quantity']);
                    query("UPDATE order_items SET price = ?, subtotal = ? WHERE id = ?", [$price, $sub, $itemId]);
                    $newSubtotal += $sub;
                }
            }
            $newTotal = $newSubtotal + $newShipping;
            query("UPDATE orders SET shipping_cost = ?, total_amount = ? WHERE id = ?", [$newShipping, $newTotal, $orderId]);
            header('Location: orders.php?view=' . $orderId . '&price_updated=1');
            exit;
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }
    if ($_POST['action'] === 'send_steadfast') {
        $orderId = intval($_POST['order_id'] ?? 0);
        $customCod = isset($_POST['cod_amount']) ? floatval($_POST['cod_amount']) : null;
        $orderData = fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
        if ($orderData) {
            if (empty($orderData['customer_name']) || empty($orderData['customer_phone']) || empty($orderData['customer_address'])) {
                $error = 'গ্রাহকের নাম, ফোন এবং ঠিকানা পূরণ করা আবশ্যক স্টেডফাস্টে পাঠানোর জন্য';
            } else {
                // এডমিন দাম কম/বেশি করে দিলে ডাটাবেজেও আপডেট হয়ে যাবে
                if ($customCod !== null && $customCod >= 0 && $customCod != floatval($orderData['total_amount'])) {
                    try {
                        query("UPDATE orders SET total_amount = ? WHERE id = ?", [$customCod, $orderId]);
                        $orderData['total_amount'] = $customCod;
                    } catch (Exception $e) {}
                }
                $result = sendOrderToSteadfast($orderData);
                if ($result['http_code'] == 200 && isset($result['response']['status']) && $result['response']['status'] == 200) {
                    $consignmentId = $result['response']['consignment']['consignment_id'] ?? '';
                    $trackingCode = $result['response']['consignment']['tracking_code'] ?? '';
                    try {
                        query("UPDATE orders SET steadfast_consignment_id = ?, steadfast_tracking_code = ? WHERE id = ?", [$consignmentId, $trackingCode, $orderId]);
                    } catch (Exception $e) {}
                    $message = 'স্টেডফাস্টে সফলভাবে পাঠানো হয়েছে! Tracking Code: ' . clean($trackingCode);
                } else {
                    $errorMsg = $result['response']['message'] ?? '';
                    if (empty($errorMsg) && isset($result['response']['errors'])) {
                        $errs = $result['response']['errors'];
                        $errorMsg = is_array($errs) ? implode(', ', array_map(function($v){ return is_array($v)?implode(', ',$v):$v; }, $errs)) : strval($errs);
                    }
                    if (empty($errorMsg)) { $errorMsg = 'HTTP ' . $result['http_code']; }
                    $error = 'স্টেডফাস্টে পাঠাতে ব্যর্থ: ' . $errorMsg;
                }
            }
        } else {
            $error = 'অর্ডার পাওয়া যায়নি';
        }
    }
    if ($_POST['action'] === 'fraud_check') {
        $orderId = intval($_POST['order_id'] ?? 0);
        $orderData = fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
        if ($orderData) {
            $phone = sanitizePhoneForSteadfast($orderData['customer_phone'] ?? '');
            if (strlen($phone) !== 11) {
                $error = 'ফোন নম্বর ১১ ডিজিট হতে হবে ফ্রড চেকের জন্য';
            } else {
                $result = checkFraudSteadfast($phone);
                if ($result['http_code'] == 200 && isset($result['response']['total_parcels'])) {
                    $fraudData = $result['response'];
                    $fraudMsg = sprintf(
                        'ফ্রড রিপোর্ট: মোট পার্সেল %d, ডেলিভার্ড %d, ক্যান্সেল্ড %d, ফ্রড রিপোর্ট %d',
                        $fraudData['total_parcels'] ?? 0,
                        $fraudData['total_delivered'] ?? 0,
                        $fraudData['total_cancelled'] ?? 0,
                        count($fraudData['total_fraud_reports'] ?? [])
                    );
                    $message = $fraudMsg;
                } else {
                    $errMsg = $result['response']['message'] ?? 'ফ্রড চেক করতে ব্যর্থ';
                    $error = 'ফ্রড চেক: ' . $errMsg;
                }
            }
        } else {
            $error = 'অর্ডার পাওয়া যায়নি';
        }
    }
}

$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$sql = "SELECT o.*, 
    (SELECT product_image FROM order_items WHERE order_id = o.id ORDER BY id LIMIT 1) as product_image 
    FROM orders o WHERE 1=1";
$params = [];
if ($filterStatus) {
    $sql .= " AND o.status = ?";
    $params[] = $filterStatus;
}
if ($search !== '') {
    $sql .= " AND (o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.order_number LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$sql .= " ORDER BY o.created_at DESC";
$orders = fetchAll($sql, $params);

$statusCounts = [];
$allStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
foreach ($allStatuses as $st) {
    $row = fetchOne("SELECT COUNT(*) as c FROM orders WHERE status = ?", [$st]);
    $statusCounts[$st] = $row['c'] ?? 0;
}
$totalCount = array_sum($statusCounts);
$pendingCount = $statusCounts['pending'] ?? 0;

if (isset($_GET['updated'])) {
    $message = 'কাস্টমার তথ্য আপডেট ও সিংক হয়েছে!';
}
if (isset($_GET['price_updated'])) {
    $message = 'অর্ডারের দাম আপডেট হয়েছে!';
}

$viewOrder = null;
$viewItems = [];
if (isset($_GET['view'])) {
    $viewOrder = fetchOne("SELECT * FROM orders WHERE id = ?", [intval($_GET['view'])]);
    if ($viewOrder) {
        $viewItems = fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$viewOrder['id']]);
    } else {
        header('Location: orders.php');
        exit;
    }
}

$statusLabels = [
    'pending'    => 'Pending',
    'confirmed'  => 'Confirmed',
    'processing' => 'Processing',
    'shipped'    => 'Shipped',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled'
];
$statusColors = [
    'pending'    => 'pending',
    'confirmed'  => 'confirmed',
    'processing' => 'processing',
    'shipped'    => 'shipped',
    'delivered'  => 'delivered',
    'cancelled'  => 'cancelled'
];

// ─── বাংলাদেশ টাইম কনভার্টার (DB তে UTC সেভ হয়, দেখাবে Asia/Dhaka) ───
function bdDate($timestamp, $format) {
    try {
        $dt = new DateTime($timestamp, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Dhaka'));
        return $dt->format($format);
    } catch (Exception $e) {
        return date($format, strtotime($timestamp));
    }
}

$siteName = getSetting('site_name', 'Mahi Fashion House');
$adminName = $_SESSION['admin_username'] ?? 'Admin';

function sanitizePhoneForSteadfast($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($phone, '880') === 0) {
        $phone = '0' . substr($phone, 3);
    }
    if (strpos($phone, '0') !== 0) {
        $phone = '0' . $phone;
    }
    return substr($phone, 0, 11);
}

function sendOrderToSteadfast($order) {
    $apiKey    = 'vro8fvefldpcntyz7jlx7ndo1pmbls9y';
    $secretKey = 'j7zmhzntohpfn2jstlxayw8e';
    $baseUrl   = 'https://portal.packzy.com/api/v1';

    $phone = sanitizePhoneForSteadfast($order['customer_phone'] ?? '');
    if (strlen($phone) !== 11) {
        return ['http_code' => 0, 'response' => ['message' => 'Invalid phone number: must be 11 digits']];
    }

    $parts = array_filter([
        $order['customer_address'] ?? '',
        $order['customer_district'] ?? '',
        $order['customer_division'] ?? ''
    ]);
    $address = implode(', ', $parts);
    if (strlen($address) > 250) {
        $address = substr($address, 0, 250);
    }

    $data = [
        'invoice'         => $order['order_number'] ?? ('ORD' . $order['id']),
        'recipient_name'  => $order['customer_name'] ?? '',
        'recipient_phone' => $phone,
        'recipient_address'=> $address,
        'cod_amount'      => floatval($order['total_amount'] ?? 0),
        'note'            => !empty($order['customer_note']) ? $order['customer_note'] : ('Order from ' . SITE_NAME)
    ];

    $ch = curl_init($baseUrl . '/create_order');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Api-Key: ' . $apiKey,
        'Secret-Key: ' . $secretKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
    curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 120);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['http_code' => 0, 'response' => ['message' => 'Connection error: ' . $curlError]];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['http_code' => $httpCode, 'response' => ['message' => 'Invalid API response']];
    }
    return ['http_code' => $httpCode, 'response' => $decoded];
}

function checkFraudSteadfast($phone) {
    $apiKey    = 'vro8fvefldpcntyz7jlx7ndo1pmbls9y';
    $secretKey = 'j7zmhzntohpfn2jstlxayw8e';
    $baseUrl   = 'https://portal.packzy.com/api/v1';

    $ch = curl_init($baseUrl . '/fraud_check/' . urlencode($phone));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Api-Key: ' . $apiKey,
        'Secret-Key: ' . $secretKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
    curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 120);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['http_code' => 0, 'response' => ['message' => 'Connection error: ' . $curlError]];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['http_code' => $httpCode, 'response' => ['message' => 'Invalid API response']];
    }
    return ['http_code' => $httpCode, 'response' => $decoded];
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#667eea">
    <title>অর্ডারস – <?php echo $siteName; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6C63FF;
            --primary-dark: #5a52d5;
            --primary-light: #8b83ff;
            --secondary: #FF6B6B;
            --gradient-start: #667eea;
            --gradient-end: #764ba2;
            --bg: #f0f2f5;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #6c757d;
            --shadow: 0 8px 30px rgba(108, 99, 255, 0.12);
            --shadow-lg: 0 12px 40px rgba(108, 99, 255, 0.18);
            --radius: 20px;
            --radius-sm: 14px;
            --sidebar-width: 280px;
            --bottom-nav-height: 72px;
            --header-height: 70px;
            --safe-top: env(safe-area-inset-top);
            --safe-bottom: env(safe-area-inset-bottom);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Hind Siliguri', 'Kalpurush', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            padding-bottom: calc(var(--bottom-nav-height) + var(--safe-bottom) + 16px);
            padding-top: var(--safe-top);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; touch-action: manipulation; }
        img { max-width: 100%; display: block; }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }

        .app { max-width: 1400px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; }

        /* ===== HEADER ===== */
        .header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 24px rgba(102, 126, 234, 0.4);
            min-height: var(--header-height);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .header-left { display: flex; align-items: center; gap: 14px; }
        .menu-btn {
            color: #fff; font-size: 20px; width: 42px; height: 42px; border-radius: 12px;
            background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s cubic-bezier(0.22,1,0.36,1); display: none;
        }
        .menu-btn:active { transform: scale(0.88); background: rgba(255,255,255,0.25); }
        .brand { display: flex; align-items: center; gap: 10px; color: #fff; }
        .brand i { font-size: 24px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        .brand h1 { font-size: 19px; font-weight: 700; letter-spacing: 0.3px; line-height: 1.2; }
        .brand span { font-size: 11px; font-weight: 400; opacity: 0.85; display: block; margin-top: -2px; }
        .header-right { display: flex; align-items: center; gap: 10px; }
        .header-btn {
            color: #fff; font-size: 18px; width: 42px; height: 42px; border-radius: 12px;
            background: rgba(255,255,255,0.12); backdrop-filter: blur(10px);
            display: flex; align-items: center; justify-content: center; position: relative; transition: all 0.2s;
        }
        .header-btn:active { transform: scale(0.88); background: rgba(255,255,255,0.22); }
        .badge-dot {
            position: absolute; top: 8px; right: 8px; width: 10px; height: 10px;
            background: var(--secondary); border-radius: 50%; border: 2px solid var(--gradient-start);
            box-shadow: 0 0 0 2px rgba(255,107,107,0.3); animation: pulse 2s infinite;
        }
        @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.1); opacity: 0.8; } }
        .admin-avatar {
            width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,0.20);
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px;
            color: #fff; backdrop-filter: blur(10px); border: 1.5px solid rgba(255,255,255,0.25); transition: transform 0.2s;
        }
        .admin-avatar:active { transform: scale(0.9); }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed; top: var(--header-height); left: 0; bottom: 0; width: var(--sidebar-width);
            background: #fff; box-shadow: 4px 0 30px rgba(0,0,0,0.06); padding: 0; overflow-y: auto;
            z-index: 90; transition: transform 0.35s cubic-bezier(0.32,0.72,0,1);
            border-right: 1px solid rgba(0,0,0,0.04); display: flex; flex-direction: column;
        }
        .sidebar-profile {
            padding: 24px 20px 20px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            position: relative; overflow: hidden;
        }
        .sidebar-profile::before {
            content: ''; position: absolute; top: -30px; right: -30px; width: 100px; height: 100px;
            background: rgba(255,255,255,0.08); border-radius: 50%;
        }
        .sidebar-profile::after {
            content: ''; position: absolute; bottom: -20px; left: -20px; width: 80px; height: 80px;
            background: rgba(255,255,255,0.05); border-radius: 50%;
        }
        .profile-top { display: flex; align-items: center; gap: 14px; position: relative; z-index: 1; }
        .profile-avatar {
            width: 52px; height: 52px; border-radius: 16px; background: rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 20px;
            color: #fff; border: 2px solid rgba(255,255,255,0.3); backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .profile-info h4 { color: #fff; font-size: 16px; font-weight: 700; margin-bottom: 2px; }
        .profile-info p { color: rgba(255,255,255,0.8); font-size: 12px; font-weight: 500; }
        .profile-status {
            display: inline-flex; align-items: center; gap: 6px; margin-top: 8px;
            background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 20px;
            font-size: 11px; color: #fff; font-weight: 600; backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .profile-status .dot { width: 8px; height: 8px; background: #4ade80; border-radius: 50%; box-shadow: 0 0 0 2px rgba(74,222,128,0.3); }
        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .sidebar .nav-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary); padding: 12px 14px 6px; font-weight: 700; opacity: 0.7; }
        .sidebar .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 12px 16px; margin: 3px 0;
            border-radius: 14px; color: var(--text-secondary); font-weight: 500; font-size: 14px;
            transition: all 0.25s cubic-bezier(0.22,1,0.36,1); position: relative; overflow: hidden;
        }
        .sidebar .nav-item::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 0;
            background: linear-gradient(90deg, var(--primary), var(--primary-light)); opacity: 0.08;
            transition: width 0.3s; border-radius: 14px;
        }
        .sidebar .nav-item:hover::before { width: 100%; }
        .sidebar .nav-item i { width: 22px; font-size: 18px; text-align: center; transition: all 0.3s; display: flex; align-items: center; justify-content: center; }
        .sidebar .nav-item:hover { color: var(--primary); transform: translateX(4px); }
        .sidebar .nav-item:hover i { transform: scale(1.1); }
        .sidebar .nav-item.active {
            background: linear-gradient(135deg, rgba(108,99,255,0.12), rgba(108,99,255,0.06));
            color: var(--primary); font-weight: 600; box-shadow: 0 2px 12px rgba(108,99,255,0.1);
        }
        .sidebar .nav-item.active i { color: var(--primary); }
        .sidebar .nav-item .badge {
            margin-left: auto; background: linear-gradient(135deg, var(--secondary), #ff8e8e);
            color: #fff; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 20px;
            box-shadow: 0 2px 8px rgba(255,107,107,0.3);
        }
        .sidebar-footer { padding: 12px; border-top: 1px solid rgba(0,0,0,0.05); background: #fafbfc; }
        .sidebar-footer .nav-item { color: #dc3545; margin: 0; }
        .sidebar-footer .nav-item:hover { background: rgba(220, 53, 69, 0.06); color: #dc3545; transform: translateX(4px); }
        .sidebar-footer .nav-item::before { background: rgba(220, 53, 69, 0.08); }
        .sidebar-bottom-info { padding: 12px 16px; text-align: center; font-size: 11px; color: var(--text-secondary); opacity: 0.6; border-top: 1px solid rgba(0,0,0,0.04); }

        .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 85; opacity: 0; pointer-events: none; transition: opacity 0.3s; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
        .sidebar-overlay.active { opacity: 1; pointer-events: all; }

        .main-content { margin-left: var(--sidebar-width); padding: 24px 28px 100px; flex: 1; transition: margin 0.3s; }

        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; animation: fadeUp 0.5s ease forwards; }
        .page-title { font-size: 24px; font-weight: 700; }
        .page-title small { font-size: 14px; font-weight: 400; color: var(--text-secondary); margin-left: 8px; }

        .alert { padding: 14px 20px; border-radius: 14px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px; animation: fadeUp 0.5s ease 0.1s both; }
        .alert-success { background: #e6f7ed; color: #0caa5e; }
        .alert-danger { background: #fee; color: #e74c3c; }

        .filter-bar { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; animation: fadeUp 0.5s ease 0.15s both; }
        .filter-btn { padding: 8px 18px; border-radius: 30px; font-size: 13px; font-weight: 500; background: #fff; color: var(--text-secondary); border: 1px solid #e0e0e0; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .filter-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-1px); }
        .filter-btn.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; border-color: var(--primary); box-shadow: 0 4px 12px rgba(108,99,255,0.25); }
        .filter-btn .count { background: rgba(0,0,0,0.08); padding: 0 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .filter-btn.active .count { background: rgba(255,255,255,0.25); }

        /* ===== ORDERS LIST (TWO-LINE CARD WITH IMAGE) ===== */
.orders-grid { display: flex; flex-direction: column; gap: 10px; }
.order-card {
    background: var(--card-bg); border-radius: 16px; padding: 12px 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid rgba(108,99,255,0.04);
    transition: all 0.2s cubic-bezier(0.22,1,0.36,1); position: relative; overflow: hidden;
    animation: fadeUp 0.4s ease forwards; opacity: 0;
}
.order-card:active { transform: scale(0.98); }
.order-card:hover { box-shadow: 0 4px 20px rgba(108,99,255,0.12); }
.order-card::before {
    content: ''; position: absolute; left: 0; top: 8px; bottom: 8px; width: 3px;
    border-radius: 0 4px 4px 0;
}
.order-card.pending::before { background: linear-gradient(180deg, #f57c00, #ff9800); }
.order-card.confirmed::before { background: linear-gradient(180deg, #4a6cf7, #6b8cff); }
.order-card.processing::before { background: linear-gradient(180deg, #4a6cf7, #6b8cff); }
.order-card.shipped::before { background: linear-gradient(180deg, #0caa5e, #4ade80); }
.order-card.delivered::before { background: linear-gradient(180deg, #0caa5e, #4ade80); }
.order-card.cancelled::before { background: linear-gradient(180deg, #e74c3c, #ff6b6b); }

/* Card Inner Layout — RIGID SINGLE-LINE */
.order-card-inner {
    display: flex; align-items: center; gap: 10px;
}
.order-thumb-wrap {
    width: 48px; height: 48px; border-radius: 12px; overflow: hidden;
    flex-shrink: 0; background: #f0f2f5; display: flex; align-items: center;
    justify-content: center; border: 1px solid #eee;
}
.order-thumb-wrap img { width: 100%; height: 100%; object-fit: cover; }
.order-thumb-wrap .thumb-icon { font-size: 18px; color: #ccc; }

.order-card-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }

/* Top Line — NEVER WRAP */
.order-top-line {
    display: flex; align-items: center; gap: 8px;
    flex-wrap: nowrap; overflow: hidden;
}
.order-top-line .order-name {
    font-size: 14px; font-weight: 700; color: var(--text-primary);
    line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    flex: 1; min-width: 0;
}
.order-top-meta {
    display: flex; align-items: center; gap: 6px;
    font-size: 11px; color: var(--text-secondary);
    flex-shrink: 0; white-space: nowrap;
}
.order-top-meta .meta-item { display: inline-flex; align-items: center; gap: 3px; }
.order-top-meta .meta-item i { font-size: 10px; opacity: 0.6; }
.order-top-meta .meta-sep { color: #ccc; font-size: 9px; }
.order-top-meta .meta-item.price { font-weight: 700; color: var(--primary); }

/* Bottom Line — NEVER WRAP */
.order-bottom-line {
    display: flex; align-items: center; justify-content: space-between;
    gap: 6px; flex-wrap: nowrap; overflow: hidden;
}
.order-badges { display: flex; align-items: center; gap: 5px; flex-shrink: 1; min-width: 0; overflow: hidden; }
.status-pill-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 20px; font-size: 11px;
    font-weight: 600; text-transform: capitalize; white-space: nowrap; flex-shrink: 0;
}
.status-pill-badge .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.status-pill-badge.pending { background: #fef0e6; color: #f57c00; }
.status-pill-badge.confirmed { background: #e8edfd; color: #4a6cf7; }
.status-pill-badge.processing { background: #e8edfd; color: #4a6cf7; }
.status-pill-badge.shipped { background: #e6f7ed; color: #0caa5e; }
.status-pill-badge.delivered { background: #e6f7ed; color: #0caa5e; }
.status-pill-badge.cancelled { background: #fee; color: #e74c3c; }

.steadfast-pill-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 20px; font-size: 10px;
    font-weight: 600; white-space: nowrap; flex-shrink: 0;
}
.steadfast-pill-badge.sent { background: #e6f7ed; color: #0caa5e; }
.steadfast-pill-badge.none { background: #f0f2f5; color: #999; border: 1px solid #e8e8e8; }
.steadfast-pill-badge i { font-size: 9px; }

.order-actions { display: flex; align-items: center; gap: 5px; flex-shrink: 0; }
.btn-action-sm {
    width: 30px; height: 30px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; transition: all 0.2s; border: none; cursor: pointer; flex-shrink: 0;
}
.btn-action-sm:active { transform: scale(0.9); }
.btn-action-sm.view {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: #fff; box-shadow: 0 2px 6px rgba(108,99,255,0.2);
}
.btn-action-sm.delete { background: #fee; color: #e74c3c; }
.btn-action-sm.delete:active { background: #e74c3c; color: #fff; }

/* Desktop tweaks */
@media (min-width: 769px) {
    .order-thumb-wrap { width: 56px; height: 56px; border-radius: 14px; }
    .order-thumb-wrap .thumb-icon { font-size: 20px; }
    .order-top-line .order-name { font-size: 15px; }
    .order-top-meta { font-size: 12px; gap: 8px; }
    .status-pill-badge { padding: 5px 12px; font-size: 12px; }
    .steadfast-pill-badge { padding: 5px 12px; font-size: 11px; }
    .btn-action-sm { width: 34px; height: 34px; border-radius: 10px; font-size: 14px; }
    .order-card-body { gap: 8px; }
}

/* ===== DETAIL VIEW ===== */
        .detail-container { max-width: 800px; margin: 0 auto; animation: fadeUp 0.5s ease forwards; }

        /* Detail Header Card */
        .detail-header-card {
            background: var(--card-bg); border-radius: var(--radius); padding: 20px 24px;
            box-shadow: var(--shadow); border: 1px solid rgba(108,99,255,0.04); margin-bottom: 16px;
        }
        .detail-header-top {
            display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px;
        }
        .detail-customer-name { font-size: 20px; font-weight: 700; color: var(--text-primary); }
        .detail-meta { font-size: 13px; color: var(--text-secondary); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .detail-meta i { font-size: 12px; }
        .detail-meta .sep { color: #ddd; }
        .back-btn {
            padding: 8px 16px; border-radius: 10px; background: #f0f2f5; color: var(--text-secondary);
            font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; border: none; cursor: pointer;
        }
        .back-btn:hover { background: #e0e0e0; }
        .back-btn:active { transform: scale(0.95); }

        /* Section Card — Clean & Consistent */
        .section-card {
            background: var(--card-bg); border-radius: var(--radius); padding: 20px 24px;
            box-shadow: var(--shadow); border: 1px solid rgba(108,99,255,0.04); margin-bottom: 16px;
        }
        .section-title {
            font-size: 13px; font-weight: 700; color: var(--text-primary);
            display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .section-title i {
            color: var(--primary); font-size: 14px; width: 32px; height: 32px;
            background: rgba(108,99,255,0.08); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }

        /* Info Grid */
        /* ===== CUSTOMER EDIT + SYNC BUTTON ===== */
        .edit-input {
            width: 100%; padding: 10px 12px; border: 1.5px solid #e6e6f0; border-radius: 10px;
            font-size: 14px; font-family: 'Hind Siliguri', sans-serif; background: #fafbff;
            color: var(--text-primary); transition: all 0.2s; outline: none; margin-top: 4px;
        }
        .edit-input:focus { border-color: #00b894; background: #fff; box-shadow: 0 0 0 3px rgba(0,184,148,0.10); }
        .edit-textarea { resize: vertical; min-height: 52px; }
        .btn-sync {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; margin-top: 14px; padding: 13px;
            background: linear-gradient(135deg, #00b894, #0caa5e); color: #fff;
            border: none; border-radius: 12px; font-size: 14px; font-weight: 700;
            font-family: 'Hind Siliguri', sans-serif; cursor: pointer;
            box-shadow: 0 4px 14px rgba(0,184,148,0.30); transition: all 0.2s;
        }
        .btn-sync:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,184,148,0.40); }
        .btn-sync:active { transform: scale(0.97); }
        .addr-edit-btn {
            display: inline-flex; align-items: center; gap: 5px; margin-left: 8px;
            padding: 4px 12px; border: none; border-radius: 20px; cursor: pointer;
            background: linear-gradient(135deg, #00b894, #0caa5e); color: #fff;
            font-size: 11px; font-weight: 700; font-family: 'Hind Siliguri', sans-serif;
            box-shadow: 0 3px 10px rgba(0,184,148,0.30); transition: all 0.2s; vertical-align: middle;
        }
        .addr-edit-btn:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(0,184,148,0.40); }
        .addr-edit-btn:active { transform: scale(0.94); }
        .price-edit-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border: none; border-radius: 20px; cursor: pointer;
            background: linear-gradient(135deg, #00b894, #0caa5e); color: #fff;
            font-size: 11px; font-weight: 700; font-family: 'Hind Siliguri', sans-serif;
            box-shadow: 0 3px 10px rgba(0,184,148,0.30); transition: all 0.2s;
        }
        .price-edit-btn:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(0,184,148,0.40); }
        .price-edit-btn:active { transform: scale(0.94); }
        .price-edit-btn i { font-size: 9px; }
        .price-edit-row {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 10px 0; border-bottom: 1px solid #f0f2f5;
        }
        .price-edit-row:last-of-type { border-bottom: none; }
        .price-edit-name { font-size: 13px; font-weight: 600; flex: 1; min-width: 0; }
        .price-edit-name small { display: block; font-size: 11px; color: var(--text-secondary); font-weight: 500; }
        .price-edit-input {
            display: flex; align-items: center; gap: 6px; background: #f0fdf4;
            border: 1.5px solid #bbf7d0; border-radius: 10px; padding: 4px 10px; flex-shrink: 0;
            transition: all 0.2s;
        }
        .price-edit-input:focus-within { border-color: #00b894; background: #fff; box-shadow: 0 0 0 3px rgba(0,184,148,0.12); }
        .price-edit-input span { font-size: 14px; font-weight: 700; color: #00b894; }
        .price-edit-input input {
            width: 80px; border: none; outline: none; background: transparent;
            font-size: 15px; font-weight: 700; color: var(--text-primary); padding: 6px 0;
            font-family: 'Hind Siliguri', sans-serif; -moz-appearance: textfield;
        }
        .price-edit-input input::-webkit-outer-spin-button, .price-edit-input input::-webkit-inner-spin-button { -webkit-appearance: none; }
        .shipping-row { background: #fafbff; border-radius: 10px; padding: 10px 12px; margin-top: 4px; }
        .addr-modal-head i.fa-tag { color: #00b894; }
        .addr-edit-btn i { font-size: 9px; }
        .addr-modal-overlay {
            position: fixed; inset: 0; background: rgba(26,26,46,0.55); backdrop-filter: blur(3px);
            display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px;
        }
        .addr-modal-overlay.show { display: flex; }
        .addr-modal {
            background: #fff; border-radius: 18px; padding: 20px; width: 100%; max-width: 380px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25); max-height: 90vh; overflow-y: auto;
            animation: addrPop 0.25s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes addrPop { from { opacity: 0; transform: scale(0.9) translateY(15px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .addr-modal-head {
            display: flex; align-items: center; justify-content: space-between;
            font-size: 15px; font-weight: 700; margin-bottom: 14px; color: var(--text-primary);
        }
        .addr-modal-head i { color: #00b894; margin-right: 6px; }
        .addr-modal-close {
            width: 30px; height: 30px; border-radius: 10px; background: #f0f2f5; border: none;
            color: var(--text-secondary); font-size: 13px; cursor: pointer;
        }
        .addr-field { margin-bottom: 12px; }
        .addr-field label { font-size: 12px; font-weight: 600; color: var(--text-secondary); display: block; }
        .btn-sync i { animation: syncSpin 3s linear infinite; }
        @keyframes syncSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; }
        .info-item { display: flex; flex-direction: column; gap: 4px; }
        .info-item.full { grid-column: 1 / -1; }
        .info-label { font-size: 11px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .info-value { font-size: 15px; font-weight: 600; color: var(--text-primary); word-break: break-word; }
        .info-value.note {
            font-size: 14px; font-weight: 400; color: var(--text-secondary); line-height: 1.5;
            background: #fafbfc; padding: 12px 14px; border-radius: 12px; border: 1px solid #eee;
        }

        /* ===== ORDER ITEMS — NO SCROLL (Responsive Cards) ===== */
        .items-list { display: flex; flex-direction: column; gap: 10px; }
        .item-row {
            display: flex; align-items: center; gap: 12px;
            background: #fafbfc; border-radius: 14px; padding: 12px 14px;
            border: 1px solid #f0f2f5; transition: all 0.2s;
        }
        .item-row:hover { background: #f0f2f5; }
        .item-img-wrap { width: 48px; height: 48px; border-radius: 12px; overflow: hidden; flex-shrink: 0; background: #f0f2f5; display: flex; align-items: center; justify-content: center; }
        .item-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .item-img-wrap i { font-size: 18px; color: #ccc; }
        .item-details { flex: 1; min-width: 0; }
        .item-name { font-size: 14px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
        .item-sub { font-size: 22px; color: #e74c3c; font-weight: 800; }
        .item-price { font-size: 15px; font-weight: 700; color: var(--text-primary); white-space: nowrap; }
        .item-qty {
            font-size: 12px; font-weight: 700; color: #fff; background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 4px 10px; border-radius: 20px; white-space: nowrap;
        }

        .totals-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 18px; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 14px; margin-top: 14px; color: #fff;
        }
        .totals-bar .label { font-size: 12px; opacity: 0.85; font-weight: 500; }
        .totals-bar .amount { font-size: 22px; font-weight: 700; }

        /* ===== PAYMENT — ONE LINE ===== */
        .payment-row { display: flex; gap: 12px; }
        .payment-box {
            flex: 1; background: #fafbfc; border-radius: 14px; padding: 14px 16px;
            border: 1px solid #f0f2f5; display: flex; flex-direction: column; gap: 4px;
        }
        .payment-box .label { font-size: 11px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; }
        .payment-box .value { font-size: 15px; font-weight: 700; color: var(--text-primary); }

        /* ===== STATUS PILLS ===== */
        .status-pills { display: flex; flex-wrap: wrap; gap: 8px; }
        .status-pill {
            padding: 10px 16px; border-radius: 30px; font-size: 12px; font-weight: 700;
            font-family: 'Hind Siliguri', sans-serif; border: 2px solid transparent; cursor: pointer;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
            touch-action: manipulation; background: #fff; border-color: #e0e0e0; color: var(--text-secondary);
        }
        .status-pill:active { transform: scale(0.92); }
        .status-pill.active { color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .status-pill.pending.active { background: #f57c00; border-color: #f57c00; }
        .status-pill.confirmed.active { background: #4a6cf7; border-color: #4a6cf7; }
        .status-pill.processing.active { background: #4a6cf7; border-color: #4a6cf7; }
        .status-pill.shipped.active { background: #0caa5e; border-color: #0caa5e; }
        .status-pill.delivered.active { background: #0caa5e; border-color: #0caa5e; }
        .status-pill.cancelled.active { background: #e74c3c; border-color: #e74c3c; }
        .status-pill .check { display: none; }
        .status-pill.active .check { display: inline; }

        /* ===== STEADFAST — CLEAN COMPACT ===== */
        .steadfast-card {
            background: linear-gradient(135deg, #f0fdf4, #fff); border: 1px solid #bbf7d0;
            border-radius: 16px; padding: 16px 20px;
        }
        .steadfast-card.sent { background: linear-gradient(135deg, #f0f9ff, #fff); border-color: #bae6fd; }
        .steadfast-header { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .steadfast-icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: linear-gradient(135deg, #00b894, #00a383); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;
        }
        .steadfast-card.sent .steadfast-icon { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .steadfast-title { font-size: 15px; font-weight: 700; color: var(--text-primary); }
        .steadfast-sub { font-size: 12px; color: var(--text-secondary); }
        .steadfast-id-row { display: flex; gap: 10px; margin-bottom: 12px; }
        .steadfast-id-box {
            flex: 1; background: #fff; border-radius: 12px; padding: 10px 14px;
            border: 1px solid #e0f2fe; display: flex; align-items: center; gap: 8px;
        }
        .steadfast-id-box .code {
            font-family: 'SF Mono', 'Courier New', monospace; font-size: 14px; font-weight: 700;
            color: var(--primary); flex: 1; letter-spacing: 0.5px; word-break: break-all;
        }
        .steadfast-id-box .copy-btn {
            width: 32px; height: 32px; border-radius: 8px; background: #e0f2fe;
            color: var(--primary); border: none; display: flex; align-items: center;
            justify-content: center; font-size: 12px; cursor: pointer; transition: all 0.2s; flex-shrink: 0;
        }
        .steadfast-id-box .copy-btn:hover { background: var(--primary); color: #fff; }
        .steadfast-id-box .copy-btn:active { transform: scale(0.9); }
        .steadfast-id-box .copy-btn.copied { background: #0caa5e; color: #fff; }
        .steadfast-track-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            padding: 12px 20px; border-radius: 12px; width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; font-size: 14px; font-weight: 700; text-decoration: none;
            box-shadow: 0 4px 12px rgba(108,99,255,0.25); transition: all 0.2s;
        }
        .steadfast-track-btn:hover { transform: translateY(-2px); }
        .steadfast-track-btn:active { transform: scale(0.97); }
        .btn-action {
            padding: 12px 20px; border-radius: 12px; font-size: 14px; font-weight: 700;
            font-family: 'Hind Siliguri', sans-serif; display: inline-flex; align-items: center;
            justify-content: center; gap: 8px; cursor: pointer; transition: all 0.2s; border: none;
            touch-action: manipulation; width: 100%;
        }
        .btn-action:active { transform: scale(0.95); }
        .btn-action.steadfast {
            background: linear-gradient(135deg, #00b894, #00a383); color: #fff;
            box-shadow: 0 4px 12px rgba(0,184,148,0.25);
        }
        .btn-action.steadfast:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,184,148,0.35); }

        /* ===== FRAUD CHECK — COMPACT INLINE ===== */
        .fraud-card {
            background: linear-gradient(135deg, #fff5f5, #fff); border: 1px solid #fed7d7;
            border-radius: 16px; padding: 16px 20px;
        }
        .fraud-header-row { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .fraud-icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: linear-gradient(135deg, #e17055, #d63031); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;
        }
        .fraud-title { font-size: 15px; font-weight: 700; color: var(--text-primary); }
        .fraud-sub { font-size: 12px; color: var(--text-secondary); }
        .fraud-btn {
            padding: 10px 18px; border-radius: 10px; background: linear-gradient(135deg, #e17055, #d63031);
            color: #fff; font-size: 13px; font-weight: 700; border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
            box-shadow: 0 4px 12px rgba(225,112,85,0.25); transition: all 0.2s; flex-shrink: 0;
        }
        .fraud-btn:hover { transform: translateY(-2px); }
        .fraud-btn:active { transform: scale(0.95); }
        .fraud-stats-row { display: flex; gap: 8px; }
        .fraud-stat-box {
            flex: 1; background: #fff; border-radius: 12px; padding: 12px 8px;
            text-align: center; border: 1px solid #fed7d7;
        }
        .fraud-stat-box .val { font-size: 20px; font-weight: 700; color: var(--text-primary); display: block; line-height: 1; }
        .fraud-stat-box .val.delivered { color: #0caa5e; }
        .fraud-stat-box .val.cancelled { color: #e74c3c; }
        .fraud-stat-box .val.fraud { color: #c53030; }
        .fraud-stat-box .lbl { font-size: 10px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; margin-top: 4px; display: block; }

        /* ===== ACTION ROW (CONTACT + DELETE) ===== */
        .action-row { display: flex; gap: 10px; margin-top: 20px; }
        .action-row .btn-action { flex: 1; }
        .btn-action.call {
            background: linear-gradient(135deg, #25D366, #1da85a); color: #fff;
            box-shadow: 0 4px 12px rgba(37,211,102,0.25);
        }
        .btn-action.call:hover { transform: translateY(-2px); }
        .btn-action.delete {
            background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff;
            box-shadow: 0 4px 12px rgba(231,76,60,0.2);
        }
        .btn-action.delete:hover { transform: translateY(-2px); }

        /* ===== BOTTOM NAV ===== */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: calc(var(--bottom-nav-height) + var(--safe-bottom));
            padding-bottom: var(--safe-bottom);
            background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            display: none; align-items: center; justify-content: space-around;
            box-shadow: 0 -4px 24px rgba(0,0,0,0.08); border-top: 1px solid rgba(0,0,0,0.04); z-index: 200;
        }
        .bottom-nav .nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 3px;
            color: var(--text-secondary); font-size: 10px; font-weight: 600;
            padding: 6px 10px; border-radius: 14px; transition: all 0.2s;
            position: relative; min-width: 56px; flex: 1;
        }
        .bottom-nav .nav-item i { font-size: 20px; transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
        .bottom-nav .nav-item.active { color: var(--primary); }
        .bottom-nav .nav-item.active i { transform: translateY(-2px); }
        .bottom-nav .nav-item.active::after {
            content: ''; position: absolute; bottom: -2px; width: 20px; height: 4px;
            background: var(--primary); border-radius: 4px;
        }
        .bottom-nav .nav-item .badge {
            position: absolute; top: 0; right: 6px; background: var(--secondary); color: #fff;
            font-size: 9px; font-weight: 700; padding: 1px 7px; border-radius: 20px;
            line-height: 1.5; box-shadow: 0 2px 6px rgba(255,107,107,0.3);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            :root { --sidebar-width: 300px; --header-height: 60px; --bottom-nav-height: 68px; }
            body { padding-bottom: calc(var(--bottom-nav-height) + var(--safe-bottom) + 12px); }
            .menu-btn { display: flex; }
            .sidebar { top: 0; transform: translateX(-100%); box-shadow: 8px 0 40px rgba(0,0,0,0.15); border-right: none; width: var(--sidebar-width); border-radius: 0 24px 24px 0; }
            .sidebar.open { transform: translateX(0); }
            .sidebar-profile { border-radius: 0 24px 0 0; padding: 28px 20px 24px; }
            .main-content { margin-left: 0; padding: 16px 16px 90px; }
            .bottom-nav { display: flex; }
            .header { padding: 10px 14px; min-height: var(--header-height); }
            .brand h1 { font-size: 16px; }
            .brand span { font-size: 10px; }
            .admin-avatar { width: 36px; height: 36px; font-size: 14px; }
            .header-btn { width: 36px; height: 36px; font-size: 16px; }
            .page-title { font-size: 20px; }
            .page-title small { font-size: 12px; }
            .filter-bar { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; gap: 6px; }
            .filter-btn { padding: 6px 14px; font-size: 12px; flex-shrink: 0; }
            .order-card { padding: 12px 14px; }
            .order-name { font-size: 14px; }
            .order-meta { font-size: 11px; gap: 6px; }
            .btn-icon { width: 34px; height: 34px; }

            .detail-header-card { padding: 16px 18px; border-radius: var(--radius-sm); }
            .detail-customer-name { font-size: 17px; }
            .detail-meta { font-size: 12px; gap: 8px; }
            .section-card { padding: 16px 18px; border-radius: var(--radius-sm); }
            .section-title { font-size: 12px; margin-bottom: 12px; }
            .info-grid { grid-template-columns: 1fr; gap: 10px; }
            .info-value { font-size: 14px; }
            .info-value.note { padding: 10px 12px; font-size: 13px; }

            .item-row { padding: 10px 12px; }
            .item-img-wrap { width: 40px; height: 40px; border-radius: 10px; }
            .item-name { font-size: 13px; }
            .item-sub { font-size: 16px; }
            .item-price { font-size: 14px; }
            .item-qty { padding: 3px 8px; font-size: 11px; }
            .totals-bar { padding: 14px 16px; }
            .totals-bar .amount { font-size: 18px; }

            .payment-row { gap: 8px; }
            .payment-box { padding: 12px 14px; }
            .payment-box .value { font-size: 14px; }

            .status-pills { gap: 6px; }
            .status-pill { padding: 8px 12px; font-size: 11px; }

            .steadfast-card { padding: 14px 16px; }
            .steadfast-id-row { flex-direction: column; gap: 8px; }
            .steadfast-id-box { padding: 10px 12px; }
            .steadfast-id-box .code { font-size: 13px; }
            .steadfast-track-btn { padding: 10px 16px; font-size: 13px; }
            .btn-action { padding: 12px 14px; font-size: 13px; }

            .fraud-card { padding: 14px 16px; }
            .fraud-header-row { gap: 10px; }
            .fraud-btn { padding: 8px 14px; font-size: 12px; }
            .fraud-stats-row { flex-wrap: wrap; }
            .fraud-stat-box { min-width: calc(50% - 4px); }

            .action-row { gap: 8px; }
            .btn-action { padding: 12px 14px; font-size: 13px; }
        }

        @media (max-width: 420px) {
            .brand h1 { font-size: 14px; }
            .bottom-nav .nav-item { font-size: 9px; min-width: 48px; padding: 4px 6px; }
            .bottom-nav .nav-item i { font-size: 18px; }
            .filter-btn { padding: 5px 12px; font-size: 11px; }
            .order-name { font-size: 13px; }
            .order-meta { font-size: 10px; }
            .btn-icon { width: 32px; height: 32px; font-size: 12px; }
            .status-pill { padding: 7px 10px; font-size: 10px; }
            .fraud-stat-box { min-width: calc(50% - 4px); }
            .detail-customer-name { font-size: 16px; }
        }

        @keyframes fadeUp { from { transform: translateY(24px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .order-card:nth-child(1) { animation-delay: 0.03s; }
        .order-card:nth-child(2) { animation-delay: 0.06s; }
        .order-card:nth-child(3) { animation-delay: 0.09s; }
        .order-card:nth-child(4) { animation-delay: 0.12s; }
        .order-card:nth-child(5) { animation-delay: 0.15s; }
        .order-card:nth-child(6) { animation-delay: 0.18s; }
        .bottom-nav { animation: slideUp 0.5s cubic-bezier(0.22,1,0.36,1) forwards; }

        /* ===== SEARCH BAR ===== */
        .search-bar { display: flex; gap: 10px; margin-bottom: 16px; animation: fadeUp 0.5s ease 0.1s both; }
        .search-input-wrap { flex: 1; display: flex; align-items: center; gap: 10px; background: #fff; border-radius: 14px; padding: 0 16px; border: 1.5px solid #e6e6f0; box-shadow: 0 2px 12px rgba(108,99,255,0.06); transition: all 0.2s; }
        .search-input-wrap:focus-within { border-color: var(--primary); box-shadow: 0 4px 16px rgba(108,99,255,0.15); }
        .search-input-wrap > i { color: var(--primary); font-size: 14px; flex-shrink: 0; }
        .search-input-wrap input { flex: 1; border: none; outline: none; padding: 13px 0; font-size: 14px; font-family: 'Hind Siliguri', sans-serif; background: transparent; color: var(--text-primary); min-width: 0; }
        .search-input-wrap input::placeholder { color: #b0b3c0; }
        .search-clear { width: 26px; height: 26px; border-radius: 8px; background: #f0f2f5; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; transition: all 0.2s; }
        .search-clear:hover { background: #fee; color: #e74c3c; }
        .search-btn { padding: 0 22px; border-radius: 14px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; font-size: 14px; font-weight: 700; font-family: 'Hind Siliguri', sans-serif; cursor: pointer; border: none; box-shadow: 0 4px 12px rgba(108,99,255,0.25); transition: all 0.2s; white-space: nowrap; }
        .search-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(108,99,255,0.35); }
        .search-btn:active { transform: scale(0.95); }
        .search-result-info { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 13px; color: var(--text-secondary); background: rgba(108,99,255,0.06); border: 1px solid rgba(108,99,255,0.12); padding: 10px 16px; border-radius: 12px; }
        .search-result-info i { color: var(--primary); }
        .search-result-info b { color: var(--primary); }

        /* ===== COD EDIT (Steadfast) ===== */
        .cod-edit-box { background: #fff; border: 1px dashed #86efac; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; }
        .cod-edit-box .cod-label { font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.3px; display: block; margin-bottom: 8px; }
        .cod-input-wrap { display: flex; align-items: center; gap: 8px; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 10px; padding: 4px 12px; transition: all 0.2s; }
        .cod-input-wrap:focus-within { border-color: #00b894; box-shadow: 0 0 0 3px rgba(0,184,148,0.12); background: #fff; }
        .cod-input-wrap .tk { font-size: 17px; font-weight: 700; color: #00b894; }
        .cod-input-wrap input { flex: 1; border: none; outline: none; background: transparent; font-size: 18px; font-weight: 700; color: var(--text-primary); padding: 8px 0; font-family: 'Hind Siliguri', sans-serif; min-width: 0; -moz-appearance: textfield; }
        .cod-input-wrap input::-webkit-outer-spin-button, .cod-input-wrap input::-webkit-inner-spin-button { -webkit-appearance: none; }
        .cod-edit-box .cod-hint { font-size: 11px; color: #0caa5e; margin-top: 8px; display: flex; align-items: center; gap: 5px; }
        @media (max-width: 768px) {
            .search-bar { gap: 8px; }
            .search-btn { padding: 0 16px; font-size: 13px; }
            .search-input-wrap { padding: 0 12px; }
            .search-input-wrap input { padding: 11px 0; font-size: 13px; }
        }

        .empty-state { text-align: center; padding: 40px 0; color: var(--text-secondary); animation: fadeUp 0.5s ease forwards; }
        .empty-state i { font-size: 48px; color: #ddd; display: block; margin-bottom: 12px; }
    </style>
</head>
<body>

<div class="app">

    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="header-left">
            <button class="menu-btn" id="menuToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
            <div class="brand">
                <i class="fas fa-store-alt"></i>
                <div><h1><?php echo $siteName; ?></h1><span>Admin Panel</span></div>
            </div>
        </div>
        <div class="header-right">
            <button class="header-btn" aria-label="Notifications"><i class="fas fa-bell"></i><span class="badge-dot"></span></button>
            <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 2)); ?></div>
        </div>
    </header>

    <!-- ===== SIDEBAR OVERLAY ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-profile">
            <div class="profile-top">
                <div class="profile-avatar"><?php echo strtoupper(substr($adminName, 0, 2)); ?></div>
                <div class="profile-info">
                    <h4><?php echo $adminName; ?></h4>
                    <p>Administrator</p>
                    <div class="profile-status"><span class="dot"></span><span>Online</span></div>
                </div>
            </div>
        </div>
        <div class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="orders.php" class="nav-item active">
                <i class="fas fa-shopping-bag"></i> <span>Orders</span>
                <?php if ($pendingCount > 0): ?><span class="badge"><?php echo $pendingCount; ?></span><?php endif; ?>
            </a>
            <a href="products.php" class="nav-item"><i class="fas fa-box"></i> <span>Products</span></a>
            <a href="categories.php" class="nav-item"><i class="fas fa-tags"></i> <span>Categories</span></a>
            <a href="analytics.php" class="nav-item"><i class="fas fa-chart-line"></i> <span>Analytics</span></a>
            <div class="nav-label" style="margin-top:8px;">Management</div>
            <a href="banners.php" class="nav-item"><i class="fas fa-image"></i> <span>Banners</span></a>
            <a href="facebook-pixel.php" class="nav-item"><i class="fab fa-facebook"></i> <span>Facebook Pixel</span></a>
            <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i> <span>Settings</span></a>
            <div class="sidebar-footer" style="margin-top:8px;">
                <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            </div>
        </div>
        <div class="sidebar-bottom-info">v1.0.0 &middot; Mahi Fashion House</div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <?php if (isset($_GET['view']) && $viewOrder): ?>
            <!-- ===== DETAIL VIEW ===== -->
            <div class="detail-container">
                <?php if ($message): ?>
                <div class="alert alert-success" style="margin-bottom: 16px;"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 16px;"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Header Card -->
                <div class="detail-header-card">
                    <div class="detail-header-top">
                        <div class="detail-customer-name"><?php echo clean($viewOrder['customer_name']); ?></div>
                        <a href="orders.php<?php echo $filterStatus ? '?status=' . $filterStatus : ''; ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                    <div class="detail-meta">
                        <i class="far fa-calendar-alt"></i> <?php echo bdDate($viewOrder['created_at'], 'd M Y'); ?>
                        <span class="sep">|</span>
                        <i class="far fa-clock"></i> <?php echo bdDate($viewOrder['created_at'], 'h:i A'); ?>
                        <span class="sep">|</span>
                        #<?php echo $viewOrder['order_number']; ?>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="section-card">
                    <div class="section-title"><i class="fas fa-user"></i> Customer Info</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Name</span>
                            <span class="info-value"><?php echo $viewOrder['customer_name']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone</span>
                            <span class="info-value"><?php echo $viewOrder['customer_phone']; ?></span>
                        </div>
                        <div class="info-item full">
                            <span class="info-label">Address</span>
                            <span class="info-value">
                                <?php echo $viewOrder['customer_address']; ?>, <?php echo $viewOrder['customer_district']; ?>, <?php echo $viewOrder['customer_division']; ?>
                                <button type="button" class="addr-edit-btn" onclick="document.getElementById('addrModal').classList.add('show')" title="ঠিকানা আপডেট"><i class="fas fa-pen"></i> আপডেট</button>
                            </span>
                        </div>
                        <div class="info-item full">
                            <span class="info-label">Order Note</span>
                            <span class="info-value note"><?php echo !empty($viewOrder['customer_note']) ? nl2br(htmlspecialchars($viewOrder['customer_note'])) : '—'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- ঠিকানা আপডেট পপআপ -->
                <div class="addr-modal-overlay" id="addrModal" onclick="if(event.target===this)this.classList.remove('show')">
                    <div class="addr-modal">
                        <div class="addr-modal-head">
                            <span><i class="fas fa-location-dot"></i> ঠিকানা আপডেট করুন</span>
                            <button type="button" class="addr-modal-close" onclick="document.getElementById('addrModal').classList.remove('show')"><i class="fas fa-times"></i></button>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_customer">
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                            <div class="addr-field">
                                <label>নাম</label>
                                <input type="text" name="customer_name" class="edit-input" value="<?php echo htmlspecialchars($viewOrder['customer_name']); ?>" required>
                            </div>
                            <div class="addr-field">
                                <label>ফোন</label>
                                <input type="text" name="customer_phone" class="edit-input" value="<?php echo htmlspecialchars($viewOrder['customer_phone']); ?>" maxlength="11" required>
                            </div>
                            <div class="addr-field">
                                <label>জেলা</label>
                                <input type="text" name="customer_district" class="edit-input" value="<?php echo htmlspecialchars($viewOrder['customer_district']); ?>">
                            </div>
                            <div class="addr-field">
                                <label>বিভাগ</label>
                                <input type="text" name="customer_division" class="edit-input" value="<?php echo htmlspecialchars($viewOrder['customer_division'] ?? ''); ?>">
                            </div>
                            <div class="addr-field">
                                <label>পূর্ণ ঠিকানা</label>
                                <textarea name="customer_address" class="edit-input edit-textarea" rows="3" required><?php echo htmlspecialchars($viewOrder['customer_address']); ?></textarea>
                            </div>
                            <button type="submit" class="btn-sync"><i class="fas fa-sync-alt"></i> আপডেট সিংক করুন</button>
                        </form>
                    </div>
                </div>

                <!-- Order Items — NO SCROLL (Card List) -->
                <div class="section-card">
                    <div class="section-title" style="display:flex; align-items:center; justify-content:space-between;">
                        <span><i class="fas fa-boxes"></i> Order Items</span>
                        <button type="button" class="price-edit-btn" onclick="document.getElementById('priceModal').classList.add('show')"><i class="fas fa-pen"></i> আপডেট</button>
                    </div>
                    <div class="items-list">
                        <?php foreach ($viewItems as $item): ?>
                        <div class="item-row">
                            <div class="item-img-wrap">
                                <?php if (!empty($item['product_image'])): ?>
                                    <img src="../uploads/products/<?php echo $item['product_image']; ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-tshirt"></i>
                                <?php endif; ?>
                            </div>
                            <div class="item-details">
                                <div class="item-name"><?php echo $item['product_title']; ?></div>
                                <div class="item-sub">Size: <?php echo $item['size'] ?: 'N/A'; ?> &middot; Qty: <?php echo $item['quantity']; ?></div>
                            </div>
                            <div class="item-price">৳<?php echo number_format($item['price'], 0); ?></div>
                            <div class="item-qty">x<?php echo $item['quantity']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="totals-bar">
                        <div>
                            <div class="label">Subtotal: ৳<?php echo number_format($viewOrder['total_amount'] - $viewOrder['shipping_cost'], 0); ?></div>
                            <div class="label">Shipping: ৳<?php echo number_format($viewOrder['shipping_cost'], 0); ?></div>
                        </div>
                        <div class="amount">৳<?php echo number_format($viewOrder['total_amount'], 0); ?></div>
                    </div>
                </div>

                <!-- দাম আপডেট পপআপ -->
                <div class="addr-modal-overlay" id="priceModal" onclick="if(event.target===this)this.classList.remove('show')">
                    <div class="addr-modal">
                        <div class="addr-modal-head">
                            <span><i class="fas fa-tag"></i> দাম আপডেট করুন</span>
                            <button type="button" class="addr-modal-close" onclick="document.getElementById('priceModal').classList.remove('show')"><i class="fas fa-times"></i></button>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_items">
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                            <?php foreach ($viewItems as $item): ?>
                            <div class="price-edit-row">
                                <div class="price-edit-name">
                                    <?php echo $item['product_title']; ?>
                                    <small>Qty: <?php echo $item['quantity']; ?></small>
                                </div>
                                <div class="price-edit-input">
                                    <span>৳</span>
                                    <input type="number" name="item_price[<?php echo $item['id']; ?>]" value="<?php echo number_format(floatval($item['price']), 0, '.', ''); ?>" min="0" step="1" required>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="price-edit-row shipping-row">
                                <div class="price-edit-name">ডেলিভারি চার্জ</div>
                                <div class="price-edit-input">
                                    <span>৳</span>
                                    <input type="number" name="shipping_cost" value="<?php echo number_format(floatval($viewOrder['shipping_cost']), 0, '.', ''); ?>" min="0" step="1" required>
                                </div>
                            </div>
                            <button type="submit" class="btn-sync"><i class="fas fa-sync-alt"></i> আপডেট সিংক করুন</button>
                        </form>
                    </div>
                </div>

                <!-- Payment Details — ONE LINE -->
                <div class="section-card">
                    <div class="section-title"><i class="fas fa-credit-card"></i> Payment Details</div>
                    <div class="payment-row">
                        <div class="payment-box">
                            <span class="label">Payment Method</span>
                            <span class="value"><?php echo strtoupper($viewOrder['payment_method']); ?></span>
                        </div>
                        <div class="payment-box">
                            <span class="label">Order Status</span>
                            <span class="value"><span class="order-status-badge <?php echo $statusColors[$viewOrder['status']] ?? 'pending'; ?>"><span class="status-dot"></span> <?php echo $statusLabels[$viewOrder['status']] ?? $viewOrder['status']; ?></span></span>
                        </div>
                    </div>
                </div>

                <!-- Update Status — PILL BUTTONS -->
                <div class="section-card">
                    <div class="section-title"><i class="fas fa-sync-alt"></i> Update Status</div>
                    <div class="status-pills">
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="status-pill pending <?php echo $viewOrder['status'] === 'pending' ? 'active' : ''; ?>"><i class="fas fa-check check"></i> Pending</button>
                        </form>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                            <input type="hidden" name="status" value="confirmed">
                            <button type="submit" class="status-pill confirmed <?php echo $viewOrder['status'] === 'confirmed' ? 'active' : ''; ?>"><i class="fas fa-check check"></i> Confirmed</button>
                        </form>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                            <input type="hidden" name="status" value="processing">
                            <button type="submit" class="status-pill processing <?php echo $viewOrder['status'] === 'processing' ? 'active' : ''; ?>"><i class="fas fa-check check"></i> Processing</button>
                        </form>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                            <input type="hidden" name="status" value="shipped">
                            <button type="submit" class="status-pill shipped <?php echo $viewOrder['status'] === 'shipped' ? 'active' : ''; ?>"><i class="fas fa-check check"></i> Shipped</button>
                        </form>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                            <input type="hidden" name="status" value="delivered">
                            <button type="submit" class="status-pill delivered <?php echo $viewOrder['status'] === 'delivered' ? 'active' : ''; ?>"><i class="fas fa-check check"></i> Delivered</button>
                        </form>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="status-pill cancelled <?php echo $viewOrder['status'] === 'cancelled' ? 'active' : ''; ?>"><i class="fas fa-check check"></i> Cancelled</button>
                        </form>
                    </div>
                </div>

                <!-- Steadfast Courier — CLEAN COMPACT -->
                <div class="section-card" style="padding: 0; overflow: hidden;">
                    <?php if (!empty($viewOrder['steadfast_tracking_code'])): ?>
                    <div class="steadfast-card sent">
                        <div class="steadfast-header">
                            <div class="steadfast-icon"><i class="fas fa-shipping-fast"></i></div>
                            <div>
                                <div class="steadfast-title">Steadfast Courier</div>
                                <div class="steadfast-sub">Parcel already sent</div>
                            </div>
                        </div>
                        <div class="steadfast-id-row">
                            <div class="steadfast-id-box">
                                <i class="fas fa-barcode" style="color: var(--primary); font-size: 12px;"></i>
                                <span class="code" id="trackCode"><?php echo clean($viewOrder['steadfast_tracking_code']); ?></span>
                                <button type="button" class="copy-btn" onclick="copyToClipboard('trackCode')" title="Copy"><i class="fas fa-copy"></i></button>
                            </div>
                            <div class="steadfast-id-box">
                                <i class="fas fa-hashtag" style="color: var(--primary); font-size: 12px;"></i>
                                <span class="code" id="consignId"><?php echo clean($viewOrder['steadfast_consignment_id']); ?></span>
                                <button type="button" class="copy-btn" onclick="copyToClipboard('consignId')" title="Copy"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <a href="https://packzy.com/track?tracking_code=<?php echo urlencode($viewOrder['steadfast_tracking_code']); ?>" target="_blank" class="steadfast-track-btn">
                            <i class="fas fa-external-link-alt"></i> Track Parcel
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="steadfast-card">
                        <div class="steadfast-header">
                            <div class="steadfast-icon"><i class="fas fa-shipping-fast"></i></div>
                            <div>
                                <div class="steadfast-title">Steadfast Courier</div>
                                <div class="steadfast-sub">এই অর্ডারটি এখনো পাঠানো হয়নি</div>
                            </div>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="send_steadfast">
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                            <div class="cod-edit-box" style="display:none;">
                                <span class="cod-label"><i class="fas fa-money-bill-wave" style="color:#00b894; margin-right:4px;"></i> কুরিয়ারে যাবে এমন COD এমাউন্ট</span>
                                <div class="cod-input-wrap">
                                    <span class="tk">৳</span>
                                    <input type="number" name="cod_amount" value="<?php echo number_format(floatval($viewOrder['total_amount']), 0, '.', ''); ?>" min="0" step="1" required>
                                </div>
                                <span class="cod-hint"><i class="fas fa-info-circle"></i> প্রয়োজনে দাম কম/বেশি করে দিন — এই দামেই কুরিয়ারে পাঠানো হবে</span>
                            </div>
                            <button type="submit" class="btn-action steadfast"><i class="fas fa-paper-plane"></i> Send to Steadfast</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Fraud Check — COMPACT INLINE -->
                <div class="section-card" style="padding: 0; overflow: hidden;">
                    <div class="fraud-card">
                        <div class="fraud-header-row">
                            <div class="fraud-icon"><i class="fas fa-shield-alt"></i></div>
                            <div style="flex:1;">
                                <div class="fraud-title">Fraud Check</div>
                                <div class="fraud-sub">কাস্টমারের ফোন নম্বর দিয়ে চেক করুন</div>
                            </div>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="action" value="fraud_check">
                                <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                                <button type="submit" class="fraud-btn"><i class="fas fa-search"></i> Check</button>
                            </form>
                        </div>
                        <?php
                        $fraudChecked = false;
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fraud_check' && isset($viewOrder)) {
                            $fraudChecked = true;
                        }
                        ?>
                        <?php if ($fraudChecked && isset($result) && $result['http_code'] == 200 && isset($result['response']['total_parcels'])): ?>
                        <div class="fraud-stats-row">
                            <div class="fraud-stat-box">
                                <span class="val"><?php echo intval($result['response']['total_parcels'] ?? 0); ?></span>
                                <span class="lbl">Parcels</span>
                            </div>
                            <div class="fraud-stat-box">
                                <span class="val delivered"><?php echo intval($result['response']['total_delivered'] ?? 0); ?></span>
                                <span class="lbl">Delivered</span>
                            </div>
                            <div class="fraud-stat-box">
                                <span class="val cancelled"><?php echo intval($result['response']['total_cancelled'] ?? 0); ?></span>
                                <span class="lbl">Cancelled</span>
                            </div>
                            <div class="fraud-stat-box">
                                <span class="val fraud"><?php echo count($result['response']['total_fraud_reports'] ?? []); ?></span>
                                <span class="lbl">Fraud</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contact & Delete — ONE LINE -->
                <div class="action-row">
                    <a href="tel:<?php echo $viewOrder['customer_phone']; ?>" class="btn-action call"><i class="fas fa-phone"></i> Contact Customer</a>
                    <form method="POST" style="display:inline; flex:1;" onsubmit="return confirm('অর্ডার মুছে ফেলবেন?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                        <button type="submit" class="btn-action delete" style="width:100%;"><i class="fas fa-trash-alt"></i> Delete Order</button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- ===== LIST VIEW ===== -->
            <div class="top-bar">
                <h1 class="page-title">Orders <small>(<?php echo $totalCount; ?> total)</small></h1>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>

            <!-- ===== SEARCH BAR ===== -->
            <form method="GET" action="orders.php" class="search-bar">
                <?php if ($filterStatus): ?><input type="hidden" name="status" value="<?php echo clean($filterStatus); ?>"><?php endif; ?>
                <div class="search-input-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="নাম, ফোন নম্বর বা অর্ডার নম্বর দিয়ে খুঁজুন..." autocomplete="off">
                    <?php if ($search !== ''): ?>
                    <a href="orders.php<?php echo $filterStatus ? '?status=' . $filterStatus : ''; ?>" class="search-clear" title="ক্লিয়ার"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
                <button type="submit" class="search-btn"><i class="fas fa-search"></i> খুঁজুন</button>
            </form>

            <?php if ($search !== ''): ?>
            <div class="search-result-info">
                <i class="fas fa-filter"></i>
                "<b><?php echo clean($search); ?></b>" এর জন্য <b><?php echo count($orders); ?></b> টি অর্ডার পাওয়া গেছে
            </div>
            <?php endif; ?>

            <div class="filter-bar">
                <a href="orders.php<?php echo $search !== '' ? '?search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo !$filterStatus ? 'active' : ''; ?>">All <span class="count"><?php echo $totalCount; ?></span></a>
                <a href="orders.php?status=pending<?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $filterStatus === 'pending' ? 'active' : ''; ?>">Pending <span class="count"><?php echo $statusCounts['pending']; ?></span></a>
                <a href="orders.php?status=confirmed<?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $filterStatus === 'confirmed' ? 'active' : ''; ?>">Confirmed <span class="count"><?php echo $statusCounts['confirmed']; ?></span></a>
                <a href="orders.php?status=processing<?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $filterStatus === 'processing' ? 'active' : ''; ?>">Processing <span class="count"><?php echo $statusCounts['processing']; ?></span></a>
                <a href="orders.php?status=shipped<?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $filterStatus === 'shipped' ? 'active' : ''; ?>">Shipped <span class="count"><?php echo $statusCounts['shipped']; ?></span></a>
                <a href="orders.php?status=delivered<?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $filterStatus === 'delivered' ? 'active' : ''; ?>">Delivered <span class="count"><?php echo $statusCounts['delivered']; ?></span></a>
                <a href="orders.php?status=cancelled<?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $filterStatus === 'cancelled' ? 'active' : ''; ?>">Cancelled <span class="count"><?php echo $statusCounts['cancelled']; ?></span></a>
            </div>

            <?php if (!empty($orders)): ?>
            <div class="orders-grid">
                <?php foreach ($orders as $index => $order): ?>
                <div class="order-card <?php echo $order['status']; ?>" style="animation-delay: <?php echo $index * 0.03; ?>s;">
                    <div class="order-card-inner">
                        <!-- Product Thumbnail -->
                        <div class="order-thumb-wrap">
                            <?php if (!empty($order['product_image'])): ?>
                                <img src="../uploads/products/<?php echo $order['product_image']; ?>" alt="">
                            <?php else: ?>
                                <i class="fas fa-tshirt thumb-icon"></i>
                            <?php endif; ?>
                        </div>
                        <!-- Card Body -->
                        <div class="order-card-body">
                            <!-- Top Line: Name | Date | Time | Price -->
                            <div class="order-top-line">
                                <div class="order-name"><?php echo clean($order['customer_name']); ?></div>
                                <div class="order-top-meta">
                                    <span class="meta-item"><i class="far fa-calendar-alt"></i> <?php echo bdDate($order['created_at'], 'd M Y'); ?></span>
                                    <span class="meta-sep">•</span>
                                    <span class="meta-item"><i class="far fa-clock"></i> <?php echo bdDate($order['created_at'], 'h:i A'); ?></span>
                                    <span class="meta-sep">•</span>
                                    <span class="meta-item price">৳<?php echo number_format($order['total_amount'], 0); ?></span>
                                </div>
                            </div>
                            <!-- Bottom Line: Status | Steadfast | View | Delete -->
                            <div class="order-bottom-line">
                                <div class="order-badges">
                                    <span class="status-pill-badge <?php echo $statusColors[$order['status']] ?? 'pending'; ?>">
                                        <span class="dot"></span> <?php echo $statusLabels[$order['status']] ?? $order['status']; ?>
                                    </span>
                                    <?php if (!empty($order['steadfast_tracking_code'])): ?>
                                    <span class="steadfast-pill-badge sent"><i class="fas fa-shipping-fast"></i> Steadfast</span>
                                    <?php else: ?>
                                    <span class="steadfast-pill-badge none"><i class="fas fa-times"></i> No Steadfast</span>
                                    <?php endif; ?>
                                </div>
                                <div class="order-actions">
                                    <a href="orders.php?view=<?php echo $order['id']; ?><?php echo $filterStatus ? '&status=' . $filterStatus : ''; ?>" class="btn-action-sm view" title="View"><i class="fas fa-eye"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('অর্ডার মুছে ফেলবেন?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" class="btn-action-sm delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>কোনো অর্ডার পাওয়া যায়নি</p></div>
            <?php endif; ?>

        <?php endif; ?>

    </main>

    <!-- ===== BOTTOM NAV ===== -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="index.php" class="nav-item"><i class="fas fa-home"></i><span>ড্যাশ</span></a>
        <a href="orders.php" class="nav-item active">
            <i class="fas fa-shopping-bag"></i><span>অর্ডার</span>
            <?php if ($pendingCount > 0): ?><span class="badge"><?php echo $pendingCount; ?></span><?php endif; ?>
        </a>
        <a href="products.php" class="nav-item"><i class="fas fa-box"></i><span>প্রোডাক্ট</span></a>
        <a href="analytics.php" class="nav-item"><i class="fas fa-chart-line"></i><span>ভিজিটর</span></a>
        <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i><span>সেটিংস</span></a>
        <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>লগআউট</span></a>
    </nav>

</div>

<script>
    (function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const menuToggle = document.getElementById('menuToggle');

        function openSidebar() { sidebar.classList.add('open'); overlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
        function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; }

        menuToggle.addEventListener('click', function(e) { e.stopPropagation(); if (sidebar.classList.contains('open')) closeSidebar(); else openSidebar(); });
        overlay.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar(); });
        document.querySelectorAll('.sidebar .nav-item').forEach(function(item) { item.addEventListener('click', function() { if (window.innerWidth <= 768) closeSidebar(); }); });
        window.addEventListener('resize', function() { if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar(); });

        const currentPath = window.location.pathname.split('/').pop() || 'orders.php';
        document.querySelectorAll('.bottom-nav .nav-item, .sidebar .nav-item').forEach(item => {
            const href = item.getAttribute('href');
            if (href === currentPath) { item.classList.add('active'); item.parentElement.querySelectorAll('.nav-item').forEach(sib => { if (sib !== item) sib.classList.remove('active'); }); }
        });

        document.querySelectorAll('a, button, .order-card, .filter-btn').forEach(el => { el.addEventListener('touchstart', function() {}, {passive: true}); });

        window.copyToClipboard = function(elementId) {
            var text = document.getElementById(elementId).textContent.trim();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() { showCopyFeedback(elementId); });
            } else {
                var textarea = document.createElement('textarea'); textarea.value = text; textarea.style.position = 'fixed'; textarea.style.opacity = '0';
                document.body.appendChild(textarea); textarea.select(); document.execCommand('copy'); document.body.removeChild(textarea); showCopyFeedback(elementId);
            }
        };
        function showCopyFeedback(elementId) {
            var btn = document.querySelector('button[onclick*="' + elementId + '"'); if (!btn) return;
            var originalHTML = btn.innerHTML; btn.innerHTML = '<i class="fas fa-check"></i>'; btn.classList.add('copied');
            setTimeout(function() { btn.innerHTML = originalHTML; btn.classList.remove('copied'); }, 1500);
        }
    })();
</script>

</body>
</html>