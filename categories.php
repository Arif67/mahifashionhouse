<?php
require_once 'Config.php';

try {
    $categories = fetchAll("SELECT * FROM categories WHERE status = 1 ORDER BY sort_order ASC");
} catch (Exception $e) {
    $categories = [];
}

$favicon = getFavicon();
$siteName = getSetting('site_name', 'Mahi Fashion House');
$logo = getLogo();
$cartCount = getCartCount();
$metaPixel = getMetaPixelCode();

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
    <title>ক্যাটাগরি - <?php echo $siteName; ?></title>
    <?php if ($favicon): ?><link rel="icon" type="image/x-icon" href="<?php echo $favicon; ?>"><?php endif; ?>

    <?php if ($metaPixel): ?>
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
        fbq('init', '<?php echo $metaPixel; ?>');
        fbq('track', 'PageView', {}, {eventID: '<?php echo $pageViewEventId; ?>'});
        fbq('track', 'ViewCategory', {
            content_name: 'All Categories',
            content_type: 'product_group'
        });
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo $metaPixel; ?>&ev=PageView&noscript=1"/></noscript>
    <!-- End Meta Pixel Code -->
    <?php endif; ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Hind Siliguri', 'Kalpurush', sans-serif;
            background: #FFFFFF; color: #1A1A2E;
            overflow-x: hidden; -webkit-font-smoothing: antialiased;
            padding-bottom: calc(88px + env(safe-area-inset-bottom));
            overscroll-behavior-y: none;
        }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }

        /* ═══ HEADER — index.php এর মতো সেম ═══ */
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
        .logo-container {
            display: flex; align-items: center; gap: 8px;
            position: relative; padding: 3px; border-radius: 12px;
        }
        .logo-container img {
            height: 36px; width: auto; object-fit: contain;
            border-radius: 8px; padding: 2px;
            background: rgba(255,255,255,0.95);
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        .logo-container:hover img { transform: scale(1.05) rotate(-2deg); }
        .logo-fallback {
            width: 36px; height: 36px; border-radius: 8px;
            background: rgba(255,255,255,0.95);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .site-name {
            font-size: 16px; font-weight: 700; color: #FFFFFF;
            line-height: 1.2; letter-spacing: 0.3px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        .header-right { display: flex; align-items: center; gap: 8px; }
        .header-icon {
            width: 40px; height: 40px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: #FFFFFF;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            position: relative; cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        .header-icon::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0));
            opacity: 0; transition: opacity 0.3s ease;
        }
        .header-icon:hover {
            transform: translateY(-2px) scale(1.05);
            background: rgba(255,255,255,0.22);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.3);
        }
        .header-icon:hover::before { opacity: 1; }
        .header-icon:active { transform: scale(0.92); }
        .header-icon i { position: relative; z-index: 1; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1)); }
        .cart-badge {
            position: absolute; top: -4px; right: -4px;
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: #fff; font-size: 9px;
            width: 20px; height: 20px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
            box-shadow: 0 3px 8px rgba(239,68,68,0.45), 0 0 0 2px rgba(237,7,99,0.3);
            border: 2px solid #fff;
            animation: badgePop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 2;
        }
        @keyframes badgePop { 0% { transform: scale(0); } 80% { transform: scale(1.2); } 100% { transform: scale(1); } }

        /* ═══ SEARCH OVERLAY ═══ */
        .search-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(17, 24, 39, 0.60);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 2000; display: none;
            align-items: flex-start; justify-content: center;
            padding-top: 80px; animation: fadeIn 0.3s ease;
        }
        .search-overlay.active { display: flex; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .search-box {
            background: #FFFFFF; width: 92%; max-width: 500px;
            border-radius: 16px; padding: 24px;
            animation: slideDown 0.3s ease;
            box-shadow: 0 12px 40px rgba(0,0,0,0.18);
            border: 1px solid #FCE4EC;
        }
        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .search-input-wrapper {
            display: flex; align-items: center; gap: 12px;
            background: #FDF2F8; border-radius: 12px;
            padding: 14px 18px; border: 2px solid #FCE4EC;
            transition: all 0.3s ease;
        }
        .search-input-wrapper:focus-within {
            border-color: #EC407A;
            box-shadow: 0 0 0 4px rgba(236, 64, 122, 0.10);
            background: #fff;
        }
        .search-input-wrapper i { color: #EC407A; font-size: 18px; }
        .search-input-wrapper input {
            border: none; background: none; outline: none; flex: 1;
            font-size: 16px; font-family: 'Hind Siliguri', sans-serif;
            color: #1A1A2E;
        }
        .search-input-wrapper input::placeholder { color: #9CA3AF; }
        .search-close {
            text-align: center; margin-top: 18px;
            color: #6B7280; font-size: 14px; cursor: pointer;
            font-weight: 600; padding: 10px; border-radius: 12px;
            transition: all 0.3s ease;
        }
        .search-close:hover { color: #EC407A; background: rgba(236, 64, 122, 0.06); }

        /* ═══ STYLISH TAGLINE ═══ */
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
            0% { opacity: 0; transform: translateY(12px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .fire-particles {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
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
            0% { transform: translateY(0) scale(1); opacity: 0.8; }
            50% { opacity: 0.6; }
            100% { transform: translateY(-50px) scale(0.2); opacity: 0; }
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
            width: 100%;
        }

        /* ═══ CATEGORIES GRID ═══ */
        .categories-grid {
            padding: 16px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .category-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 28px 18px;
            text-align: center;
            border: 1.5px solid #FBCFE8;
            box-shadow: 0 4px 12px rgba(236, 64, 122, 0.06);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
            position: relative;
            overflow: hidden;
        }
        .category-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #F59E0B, #EF4444);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .category-card:nth-child(1) { animation-delay: 0.04s; }
        .category-card:nth-child(2) { animation-delay: 0.08s; }
        .category-card:nth-child(3) { animation-delay: 0.12s; }
        .category-card:nth-child(4) { animation-delay: 0.16s; }
        .category-card:nth-child(5) { animation-delay: 0.20s; }
        .category-card:nth-child(6) { animation-delay: 0.24s; }
        @keyframes fadeInUp {
            from { transform: translateY(24px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .category-card:hover::before { opacity: 1; }
        .category-card:active { transform: scale(0.96); }
        .category-card i {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #EC407A, #D81B60);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 12px;
            box-shadow: 0 4px 14px rgba(236, 64, 122, 0.25);
            transition: transform 0.3s ease;
        }
        .category-card:hover i {
            transform: scale(1.08) rotate(-3deg);
        }
        .category-card h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1A1A2E;
            margin-bottom: 4px;
        }
        .category-card .product-count {
            font-size: 12px;
            color: #6B7280;
            font-weight: 500;
        }

        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 50px;
            color: #9CA3AF;
            background: #FFFFFF;
            border-radius: 16px;
            border: 2px dashed #FBCFE8;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 14px;
            color: #F9A8D4;
        }
        .empty-state p {
            font-size: 14px;
            font-weight: 500;
        }

        /* ═══ FOOTER ═══ */
        .footer-section {
            margin-top: 36px;
            background: #FFFFFF;
            padding: 28px 16px;
            border-top: 1px solid #FBCFE8;
        }
        .benefit-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: #FDF2F8;
            border-radius: 12px;
            border: 1px solid #FBCFE8;
            box-shadow: 0 4px 12px rgba(236, 64, 122, 0.06);
            animation: slideInRight 0.5s ease forwards;
            opacity: 0;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }
        .benefit-item:last-child { margin-bottom: 0; }
        @keyframes slideInRight {
            from { transform: translateX(30px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .benefit-item:active { transform: scale(0.98); }
        .benefit-item i {
            width: 40px; height: 40px; border-radius: 12px;
            background: linear-gradient(135deg, rgba(236,64,122,0.08), rgba(236,64,122,0.02));
            color: #EC407A;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0;
            border: 1px solid rgba(236,64,122,0.08);
        }
        .benefit-item p {
            font-size: 13px; color: #1A1A2E;
            line-height: 1.5; font-weight: 600; flex: 1;
        }
        .verify-icon {
            color: #22C55E; font-size: 16px; margin-left: 4px;
            animation: verifyPulse 2s infinite;
        }
        @keyframes verifyPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.15); opacity: 0.85; }
        }
        .footer-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #FBCFE8, transparent);
            margin: 20px 0;
        }
        .footer-social {
            display: flex;
            justify-content: center;
            gap: 14px;
            margin-bottom: 20px;
        }
        .social-icon {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: #fff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .social-icon:active { transform: scale(0.90); }
        .social-facebook { background: linear-gradient(135deg, #1877f2, #0d65d9); }
        .social-whatsapp { background: linear-gradient(135deg, #25d366, #128C7E); }
        .social-phone { background: linear-gradient(135deg, #EC407A, #D81B60); }
        .footer-contact { text-align: center; padding: 8px 0; }
        .footer-contact a {
            display: inline-flex; align-items: center; gap: 10px;
            font-size: 15px; font-weight: 700; color: #EC407A;
            padding: 12px 28px; border: 2px solid #EC407A;
            border-radius: 9999px; transition: all 0.3s ease;
            background: #FFFFFF;
            box-shadow: 0 4px 12px rgba(236, 64, 122, 0.06);
        }
        .footer-contact a:hover {
            background: linear-gradient(135deg, #EC407A, #D81B60);
            color: #fff; transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(236, 64, 122, 0.10);
        }
        .footer-copyright {
            text-align: center;
            font-size: clamp(12px, 2.2vw, 14px);
            color: #6B7280; margin-top: 20px;
            padding-top: 16px; border-top: 1px solid #FBCFE8;
            font-weight: 500;
        }
        .developer-credit {
            text-align: center; margin-top: 12px;
            padding-top: 12px; border-top: 1px solid #FBCFE8;
        }
        .developer-credit p {
            font-size: clamp(13px, 2.5vw, 16px);
            color: #6B7280; font-weight: 500;
        }
        .developer-credit a {
            color: #FFD700; font-weight: 700;
            text-decoration: none; position: relative;
            display: inline-block; transition: all 0.3s ease;
            font-size: clamp(14px, 2.8vw, 18px);
        }
        .developer-credit a::after {
            content: ''; position: absolute;
            bottom: -2px; left: 0; width: 0; height: 2px;
            background: linear-gradient(90deg, #FFD700, #FFA500);
            transition: width 0.3s ease;
        }
        .developer-credit a:hover {
            color: #FFA500;
            text-shadow: 0 0 8px rgba(255, 215, 0, 0.4);
        }
        .developer-credit a:hover::after { width: 100%; }
        .developer-credit .heart-beat {
            display: inline-block; color: #EC407A;
            animation: heartbeat 1.2s ease-in-out infinite;
        }
        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            14% { transform: scale(1.15); }
            28% { transform: scale(1); }
            42% { transform: scale(1.15); }
            70% { transform: scale(1); }
        }

        /* ═══ BOTTOM NAV ═══ */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
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
        .bottom-nav-item.active span { color: #fff; }
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

        .page-content { animation: pageIn 0.3s ease; }
        @keyframes pageIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @supports (-webkit-touch-callout: none) {
            body { padding-bottom: calc(100px + env(safe-area-inset-bottom)); }
            .bottom-nav { padding-bottom: calc(18px + env(safe-area-inset-bottom)); }
        }
    </style>
</head>
<body>
    <div class="page-content">
        <!-- ═══ HEADER — index.php এর মতো সেম ═══ -->
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

        <!-- ═══ SEARCH OVERLAY ═══ -->
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

        <div class="stylish-tagline" id="stylishTagline">
            <div class="fire-particles" id="fireParticles"></div>
            <span class="tagline-emoji">🛍️</span>
            <span class="tagline-text" id="taglineText"></span>
            <span class="dancer">💃</span>
            <span class="tagline-sub">❝ আপনার সন্তুষ্টি, আমাদের সাফল্য ❞</span>
        </div>

        <div class="categories-grid">
            <?php foreach ($categories as $cat):
                $count = fetchOne("SELECT COUNT(*) as c FROM products WHERE category_id = ? AND status = 1", [$cat['id']])['c'] ?? 0;
            ?>
            <a href="category.php?id=<?php echo $cat['id']; ?>" class="category-card">
                <i class="fas <?php echo $cat['icon']; ?>"></i>
                <h3><?php echo $cat['name']; ?></h3>
                <span class="product-count"><?php echo $count; ?>টি প্রোডাক্ট</span>
            </a>
            <?php endforeach; ?>

            <?php if (empty($categories)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>কোনো ক্যাটাগরি পাওয়া যায়নি</p>
            </div>
            <?php endif; ?>
        </div>

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
    </div>

    <nav class="bottom-nav">
        <a href="index.php" class="bottom-nav-item" id="bottomHomeLink">
            <i class="fas fa-house"></i>
            <span>হোম</span>
        </a>
        <a href="categories.php" class="bottom-nav-item active">
            <i class="fas fa-border-all"></i>
            <span>ক্যাটাগরি</span>
        </a>
        <a href="cart.php" class="bottom-nav-item">
            <i class="fas fa-bag-shopping"></i>
            <span>কার্ট</span>
        </a>
    </nav>

    <script>
        // ── STYLISH TAGLINE ──
        (function() {
            const text = "Stay Stylish with Mahi";
            const container = document.getElementById('taglineText');
            const particlesContainer = document.getElementById('fireParticles');
            const colors = ['#FF6B35', '#FF4500', '#FFD700', '#FF8C00', '#FF6347'];

            text.split('').forEach((char, i) => {
                const span = document.createElement('span');
                span.className = 'tagline-char';
                span.textContent = char === ' ' ? '\u00A0' : char;
                span.style.animationDelay = (0.3 + i * 0.05) + 's';
                container.appendChild(span);
            });

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

            setInterval(createFireParticle, 200);
            for (let i = 0; i < 15; i++) {
                setTimeout(createFireParticle, i * 100);
            }
        })();

        // ── SEARCH TOGGLE ──
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

        // ── HOME 404 FIX ──
        (function() {
            const path = window.location.pathname;
            const isHome = path.endsWith('index.php') || path.endsWith('/') || path === '' ||
                path.endsWith('/index') || path.endsWith('index');

            document.getElementById('homeLink').addEventListener('click', function(e) {
                if (isHome) {
                    e.preventDefault();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
            document.getElementById('bottomHomeLink').addEventListener('click', function(e) {
                if (isHome) {
                    e.preventDefault();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        })();
    </script>
</body>
</html>