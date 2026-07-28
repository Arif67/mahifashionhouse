<?php
require_once 'Config.php';

// ─── সফল অর্ডারের সেশন চেক (রিফ্রেশে ডুপ্লিকেট অর্ডার/পিক্সেল আটকায়) ───
$orderSuccess = false;
$orderData = [];
$firePurchasePixel = false;
if (isset($_GET['order_success']) && !empty($_SESSION['last_order'])) {
    $orderSuccess = true;
    $orderData = $_SESSION['last_order'];
    // Purchase ইভেন্ট এক অর্ডারে শুধু একবার ফায়ার হবে
    $trackKey = 'purchase_tracked_' . $orderData['order_number'];
    if (empty($_SESSION[$trackKey])) {
        $firePurchasePixel = true;
        $_SESSION[$trackKey] = true;
    }
}
// Browser Pixel ও Conversions API উভয় জায়গায় ব্যবহারের জন্য একই Purchase Event ID
$purchaseEventId = $orderData['event_id'] ?? generateMetaEventId('pur');

// Redirect if cart is empty
$cartItems = $_SESSION['cart'] ?? [];
if (empty($cartItems) && !$orderSuccess) {
    header('Location: cart.php');
    exit;
}

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['qty'];
}

$settings = getAllSettings();
$favicon = getFavicon();
$siteName = getSetting('site_name', 'Mahi Fashion House');

// Handle order submission
$orderNumber = '';
$orderBlocked = false;   // ২৪ ঘণ্টার মধ্যে আগের অর্ডার থাকলে true হবে

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $name = clean($_POST['customer_name'] ?? '');
    $phone = clean($_POST['customer_phone'] ?? '');
    $district = clean($_POST['customer_district'] ?? '');
    $address = clean($_POST['customer_address'] ?? '');
    $note = clean($_POST['customer_note'] ?? '');
    $shippingType = $_POST['shipping_type'] ?? 'outside_dhaka';
    $paymentMethod = $_POST['payment_method'] ?? 'cod';
    $deviceToken = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['device_token'] ?? '');

    $shippingCost = ($shippingType === 'inside_dhaka') ? 70 : 120;
    $totalAmount = $subtotal + $shippingCost;
    $orderNumber = 'ORD' . date('Ymd') . rand(1000, 9999);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

    // ─── ফেক/ডাবল অর্ডার ব্লক: ২৪ ঘণ্টার মধ্যে একই আইপি / ফোন / ডিভাইস থেকে আগের অর্ডার আছে কিনা ───
    try {
        $stmt = getDB()->prepare(
            "SELECT order_number, created_at FROM orders
             WHERE created_at >= (NOW() - INTERVAL 24 HOUR)
               AND status != 'cancelled'
               AND (
                    ip_address = ?
                    OR customer_phone = ?
                    OR (device_token IS NOT NULL AND device_token != '' AND device_token = ?)
               )
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$ipAddress, $phone, $deviceToken]);
        $existingOrder = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $existingOrder = false;
    }

    if ($existingOrder) {
        // ব্লক করা হয়েছে — পপআপ দেখানো হবে
        $orderBlocked = true;
    } else {
        try {
            query("INSERT INTO orders (order_number, customer_name, customer_phone, customer_district, customer_address, customer_note, total_amount, shipping_cost, shipping_type, payment_method, ip_address, device_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                $orderNumber, $name, $phone, $district, $address, $note, $totalAmount, $shippingCost, $shippingType, $paymentMethod, $ipAddress, $deviceToken
            ]);

            $orderId = getDB()->lastInsertId();

            foreach ($cartItems as $item) {
                query("INSERT INTO order_items (order_id, product_id, product_title, product_image, size, color, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                    $orderId, $item['product_id'], $item['title'], $item['image'], $item['size'], $item['variant'], $item['price'], $item['qty'], $item['price'] * $item['qty']
                ]);
            }

            $_SESSION['cart'] = [];

            // সেশনে অর্ডার সেভ করে রিডাইরেক্ট — রিফ্রেশে ডাবল অর্ডার/পিক্সেল হবে না
            $_SESSION['last_order'] = [
                'order_number' => $orderNumber,
                'name' => $name,
                'phone' => $phone,
                'address' => $address . ', ' . $district,
                'total' => $totalAmount,
                'shipping' => $shippingCost,
                'payment_method' => $paymentMethod,
                'shipping_type' => $shippingType,
                'event_id' => generateMetaEventId('pur')
            ];
            header('Location: checkout.php?order_success=1');
            exit;

        } catch (Exception $e) {
            $error = "Order failed: " . $e->getMessage();
        }
    }
}

