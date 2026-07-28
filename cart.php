<?php
require_once 'Config.php';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $productId = intval($_POST['product_id'] ?? 0);
        $title = $_POST['title'] ?? '';
        $image = $_POST['image'] ?? '';
        $size = $_POST['size'] ?? '';
        $variant = $_POST['variant'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        $qty = intval($_POST['qty'] ?? 1);
        
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        
        $key = $productId . '_' . $size . ($variant ? '_' . $variant : '');
        
        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['qty'] += $qty;
        } else {
            $_SESSION['cart'][$key] = [
                'product_id' => $productId,
                'title' => $title,
                'image' => $image,
                'size' => $size,
                'variant' => $variant,
                'price' => $price,
                'qty' => $qty
            ];
        }
        
        // ─── Meta Conversions API: AddToCart (Browser Pixel gets same event_id) ───
        $addToCartEventId = generateMetaEventId('atc');
        queueMetaCapiEvent('AddToCart', $addToCartEventId, [
            'value' => (float)($price * $qty),
            'currency' => 'BDT',
            'content_ids' => [(string)$productId],
            'content_type' => 'product',
            'content_name' => $title,
            'num_items' => $qty,
            'contents' => [[
                'id' => (string)$productId,
                'quantity' => (int)$qty,
                'item_price' => (float)$price,
            ]],
        ], [], getCurrentUrl());

        echo json_encode(['success' => true, 'count' => getCartCount(), 'event_id' => $addToCartEventId]);
        exit;
    }
    
    if ($action === 'update') {
        $key = $_POST['key'] ?? '';
        $qty = intval($_POST['qty'] ?? 1);
        if (isset($_SESSION['cart'][$key])) {
            if ($qty < 1) {
                unset($_SESSION['cart'][$key]);
            } else {
                $_SESSION['cart'][$key]['qty'] = $qty;
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'remove') {
        $key = $_POST['key'] ?? '';
        unset($_SESSION['cart'][$key]);
        echo json_encode(['success' => true]);
        exit;
    }
}

$cartItems = $_SESSION['cart'] ?? [];
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['qty'];
}

$settings = getAllSettings();
$favicon = getFavicon();
$siteName = getSetting('site_name', 'Mahi Fashion House');
$logo = getLogo();
$cartCount = getCartCount();

// Meta Pixel Code — একবার কল করে ভেরিয়েবলে রাখা
$pixelCode = getMetaPixelCode();

