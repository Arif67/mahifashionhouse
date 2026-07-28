<?php
require_once 'Config.php';

// Fetch data
try {
    $categories = fetchAll("SELECT * FROM categories WHERE status = 1 ORDER BY sort_order ASC");
    $banners = fetchAll("SELECT * FROM banners WHERE status = 1 ORDER BY sort_order ASC LIMIT 5");
    $products = fetchAll("SELECT p.*, c.name as category_name, 
        (SELECT image FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 1 ORDER BY p.position ASC, p.id DESC");
} catch (Exception $e) {
    $categories = [];
    $banners = [];
    $products = [];
}

$settings = getAllSettings();
$metaPixel = getMetaPixelCode();
$googleTag = getGoogleTagCode();
$favicon = getFavicon();
$logo = getLogo();
$siteName = getSetting('site_name', 'Mahi Fashion House');
$cartCount = getCartCount();

// ─── Meta Conversions API: PageView (Browser Pixel-এর সাথে একই Event ID) ───
$pageViewEventId = generateMetaEventId('pv');
queueMetaCapiEvent('PageView', $pageViewEventId, [], [], getCurrentUrl());
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="<?php echo getSetting('meta_description', 'Mahi Fashion House - প্রিমিয়াম পাঞ্জাবি কালেকশন'); ?>">
    <meta name="keywords" content="<?php echo getSetting('meta_keywords', 'পাঞ্জাবি, ফ্যাশন, মাহি ফ্যাশন'); ?>">
    <meta name="theme-color" content="#FCE4EC">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta property="og:title" content="<?php echo $siteName; ?>">
    <meta property="og:description" content="<?php echo getSetting('meta_description', 'Premium Fashion Collection'); ?>">
    <meta property="og:type" content="website">
    <title><?php echo $siteName; ?> - প্রিমিয়াম পাঞ্জাবি কালেকশন</title>
    <?php if ($favicon): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo $favicon; ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <?php if ($metaPixel): ?>
    <!-- Meta Pixel Code -->
    <script>
        !function(f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function() {
                n.callMethod ?
                    n.callMethod.apply(n, arguments) : n.queue.push(arguments)
            };
            if (!f._fbq) f._fbq = n;
            n.push = n;
            n.loaded = !0;
            n.version = '2.0';
            n.queue = [];
            t = b.createElement(e);
            t.async = !0;
            t.src = v;
            s = b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t, s)
        }(window, document, 'script',
            'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo $metaPixel; ?>');
        fbq('track', 'PageView', {}, {eventID: '<?php echo $pageViewEventId; ?>'});
    </script>
    <noscript>
        <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo $metaPixel; ?>&ev=PageView&noscript=1" />
    </noscript>
    <!-- End Meta Pixel Code -->
    <?php endif; ?>

    <?php if ($googleTag): ?>
    <!-- Google Tag -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $googleTag; ?>">
    </script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', '<?php echo $googleTag; ?>');
    </script>
    <?php endif; ?>

    <style>
        /* ============================================================
                   SOFT PINK PREMIUM UI — Mahi Fashion House
                   ============================================================ */

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

        /* ── Reset ── */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Hind Siliguri', 'Kalpurush', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            padding-bottom: calc(88px + env(safe-area-inset-bottom));
            overscroll-behavior-y: none;
        }

        a {
            text-decoration: none;
            color: inherit;
        }
        ul {
            list-style: none;
        }
        img {
            max-width: 100%;
            display: block;
        }

        /* ═══════════════════════════════════════════════════════════
           HEADER — SLIGHTLY COMPACT SIZE
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

        .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }

        /* ── Logo Container — Clean, No Border ── */
        .logo-container {
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            padding: 3px;
            border-radius: 12px;
        }

        .logo-container img {
            height: 36px;
            width: auto;
            object-fit: contain;
            border-radius: 8px;
            padding: 2px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }

        .logo-container:hover img {
            transform: scale(1.05) rotate(-2deg);
        }

        .logo-fallback {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 1;
        }

        .site-name {
            font-size: 16px;
            font-weight: 700;
            color: #FFFFFF;
            line-height: 1.2;
            letter-spacing: 0.3px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            position: relative;
            z-index: 1;
        }

        /* ── Header Right Icons (Search + Cart) ── */
        .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #FFFFFF;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            position: relative;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .header-icon::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .header-icon:hover {
            transform: translateY(-2px) scale(1.05);
            background: rgba(255, 255, 255, 0.22);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.3);
        }

        .header-icon:hover::before {
            opacity: 1;
        }

        .header-icon:active {
            transform: scale(0.92) translateY(0);
            transition: all 0.1s ease;
        }

        .header-icon i {
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1));
        }

        /* Cart Badge Enhanced */
        .cart-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: #fff;
            font-size: 9px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            box-shadow: 0 3px 8px rgba(239, 68, 68, 0.45), 0 0 0 2px rgba(237, 7, 99, 0.3);
            border: 2px solid #fff;
            animation: badgePop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 2;
        }

        @keyframes badgePop {
            0% { transform: scale(0); }
            80% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* Search icon specific glow */
        .header-icon[onclick="toggleSearch()"] {
            background: rgba(255, 255, 255, 0.15);
        }

        .header-icon[onclick="toggleSearch()"]:hover {
            background: rgba(255, 255, 255, 0.28);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15), 0 0 15px rgba(255, 255, 255, 0.2);
        }

        /* Cart icon specific */
        .header-icon[href="cart.php"]:hover {
            background: rgba(255, 255, 255, 0.28);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15), 0 0 15px rgba(255, 107, 107, 0.2);
        }

        /* ────────────────────────────────────────────────
                   CATEGORIES — স্ক্রিনশটের মতো হালকা পিঙ্ক টাইল
                   ──────────────────────────────────────────────── */

        .categories-section {
            padding: 14px 0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .categories-section::-webkit-scrollbar {
            display: none;
        }

        .categories-wrapper {
            display: flex;
            gap: 8px;
            padding: 0 16px;
            min-width: max-content;
        }

        .category-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 14px;
            min-width: 72px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: var(--radius);
            /* ⬇️ এই কালারটি স্ক্রিনশটের মতো */
            background: #FDF2F8;
            border: 1.5px solid #FBCFE8;
            position: relative;
            overflow: hidden;
        }

        .category-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-accent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .category-item:active {
            transform: scale(0.95);
        }
        .category-item:hover::before {
            opacity: 1;
        }

        .category-item i {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            /* ⬇️ আইকনের ব্যাকগ্রাউন্ড পিঙ্ক গ্রেডিয়েন্ট */
            background: linear-gradient(135deg, #EC407A, #D81B60);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            margin-bottom: 6px;
            box-shadow: 0 3px 10px rgba(236, 64, 122, 0.20);
            transition: transform 0.3s ease;
        }

        .category-item:hover i {
            transform: scale(1.08) rotate(-3deg);
        }

        .category-item span {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-primary);
            text-align: center;
            white-space: nowrap;
        }

        /* ────────────────────────────────────────────────
                   BANNER SLIDER
                   ──────────────────────────────────────────────── */

        .banner-section {
            padding: 12px 16px 8px;
        }

        .banner-slider {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .banner-slides {
            display: flex;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .banner-slide {
            min-width: 100%;
            position: relative;
        }

        .banner-slide img {
            width: 100%;
            height: 190px;
            object-fit: cover;
            display: block;
        }

        .banner-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(17, 24, 39, 0.80) 0%, rgba(17, 24, 39, 0.20) 60%, transparent 100%);
            padding: 40px 18px 18px;
            color: #fff;
        }

        .banner-overlay h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 2px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.30);
        }

        .banner-overlay p {
            font-size: 12px;
            opacity: 0.92;
            font-weight: 400;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.20);
        }

        .banner-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
        }

        .banner-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--border);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .banner-dot.active {
            background: var(--primary);
            width: 28px;
            border-radius: 4px;
        }

        /* ────────────────────────────────────────────────
                   PRODUCTS / COLLECTIONS
                   ──────────────────────────────────────────────── */

        .collections-section {
            margin-top: 20px;
            padding: 0 16px;
        }

        .section-header {
            display: inline-flex;
            align-items: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 0 var(--radius-lg) 0 0;
            margin-bottom: 16px;
            box-shadow: 0 4px 16px rgba(236, 64, 122, 0.25);
            animation: fadeInLeft 0.6s ease;
            letter-spacing: 0.3px;
        }

        .section-header i {
            margin-right: 8px;
            color: var(--highlight);
        }

        @keyframes fadeInLeft {
            from {
                transform: translateX(-30px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .product-card {
            background: var(--surface);
            border-radius: var(--radius);
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
            position: relative;
        }

        .product-card:nth-child(1) { animation-delay: 0.04s; }
        .product-card:nth-child(2) { animation-delay: 0.08s; }
        .product-card:nth-child(3) { animation-delay: 0.12s; }
        .product-card:nth-child(4) { animation-delay: 0.16s; }
        .product-card:nth-child(5) { animation-delay: 0.20s; }
        .product-card:nth-child(6) { animation-delay: 0.24s; }

        @keyframes fadeInUp {
            from {
                transform: translateY(24px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .product-card:active {
            transform: scale(0.97);
        }

        .product-image {
            position: relative;
            overflow: hidden;
            background: #FDF2F8;
        }

        .product-image img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .product-card:hover .product-image img {
            transform: scale(1.06);
        }

        /* Discount badge — RED */
        .discount-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: var(--discount);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            box-shadow: 0 3px 10px rgba(239, 68, 68, 0.30);
            z-index: 2;
            letter-spacing: 0.2px;
        }

        .new-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--gradient-accent);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: var(--radius-full);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.30);
            z-index: 2;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .product-info {
            padding: 12px;
        }

        .product-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.4;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 38px;
        }

        .product-prices {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .product-price {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: -0.2px;
        }

        .product-old-price {
            font-size: 12px;
            color: var(--text-secondary);
            text-decoration: line-through;
            font-weight: 500;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 3px;
            font-size: 10px;
        }

        .product-rating .fa-star,
        .product-rating .fa-star-half-alt {
            color: var(--highlight);
        }

        .product-rating .far.fa-star {
            color: #d1d5db;
        }

        .product-rating span {
            color: var(--text-secondary);
            margin-left: 5px;
            font-size: 11px;
            font-weight: 500;
        }

        /* ────────────────────────────────────────────────
                   FOOTER
                   ──────────────────────────────────────────────── */

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

        /* ────────────────────────────────────────────────
                   WHATSAPP FLOATING — GREEN
                   ──────────────────────────────────────────────── */

        .whatsapp-float {
            position: fixed;
            bottom: 100px;
            right: 18px;
            z-index: 999;
            animation: whatsappPulse 2s infinite, slideInWhatsApp 0.5s ease;
        }

        @keyframes slideInWhatsApp {
            from {
                transform: translateX(100px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes whatsappPulse {
            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.45);
            }
            50% {
                box-shadow: 0 0 0 16px rgba(34, 197, 94, 0);
            }
        }

        .whatsapp-float a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--secondary), var(--secondary-dark));
            border-radius: 50%;
            color: #fff;
            font-size: 28px;
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.40);
            transition: transform 0.3s ease;
        }

        .whatsapp-float a:active {
            transform: scale(0.92);
        }

        /* ═══════════════════════════════════════════════════════════
           BOTTOM NAV — FIXED: Visible separation from white bg
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
            border-top: 1.5px solid var(--border);
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            padding: 6px 18px;
            border-radius: var(--radius);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
        }

        .bottom-nav-item i {
            font-size: 20px;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }

        .bottom-nav-item span {
            font-size: 10px;
            color: var(--text-secondary);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        /* Active state — pink */
        .bottom-nav-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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
            background: var(--gradient-accent);
            border-radius: 2px;
            animation: navIndicator 0.3s ease forwards;
        }

        @keyframes navIndicator {
            from {
                opacity: 0;
                transform: scaleX(0);
            }
            to {
                opacity: 1;
                transform: scaleX(1);
            }
        }

        .bottom-nav-item:active:not(.active) {
            transform: scale(0.90);
        }

        /* ────────────────────────────────────────────────
                   SEARCH OVERLAY
                   ──────────────────────────────────────────────── */

        .search-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(17, 24, 39, 0.60);
            backdrop-filter: blur(12px);
            z-index: 2000;
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding-top: 80px;
            animation: fadeIn 0.3s ease;
        }

        .search-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .search-box {
            background: var(--surface);
            width: 92%;
            max-width: 500px;
            border-radius: var(--radius-lg);
            padding: 24px;
            animation: slideDown 0.3s ease;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
        }

        .search-input-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #FDF2F8;
            border-radius: var(--radius);
            padding: 14px 18px;
            border: 2px solid var(--border);
            transition: all 0.3s ease;
        }

        .search-input-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(236, 64, 122, 0.10);
            background: #fff;
        }

        .search-input-wrapper i {
            color: var(--primary);
            font-size: 18px;
        }

        .search-input-wrapper input {
            border: none;
            background: none;
            outline: none;
            flex: 1;
            font-size: 16px;
            font-family: 'Hind Siliguri', sans-serif;
            color: var(--text-primary);
        }

        .search-input-wrapper input::placeholder {
            color: var(--text-light);
        }

        .search-close {
            text-align: center;
            margin-top: 18px;
            color: var(--text-secondary);
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
            padding: 10px;
            border-radius: var(--radius);
            transition: all 0.3s ease;
        }

        .search-close:hover {
            color: var(--primary);
            background: rgba(236, 64, 122, 0.06);
        }

        /* ────────────────────────────────────────────────
                   EMPTY STATE
                   ──────────────────────────────────────────────── */

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 48px 20px;
            color: var(--text-secondary);
            background: var(--surface);
            border-radius: var(--radius);
            border: 2px dashed var(--border);
        }

        .empty-state i {
            font-size: 52px;
            margin-bottom: 14px;
            color: var(--border);
        }

        .empty-state p {
            font-size: 14px;
            font-weight: 500;
        }

        /* ────────────────────────────────────────────────
                   FALLBACK BANNER
                   ──────────────────────────────────────────────── */

        .fallback-banner {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
            padding: 44px 24px;
            text-align: center;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .fallback-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0%,
            100% {
                transform: translate(0, 0);
            }
            50% {
                transform: translate(-10%, -10%);
            }
        }

        .fallback-banner h2 {
            font-size: 22px;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
            font-weight: 700;
        }

        .fallback-banner p {
            font-size: 13px;
            opacity: 0.92;
            position: relative;
            z-index: 1;
            font-weight: 400;
        }

        /* ────────────────────────────────────────────────
                   RESPONSIVE
                   ──────────────────────────────────────────────── */

        @media (min-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
            }
            .banner-slide img {
                height: 300px;
            }
            .product-image img {
                height: 210px;
            }
        }

        @media (min-width: 1024px) {
            .products-grid {
                grid-template-columns: repeat(4, 1fr);
                max-width: 1200px;
                margin: 0 auto;
            }
            .banner-slide img {
                height: 380px;
            }
            .banner-overlay h3 {
                font-size: 26px;
            }
            .banner-overlay p {
                font-size: 15px;
            }
        }

        /* ═══════════════════════════════════════════════════════════
           STYLISH TAGLINE — FIRE TEXT ANIMATION (NO RED BG)
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
        
        @keyframes emojiPop {
            0% { transform: scale(0) rotate(-20deg); opacity: 0; }
            70% { transform: scale(1.2) rotate(5deg); }
            100% { transform: scale(1) rotate(0); opacity: 1; }
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
        @keyframes taglineGlow {
            0%, 100% { text-shadow: 0 0 5px rgba(22, 163, 74, 0.3), 0 0 15px rgba(22, 163, 74, 0.2); filter: brightness(1); }
            50% { text-shadow: 0 0 12px rgba(22, 163, 74, 0.5), 0 0 25px rgba(22, 163, 74, 0.3); filter: brightness(1.1); }
        }
        
        .tagline-char {
            display: inline-block;
            opacity: 0;
            transform: translateY(12px);
            animation: charReveal 0.4s ease forwards;
            margin: 0 -0.5px;
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
        
        /* Fire particles behind text */
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
        
        .dancer {
            font-size: clamp(20px, 4.5vw, 28px);
            display: inline-block;
            animation: dance 1.2s ease-in-out infinite;
            transform-origin: bottom center;
            z-index: 1;
            margin-left: 6px;
            filter: drop-shadow(0 2px 4px rgba(22, 163, 74, 0.3));
        }
        @keyframes dance {
            0%, 100% { transform: rotate(-8deg) scale(1); }
            25% { transform: rotate(8deg) scale(1.1) translateY(-2px); }
            50% { transform: rotate(-4deg) scale(1); }
            75% { transform: rotate(4deg) scale(1.05) translateY(-1px); }
        }

        /* ═══════════════════════════════════════════════════════════
           SATISFACTION TAGLINE — COMPACT, LESS BACKGROUND
           ═══════════════════════════════════════════════════════════ */
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

        /* iOS Safe Area */
        @supports (-webkit-touch-callout: none) {
            body {
                padding-bottom: calc(100px + env(safe-area-inset-bottom));
            }
            .bottom-nav {
                padding-bottom: calc(18px + env(safe-area-inset-bottom));
            }
        }
    

        /* ────────────────────────────────────────────────
                   DEVELOPER CREDIT
                   ──────────────────────────────────────────────── */
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
        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            14% { transform: scale(1.15); }
            28% { transform: scale(1); }
            42% { transform: scale(1.15); }
            70% { transform: scale(1); }
        }
    
        /* ═══ কার্ডে অর্ডার বাটন + স্পেসিং ═══ */
        .product-info { padding: 12px 14px 14px !important; }
        .product-title { margin-bottom: 8px !important; }
        .product-prices { margin-bottom: 6px !important; }
        .product-rating { margin-bottom: 10px !important; }
        .order-now-btn {
            display: flex; align-items: center; justify-content: center; gap: 7px;
            width: 100%; padding: 10px 12px;
            background: linear-gradient(135deg, #ED0763, #C2185B);
            color: #fff; border-radius: 12px;
            font-size: 13px; font-weight: 700; letter-spacing: 0.3px;
            box-shadow: 0 4px 14px rgba(237, 7, 99, 0.28);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .order-now-btn i { font-size: 12px; }
        .product-card:hover .order-now-btn {
            background: linear-gradient(135deg, #C2185B, #880E4F);
            box-shadow: 0 6px 18px rgba(237, 7, 99, 0.40);
            transform: translateY(-1px);
        }
        .product-card:active .order-now-btn { transform: scale(0.97); }

        /* ═══ Developer Credit — পুরোটা লিংক, আন্ডারলাইন নাই ═══ */
        .developer-credit a, .developer-credit a:hover, .developer-credit a:visited {
            text-decoration: none !important;
        }
        .developer-credit a::after { display: none !important; }
        .developer-credit p a.dev-link, .developer-credit a.dev-link {
            color: #6B7280 !important; font-weight: 500; font-size: inherit;
        }
        .developer-credit a.dev-link b { color: #FFD700; font-weight: 700; transition: all 0.3s ease; }
        .developer-credit a.dev-link:hover b { color: #FFA500; text-shadow: 0 0 8px rgba(255, 215, 0, 0.4); }
    </style>
</head>
<body>
    <div class="page-content">

        <!-- ═══════════════════════════════════════════════════
        HEADER — COMPACT SIZE
        ═══════════════════════════════════════════════════ -->
        <header class="header">
            <div class="header-left">
                <a href="index.php" class="logo-container" id="homeLink">
                    <?php if ($logo): ?>
                    <img src="<?php echo $logo; ?>" alt="<?php echo $siteName; ?>">
                    <?php else: ?>
                    <div class="logo-fallback">
                        <i class="fas fa-store" style="font-size:16px;color:#880E4F;"></i>
                    </div>
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

        <!-- ============================================================
        CATEGORIES — হালকা পিঙ্ক টাইল (স্ক্রিনশটের মতো)
        ============================================================ -->
        <section class="categories-section">
            <div class="categories-wrapper">
                <?php foreach ($categories as $cat): ?>
                <a href="category.php?id=<?php echo $cat['id']; ?>" class="category-item">
                    <i class="fas <?php echo $cat['icon']; ?>"></i>
                    <span><?php echo $cat['name']; ?></span>
                </a>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <div class="category-item"><i class="fas fa-male"></i><span>পাঞ্জাবি</span></div>
                <div class="category-item"><i class="fas fa-tshirt"></i><span>কটন</span></div>
                <div class="category-item"><i class="fas fa-user-tie"></i><span>প্রিমিয়াম</span></div>
                <div class="category-item"><i class="fas fa-gem"></i><span>এম্ব্রডারি</span></div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============================================================
        BANNER SLIDER — FIXED: subtitle fallback
        ============================================================ -->
        <?php if (!empty($banners)): ?>
        <section class="banner-section">
            <div class="banner-slider" id="bannerSlider">
                <div class="banner-slides" id="bannerSlides">
                    <?php foreach ($banners as $banner): ?>
                    <div class="banner-slide">
                        <img src="uploads/banners/<?php echo $banner['image']; ?>" alt="<?php echo $banner['title']; ?>" loading="lazy">
                        <div class="banner-overlay">
                            <h3><?php echo $banner['title']; ?></h3>
                            <!-- ✅ FIX: null coalescing -->
                            <p><?php echo $banner['subtitle'] ?? ''; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="banner-dots" id="bannerDots"></div>
        </section>
        <?php else: ?>
        <section class="banner-section">
            <div class="fallback-banner">
                <h2>Mahi Fashion House</h2>
                <p>প্রিমিয়াম পাঞ্জাবি কালেকশন — নতুন ডিজাইন ২০২৫</p>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============================================================
        SATISFACTION TAGLINE — COMPACT
        ============================================================ -->

        <!-- ============================================================
        PRODUCTS / COLLECTIONS
        ============================================================ -->
        <section class="collections-section">
            <div class="section-header">
                <i class="fas fa-fire"></i> Collections
            </div>
            <div class="products-grid">
                <?php foreach ($products as $index => $product):
                    $discountPercent = $product['discount_price'] > 0 ?
                    round((($product['price'] - $product['discount_price']) / $product['price']) * 100) : 0;
                    $isNew = (time() - strtotime($product['created_at'])) < (7 * 24 * 60 * 60);
                ?>
                <a href="product.php?slug=<?php echo $product['slug']; ?>" class="product-card">
                    <div class="product-image">
                        <?php if ($discountPercent > 0): ?>
                        <span class="discount-badge">-<?php echo $discountPercent; ?>%</span>
                        <?php endif; ?>
                        <?php if ($isNew && $discountPercent == 0): ?>
                        <span class="new-badge">New</span>
                        <?php endif; ?>
                        <?php if ($product['primary_image']): ?>
                        <img src="uploads/products/<?php echo $product['primary_image']; ?>" alt="<?php echo $product['title']; ?>" loading="lazy">
                        <?php else: ?>
                        <div style="width:100%;height:180px;background:#FDF2F8;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-tshirt" style="font-size:48px;color:#F9A8D4;"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3 class="product-title"><?php echo $product['title']; ?></h3>
                        <div class="product-prices">
                            <span class="product-price">
                                <?php echo $product['discount_price'] > 0 ? formatPrice($product['discount_price']) : formatPrice($product['price']); ?>
                            </span>
                            <?php if ($product['discount_price'] > 0): ?>
                            <span class="product-old-price"><?php echo formatPrice($product['price']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= floor($product['rating'])): ?>
                            <i class="fas fa-star"></i>
                            <?php elseif ($i - 0.5 <= $product['rating']): ?>
                            <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                            <i class="far fa-star"></i>
                            <?php endif; ?>
                            <?php endfor; ?>
                            <span>(<?php echo $product['rating']; ?>)</span>
                        </div>
                        <span class="order-now-btn"><i class="fas fa-bolt"></i> অর্ডার করুন</span>
                    </div>
                </a>
                <?php endforeach; ?>

                <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>কোনো প্রোডাক্ট পাওয়া যায়নি</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

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
                <p><a href="https://ah-nayon.github.io/web/" target="_blank" rel="noopener noreferrer" class="dev-link">Developed with <span class="heart-beat">❤️</span> by <b>AHN</b></a></p>
            </div>
        </footer>
    </div>
    <!-- /.page-content -->

    <!-- ============================================================
    WHATSAPP FLOATING — GREEN
    ============================================================ -->
    <div class="whatsapp-float">
        <a href="https://wa.me/<?php echo getWhatsAppNumber(); ?>" target="_blank" aria-label="WhatsApp Chat">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>

    <!-- ═══════════════════════════════════════════════════
    BOTTOM NAV — FIXED: Visible pink border + shadow
    ═══════════════════════════════════════════════════ -->
    <nav class="bottom-nav">
        <a href="index.php" class="bottom-nav-item active" id="bottomHomeLink">
            <i class="fas fa-house"></i>
            <span>হোম</span>
        </a>
        <a href="categories.php" class="bottom-nav-item">
            <i class="fas fa-border-all"></i>
            <span>ক্যাটাগরি</span>
        </a>
        <a href="cart.php" class="bottom-nav-item">
            <i class="fas fa-bag-shopping"></i>
            <span>কার্ট</span>
        </a>
    </nav>

    <!-- ============================================================
    SEARCH OVERLAY
    ============================================================ -->
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
    SCRIPTS
    ============================================================ -->
    <script>
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

        // ── HOME 404 FIX ──
        (function() {
            const path = window.location.pathname;
            const isHome = path.endsWith('index.php') || path.endsWith('/') || path === '' ||
                path.endsWith('/index') || path.endsWith('index');

            if (isHome) {
                document.querySelectorAll('a[href="index.php"]').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        document.getElementById('searchOverlay').classList.remove('active');
                    });
                });
            }
        })();

        // ── BANNER SLIDER ──
        (function() {
            const slides = document.getElementById('bannerSlides');
            const dotsContainer = document.getElementById('bannerDots');
            if (!slides || !dotsContainer) return;

            const slideCount = slides.children.length;
            if (slideCount <= 1) return;

            let current = 0;
            let autoSlideInterval;

            for (let i = 0; i < slideCount; i++) {
                const dot = document.createElement('div');
                dot.className = 'banner-dot' + (i === 0 ? ' active' : '');
                dot.setAttribute('role', 'button');
                dot.setAttribute('aria-label', 'Slide ' + (i + 1));
                dot.onclick = () => goTo(i);
                dotsContainer.appendChild(dot);
            }

            function goTo(index) {
                current = index;
                slides.style.transform = 'translateX(-' + (current * 100) + '%)';
                updateDots();
                resetAutoSlide();
            }

            function updateDots() {
                const dots = dotsContainer.children;
                for (let i = 0; i < dots.length; i++) {
                    dots[i].classList.toggle('active', i === current);
                }
            }

            function next() {
                current = (current + 1) % slideCount;
                goTo(current);
            }

            function resetAutoSlide() {
                clearInterval(autoSlideInterval);
                autoSlideInterval = setInterval(next, 4000);
            }

            resetAutoSlide();

            let startX = 0;
            let isDragging = false;

            slides.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
                isDragging = true;
            }, { passive: true });

            slides.addEventListener('touchend', function(e) {
                if (!isDragging) return;
                const diff = startX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 50) {
                    if (diff > 0) {
                        current = (current + 1) % slideCount;
                    } else {
                        current = (current - 1 + slideCount) % slideCount;
                    }
                    goTo(current);
                }
                isDragging = false;
            }, { passive: true });

            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    clearInterval(autoSlideInterval);
                } else {
                    resetAutoSlide();
                }
            });
        })();

        // ── SEARCH TOGGLE ──
        function toggleSearch() {
            const overlay = document.getElementById('searchOverlay');
            overlay.classList.toggle('active');
            if (overlay.classList.contains('active')) {
                setTimeout(function() {
                    overlay.querySelector('input').focus();
                }, 100);
            }
        }

        document.getElementById('searchOverlay').addEventListener('click', function(e) {
            if (e.target === this) toggleSearch();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') document.getElementById('searchOverlay').classList.remove('active');
        });

        // ── SMOOTH SCROLL ──
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth' });
            });
        });

        // ── ACTIVE NAV INDICATOR ──
        (function() {
            const path = window.location.pathname;
            const navItems = document.querySelectorAll('.bottom-nav-item');

            navItems.forEach(function(item) {
                item.classList.remove('active');
                const href = item.getAttribute('href');
                if (href && path.includes(href.replace('./', ''))) {
                    item.classList.add('active');
                }
            });

            if (!document.querySelector('.bottom-nav-item.active')) {
                navItems[0].classList.add('active');
            }
        })();
    </script>
</body>
</html>