// WhatsApp মেসেজ (ব্লক পপআপের জন্য)
$waBlockMessage = urlencode('আমি ১ম অর্ডার করেছিলাম ২য় অর্ডার এর জন্য নক করছি অনুগ্রহ করে আমাকে সাহায্য করুন।');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>চেকআউট - <?php echo $siteName; ?></title>
    <?php if ($favicon): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo $favicon; ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">

    <?php if (!$orderSuccess && getMetaPixelCode()):
        // ─── Meta CAPI + Browser Pixel: PageView + InitiateCheckout (shared event IDs) ───
        $checkoutPageViewEventId = generateMetaEventId('pv');
        $initiateCheckoutEventId = generateMetaEventId('ic');
        $checkoutContentIds = array_map(function($item) { return (string)$item['product_id']; }, $cartItems);
        $checkoutNumItems = array_sum(array_map(function($item) { return (int)$item['qty']; }, $cartItems));
        $checkoutCurrency = getSetting('site_currency', 'BDT');
        queueMetaCapiEvent('PageView', $checkoutPageViewEventId, [], [], getCurrentUrl());
        queueMetaCapiEvent('InitiateCheckout', $initiateCheckoutEventId, [
            'value' => (float)$subtotal,
            'currency' => $checkoutCurrency,
            'num_items' => $checkoutNumItems,
            'content_ids' => $checkoutContentIds,
            'content_type' => 'product',
            'contents' => array_map(function($item) {
                return [
                    'id' => (string)$item['product_id'],
                    'quantity' => (int)$item['qty'],
                    'item_price' => (float)$item['price'],
                ];
            }, $cartItems),
        ], [], getCurrentUrl());
    ?>
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '<?php echo getMetaPixelCode(); ?>');
    fbq('track', 'PageView', {}, {eventID: '<?php echo $checkoutPageViewEventId; ?>'});
    fbq('track', 'InitiateCheckout', {
        value: <?php echo (float)$subtotal; ?>,
        currency: '<?php echo $checkoutCurrency; ?>',
        num_items: <?php echo (int)$checkoutNumItems; ?>,
        content_ids: <?php echo json_encode($checkoutContentIds); ?>,
        content_type: 'product'
    }, {eventID: '<?php echo $initiateCheckoutEventId; ?>'});
    </script>
    <?php endif; ?>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Inter', 'Hind Siliguri', sans-serif;
            background: #FFFFFF;
            color: #111827;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            padding-bottom: calc(20px + env(safe-area-inset-bottom));
            line-height: 1.5;
        }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }

        /* ─── HEADER (পিঙ্ক গ্রেডিয়েন্ট) ─── */
        .header {
            background: linear-gradient(135deg, #BE185D 0%, #9D174D 50%, #831843 100%);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(190, 24, 93, 0.25);
        }
        .back-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: rgba(255,255,255,0.15);
            color: #fff;
            font-size: 14px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }
        .back-btn:hover { background: rgba(255,255,255,0.25); }
        .page-title {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            letter-spacing: -0.2px;
        }

        /* ─── কন্টেন্ট ব্যাকগ্রাউন্ড (হালকা গোলাপি) ─── */
        .page-content {
            background: #FDF2F8;
            min-height: 100vh;
            padding-top: 0;
        }
        .container {
            max-width: 640px;
            margin: 0 auto;
            padding: 16px;
        }
        .card {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border: 1px solid #FCE7F3;
        }

        /* ─── সেকশন টাইটেল ─── */
        .section-title {
            font-size: 13px;
            font-weight: 600;
            color: #BE185D;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #FCE7F3;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title i {
            font-size: 12px;
        }

        /* ─── অর্ডার আইটেম ─── */
        .order-item {
            display: flex;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid #F3F4F6;
        }
        .order-item:last-child { border-bottom: none; padding-bottom: 0; }
        .order-item:first-child { padding-top: 0; }
        .order-item-img {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            overflow: hidden;
            background: #FDF2F8;
            flex-shrink: 0;
            border: 1px solid #FCE7F3;
        }
        .order-item-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .order-item-info { flex: 1; min-width: 0; }
        .order-item-title {
            font-size: 14px;
            font-weight: 500;
            color: #111827;
            margin-bottom: 4px;
            line-height: 1.4;
        }
        .order-item-meta {
            font-size: 12px;
            color: #6B7280;
            margin-bottom: 6px;
        }
        .order-item-price {
            font-size: 14px;
            font-weight: 600;
            color: #BE185D;
        }

        /* ─── ফর্ম ─── */
        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-label .required { color: #DC2626; }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #FCE7F3;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Inter', 'Hind Siliguri', sans-serif;
            transition: all 0.2s ease;
            background: #FFFFFF;
            color: #111827;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #BE185D;
            box-shadow: 0 0 0 3px rgba(190, 24, 93, 0.08);
        }
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* ─── ফোন ভ্যালিডেশন মেসেজ ─── */
        .phone-hint {
            font-size: 12px;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            min-height: 18px;
        }
        .phone-hint i {
            font-size: 11px;
        }
        .phone-hint.error {
            color: #DC2626;
        }
        .phone-hint.success {
            color: #059669;
        }
        .phone-hint.neutral {
            color: #9CA3AF;
        }
        .form-input.error {
            border-color: #FCA5A5;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08);
        }
        .form-input.success {
            border-color: #6EE7B7;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.08);
        }

        /* ─── শিপিং ও পেমেন্ট ─── */
        .option-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .option-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border: 1.5px solid #FCE7F3;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #FFFFFF;
        }
        .option-item:hover { border-color: #F9A8D4; }
        .option-item.selected {
            border-color: #BE185D;
            background: #FDF2F8;
        }
        .option-radio {
            width: 20px;
            height: 20px;
            border: 2px solid #FCE7F3;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #FFFFFF;
            transition: all 0.2s ease;
        }
        .option-item.selected .option-radio {
            border-color: #BE185D;
            background: #BE185D;
        }
        .option-radio i {
            font-size: 8px;
            color: #fff;
            opacity: 0;
        }
        .option-item.selected .option-radio i { opacity: 1; }
        .option-info { flex: 1; }
        .option-name {
            font-size: 14px;
            font-weight: 500;
            color: #111827;
        }
        .option-desc {
            font-size: 12px;
            color: #6B7280;
            margin-top: 2px;
        }
        .option-price {
            font-size: 15px;
            font-weight: 600;
            color: #BE185D;
        }

        .payment-icon {
            width: 36px;
            height: 36px;
            background: #FDF2F8;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #BE185D;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        .option-item.selected .payment-icon {
            background: #BE185D;
            color: #fff;
        }
        .option-check {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #FDF2F8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9CA3AF;
            font-size: 10px;
            transition: all 0.2s ease;
        }
        .option-item.selected .option-check {
            background: #BE185D;
            color: #fff;
        }

        /* ─── বিল সামারি ─── */
        .bill-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 14px;
            border-bottom: 1px solid #F3F4F6;
            align-items: center;
        }
        .bill-row:last-child { border-bottom: none; }
        .bill-row.total {
            border-top: 2px solid #BE185D;
            border-bottom: none;
            margin-top: 8px;
            padding-top: 14px;
        }
        .bill-row .label { color: #6B7280; font-weight: 400; }
        .bill-row .value { font-weight: 500; color: #111827; }
        .bill-row.total .label { font-size: 15px; font-weight: 600; color: #111827; }
        .bill-row.total .value { font-size: 18px; font-weight: 700; color: #BE185D; }

        /* ─── অর্ডার বাটন ─── */
        .btn-primary {
            display: block;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #BE185D 0%, #9D174D 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', 'Hind Siliguri', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            box-shadow: 0 4px 12px rgba(190, 24, 93, 0.25);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #9D174D 0%, #831843 100%);
            box-shadow: 0 6px 16px rgba(190, 24, 93, 0.35);
        }
        .btn-primary:active { transform: scale(0.98); }

        /* ─── ২৪ ঘণ্টা ব্লক পপআপ ─── */
        .block-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.55);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
            animation: fadeIn 0.25s ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .block-modal {
            background: #FFFFFF;
            border-radius: 14px;
            padding: 26px 22px;
            width: 100%;
            max-width: 340px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
            animation: popIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.85) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .block-close {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #FDF2F8;
            border: none;
            color: #BE185D;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .block-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #BE185D, #9D174D);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            box-shadow: 0 6px 16px rgba(190, 24, 93, 0.3);
        }
        .block-icon i { font-size: 20px; color: #fff; }
        .block-title {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .block-text {
            font-size: 13px;
            color: #6B7280;
            line-height: 1.7;
            margin-bottom: 18px;
        }
        .btn-whatsapp {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', 'Hind Siliguri', sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
            transition: all 0.2s ease;
        }
        .btn-whatsapp:hover { box-shadow: 0 6px 18px rgba(37, 211, 102, 0.45); }
        .btn-whatsapp:active { transform: scale(0.97); }
        .btn-whatsapp i { font-size: 17px; }
        .block-note {
            margin-top: 12px;
            font-size: 11px;
            color: #9CA3AF;
        }

        /* ─── সাফল্য পেজ ─── */
        .success-page {
            min-height: 100vh;
            background: #FDF2F8;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        .success-close {
            position: fixed;
            top: 16px;
            right: 16px;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #FCE7F3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #BE185D;
            cursor: pointer;
            z-index: 100;
            transition: all 0.2s ease;
        }
        .success-close:hover { background: #FDF2F8; }
        .success-card-main {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 32px 24px;
            width: 100%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #FCE7F3;
        }
        .success-icon-main {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #10B981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }
        .success-icon-main i { font-size: 24px; color: #fff; }
        .success-title-main {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }
        .success-text-main {
            font-size: 14px;
            color: #6B7280;
            margin-bottom: 24px;
        }
        .success-details {
            background: #FDF2F8;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: left;
            border: 1px solid #FCE7F3;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
        }
        .detail-row .label { color: #6B7280; }
        .detail-row .value { font-weight: 500; color: #111827; }
        .detail-row .value.price { font-weight: 700; font-size: 14px; color: #BE185D; }
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn-outline {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: #FFFFFF;
            color: #111827;
            border: 1.5px solid #FCE7F3;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Inter', 'Hind Siliguri', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-outline:hover {
            background: #FDF2F8;
            border-color: #F9A8D4;
        }

        /* ─── রেসপন্সিভ ─── */
        @media (max-width: 480px) {
            .form-row { grid-template-columns: 1fr; }
            .container { padding: 12px; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>
    <?php if ($orderSuccess): ?>
    <!-- Facebook Purchase Event (এক অর্ডারে একবারই ফায়ার হয়) -->
    <?php if ($firePurchasePixel && getMetaPixelCode()):
        // ─── Meta CAPI + Browser Pixel: Purchase (shared event ID + advanced matching) ───
        try {
            $purchaseItems = fetchAll(
                "SELECT oi.product_id, oi.quantity, oi.price FROM order_items oi
                 JOIN orders o ON oi.order_id = o.id
                 WHERE o.order_number = ?",
                [$orderData['order_number']]
            );
        } catch (Exception $e) {
            $purchaseItems = [];
        }
        $purchaseContentIds = array_map(function($it) { return (string)$it['product_id']; }, $purchaseItems);
        $purchaseNumItems = $purchaseItems ? array_sum(array_column($purchaseItems, 'quantity')) : 1;
        $purchaseCurrency = getSetting('site_currency', 'BDT');
        $purchasePageViewEventId = generateMetaEventId('pv');

        $nameParts = preg_split('/\s+/', trim($orderData['name'] ?? ''), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        queueMetaCapiEvent('PageView', $purchasePageViewEventId, [], [
            'phone' => $orderData['phone'] ?? '',
            'first_name' => $firstName,
            'last_name' => $lastName,
        ], getCurrentUrl());

        queueMetaCapiEvent('Purchase', $purchaseEventId, [
            'value' => (float)$orderData['total'],
            'currency' => $purchaseCurrency,
            'order_id' => $orderData['order_number'],
            'content_ids' => $purchaseContentIds,
            'content_type' => 'product',
            'num_items' => $purchaseNumItems,
            'contents' => array_map(function($it) {
                return [
                    'id' => (string)$it['product_id'],
                    'quantity' => (int)$it['quantity'],
                    'item_price' => (float)$it['price'],
                ];
            }, $purchaseItems),
        ], [
            'phone' => $orderData['phone'] ?? '',
            'first_name' => $firstName,
            'last_name' => $lastName,
        ], getCurrentUrl());
    ?>
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo getMetaPixelCode(); ?>', {
            ph: '<?php echo addslashes($orderData['phone'] ?? ''); ?>',
            fn: '<?php echo addslashes($firstName); ?>',
            ln: '<?php echo addslashes($lastName); ?>'
        });
        fbq('track', 'PageView', {}, {eventID: '<?php echo $purchasePageViewEventId; ?>'});
        fbq('track', 'Purchase', {
            value: <?php echo (float)$orderData['total']; ?>,
            currency: '<?php echo $purchaseCurrency; ?>',
            order_id: '<?php echo addslashes($orderData['order_number']); ?>',
            content_ids: <?php echo json_encode($purchaseContentIds); ?>,
            content_type: 'product',
            num_items: <?php echo (int)$purchaseNumItems; ?>
        }, {eventID: '<?php echo $purchaseEventId; ?>'});
    </script>
    <?php endif; ?>

    <!-- সাফল্য পেজ (১৫ সেকেন্ড অটো রিডাইরেক্ট) -->
    <div class="success-page">
        <button class="success-close" onclick="window.location.href='index.php'">
            <i class="fas fa-times"></i>
        </button>

        <div class="success-card-main">
            <div class="success-icon-main">
                <i class="fas fa-check"></i>
            </div>

            <h1 class="success-title-main">আপনার অর্ডার সফল হয়েছে!</h1>
            <p class="success-text-main">কিচ্ছুক্ষনের মধ্যে একজন প্রতিনিধি আপনাকে কল করবেন</p>

            <div class="success-details">
                <div class="detail-row">
                    <span class="label">অর্ডার নম্বর</span>
                    <span class="value">#<?php echo $orderData['order_number']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">নাম</span>
                    <span class="value"><?php echo $orderData['name']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">মোট</span>
                    <span class="value price">৳ <?php echo number_format($orderData['total']); ?></span>
                </div>
            </div>

            <div class="btn-group">
                <a href="index.php" class="btn-primary">শপিং চালিয়ে যান</a>
                <a href="https://wa.me/<?php echo getWhatsAppNumber(); ?>?text=<?php echo urlencode('আমার অর্ডার #' . $orderData['order_number'] . ' সম্পর্কে জানতে চাই'); ?>"
                   target="_blank" class="btn-outline">
                    <i class="fab fa-whatsapp" style="color: #10B981;"></i>
                    সাপোর্টে যোগাযোগ করুন
                </a>
            </div>

            <p style="margin-top: 20px; font-size: 13px; color: #6B7280;">
                <span id="countdown">১৫</span> সেকেন্ড পর হোম পেজে নিয়ে যাওয়া হবে...
            </p>
        </div>
    </div>

    <script>
    // লোকাল স্টোরেজ ক্লিয়ার
    Object.keys(localStorage).forEach(key => {
        if (key.startsWith('mahi_order_') || key.startsWith('mahi_cart_')) {
            localStorage.removeItem(key);
        }
    });

    // ১৫ সেকেন্ড কাউন্টডাউন
    let seconds = 15;
    const countdownEl = document.getElementById('countdown');
    const timer = setInterval(() => {
        seconds--;
        if (countdownEl) countdownEl.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = 'index.php';
        }
    }, 1000);
    </script>

    <?php else: ?>

    <div class="page-content">
        <!-- হেডার -->
        <header class="header">
            <a href="cart.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <span class="page-title">চেকআউট</span>
        </header>

        <div class="container">
            <!-- অর্ডার আইটেম -->
            <div class="card">
                <div class="section-title"><i class="fas fa-fire"></i> অর্ডার সামারি</div>
                <?php foreach ($cartItems as $item): ?>
                <div class="order-item">
                    <div class="order-item-img">
                        <?php if (!empty($item['image'])): ?>
                        <img src="uploads/products/<?php echo $item['image']; ?>" alt="">
                        <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#FDF2F8;">
                            <i class="fas fa-tshirt" style="color:#F9A8D4;font-size:20px;"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="order-item-info">
                        <div class="order-item-title"><?php echo $item['title']; ?></div>
                        <div class="order-item-meta">
                            <?php echo $item['size']; ?> <?php if ($item['variant']): ?>/ <?php echo $item['variant']; endif; ?> / পরিমাণ: <?php echo $item['qty']; ?>
                        </div>
                        <div class="order-item-price">৳ <?php echo number_format($item['price'] * $item['qty']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- গ্রাহকের তথ্য -->
            <div class="card">
                <div class="section-title"><i class="fas fa-user"></i> গ্রাহকের তথ্য</div>
                <form id="checkoutForm" method="POST" action="">
                    <input type="hidden" name="device_token" id="deviceTokenInput" value="">
                    <div class="form-group">
                        <label class="form-label">পুরো নাম <span class="required">*</span></label>
                        <input type="text" name="customer_name" class="form-input" placeholder="আপনার নাম লিখুন" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ফোন নম্বর <span class="required">*</span></label>
                        <input type="tel" name="customer_phone" id="phoneInput" class="form-input" placeholder="01XXXXXXXXX" maxlength="11" autocomplete="off" required>
                        <div class="phone-hint neutral" id="phoneHint">
                            <i class="fas fa-info-circle"></i>
                            <span>11 ডিজিটের বাংলাদেশি ফোন নম্বর লিখুন (যেমন: 01712345678)</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">জেলা <span class="required">*</span></label>
                        <input type="text" name="customer_district" class="form-input" placeholder="জেলার নাম লিখুন" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">পূর্ণ ঠিকানা <span class="required">*</span></label>
                        <textarea name="customer_address" class="form-textarea" placeholder="পূর্ণ ঠিকানা ও পণ্য রিসিভ করার স্থান লিখুন" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">অর্ডার নোট (ঐচ্ছিক)</label>
                        <textarea name="customer_note" class="form-textarea" placeholder="প্রয়োজনীয় কিছু বলার থাকলে এখানে লিখুন"></textarea>
                    </div>
            </div>

            <!-- ডেলিভারি মেথড -->
            <div class="card">
                <div class="section-title"><i class="fas fa-truck"></i> ডেলিভারি পদ্ধতি</div>
                <div class="option-list">
                    <label class="option-item selected" onclick="selectShipping(this, 'outside_dhaka', 120)">
                        <input type="radio" name="shipping_type" value="outside_dhaka" checked style="display:none;">
                        <div class="option-radio"><i class="fas fa-check"></i></div>
                        <div class="option-info">
                            <div class="option-name">ঢাকার বাহিরে</div>
                            <div class="option-desc">ডেলিভারি সময় ৫-৭ কার্যদিবস</div>
                        </div>
                        <div class="option-price">৳ 120</div>
                    </label>
                    <label class="option-item" onclick="selectShipping(this, 'inside_dhaka', 70)">
                        <input type="radio" name="shipping_type" value="inside_dhaka" style="display:none;">
                        <div class="option-radio"><i class="fas fa-check"></i></div>
                        <div class="option-info">
                            <div class="option-name">ঢাকার ভিতরে</div>
                            <div class="option-desc">ডেলিভারি সময় ২-৩ কার্যদিবস</div>
                        </div>
                        <div class="option-price">৳ 70</div>
                    </label>
                </div>
            </div>

            <!-- পেমেন্ট মেথড -->
            <div class="card">
                <div class="section-title"><i class="fas fa-credit-card"></i> পেমেন্ট পদ্ধতি</div>
                <div class="option-list">
                    <label class="option-item selected" onclick="selectPayment(this)">
                        <input type="radio" name="payment_method" value="cod" checked style="display:none;">
                        <div class="payment-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="option-info">
                            <div class="option-name">ক্যাশ অন ডেলিভারি (COD)</div>
                        </div>
                        <div class="option-check"><i class="fas fa-check"></i></div>
                    </label>
                </div>
            </div>

            <!-- বিল সামারি -->
            <div class="card">
                <div class="section-title"><i class="fas fa-receipt"></i> বিল সামারি</div>
                <div class="bill-row">
                    <span class="label">সাবটোটাল</span>
                    <span class="value" id="billSubtotal">৳ <?php echo number_format($subtotal); ?></span>
                </div>
                <div class="bill-row">
                    <span class="label">ডেলিভারি চার্জ</span>
                    <span class="value" id="billShipping">৳ 120</span>
                </div>
                <div class="bill-row total">
                    <span class="label">মোট</span>
                    <span class="value" id="billTotal">৳ <?php echo number_format($subtotal + 120); ?></span>
                </div>
            </div>

            <button type="submit" name="place_order" class="btn-primary">
                অর্ডার কনফার্ম করুন
            </button>
            </form>
        </div>
    </div>

    <?php if ($orderBlocked): ?>
    <!-- ─── ২৪ ঘণ্টা ব্লক পপআপ ─── -->
    <div class="block-overlay" id="blockOverlay" onclick="if(event.target===this)this.style.display='none'">
        <div class="block-modal">
            <button class="block-close" onclick="document.getElementById('blockOverlay').style.display='none'">
                <i class="fas fa-times"></i>
            </button>
            <div class="block-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="block-title">প্রথম অর্ডার কনফার্ম হয়েছে</div>
            <p class="block-text">
                পরবর্তী অর্ডারের জন্য আমাদের কল করুন<br>অথবা হোয়াটসঅ্যাপে যোগাযোগ করুন।
            </p>
            <a href="https://wa.me/<?php echo getWhatsAppNumber(); ?>?text=<?php echo $waBlockMessage; ?>"
               target="_blank" class="btn-whatsapp">
                <i class="fab fa-whatsapp"></i>
                হোয়াটসঅ্যাপে মেসেজ করুন
            </a>
            <p class="block-note">ক্লিক করলে সরাসরি আমাদের হোয়াটসঅ্যাপ ইনবক্সে নিয়ে যাওয়া হবে</p>
        </div>
    </div>
    <?php endif; ?>

    <script>
    let shippingCost = 120;
    let subtotal = <?php echo $subtotal; ?>;

    // ─── ডিভাইস টোকেন (২৪ ঘণ্টা ব্লকের জন্য ইউনিক ডিভাইস আইডি) ───
    (function() {
        try {
            let dt = localStorage.getItem('mahi_device_id');
            if (!dt) {
                dt = 'dev_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 14);
                localStorage.setItem('mahi_device_id', dt);
            }
            const inp = document.getElementById('deviceTokenInput');
            if (inp) inp.value = dt;
        } catch (e) {}
    })();

    function selectShipping(card, type, cost) {
        document.querySelectorAll('.option-item').forEach(c => {
            if (c.querySelector('input[name="shipping_type"]')) {
                c.classList.remove('selected');
                c.querySelector('input').checked = false;
            }
        });
        card.classList.add('selected');
        card.querySelector('input').checked = true;
        shippingCost = cost;
        updateBill();
    }

    function selectPayment(card) {
        document.querySelectorAll('.option-item').forEach(c => {
            if (c.querySelector('input[name="payment_method"]')) {
                c.classList.remove('selected');
                c.querySelector('input').checked = false;
            }
        });
        card.classList.add('selected');
        card.querySelector('input').checked = true;
    }

    function updateBill() {
        document.getElementById('billShipping').textContent = '৳ ' + shippingCost.toLocaleString('en-US');
        document.getElementById('billTotal').textContent = '৳ ' + (subtotal + shippingCost).toLocaleString('en-US');
    }

    // ─── ফোন নম্বর রিয়েল টাইম ভ্যালিডেশন ───
    const phoneInput = document.getElementById('phoneInput');
    const phoneHint = document.getElementById('phoneHint');

    function validatePhoneRealtime(value) {
        // শুধু সংখ্যা গ্রহণ
        const digitsOnly = value.replace(/\D/g, '');

        // ইনপুট ফিল্ডে শুধু সংখ্যা রাখুন
        if (value !== digitsOnly) {
            phoneInput.value = digitsOnly;
        }

        const len = digitsOnly.length;

        // খালি থাকলে
        if (len === 0) {
            return { valid: false, state: 'neutral', msg: '১১ ডিজিটের বাংলাদেশি ফোন নম্বর লিখুন (যেমন: 01712345678)' };
        }

        // ০ দিয়ে শুরু হয়েছে কিনা চেক
        if (!digitsOnly.startsWith('0')) {
            return { valid: false, state: 'error', msg: 'ফোন নম্বর ০ দিয়ে শুরু হতে হবে' };
        }

        // ০১ দিয়ে শুরু হয়েছে কিনা চেক
        if (len >= 2 && digitsOnly.substring(0, 2) !== '01') {
            return { valid: false, state: 'error', msg: 'বাংলাদেশি নম্বর ০১ দিয়ে শুরু হতে হবে' };
        }

        // ৩য় ডিজিট চেক (৩-৯)
        if (len >= 3) {
            const thirdDigit = digitsOnly.charAt(2);
            if (!/[3-9]/.test(thirdDigit)) {
                return { valid: false, state: 'error', msg: '৩য় ডিজিট ৩-৯ এর মধ্যে হতে হবে (যেমন: 013, 017, 018)' };
            }
        }

        // ১১ ডিজিটের কম
        if (len < 11) {
            return { valid: false, state: 'error', msg: '১১ ডিজিটের ফোন নম্বর লিখুন (' + len + '/১১)' };
        }

        // ১১ ডিজিটের বেশি
        if (len > 11) {
            return { valid: false, state: 'error', msg: 'ফোন নম্বর ১১ ডিজিটের বেশি হতে পারবে না' };
        }

        // ১১ ডিজিট পূর্ণ এবং সঠিক
        const bdRegex = /^01[3-9][0-9]{8}$/;
        if (bdRegex.test(digitsOnly)) {
            return { valid: true, state: 'success', msg: 'সঠিক বাংলাদেশি ফোন নম্বর ✓' };
        }

        return { valid: false, state: 'error', msg: 'সঠিক ফোন নম্বর ফরম্যাট নয়' };
    }

    function updatePhoneUI(result) {
        phoneHint.className = 'phone-hint ' + result.state;

        let icon = 'fa-info-circle';
        if (result.state === 'error') icon = 'fa-exclamation-circle';
        if (result.state === 'success') icon = 'fa-check-circle';

        phoneHint.innerHTML = '<i class="fas ' + icon + '"></i><span>' + result.msg + '</span>';

        phoneInput.classList.remove('error', 'success');
        if (result.state === 'error') phoneInput.classList.add('error');
        if (result.state === 'success') phoneInput.classList.add('success');
    }

    // ইনপুট টাইপ করার সময় রিয়েল টাইম চেক
    phoneInput.addEventListener('input', function(e) {
        const result = validatePhoneRealtime(e.target.value);
        updatePhoneUI(result);
    });

    // ফোকাস হারালে চেক
    phoneInput.addEventListener('blur', function(e) {
        const result = validatePhoneRealtime(e.target.value);
        updatePhoneUI(result);
    });

    // ফর্ম সাবমিট
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        const phone = phoneInput.value.replace(/\D/g, '');
        const result = validatePhoneRealtime(phone);

        if (!result.valid) {
            e.preventDefault();
            updatePhoneUI(result);
            phoneInput.focus();

            // শেক এনিমেশন
            phoneInput.style.animation = 'shake 0.4s ease';
            setTimeout(() => { phoneInput.style.animation = ''; }, 400);
            return false;
        }
        return true;
    });

    // শেক এনিমেশন CSS
    const shakeStyle = document.createElement('style');
    shakeStyle.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }
    `;
    document.head.appendChild(shakeStyle);
    </script>

    <?php endif; ?>
</body>
</html>