// ─── Meta Conversions API: PageView (Browser Pixel-এর সাথে একই Event ID) ───
$pageViewEventId = generateMetaEventId('pv');
queueMetaCapiEvent('PageView', $pageViewEventId, [], [], getCurrentUrl());
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FCE4EC">
    <title>কার্ট - <?php echo $siteName; ?></title>
    <?php if ($favicon): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo $favicon; ?>">
    <?php endif; ?>

    <?php if (!empty($pixelCode)): ?>
    <!-- Meta Pixel Code -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo $pixelCode; ?>');
        fbq('track', 'PageView', {}, {eventID: '<?php echo $pageViewEventId; ?>'});
    </script>
    <noscript>
        <img height="1" width="1" style="display:none" 
             src="https://www.facebook.com/tr?id=<?php echo $pixelCode; ?>&ev=PageView&noscript=1" />
    </noscript>
    <!-- End Meta Pixel Code -->
    <?php endif; ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* ── Primary (Soft Pink) ── */
            --primary: #EC407A;
            --primary-dark: #D81B60;
            --primary-light: #F48FB1;

            /* ── Header Background (Soft Pink) ── */
            --header-bg: #ED0763;

            /* ── Secondary / CTA (Green) ── */
            --secondary: #22C55E;
            --secondary-dark: #16A34A;
            --secondary-light: #86EFAC;

            /* ── Background & Layout (PURE WHITE) ── */
            --bg: #FFFFFF;
            --surface: #FFFFFF;
            --border: #FBCFE8;

            /* ── Accent ── */
            --discount: #EF4444;
            --highlight: #F59E0B;
            --gradient-accent: linear-gradient(90deg, #F59E0B, #EF4444);

            /* ── Text ── */
            --text-primary: #1A1A2E;
            --text-secondary: #6B7280;
            --text-light: #9CA3AF;

            /* ── Shadows (light pink tint) ── */
            --shadow-sm: 0 4px 12px rgba(236, 64, 122, 0.06);
            --shadow-md: 0 8px 24px rgba(236, 64, 122, 0.10);
            --shadow-lg: 0 12px 40px rgba(236, 64, 122, 0.14);

            /* ── Radius ── */
            --radius: 12px;
            --radius-lg: 20px;
            --radius-full: 9999px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Hind Siliguri', 'Kalpurush', sans-serif;
            background: #FFFFFF;
            color: #1A1A2E;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            padding-bottom: calc(88px + env(safe-area-inset-bottom));
            overscroll-behavior-y: none;
        }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }

        /* ═══════════════════════════════════════════════════════════
           HEADER — MATCHES product.php
           ═══════════════════════════════════════════════════════════ */
        .header {
            background: linear-gradient(135deg, #ED0763 0%, #C2185B 50%, #880E4F 100%);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(237, 7, 99, 0.25), 0 1px 3px rgba(0,0,0,0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }
        .header-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
        .back-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #FFFFFF;
            font-size: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.22);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.3);
        }
        .back-btn:active { transform: scale(0.92); }
        .page-title {
            font-size: 15px;
            font-weight: 700;
            color: #FFFFFF;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            letter-spacing: 0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ═══════════════════════════════════════════════════════════
           CART SECTION
           ═══════════════════════════════════════════════════════════ */
        .cart-section { padding: 16px; }
        .cart-item {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 14px;
            display: flex;
            gap: 14px;
            margin-bottom: 14px;
            border: 1px solid #FBCFE8;
            box-shadow: 0 4px 12px rgba(236, 64, 122, 0.06);
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .cart-item:nth-child(1) { animation-delay: 0.05s; }
        .cart-item:nth-child(2) { animation-delay: 0.1s; }
        .cart-item:nth-child(3) { animation-delay: 0.15s; }
        .cart-item:nth-child(4) { animation-delay: 0.2s; }
        @keyframes fadeInUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .cart-item:active { transform: scale(0.98); }
        .cart-item-image {
            width: 90px;
            height: 90px;
            border-radius: 12px;
            overflow: hidden;
            background: #FDF2F8;
            flex-shrink: 0;
            border: 1px solid #FBCFE8;
        }
        .cart-item-image img { width: 100%; height: 100%; object-fit: cover; }
        .cart-item-image .no-img {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #FDF2F8, #FCE4EC);
        }
        .cart-item-details { flex: 1; min-width: 0; }
        .cart-item-title {
            font-size: 14px;
            font-weight: 600;
            color: #1A1A2E;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }
        .cart-item-meta {
            font-size: 12px;
            color: #6B7280;
            margin-bottom: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .cart-item-meta span {
            background: #FDF2F8;
            color: #EC407A;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 500;
            border: 1px solid #FBCFE8;
        }
        .cart-item-price {
            font-size: 16px;
            font-weight: 700;
            color: #EC407A;
            margin-bottom: 12px;
        }
        .cart-item-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            border: 1.5px solid #FBCFE8;
            border-radius: 12px;
            overflow: hidden;
        }
        .qty-btn {
            width: 34px;
            height: 34px;
            border: none;
            background: #FDF2F8;
            font-size: 15px;
            color: #EC407A;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-weight: 600;
        }
        .qty-btn:hover { background: #FBCFE8; }
        .qty-btn:active { transform: scale(0.9); }
        .qty-input {
            width: 42px;
            height: 34px;
            border: none;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Hind Siliguri', sans-serif;
            color: #1A1A2E;
            background: #FFFFFF;
        }
        .remove-btn {
            width: 34px;
            height: 34px;
            border: none;
            background: #FDF2F8;
            color: #EF4444;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .remove-btn:hover {
            background: #EF4444;
            color: #fff;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
        }

        /* ═══════════════════════════════════════════════════════════
           EMPTY CART
           ═══════════════════════════════════════════════════════════ */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            animation: fadeInUp 0.5s ease;
        }
        .empty-cart i {
            font-size: 64px;
            color: #F9A8D4;
            margin-bottom: 20px;
            display: inline-block;
        }
        .empty-cart h3 {
            font-size: 18px;
            color: #1A1A2E;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .empty-cart p {
            font-size: 14px;
            color: #6B7280;
            margin-bottom: 25px;
        }
        .btn-shop {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            background: linear-gradient(135deg, #1A1A2E, #2D2D44);
            color: #fff;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.20);
            border: none;
            font-family: 'Hind Siliguri', sans-serif;
            cursor: pointer;
        }
        .btn-shop:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(26, 26, 46, 0.30);
        }
        .btn-shop:active { transform: scale(0.96); }

        /* ═══════════════════════════════════════════════════════════
           ORDER SUMMARY — PINK THEME
           ═══════════════════════════════════════════════════════════ */
        .summary-section {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 20px;
            margin: 0 16px 16px;
            border: 1px solid #FBCFE8;
            box-shadow: 0 4px 12px rgba(236, 64, 122, 0.06);
            position: relative;
            overflow: hidden;
        }
        .summary-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #EC407A, #D81B60);
        }
        .summary-title {
            font-size: 16px;
            font-weight: 700;
            color: #1A1A2E;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #FBCFE8;
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .summary-title i {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #EC407A, #D81B60);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(236, 64, 122, 0.25);
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 14px;
            align-items: center;
        }
        .summary-row .label { color: #6B7280; font-weight: 500; }
        .summary-row .value { font-weight: 600; color: #1A1A2E; }
        .summary-row.total {
            border-top: 2px solid #FBCFE8;
            margin-top: 5px;
            padding-top: 14px;
            font-size: 18px;
        }
        .summary-row.total .label { color: #1A1A2E; font-weight: 700; }
        .summary-row.total .value { color: #EC407A; font-weight: 700; font-size: 20px; }
        .shipping-note {
            font-size: 12px;
            color: #6B7280;
            margin-top: 10px;
            padding: 10px;
            background: #FDF2F8;
            border-radius: 10px;
            border-left: 3px solid #EC407A;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .shipping-note i { color: #EC407A; font-size: 14px; }

        /* ═══════════════════════════════════════════════════════════
           CHECKOUT BUTTON — BLACK (MATCHES product.php)
           ═══════════════════════════════════════════════════════════ */
        .checkout-btn {
            display: block;
            width: calc(100% - 32px);
            margin: 0 16px 20px;
            padding: 16px;
            background: linear-gradient(135deg, #1A1A2E, #2D2D44);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Hind Siliguri', sans-serif;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.20);
            letter-spacing: 0.3px;
        }
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(26, 26, 46, 0.30);
        }
        .checkout-btn:active { transform: scale(0.96); }
        .checkout-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ═══════════════════════════════════════════════════════════
           DELETE MODAL — PINK ACCENT
           ═══════════════════════════════════════════════════════════ */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(26, 26, 46, 0.6);
            backdrop-filter: blur(4px);
            z-index: 3000;
            display: none;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
            padding: 16px;
        }
        .modal-overlay.active { display: flex; }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-box {
            background: #fff;
            border-radius: 20px;
            padding: 28px 24px;
            width: 100%;
            max-width: 340px;
            text-align: center;
            animation: scaleIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #FBCFE8;
            box-shadow: 0 20px 40px rgba(236, 64, 122, 0.15);
        }
        @keyframes scaleIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #FDF2F8, #FCE4EC);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            border: 2px solid #FBCFE8;
        }
        .modal-icon i { font-size: 24px; color: #EF4444; }
        .modal-title { font-size: 18px; font-weight: 700; color: #1A1A2E; margin-bottom: 8px; }
        .modal-text { font-size: 14px; color: #6B7280; margin-bottom: 24px; line-height: 1.5; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Hind Siliguri', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .modal-btn-cancel {
            background: #FDF2F8;
            color: #6B7280;
            border: 1px solid #FBCFE8;
        }
        .modal-btn-cancel:hover { background: #FBCFE8; }
        .modal-btn-delete {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: #fff;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
        }
        .modal-btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.35);
        }

        /* ═══════════════════════════════════════════════════════════
           BOTTOM NAV — MATCHES product.php EXACTLY
           ═══════════════════════════════════════════════════════════ */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #FFFFFF;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0 calc(12px + env(safe-area-inset-bottom));
            box-shadow:
                0 -4px 20px rgba(236, 64, 122, 0.08),
                0 -1px 0 rgba(0, 0, 0, 0.04),
                0 -8px 24px rgba(0, 0, 0, 0.06);
            z-index: 1000;
            border-radius: 24px 24px 0 0;
            border-top: 1.5px solid #FBCFE8;
        }
        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            padding: 6px 18px;
            border-radius: 12px;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
        }
        .bottom-nav-item i {
            font-size: 20px;
            color: #6B7280;
            transition: all 0.3s ease;
        }
        .bottom-nav-item span {
            font-size: 10px;
            color: #6B7280;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .bottom-nav-item.active {
            background: linear-gradient(135deg, #EC407A, #D81B60);
            box-shadow: 0 6px 20px rgba(236, 64, 122, 0.30);
            transform: translateY(-6px);
        }
        .bottom-nav-item.active i,
        .bottom-nav-item.active span {
            color: #fff;
        }
        .bottom-nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -4px;
            width: 20px;
            height: 4px;
            background: linear-gradient(90deg, #F59E0B, #EF4444);
            border-radius: 2px;
            animation: navIndicator 0.3s ease forwards;
        }
        @keyframes navIndicator {
            from { opacity: 0; transform: scaleX(0); }
            to { opacity: 1; transform: scaleX(1); }
        }
        .bottom-nav-item:active:not(.active) { transform: scale(0.90); }

        @supports (-webkit-touch-callout: none) {
            body { padding-bottom: calc(100px + env(safe-area-inset-bottom)); }
            .bottom-nav { padding-bottom: calc(18px + env(safe-area-inset-bottom)); }
        }

        .page-content { animation: pageIn 0.3s ease; }
        @keyframes pageIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1A1A2E;
            color: #fff;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 13px;
            z-index: 3000;
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
            font-weight: 500;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        .toast.error { background: linear-gradient(135deg, #EF4444, #DC2626); }
        .toast.success { background: linear-gradient(135deg, #22C55E, #16A34A); }
        /* ═══════════════════════════════════════════════════════════
           STYLISH TAGLINE — FIRE TEXT ANIMATION
           ═══════════════════════════════════════════════════════════ */
        .stylish-tagline {
            background: transparent;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            flex-wrap: wrap;
        }

        .tagline-emoji {
            font-size: clamp(20px, 4.5vw, 28px);
            line-height: 1;
            z-index: 1;
            filter: drop-shadow(0 2px 4px rgba(22, 163, 74, 0.3));
            animation: emojiPop 0.5s ease 0.1s both;
        }

        .tagline-text {
            font-size: clamp(18px, 4vw, 26px);
            font-weight: 900;
            color: #16A34A;
            letter-spacing: 3px;
            text-transform: uppercase;
            position: relative;
            display: inline-flex;
            align-items: center;
            z-index: 1;
            text-shadow: 0 0 5px rgba(22, 163, 74, 0.3), 0 0 15px rgba(22, 163, 74, 0.2);
            animation: taglineGlow 2s ease-in-out infinite;
        }

        .tagline-char {
            display: inline-block;
            opacity: 0;
            transform: translateY(12px);
            animation: charReveal 0.4s ease forwards;
            margin: 0 -0.5px;
        }

        .fire-particles {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .fire-particle {
            position: absolute;
            bottom: -10px;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            animation: fireRise 2s ease-out infinite;
            opacity: 0;
        }

        .dancer {
            font-size: clamp(20px, 4.5vw, 28px);
            display: inline-block;
            animation: dance 1.2s ease-in-out infinite;
            transform-origin: bottom center;
            z-index: 1;
            margin-left: 6px;
            filter: drop-shadow(0 2px 4px rgba(22, 163, 74, 0.3));
        }

        .tagline-sub {
            display: block;
            text-align: center;
            margin-top: 4px;
            font-size: clamp(13px, 2.8vw, 18px);
            font-weight: 700;
            color: #16A34A;
            font-style: italic;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 2px rgba(22, 163, 74, 0.15);
            z-index: 1;
            position: relative;
        }

        @keyframes emojiPop {
            0% { transform: scale(0) rotate(-20deg); opacity: 0; }
            70% { transform: scale(1.2) rotate(5deg); }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }

        @keyframes taglineGlow {
            0%, 100% { text-shadow: 0 0 5px rgba(22, 163, 74, 0.3), 0 0 15px rgba(22, 163, 74, 0.2); filter: brightness(1); }
            50% { text-shadow: 0 0 12px rgba(22, 163, 74, 0.5), 0 0 25px rgba(22, 163, 74, 0.3); filter: brightness(1.1); }
        }

        @keyframes charReveal {
            0% {
                opacity: 0;
                transform: translateY(12px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fireRise {
            0% {
                transform: translateY(0) scale(1);
                opacity: 0.8;
            }
            50% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-50px) scale(0.2);
                opacity: 0;
            }
        }

        @keyframes dance {
            0%, 100% { transform: rotate(-8deg) scale(1); }
            25% { transform: rotate(8deg) scale(1.1) translateY(-2px); }
            50% { transform: rotate(-4deg) scale(1); }
            75% { transform: rotate(4deg) scale(1.05) translateY(-1px); }
        }

        /* ═══════════════════════════════════════════════════════════
           FOOTER
           ═══════════════════════════════════════════════════════════ */
        .footer-section {
            margin-top: 36px;
            background: var(--surface);
            padding: 28px 16px;
            border-top: 1px solid var(--border);
        }

        .benefit-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: #FDF2F8;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            animation: slideInRight 0.5s ease forwards;
            opacity: 0;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }

        .benefit-item:last-child {
            margin-bottom: 0;
        }

        .benefit-item:active {
            transform: scale(0.98);
        }

        .benefit-item i {
            width: 40px;
            height: 40px;
            border-radius: var(--radius);
            background: linear-gradient(135deg, rgba(236, 64, 122, 0.08), rgba(236, 64, 122, 0.02));
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            border: 1px solid rgba(236, 64, 122, 0.08);
        }

        .benefit-item p {
            font-size: 13px;
            color: var(--text-primary);
            line-height: 1.5;
            font-weight: 600;
            flex: 1;
        }

        .verify-icon {
            color: var(--secondary);
            font-size: 16px;
            margin-left: 4px;
            animation: verifyPulse 2s infinite;
        }

        .footer-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border), transparent);
            margin: 20px 0;
        }

        .footer-social {
            display: flex;
            justify-content: center;
            gap: 14px;
            margin-bottom: 20px;
        }

        .social-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .social-icon:active {
            transform: scale(0.90);
        }

        .social-facebook {
            background: linear-gradient(135deg, #1877f2, #0d65d9);
        }

        .social-whatsapp {
            background: linear-gradient(135deg, #25d366, #128C7E);
        }

        .social-phone {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .footer-contact {
            text-align: center;
            padding: 8px 0;
        }

        .footer-contact a {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
            padding: 12px 28px;
            border: 2px solid var(--primary);
            border-radius: var(--radius-full);
            transition: all 0.3s ease;
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }

        .footer-contact a:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .footer-copyright {
            text-align: center;
            font-size: clamp(12px, 2.2vw, 14px);
            color: var(--text-secondary);
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            font-weight: 500;
        }

        .developer-credit {
            text-align: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .developer-credit p {
            font-size: clamp(13px, 2.5vw, 16px);
            color: var(--text-secondary);
            font-weight: 500;
        }

        .developer-credit a {
            color: #FFD700;
            font-weight: 700;
            text-decoration: none;
            position: relative;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: clamp(14px, 2.8vw, 18px);
        }

        .developer-credit a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #FFD700, #FFA500);
            transition: width 0.3s ease;
        }

        .developer-credit a:hover {
            color: #FFA500;
            text-shadow: 0 0 8px rgba(255, 215, 0, 0.4);
        }

        .developer-credit a:hover::after {
            width: 100%;
        }

        .developer-credit .heart-beat {
            display: inline-block;
            color: #EC407A;
            animation: heartbeat 1.2s ease-in-out infinite;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(30px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes verifyPulse {
            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.15);
                opacity: 0.85;
            }
        }

        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            14% { transform: scale(1.15); }
            28% { transform: scale(1); }
            42% { transform: scale(1.15); }
            70% { transform: scale(1); }
        }
    
        /* ═══ INDEX-এর মতো সেম হেডার ═══ */
        .header {
            background: linear-gradient(135deg, #ED0763 0%, #C2185B 50%, #880E4F 100%) !important;
            padding: 10px 14px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1000 !important;
            box-shadow: 0 4px 20px rgba(237, 7, 99, 0.25), 0 1px 3px rgba(0,0,0,0.1) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        .header-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
        .logo-container { display: flex; align-items: center; gap: 8px; position: relative; padding: 3px; border-radius: 12px; }
        .logo-container img { height: 36px; width: auto; object-fit: contain; border-radius: 8px; padding: 2px; background: rgba(255,255,255,0.95); box-shadow: 0 2px 6px rgba(0,0,0,0.08); position: relative; z-index: 1; transition: transform 0.3s ease; }
        .logo-container:hover img { transform: scale(1.05) rotate(-2deg); }
        .logo-fallback { width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.95); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.08); position: relative; z-index: 1; }
        .site-name { font-size: 16px; font-weight: 700; color: #FFFFFF; line-height: 1.2; letter-spacing: 0.3px; text-shadow: 0 2px 4px rgba(0,0,0,0.15); position: relative; z-index: 1; }
        .header-right { display: flex; align-items: center; gap: 8px; }
        .header-icon { width: 40px !important; height: 40px !important; border-radius: 12px !important; display: flex !important; align-items: center; justify-content: center; font-size: 16px !important; color: #FFFFFF !important; background: rgba(255,255,255,0.12) !important; border: 1px solid rgba(255,255,255,0.2) !important; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); position: relative; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; }
        .header-icon::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0)); opacity: 0; transition: opacity 0.3s ease; }
        .header-icon:hover { transform: translateY(-2px) scale(1.05); background: rgba(255,255,255,0.22) !important; box-shadow: 0 8px 20px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.3); }
        .header-icon:hover::before { opacity: 1; }
        .header-icon:active { transform: scale(0.92) translateY(0); transition: all 0.1s ease; }
        .header-icon i { position: relative; z-index: 1; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1)); }
        .cart-badge { position: absolute; top: -4px; right: -4px; background: linear-gradient(135deg, #EF4444, #DC2626); color: #fff; font-size: 9px; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; box-shadow: 0 3px 8px rgba(239,68,68,0.45), 0 0 0 2px rgba(237,7,99,0.3); border: 2px solid #fff; animation: badgePop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 2; }
        @keyframes badgePop { 0% { transform: scale(0); } 80% { transform: scale(1.2); } 100% { transform: scale(1); } }
        .header-icon[onclick="toggleSearch()"] { background: rgba(255,255,255,0.15) !important; }
        .header-icon[onclick="toggleSearch()"]:hover { background: rgba(255,255,255,0.28) !important; box-shadow: 0 8px 20px rgba(0,0,0,0.15), 0 0 15px rgba(255,255,255,0.2); }
        .header-icon[href="cart.php"]:hover { background: rgba(255,255,255,0.28) !important; box-shadow: 0 8px 20px rgba(0,0,0,0.15), 0 0 15px rgba(255,107,107,0.2); }

        /* ═══ সার্চ ওভারলে ═══ */
        .search-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(17, 24, 39, 0.60); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); z-index: 2000; display: none; align-items: flex-start; justify-content: center; padding-top: 80px; animation: fadeIn 0.3s ease; }
        .search-overlay.active { display: flex; }
        .search-box { background: #FFFFFF; width: 92%; max-width: 500px; border-radius: 16px; padding: 24px; animation: slideDown 0.3s ease; box-shadow: 0 12px 40px rgba(0,0,0,0.18); border: 1px solid #FCE4EC; }
        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .search-input-wrapper { display: flex; align-items: center; gap: 12px; background: #FDF2F8; border-radius: 12px; padding: 14px 18px; border: 2px solid #FCE4EC; transition: all 0.3s ease; }
        .search-input-wrapper:focus-within { border-color: #EC407A; box-shadow: 0 0 0 4px rgba(236, 64, 122, 0.10); background: #fff; }
        .search-input-wrapper i { color: #EC407A; font-size: 18px; }
        .search-input-wrapper input { border: none; background: none; outline: none; flex: 1; font-size: 16px; font-family: 'Hind Siliguri', sans-serif; color: #1A1A2E; }
        .search-input-wrapper input::placeholder { color: #9CA3AF; }
        .search-close { text-align: center; margin-top: 18px; color: #6B7280; font-size: 14px; cursor: pointer; font-weight: 600; padding: 10px; border-radius: 12px; transition: all 0.3s ease; }
        .search-close:hover { color: #EC407A; background: rgba(236, 64, 122, 0.06); }
    </style>
</head>
<body>
    <div class="page-content">
    <!-- ═══════════════════════════════════════════════════
        HEADER — MATCHES product.php
    ═══════════════════════════════════════════════════ -->
<header class="header">
    <div class="header-left">
        <a href="index.php" class="logo-container" id="homeLink">
            <?php if ($logo): ?>
            <img src="<?php echo $logo; ?>" alt="<?php echo $siteName; ?>">
            <?php else: ?>
            <div class="logo-fallback"><i class="fas fa-store" style="font-size:16px;color:#880E4F;"></i></div>
            <?php endif; ?>
            <span class="site-name"><?php echo $siteName; ?></span>
        </a>
    </div>
    <div class="header-right">
        <div class="header-icon" onclick="toggleSearch()" role="button" aria-label="Search">
            <i class="fas fa-magnifying-glass"></i>
        </div>
        <a href="cart.php" class="header-icon" aria-label="Cart">
            <i class="fas fa-bag-shopping"></i>
            <?php if ($cartCount > 0): ?>
            <span class="cart-badge" id="cartCount"><?php echo $cartCount; ?></span>
            <?php endif; ?>
        </a>
    </div>
</header>

<!-- SEARCH OVERLAY -->
<div class="search-overlay" id="searchOverlay">
    <div class="search-box">
        <form action="search.php" method="GET">
            <div class="search-input-wrapper">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="q" placeholder="প্রোডাক্ট খুঁজুন..." autofocus autocomplete="off">
            </div>
        </form>
        <div class="search-close" onclick="toggleSearch()">বন্ধ করুন</div>
    </div>
</div>

    <!-- ============================================================
        STYLISH TAGLINE — FIRE ANIMATION + CHARACTER REVEAL
        ============================================================ -->
        <div class="stylish-tagline" id="stylishTagline">
            <div class="fire-particles" id="fireParticles"></div>
            <span class="tagline-emoji">🛍️</span>
            <span class="tagline-text" id="taglineText"></span>
            <span class="dancer">💃</span>
            <span class="tagline-sub">❝ আপনার সন্তুষ্টি, আমাদের সাফল্য ❞</span>
        </div>

    <!-- ═══════════════════════════════════════════════════
        CART ITEMS
    ═══════════════════════════════════════════════════ -->
    <section class="cart-section">
        <?php if (empty($cartItems)): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-bag"></i>
            <h3>কার্ট খালি</h3>
            <p>আপনার কার্টে কোনো প্রোডাক্ট নেই</p>
            <a href="index.php" class="btn-shop">
                <i class="fas fa-store"></i>
                কেনাকাটা করুন
            </a>
        </div>
        <?php else: ?>
            <?php foreach ($cartItems as $key => $item): 
                $itemTotal = $item['price'] * $item['qty'];
            ?>
            <div class="cart-item" data-key="<?php echo $key; ?>">
                <div class="cart-item-image">
                    <?php if (!empty($item['image'])): ?>
                    <img src="uploads/products/<?php echo $item['image']; ?>" alt="">
                    <?php else: ?>
                    <div class="no-img"><i class="fas fa-tshirt" style="font-size: 28px; color: #F9A8D4;"></i></div>
                    <?php endif; ?>
                </div>
                <div class="cart-item-details">
                    <h3 class="cart-item-title"><?php echo $item['title']; ?></h3>
                    <div class="cart-item-meta">
                        <?php if ($item['size']): ?><span><i class="fas fa-ruler" style="margin-right:4px;"></i><?php echo $item['size']; ?></span><?php endif; ?>
                        <?php if ($item['variant']): ?><span><i class="fas fa-palette" style="margin-right:4px;"></i><?php echo $item['variant']; ?></span><?php endif; ?>
                    </div>
                    <div class="cart-item-price"><?php echo formatPrice($item['price']); ?></div>
                    <div class="cart-item-actions">
                        <div class="quantity-control">
                            <button class="qty-btn" onclick="updateQty('<?php echo $key; ?>', -1)">-</button>
                            <input type="text" class="qty-input" value="<?php echo $item['qty']; ?>" readonly>
                            <button class="qty-btn" onclick="updateQty('<?php echo $key; ?>', 1)">+</button>
                        </div>
                        <button class="remove-btn" onclick="showDeleteModal('<?php echo $key; ?>')">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <!-- ═══════════════════════════════════════════════════
        ORDER SUMMARY
    ═══════════════════════════════════════════════════ -->
    <?php if (!empty($cartItems)): ?>
    <section class="summary-section">
        <h2 class="summary-title"><i class="fas fa-receipt"></i>অর্ডার সামারি</h2>
        <div class="summary-row">
            <span class="label">সাবটোটাল (<?php echo $cartCount; ?>টি আইটেম)</span>
            <span class="value" id="subtotal"><?php echo formatPrice($subtotal); ?></span>
        </div>
        <div class="summary-row">
            <span class="label">ডেলিভারি চার্জ</span>
            <span class="value" style="color: #6B7280;">চেকআউটে গণনা</span>
        </div>
        <div class="summary-row total">
            <span class="label">মোট</span>
            <span class="value" id="total"><?php echo formatPrice($subtotal); ?></span>
        </div>
        <p class="shipping-note">
            <i class="fas fa-info-circle"></i>
            ঢাকার ভিতরে ৳70, ঢাকার বাহিরে ৳120 ডেলিভারি চার্জ প্রযোজ্য
        </p>
    </section>

    <a href="checkout.php" class="checkout-btn">
        <i class="fas fa-credit-card" style="margin-right: 8px;"></i>
        চেকআউট করুন
    </a>
    <?php endif; ?>
    <!-- ============================================================
        FOOTER
        ============================================================ -->
        <footer class="footer-section">
            <div class="benefit-item">
                <i class="fas fa-check-circle verify-icon"></i>
                <p>সাইজ বা অন্য যেকোনো সমস্যায় পাচ্ছেন ৩ দিনের মধ্যে এক্সচেঞ্জ সুবিধা</p>
            </div>
            <div class="benefit-item">
                <i class="fas fa-check-circle verify-icon"></i>
                <p>কোনো কারণে পণ্য রিটার্ন করতে চাইলে, শুধুমাত্র কুরিয়ার চার্জ প্রযোজ্য হবে!</p>
            </div>

            <div class="footer-divider"></div>

            <div class="footer-social">
                <a href="<?php echo getFacebookLink(); ?>" target="_blank" class="social-icon social-facebook" aria-label="Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://wa.me/<?php echo getWhatsAppNumber(); ?>" target="_blank" class="social-icon social-whatsapp" aria-label="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <a href="tel:<?php echo getPhoneNumber(); ?>" class="social-icon social-phone" aria-label="Phone">
                    <i class="fas fa-phone"></i>
                </a>
            </div>

            <div class="footer-contact">
                <a href="tel:<?php echo getPhoneNumber(); ?>">
                    <i class="fas fa-headset"></i>
                    <span><?php echo getPhoneNumber(); ?> — যোগাযোগ করুন</span>
                </a>
            </div>

            <div class="footer-copyright">
                &copy; <?php echo date('Y'); ?> <?php echo $siteName; ?>. সর্বস্বত্ব সংরক্ষিত।
            </div>
            <div class="developer-credit">
                <p>Developed with <span class="heart-beat">❤️</span> by <a href="https://ah-nayon.github.io/web/" target="_blank" rel="noopener noreferrer">AHN</a></p>
            </div>
        </footer>
    </div><!-- /page-content -->

    <!-- ═══════════════════════════════════════════════════
        BOTTOM NAV — MATCHES product.php
    ═══════════════════════════════════════════════════ -->
    <nav class="bottom-nav">
        <a href="index.php" class="bottom-nav-item">
            <i class="fas fa-house"></i>
            <span>হোম</span>
        </a>
        <a href="categories.php" class="bottom-nav-item">
            <i class="fas fa-border-all"></i>
            <span>ক্যাটাগরি</span>
        </a>
        <a href="cart.php" class="bottom-nav-item active">
            <i class="fas fa-bag-shopping"></i>
            <span>কার্ট</span>
        </a>
    </nav>

    <!-- ═══════════════════════════════════════════════════
        DELETE MODAL
    ═══════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <div class="modal-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 class="modal-title">আইটেম মুছে ফেলুন</h3>
            <p class="modal-text">আপনি কি এই আইটেমটি কার্ট থেকে মুছে ফেলতে চান?</p>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-cancel" onclick="hideDeleteModal()">বাতিল</button>
                <button class="modal-btn modal-btn-delete" onclick="confirmDelete()">মুছে ফেলুন</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <script>
    let deleteKey = '';
    
    function showToast(msg, type) {
        const toast = document.getElementById('toast');
        toast.textContent = msg;
        toast.className = 'toast ' + type + ' show';
        setTimeout(() => toast.classList.remove('show'), 3000);
    }
    
    function showDeleteModal(key) {
        deleteKey = key;
        document.getElementById('deleteModal').classList.add('active');
    }
    
    function hideDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
        deleteKey = '';
    }
    
    function confirmDelete() {
        if (!deleteKey) return;
        
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('key', deleteKey);
        
        fetch('cart.php', {
            method: 'POST',
            body: formData
        })
        .then(() => {
            localStorage.removeItem('mahi_cart_' + deleteKey.split('_')[0] + '_' + (deleteKey.split('_')[1] || ''));
            window.location.reload();
        });
    }
    
    function updateQty(key, delta) {
        const item = document.querySelector('[data-key="' + key + '"]');
        const input = item.querySelector('.qty-input');
        let newQty = parseInt(input.value) + delta;
        if (newQty < 1) {
            showDeleteModal(key);
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('key', key);
        formData.append('qty', newQty);
        
        fetch('cart.php', {
            method: 'POST',
            body: formData
        })
        .then(() => window.location.reload());
    }
    
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) hideDeleteModal();
    });
    // ── STYLISH TAGLINE: Character-by-character reveal + Fire particles ──
        (function() {
            const text = "Stay Stylish with Mahi";
            const container = document.getElementById('taglineText');
            const particlesContainer = document.getElementById('fireParticles');
            const colors = ['#FF6B35', '#FF4500', '#FFD700', '#FF8C00', '#FF6347'];
            
            // Create character spans with staggered animation
            text.split('').forEach((char, i) => {
                const span = document.createElement('span');
                span.className = 'tagline-char';
                span.textContent = char === ' ' ? '\u00A0' : char;
                span.style.animationDelay = (0.3 + i * 0.05) + 's';
                container.appendChild(span);
            });
            
            // Create fire particles
            function createFireParticle() {
                const particle = document.createElement('div');
                particle.className = 'fire-particle';
                const color = colors[Math.floor(Math.random() * colors.length)];
                particle.style.background = color;
                particle.style.boxShadow = `0 0 6px ${color}, 0 0 12px ${color}`;
                particle.style.left = (Math.random() * 100) + '%';
                particle.style.animationDuration = (1.5 + Math.random() * 1.5) + 's';
                particle.style.animationDelay = (Math.random() * 0.5) + 's';
                particle.style.width = (3 + Math.random() * 4) + 'px';
                particle.style.height = (3 + Math.random() * 4) + 'px';
                particlesContainer.appendChild(particle);
                
                setTimeout(() => particle.remove(), 3000);
            }
            
            // Spawn particles continuously
            setInterval(createFireParticle, 200);
            
            // Initial burst of particles
            for (let i = 0; i < 15; i++) {
                setTimeout(createFireParticle, i * 100);
            }
        })();
    </script>

<script>
// ── SEARCH TOGGLE (index-এর মতো) ──
function toggleSearch() {
    const overlay = document.getElementById('searchOverlay');
    overlay.classList.toggle('active');
    if (overlay.classList.contains('active')) {
        setTimeout(function() { overlay.querySelector('input').focus(); }, 100);
    }
}
document.getElementById('searchOverlay').addEventListener('click', function(e) {
    if (e.target === this) toggleSearch();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('searchOverlay').classList.remove('active');
});
</script>
</body>
</html>