<?php
require_once 'Config.php';

$query = isset($_GET['q']) ? clean($_GET['q']) : '';
$products = [];

if (!empty($query)) {
    try {
        $searchTerm = "%$query%";
        $products = fetchAll("SELECT p.*, 
            (SELECT image FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM products p 
            WHERE p.status = 1 AND (p.title LIKE ? OR p.description LIKE ?)
            ORDER BY p.position ASC, p.id DESC", [$searchTerm, $searchTerm]);
    } catch (Exception $e) {
        $products = [];
    }
}

$settings = getAllSettings();
$favicon = getFavicon();
$logo = getLogo();
$siteName = getSetting('site_name', 'Mahi Fashion House');
$cartCount = getCartCount();
$metaPixel = getMetaPixelCode();

// ─── Meta CAPI + Browser Pixel: PageView ───
$pageViewEventId = generateMetaEventId('pv');
queueMetaCapiEvent('PageView', $pageViewEventId, [], [], getCurrentUrl());
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FCE4EC">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>খোঁজ - <?php echo $siteName; ?></title>
    <?php if ($favicon): ?><link rel="icon" type="image/x-icon" href="<?php echo $favicon; ?>"><?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo $metaPixel; ?>&ev=PageView&noscript=1"/></noscript>
    <!-- End Meta Pixel Code -->
    <?php endif; ?>
    <style>
        /* ============================================================
           SEARCH PAGE — Mahi Fashion House (Matches Index Design)
           ============================================================ */

        :root {
            --primary: #EC407A;
            --primary-dark: #D81B60;
            --primary-light: #F48FB1;
            --header-bg: #ED0763;
            --secondary: #22C55E;
            --secondary-dark: #16A34A;
            --bg: #FFFFFF;
            --surface: #FFFFFF;
            --border: #FBCFE8;
            --discount: #EF4444;
            --highlight: #F59E0B;
            --gradient-accent: linear-gradient(90deg, #F59E0B, #EF4444);
            --text-primary: #1A1A2E;
            --text-secondary: #6B7280;
            --text-light: #9CA3AF;
            --shadow-sm: 0 4px 12px rgba(236, 64, 122, 0.06);
            --shadow-md: 0 8px 24px rgba(236, 64, 122, 0.10);
            --shadow-lg: 0 12px 40px rgba(236, 64, 122, 0.14);
            --radius: 12px;
            --radius-lg: 20px;
            --radius-full: 9999px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Hind Siliguri', 'Kalpurush', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            padding-bottom: calc(88px + env(safe-area-inset-bottom));
            overscroll-behavior-y: none;
        }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }

        /* ═══════════════════════════════════════════════════════════
           HEADER — Matches Index (Gradient Pink, Sticky)
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

        .back-btn {
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
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
        }
        .back-btn:hover {
            transform: translateY(-2px) scale(1.05);
            background: rgba(255, 255, 255, 0.22);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.3);
        }
        .back-btn:active { transform: scale(0.92); }

        .search-form {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .search-input-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius);
            padding: 10px 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        .search-input-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(236, 64, 122, 0.15);
        }
        .search-input-wrapper i {
            color: var(--primary);
            font-size: 16px;
        }
        .search-input {
            flex: 1;
            border: none;
            background: none;
            outline: none;
            font-size: 15px;
            font-family: 'Hind Siliguri', sans-serif;
            color: var(--text-primary);
        }
        .search-input::placeholder { color: var(--text-light); }
        .search-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: linear-gradient(135deg, var(--secondary), var(--secondary-dark));
            color: #fff;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.30);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        .search-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 16px rgba(34, 197, 94, 0.40);
        }
        .search-btn:active { transform: scale(0.92); }

        .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 8px;
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
            position: relative;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .header-icon:hover {
            transform: translateY(-2px) scale(1.05);
            background: rgba(255, 255, 255, 0.22);
        }
        .header-icon:active { transform: scale(0.92); }

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

        /* ═══════════════════════════════════════════════════════════
           RESULTS INFO — Soft Pink Style
           ═══════════════════════════════════════════════════════════ */
        .results-info {
            padding: 16px;
            font-size: 14px;
            color: var(--text-secondary);
            background: linear-gradient(135deg, #FDF2F8, #FCE7F3);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .results-info i {
            color: var(--primary);
            font-size: 16px;
        }
        .results-info span {
            font-weight: 700;
            color: var(--primary-dark);
        }
        .results-info .count-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            padding: 2px 10px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 700;
            margin-left: auto;
        }

        /* ═══════════════════════════════════════════════════════════
           PRODUCTS GRID — Matches Index Cards
           ═══════════════════════════════════════════════════════════ */
        .products-section {
            padding: 16px;
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
            from { transform: translateY(24px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .product-card:active { transform: scale(0.97); }
        .product-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
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

        .product-info { padding: 12px; }
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
        .product-rating .fa-star-half-alt { color: var(--highlight); }
        .product-rating .far.fa-star { color: #d1d5db; }
        .product-rating span {
            color: var(--text-secondary);
            margin-left: 5px;
            font-size: 11px;
            font-weight: 500;
        }

        /* ═══════════════════════════════════════════════════════════
           EMPTY SEARCH STATE
           ═══════════════════════════════════════════════════════════ */
        .empty-search {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            grid-column: 1 / -1;
            background: var(--surface);
            border-radius: var(--radius);
            border: 2px dashed var(--border);
        }
        .empty-search i {
            font-size: 56px;
            margin-bottom: 16px;
            color: var(--border);
        }
        .empty-search h3 {
            font-size: 16px;
            margin-bottom: 6px;
            color: var(--text-primary);
            font-weight: 600;
        }
        .empty-search p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* ═══════════════════════════════════════════════════════════
           SUGGESTED SEARCHES (when no query)
           ═══════════════════════════════════════════════════════════ */
        .suggested-section {
            padding: 20px 16px;
        }
        .suggested-header {
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
        .suggested-header i {
            margin-right: 8px;
            color: var(--highlight);
        }
        @keyframes fadeInLeft {
            from { transform: translateX(-30px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .suggested-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .suggested-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            background: #FDF2F8;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-full);
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-dark);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .suggested-tag:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .suggested-tag:active { transform: scale(0.95); }
        .suggested-tag i { font-size: 12px; }

        /* ═══════════════════════════════════════════════════════════
           BOTTOM NAV — Matches Index Exactly
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
        .bottom-nav-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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
            background: var(--gradient-accent);
            border-radius: 2px;
            animation: navIndicator 0.3s ease forwards;
        }
        @keyframes navIndicator {
            from { opacity: 0; transform: scaleX(0); }
            to { opacity: 1; transform: scaleX(1); }
        }
        .bottom-nav-item:active:not(.active) { transform: scale(0.90); }

        /* ═══════════════════════════════════════════════════════════
           PAGE ANIMATION
           ═══════════════════════════════════════════════════════════ */
        .page-content { animation: pageIn 0.3s ease; }
        @keyframes pageIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ═══════════════════════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════════════════════ */
        @media (min-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
            }
            .product-image img { height: 210px; }
        }
        @media (min-width: 1024px) {
            .products-grid {
                grid-template-columns: repeat(4, 1fr);
                max-width: 1200px;
                margin: 0 auto;
            }
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
    </style>
</head>
<body>
    <div class="page-content">

        <!-- ═══════════════════════════════════════════════════
        HEADER — Matches Index (Gradient Pink)
        ═══════════════════════════════════════════════════ -->
        <header class="header">
            <div class="header-left">
                <a href="index.php" class="back-btn" aria-label="Back">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <form class="search-form" action="search.php" method="GET">
                    <div class="search-input-wrapper">
                        <i class="fas fa-magnifying-glass"></i>
                        <input type="text" name="q" class="search-input" placeholder="প্রোডাক্ট খুঁজুন..." value="<?php echo htmlspecialchars($query); ?>" autofocus autocomplete="off">
                    </div>
                    <button type="submit" class="search-btn" aria-label="Search">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div class="header-right">
                <a href="cart.php" class="header-icon" aria-label="Cart">
                    <i class="fas fa-bag-shopping"></i>
                    <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <?php if (!empty($query)): ?>
        <!-- ═══════════════════════════════════════════════════
        SEARCH RESULTS
        ═══════════════════════════════════════════════════ -->
        <div class="results-info">
            <i class="fas fa-search"></i>
            <span>"<?php echo htmlspecialchars($query); ?>"</span> এর জন্য <?php echo count($products); ?>টি ফলাফল
            <span class="count-badge"><?php echo count($products); ?>টি</span>
        </div>

        <section class="products-section">
            <div class="products-grid">
                <?php foreach ($products as $product):
                    $discountPercent = ($product['discount_price'] > 0 && $product['price'] > 0)
                        ? round((($product['price'] - $product['discount_price']) / $product['price']) * 100)
                        : 0;
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
                    </div>
                </a>
                <?php endforeach; ?>

                <?php if (empty($products)): ?>
                <div class="empty-search">
                    <i class="fas fa-search"></i>
                    <h3>কোনো ফলাফল পাওয়া যায়নি</h3>
                    <p>অন্য কীওয়ার্ড দিয়ে আবার চেষ্টা করুন</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <?php else: ?>
        <!-- ═══════════════════════════════════════════════════
        EMPTY STATE + SUGGESTED SEARCHES
        ═══════════════════════════════════════════════════ -->
        <div class="empty-search" style="margin: 40px 16px;">
            <i class="fas fa-search"></i>
            <h3>প্রোডাক্ট খুঁজুন</h3>
            <p>আপনার পছন্দের প্রোডাক্টের নাম লিখুন</p>
        </div>

        <section class="suggested-section">
            <div class="suggested-header">
                <i class="fas fa-fire"></i> জনপ্রিয় সার্চ
            </div>
            <div class="suggested-tags">
                <a href="search.php?q=পাঞ্জাবি" class="suggested-tag"><i class="fas fa-male"></i> পাঞ্জাবি</a>
                <a href="search.php?q=কটন" class="suggested-tag"><i class="fas fa-tshirt"></i> কটন</a>
                <a href="search.php?q=প্রিমিয়াম" class="suggested-tag"><i class="fas fa-user-tie"></i> প্রিমিয়াম</a>
                <a href="search.php?q=এম্ব্রডারি" class="suggested-tag"><i class="fas fa-gem"></i> এম্ব্রডারি</a>
                <a href="search.php?q=নতুন কালেকশন" class="suggested-tag"><i class="fas fa-sparkles"></i> নতুন কালেকশন</a>
                <a href="search.php?q=ডিসকাউন্ট" class="suggested-tag"><i class="fas fa-tag"></i> ডিসকাউন্ট</a>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <!-- ═══════════════════════════════════════════════════
    BOTTOM NAV — Matches Index Exactly
    ═══════════════════════════════════════════════════ -->
    <nav class="bottom-nav">
        <a href="index.php" class="bottom-nav-item" id="bottomHomeLink">
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

    <script>
        // ── ACTIVE NAV INDICATOR ──
        (function() {
            const path = window.location.pathname;
            const navItems = document.querySelectorAll('.bottom-nav-item');
            navItems.forEach(function(item) {
                item.classList.remove('active');
                const href = item.getAttribute('href');
                if (href && (path.includes(href) || (href === 'index.php' && (path.endsWith('/') || path === '')))) {
                    item.classList.add('active');
                }
            });
        })();

        // ── SEARCH INPUT FOCUS ──
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            if (searchInput && !searchInput.value) {
                setTimeout(function() { searchInput.focus(); }, 300);
            }
        });
    </script>
</body>
</html>