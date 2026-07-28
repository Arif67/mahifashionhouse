<?php
require_once 'Config.php';

$slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';
if (empty($slug)) { header('Location: index.php'); exit; }

try {
    $product = fetchOne("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = ? AND p.status = 1", [$slug]);
    if (!$product) { header('Location: index.php'); exit; }
    $images = fetchAll("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC", [$product['id']]);
    $variants = fetchAll("SELECT * FROM product_variants WHERE product_id = ? AND stock > 0", [$product['id']]);
    $sizes = fetchAll("SELECT * FROM product_sizes WHERE product_id = ? AND stock > 0", [$product['id']]);
    if (empty($images)) { $images = [['image' => '', 'is_primary' => 1]]; }
    $related = fetchAll("SELECT p.*, (SELECT image FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image FROM products p WHERE p.category_id = ? AND p.id != ? AND p.status = 1 LIMIT 4", [$product['category_id'], $product['id']]);
} catch (Exception $e) { header('Location: index.php'); exit; }

$settings = getAllSettings();
$metaPixel = getMetaPixelCode();
$favicon = getFavicon();
$logo = getLogo();
$siteName = getSetting('site_name', 'Mahi Fashion House');
$cartCount = getCartCount();
$discountPercent = ($product['discount_price'] > 0 && $product['price'] > 0) ? round((($product['price'] - $product['discount_price']) / $product['price']) * 100) : 0;
$shareImage = !empty($images[0]['image']) ? 'uploads/products/' . $images[0]['image'] : '';

// ব্লক পপআপের WhatsApp মেসেজ
$waBlockMessage = urlencode('আমি ১ম অর্ডার করেছিলাম ২য় অর্ডার এর জন্য নক করছি অনুগ্রহ করে আমাকে সাহায্য করুন।');

// ─── Meta Conversions API: PageView + ViewContent (Browser Pixel-এর সাথে একই Event ID) ───
$pageViewEventId = generateMetaEventId('pv');
$viewContentEventId = generateMetaEventId('vc');
$productValueForPixel = $product['discount_price'] > 0 ? $product['discount_price'] : $product['price'];
queueMetaCapiEvent('PageView', $pageViewEventId, [], [], getCurrentUrl());
queueMetaCapiEvent('ViewContent', $viewContentEventId, [
    'value' => (float)$productValueForPixel,
    'currency' => 'BDT',
    'content_ids' => [(string)$product['id']],
    'content_type' => 'product',
    'content_name' => $product['title'],
    'contents' => [[
        'id' => (string)$product['id'],
        'quantity' => 1,
        'item_price' => (float)$productValueForPixel,
    ]],
], [], getCurrentUrl());
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="description" content="<?php echo strip_tags($product['description']); ?>">
<meta property="og:title" content="<?php echo $product['title']; ?> - <?php echo $siteName; ?>">
<meta property="og:description" content="<?php echo strip_tags($product['description']); ?>">
<?php if ($shareImage): ?><meta property="og:image" content="<?php echo $shareImage; ?>"><?php endif; ?>
<meta property="og:type" content="product">
<meta name="theme-color" content="#FCE4EC">
<title><?php echo $product['title']; ?> - <?php echo $siteName; ?></title>
<?php if ($favicon): ?><link rel="icon" type="image/x-icon" href="<?php echo $favicon; ?>"><?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<?php if ($metaPixel): ?>
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
fbq('track', 'ViewContent', {
    content_ids: ['<?php echo $product['id']; ?>'],
    content_type: 'product',
    content_name: '<?php echo addslashes($product['title']); ?>',
    value: <?php echo $product['discount_price'] > 0 ? $product['discount_price'] : $product['price']; ?>,
    currency: 'BDT'
}, {eventID: '<?php echo $viewContentEventId; ?>'});
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo $metaPixel; ?>&ev=PageView&noscript=1"/></noscript>
<?php endif; ?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
body { font-family: 'Hind Siliguri', 'Kalpurush', sans-serif; background: #FFFFFF; color: #1A1A2E; overflow-x: hidden; -webkit-font-smoothing: antialiased; padding-bottom: calc(88px + env(safe-area-inset-bottom)); overscroll-behavior-y: none; }
a { text-decoration: none; color: inherit; }
img { max-width: 100%; display: block; }

.header { background: linear-gradient(135deg, #ED0763 0%, #C2185B 50%, #880E4F 100%); padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 20px rgba(237, 7, 99, 0.25), 0 1px 3px rgba(0,0,0,0.1); border-bottom: 1px solid rgba(255, 255, 255, 0.15); }
.header-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.back-btn { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 12px; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); color: #FFFFFF; font-size: 16px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; overflow: hidden; position: relative; flex-shrink: 0; }
.back-btn::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0)); opacity: 0; transition: opacity 0.3s ease; }
.back-btn:hover { background: rgba(255, 255, 255, 0.22); transform: translateY(-2px) scale(1.05); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.3); }
.back-btn:hover::before { opacity: 1; }
.back-btn:active { transform: scale(0.92); }
.back-btn i { position: relative; z-index: 1; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1)); }
.page-title { font-size: 15px; font-weight: 700; color: #FFFFFF; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15); letter-spacing: 0.3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.header-right { display: flex; align-items: center; gap: 8px; }
.header-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 16px; color: #FFFFFF; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); position: relative; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; }
.header-icon::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0)); opacity: 0; transition: opacity 0.3s ease; }
.header-icon:hover { background: rgba(255, 255, 255, 0.22); transform: translateY(-2px) scale(1.05); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.3); }
.header-icon:hover::before { opacity: 1; }
.header-icon:active { transform: scale(0.92); }
.header-icon i { position: relative; z-index: 1; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1)); }
.cart-badge { position: absolute; top: -4px; right: -4px; background: linear-gradient(135deg, #EF4444, #DC2626); color: #fff; font-size: 9px; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; box-shadow: 0 3px 8px rgba(239, 68, 68, 0.45), 0 0 0 2px rgba(237, 7, 99, 0.3); border: 2px solid #fff; animation: badgePop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 2; }
@keyframes badgePop { 0% { transform: scale(0); } 80% { transform: scale(1.2); } 100% { transform: scale(1); } }

/* GALLERY */
.gallery-section { background: #FFFFFF; position: relative; }
.main-image-container { position: relative; overflow: hidden; }
.main-image { width: 100%; height: 340px; object-fit: cover; cursor: zoom-in; transition: transform 0.3s ease; }
.main-image.zoomed { transform: scale(2); cursor: zoom-out; }
.image-counter { position: absolute; bottom: 12px; right: 12px; background: rgba(26, 26, 46, 0.7); color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; backdrop-filter: blur(4px); }
.discount-badge-large { position: absolute; top: 12px; left: 12px; background: linear-gradient(135deg, #EF4444, #DC2626); color: #fff; font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: 20px; box-shadow: 0 3px 10px rgba(239, 68, 68, 0.30); z-index: 2; }
.thumbnails-row { display: flex; gap: 8px; padding: 12px 16px; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
.thumbnails-row::-webkit-scrollbar { display: none; }
.thumb-item { min-width: 60px; height: 60px; border-radius: 10px; overflow: hidden; border: 2px solid transparent; cursor: pointer; transition: all 0.3s ease; background: #FDF2F8; }
.thumb-item.active { border-color: #EC407A; box-shadow: 0 3px 10px rgba(236, 64, 122, 0.20); }
.thumb-item img { width: 60px; height: 60px; object-fit: cover; }

/* PRODUCT INFO */
.product-info-section { background: #FFFFFF; padding: 16px; margin-top: 8px; border-bottom: 1px solid #FBCFE8; }
.product-name { font-size: 17px; font-weight: 700; color: #1A1A2E; line-height: 1.4; margin-bottom: 10px; }
.product-meta { display: flex; align-items: center; gap: 15px; margin-bottom: 12px; }
.rating-large { display: flex; align-items: center; gap: 3px; color: #F59E0B; font-size: 13px; }
.rating-large span { color: #6B7280; margin-left: 4px; font-weight: 500; }
.stock-status { font-size: 12px; color: #22C55E; font-weight: 600; }
.stock-status.low { color: #EF4444; }
.price-section { display: flex; align-items: baseline; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.current-price { font-size: 24px; font-weight: 700; color: #EC407A; }
.old-price { font-size: 16px; color: #9CA3AF; text-decoration: line-through; font-weight: 500; }
.savings { font-size: 12px; color: #22C55E; font-weight: 600; background: rgba(34, 197, 94, 0.08); padding: 3px 10px; border-radius: 20px; }

/* VARIANTS */
.variant-section, .size-section { margin-bottom: 16px; }
.variant-label { font-size: 13px; font-weight: 600; color: #1A1A2E; margin-bottom: 10px; display: block; }
.variant-label i { color: #EC407A; margin-right: 5px; }
.variant-options, .size-options { display: flex; flex-wrap: wrap; gap: 8px; }
.variant-btn, .size-btn { padding: 10px 16px; border: 1.5px solid #FBCFE8; border-radius: 12px; background: #FFFFFF; font-size: 13px; font-family: 'Hind Siliguri', sans-serif; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); color: #1A1A2E; font-weight: 500; }
.variant-btn:hover, .size-btn:hover { border-color: #EC407A; background: #FDF2F8; }
.variant-btn.selected, .size-btn.selected { border-color: #EC407A; background: linear-gradient(135deg, #EC407A, #D81B60); color: #fff; box-shadow: 0 4px 12px rgba(236, 64, 122, 0.25); }
.variant-btn small, .size-btn small { display: block; font-size: 10px; opacity: 0.8; margin-top: 2px; font-weight: 400; }

/* QUANTITY */
.quantity-section { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding: 14px 0; border-top: 1px solid #FDF2F8; border-bottom: 1px solid #FDF2F8; }
.quantity-label { font-size: 13px; font-weight: 600; color: #1A1A2E; }
.quantity-control { display: flex; align-items: center; gap: 0; border: 1.5px solid #FBCFE8; border-radius: 12px; overflow: hidden; }
.qty-btn { width: 38px; height: 38px; border: none; background: #FDF2F8; font-size: 16px; color: #EC407A; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; font-weight: 600; }
.qty-btn:hover { background: #FBCFE8; }
.qty-btn:active { transform: scale(0.9); }
.qty-input { width: 48px; height: 38px; border: none; text-align: center; font-size: 15px; font-weight: 700; font-family: 'Hind Siliguri', sans-serif; color: #1A1A2E; background: #FFFFFF; -moz-appearance: textfield; }
.qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.available-stock { font-size: 12px; color: #6B7280; margin-left: auto; font-weight: 500; }

/* ACTION BUTTONS */
.action-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.btn { padding: 14px 16px; border: none; border-radius: 14px; font-size: 14px; font-weight: 700; font-family: 'Hind Siliguri', sans-serif; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; gap: 8px; letter-spacing: 0.2px; }
.btn:active { transform: scale(0.96); }
.btn-cart { background: #FFFFFF; color: #1A1A2E; border: 2px solid #1A1A2E; }
.btn-cart:hover { background: #1A1A2E; color: #FFFFFF; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(26, 26, 46, 0.15); }
.btn-order { background: linear-gradient(135deg, #1A1A2E, #2D2D44); color: #fff; box-shadow: 0 4px 12px rgba(26, 26, 46, 0.20); }
.btn-order:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(26, 26, 46, 0.30); }

/* SHARE */
.share-section { padding: 16px; background: #FFFFFF; margin-top: 8px; border-bottom: 1px solid #FBCFE8; }
.share-title { font-size: 13px; font-weight: 600; color: #1A1A2E; margin-bottom: 12px; }
.share-buttons { display: flex; gap: 10px; }
.share-btn { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #fff; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; border: none; box-shadow: 0 3px 10px rgba(0,0,0,0.08); }
.share-btn:hover { transform: translateY(-3px) scale(1.05); }
.share-btn:active { transform: scale(0.92); }
.share-fb { background: linear-gradient(135deg, #1877f2, #0d65d9); }
.share-tw { background: linear-gradient(135deg, #1da1f2, #0d8ecf); }
.share-wa { background: linear-gradient(135deg, #25d366, #128C7E); }
.share-link { background: linear-gradient(135deg, #6B7280, #4B5563); }

/* DESCRIPTION */
.description-section { background: linear-gradient(180deg, #FFFFFF 0%, #FDF2F8 100%); padding: 24px 16px; margin-top: 12px; border-bottom: 1px solid #FBCFE8; position: relative; overflow: hidden; }
.description-section::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #EC407A, #D81B60, #EC407A); background-size: 200% 100%; animation: gradientFlow 3s ease infinite; }
@keyframes gradientFlow { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
.desc-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #FBCFE8; position: relative; }
.desc-header::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 80px; height: 2px; background: linear-gradient(90deg, #EC407A, #D81B60); border-radius: 2px; }
.desc-icon { width: 40px; height: 40px; background: linear-gradient(135deg, #EC407A, #D81B60); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; box-shadow: 0 4px 15px rgba(236, 64, 122, 0.3); flex-shrink: 0; }
.desc-title-text { font-size: 17px; font-weight: 700; color: #1A1A2E; letter-spacing: 0.3px; }
.desc-subtitle { font-size: 12px; color: #6B7280; margin-top: 2px; font-weight: 500; }
.desc-content { font-size: 14px; line-height: 1.9; color: #374151; background: #FFFFFF; padding: 20px; border-radius: 16px; border: 1px solid #FBCFE8; box-shadow: 0 2px 12px rgba(236, 64, 122, 0.06); }
.desc-content p { margin-bottom: 14px; }
.desc-content p:last-child { margin-bottom: 0; }
.desc-content ul { padding-left: 0; margin-bottom: 14px; list-style: none; }
.desc-content ul li { margin-bottom: 10px; position: relative; padding-left: 28px; font-weight: 500; }
.desc-content ul li::before { content: '\f058'; font-family: 'Font Awesome 6 Free'; font-weight: 400; position: absolute; left: 0; top: 2px; color: #EC407A; font-size: 16px; }
.desc-content ul li:last-child { margin-bottom: 0; }
.desc-highlight-box { background: linear-gradient(135deg, #FDF2F8, #FCE4EC); border-left: 4px solid #EC407A; padding: 16px; border-radius: 12px; margin-top: 16px; display: flex; align-items: flex-start; gap: 12px; }
.desc-highlight-box i { color: #EC407A; font-size: 20px; margin-top: 2px; flex-shrink: 0; }
.desc-highlight-box p { margin: 0 !important; font-size: 13px; color: #4B5563; line-height: 1.7; }

/* RELATED PRODUCTS */
.related-section { margin-top: 24px; padding: 0 16px 28px; position: relative; }
.related-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #FBCFE8; position: relative; }
.related-header::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 80px; height: 2px; background: linear-gradient(90deg, #EC407A, #D81B60); border-radius: 2px; }
.related-icon { width: 40px; height: 40px; background: linear-gradient(135deg, #EC407A, #D81B60); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; box-shadow: 0 4px 15px rgba(236, 64, 122, 0.3); flex-shrink: 0; }
.related-title-text { font-size: 17px; font-weight: 700; color: #1A1A2E; letter-spacing: 0.3px; }
.related-count { font-size: 12px; color: #6B7280; margin-top: 2px; font-weight: 500; }
.products-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.product-card { background: #FFFFFF; border-radius: 20px; overflow: hidden; border: 1px solid #FBCFE8; box-shadow: 0 4px 16px rgba(236, 64, 122, 0.06); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); animation: fadeInUp 0.6s ease forwards; opacity: 0; position: relative; cursor: pointer; }
.product-card:nth-child(1) { animation-delay: 0.05s; }
.product-card:nth-child(2) { animation-delay: 0.1s; }
.product-card:nth-child(3) { animation-delay: 0.15s; }
.product-card:nth-child(4) { animation-delay: 0.2s; }
@keyframes fadeInUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.product-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(236, 64, 122, 0.15); border-color: #EC407A; }
.product-card:active { transform: scale(0.97); }
.product-image { position: relative; overflow: hidden; background: #FDF2F8; aspect-ratio: 1 / 1; }
.product-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
.product-card:hover .product-image img { transform: scale(1.1); }
.discount-badge { position: absolute; top: 10px; left: 10px; background: linear-gradient(135deg, #EF4444, #DC2626); color: #fff; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 20px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35); z-index: 2; letter-spacing: 0.5px; }
.product-info { padding: 14px; position: relative; }
.product-title { font-size: 13px; font-weight: 600; color: #1A1A2E; line-height: 1.5; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 40px; }
.product-meta-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.product-prices { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.product-price { font-size: 15px; font-weight: 700; color: #EC407A; }
.product-old-price { font-size: 12px; color: #9CA3AF; text-decoration: line-through; font-weight: 500; }
.product-rating { display: flex; align-items: center; gap: 3px; color: #F59E0B; font-size: 11px; }
.product-rating span { color: #6B7280; font-size: 10px; font-weight: 600; }
.product-view-btn { position: absolute; bottom: 14px; right: 14px; width: 32px; height: 32px; background: linear-gradient(135deg, #EC407A, #D81B60); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; opacity: 0; transform: scale(0.8); transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(236, 64, 122, 0.3); }
.product-card:hover .product-view-btn { opacity: 1; transform: scale(1); }

/* FOOTER */
.footer-section { margin-top: 36px; background: #FFFFFF; padding: 28px 16px; border-top: 1px solid #FBCFE8; }
.benefit-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; background: #FDF2F8; border-radius: 12px; border: 1px solid #FBCFE8; box-shadow: 0 4px 12px rgba(236, 64, 122, 0.06); animation: slideInRight 0.5s ease forwards; opacity: 0; transition: all 0.3s ease; margin-bottom: 10px; }
.benefit-item:last-child { margin-bottom: 0; }
@keyframes slideInRight { from { transform: translateX(30px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.benefit-item:active { transform: scale(0.98); }
.benefit-item i { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, rgba(236, 64, 122, 0.08), rgba(236, 64, 122, 0.02)); color: #EC407A; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; border: 1px solid rgba(236, 64, 122, 0.08); }
.benefit-item p { font-size: 13px; color: #1A1A2E; line-height: 1.5; font-weight: 600; flex: 1; }
.verify-icon { color: #22C55E; font-size: 16px; margin-left: 4px; animation: verifyPulse 2s infinite; }
@keyframes verifyPulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.15); opacity: 0.85; } }
.footer-divider { height: 1px; background: linear-gradient(90deg, transparent, #FBCFE8, transparent); margin: 20px 0; }
.footer-social { display: flex; justify-content: center; gap: 14px; margin-bottom: 20px; }
.social-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
.social-icon:active { transform: scale(0.90); }
.social-facebook { background: linear-gradient(135deg, #1877f2, #0d65d9); }
.social-whatsapp { background: linear-gradient(135deg, #25d366, #128C7E); }
.social-phone { background: linear-gradient(135deg, #EC407A, #D81B60); }
.footer-contact { text-align: center; padding: 8px 0; }
.footer-contact a { display: inline-flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 700; color: #EC407A; padding: 12px 28px; border: 2px solid #EC407A; border-radius: 9999px; transition: all 0.3s ease; background: #FFFFFF; box-shadow: 0 4px 12px rgba(236, 64, 122, 0.06); }
.footer-contact a:hover { background: linear-gradient(135deg, #EC407A, #D81B60); color: #fff; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(236, 64, 122, 0.10); }
.footer-copyright { text-align: center; font-size: clamp(12px, 2.2vw, 14px); color: #6B7280; margin-top: 20px; padding-top: 16px; border-top: 1px solid #FBCFE8; font-weight: 500; }
.developer-credit { text-align: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid #FBCFE8; }
.developer-credit p { font-size: clamp(13px, 2.5vw, 16px); color: #6B7280; font-weight: 500; }
.developer-credit a { color: #FFD700; font-weight: 700; text-decoration: none; position: relative; display: inline-block; transition: all 0.3s ease; font-size: clamp(14px, 2.8vw, 18px); }
.developer-credit a::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 0; height: 2px; background: linear-gradient(90deg, #FFD700, #FFA500); transition: width 0.3s ease; }
.developer-credit a:hover { color: #FFA500; text-shadow: 0 0 8px rgba(255, 215, 0, 0.4); }
.developer-credit a:hover::after { width: 100%; }
.developer-credit .heart-beat { display: inline-block; color: #EC407A; animation: heartbeat 1.2s ease-in-out infinite; }
@keyframes heartbeat { 0%, 100% { transform: scale(1); } 14% { transform: scale(1.15); } 28% { transform: scale(1); } 42% { transform: scale(1.15); } 70% { transform: scale(1); } }

/* TOAST */
.toast { position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%) translateY(100px); background: #1A1A2E; color: #fff; padding: 14px 24px; border-radius: 12px; font-size: 13px; z-index: 3000; opacity: 0; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); white-space: nowrap; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
.toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
.toast.error { background: linear-gradient(135deg, #EF4444, #DC2626); }
.toast.success { background: linear-gradient(135deg, #22C55E, #16A34A); }

/* WHATSAPP FLOAT */
.whatsapp-float { position: fixed; bottom: 100px; right: 18px; z-index: 999; animation: whatsappPulse 2s infinite, slideInWhatsApp 0.5s ease; }
@keyframes slideInWhatsApp { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes whatsappPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.45); border-radius: 50%; } 50% { box-shadow: 0 0 0 16px rgba(34, 197, 94, 0); border-radius: 50%; } }
.whatsapp-float a { display: flex; align-items: center; justify-content: center; width: 56px; height: 56px; background: linear-gradient(135deg, #22C55E, #16A34A); border-radius: 50%; color: #fff; font-size: 28px; box-shadow: 0 6px 20px rgba(34, 197, 94, 0.40); transition: transform 0.3s ease; }
.whatsapp-float a:active { transform: scale(0.92); }

/* BOTTOM NAV */
.bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: #FFFFFF; backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); display: flex; justify-content: space-around; align-items: center; padding: 8px 0 calc(12px + env(safe-area-inset-bottom)); box-shadow: 0 -4px 20px rgba(236, 64, 122, 0.08), 0 -1px 0 rgba(0, 0, 0, 0.04), 0 -8px 24px rgba(0, 0, 0, 0.06); z-index: 1000; border-radius: 24px 24px 0 0; border-top: 1.5px solid #FBCFE8; }
.bottom-nav-item { display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 6px 18px; border-radius: 12px; transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; position: relative; }
.bottom-nav-item i { font-size: 20px; color: #6B7280; transition: all 0.3s ease; }
.bottom-nav-item span { font-size: 10px; color: #6B7280; font-weight: 600; transition: all 0.3s ease; }
.bottom-nav-item.active { background: linear-gradient(135deg, #EC407A, #D81B60); box-shadow: 0 6px 20px rgba(236, 64, 122, 0.30); transform: translateY(-6px); }
.bottom-nav-item.active i, .bottom-nav-item.active span { color: #fff; }
.bottom-nav-item.active::after { content: ''; position: absolute; bottom: -4px; width: 20px; height: 4px; background: linear-gradient(90deg, #F59E0B, #EF4444); border-radius: 2px; animation: navIndicator 0.3s ease forwards; }
@keyframes navIndicator { from { opacity: 0; transform: scaleX(0); } to { opacity: 1; transform: scaleX(1); } }
.bottom-nav-item:active:not(.active) { transform: scale(0.90); }

/* ─── ২৪ ঘণ্টা ফেক অর্ডার ব্লক পপআপ ─── */
.block-overlay { position: fixed; inset: 0; background: rgba(26, 26, 46, 0.6); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 5000; padding: 20px; opacity: 0; transition: opacity 0.25s ease; }
.block-overlay.show { display: flex; opacity: 1; }
.block-modal { background: #FFFFFF; border-radius: 20px; padding: 28px 22px 24px; width: 100%; max-width: 340px; text-align: center; box-shadow: 0 24px 60px rgba(26, 26, 46, 0.35); position: relative; transform: scale(0.85) translateY(20px); transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); border: 1px solid #FBCFE8; }
.block-overlay.show .block-modal { transform: scale(1) translateY(0); }
.block-close { position: absolute; top: 12px; right: 12px; width: 30px; height: 30px; border-radius: 10px; background: #FDF2F8; border: 1px solid #FBCFE8; color: #EC407A; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
.block-close:hover { background: #FBCFE8; }
.block-icon { width: 58px; height: 58px; background: linear-gradient(135deg, #EC407A, #D81B60); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 8px 20px rgba(236, 64, 122, 0.35); animation: blockPulse 2s infinite; }
@keyframes blockPulse { 0%, 100% { box-shadow: 0 8px 20px rgba(236, 64, 122, 0.35); } 50% { box-shadow: 0 8px 20px rgba(236, 64, 122, 0.35), 0 0 0 12px rgba(236, 64, 122, 0.08); } }
.block-icon i { font-size: 22px; color: #fff; }
.block-title { font-size: 17px; font-weight: 700; color: #1A1A2E; margin-bottom: 10px; line-height: 1.4; }
.block-text { font-size: 13px; color: #6B7280; line-height: 1.8; margin-bottom: 20px; }
.btn-whatsapp-block { display: flex; align-items: center; justify-content: center; gap: 9px; width: 100%; padding: 13px; background: linear-gradient(135deg, #25D366, #128C7E); color: #fff; border: none; border-radius: 12px; font-size: 14px; font-weight: 700; font-family: 'Hind Siliguri', sans-serif; cursor: pointer; box-shadow: 0 6px 16px rgba(37, 211, 102, 0.35); transition: all 0.25s ease; }
.btn-whatsapp-block:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(37, 211, 102, 0.45); }
.btn-whatsapp-block:active { transform: scale(0.97); }
.btn-whatsapp-block i { font-size: 19px; }
.block-note { margin-top: 12px; font-size: 11px; color: #9CA3AF; }

@supports (-webkit-touch-callout: none) { body { padding-bottom: calc(100px + env(safe-area-inset-bottom)); } .bottom-nav { padding-bottom: calc(18px + env(safe-area-inset-bottom)); } }
.page-content { animation: pageIn 0.3s ease; }
@keyframes pageIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

@media (min-width: 768px) { .main-image { height: 420px; } .products-grid { grid-template-columns: repeat(4, 1fr); gap: 18px; } .product-image { aspect-ratio: 3 / 4; } }

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

<!-- HEADER -->
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

<!-- GALLERY -->
<section class="gallery-section">
    <div class="main-image-container">
        <?php if ($discountPercent > 0): ?><span class="discount-badge-large">-<?php echo $discountPercent; ?>% ছাড়</span><?php endif; ?>
        <?php if (!empty($images[0]['image'])): ?>
        <img src="uploads/products/<?php echo $images[0]['image']; ?>" alt="<?php echo $product['title']; ?>" class="main-image" id="mainImage" onclick="this.classList.toggle('zoomed')">
        <?php else: ?>
        <div style="width:100%;height:340px;background:#FDF2F8;display:flex;align-items:center;justify-content:center;"><i class="fas fa-tshirt" style="font-size:64px;color:#F9A8D4;"></i></div>
        <?php endif; ?>
        <span class="image-counter" id="imageCounter">1 / <?php echo count($images); ?></span>
    </div>
    <?php if (count($images) > 1): ?>
    <div class="thumbnails-row">
        <?php foreach ($images as $idx => $img): ?>
        <?php if (!empty($img['image'])): ?>
        <div class="thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="changeImage(this, '<?php echo $img['image']; ?>', <?php echo $idx + 1; ?>)"><img src="uploads/products/<?php echo $img['image']; ?>" alt=""></div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- PRODUCT INFO -->
<section class="product-info-section">
    <h1 class="product-name"><?php echo $product['title']; ?></h1>
    <div class="product-meta">
        <div class="rating-large">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php if ($i <= floor($product['rating'])): ?><i class="fas fa-star"></i>
                <?php elseif ($i - 0.5 <= $product['rating']): ?><i class="fas fa-star-half-alt"></i>
                <?php else: ?><i class="far fa-star"></i><?php endif; ?>
            <?php endfor; ?>
            <span>(<?php echo $product['rating']; ?>)</span>
        </div>
        <span class="stock-status <?php echo $product['stock'] < 10 ? 'low' : ''; ?>"><i class="fas fa-check-circle" style="margin-right:4px;"></i><?php echo $product['stock']; ?>টি স্টকে আছে</span>
    </div>
    <div class="price-section">
        <span class="current-price" id="currentPrice"><?php echo $product['discount_price'] > 0 ? formatPrice($product['discount_price']) : formatPrice($product['price']); ?></span>
        <?php if ($product['discount_price'] > 0): ?><span class="old-price" id="oldPrice"><?php echo formatPrice($product['price']); ?></span><?php endif; ?>
        <?php if ($discountPercent > 0): ?><span class="savings"><?php echo $discountPercent; ?>% সাশ্রয়</span><?php endif; ?>
    </div>

    <?php if (!empty($variants)): ?>
    <div class="variant-section">
        <span class="variant-label"><i class="fas fa-palette"></i>কালার নির্বাচন করুন</span>
        <div class="variant-options">
            <?php foreach ($variants as $v): ?>
            <button type="button" class="variant-btn" data-price="<?php echo $v['price']; ?>" data-name="<?php echo $v['variant_name']; ?>" onclick="selectVariant(this)"><?php echo $v['variant_name']; ?><small><?php echo formatPrice($v['price']); ?></small></button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="size-section">
        <span class="variant-label"><i class="fas fa-ruler"></i>সাইজ নির্বাচন করুন *</span>
        <div class="size-options">
            <?php foreach ($sizes as $s): ?>
            <button type="button" class="size-btn" data-size="<?php echo $s['size']; ?>" data-add-price="<?php echo $s['additional_price']; ?>" onclick="selectSize(this)"><?php echo $s['size']; ?><?php if ($s['additional_price'] > 0): ?><small>+<?php echo formatPrice($s['additional_price']); ?></small><?php endif; ?></button>
            <?php endforeach; ?>
            <?php if (empty($sizes)): ?>
            <button type="button" class="size-btn" data-size="S" onclick="selectSize(this)">S</button>
            <button type="button" class="size-btn" data-size="M" onclick="selectSize(this)">M</button>
            <button type="button" class="size-btn" data-size="L" onclick="selectSize(this)">L</button>
            <button type="button" class="size-btn" data-size="XL" onclick="selectSize(this)">XL</button>
            <button type="button" class="size-btn" data-size="XXL" onclick="selectSize(this)">XXL</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="quantity-section">
        <span class="quantity-label">পরিমাণ</span>
        <div class="quantity-control">
            <button type="button" class="qty-btn" onclick="changeQty(-1)">-</button>
            <input type="number" class="qty-input" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" readonly>
            <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
        </div>
        <span class="available-stock">স্টক: <?php echo $product['stock']; ?>টি</span>
    </div>

    <div class="action-buttons">
        <button type="button" class="btn btn-cart" id="btnCart" onclick="addToCart()"><i class="fas fa-cart-shopping"></i>কার্টে যোগ</button>
        <button type="button" class="btn btn-order" id="btnOrder" onclick="orderNow()"><i class="fas fa-bolt"></i>অর্ডার করুন</button>
    </div>
</section>

<!-- SHARE -->
<section class="share-section">
    <div class="share-title">শেয়ার করুন</div>
    <div class="share-buttons">
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="share-btn share-fb"><i class="fab fa-facebook-f"></i></a>
        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($product['title']); ?>" target="_blank" class="share-btn share-tw"><i class="fab fa-twitter"></i></a>
        <a href="https://wa.me/?text=<?php echo urlencode($product['title'] . ' - https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="share-btn share-wa"><i class="fab fa-whatsapp"></i></a>
        <button type="button" class="share-btn share-link" onclick="copyLink()"><i class="fas fa-link"></i></button>
    </div>
</section>

<!-- DESCRIPTION -->
<section class="description-section">
    <div class="desc-header">
        <div class="desc-icon"><i class="fas fa-align-left"></i></div>
        <div><div class="desc-title-text">বিবরণ</div><div class="desc-subtitle">প্রোডাক্টের বিস্তারিত তথ্য</div></div>
    </div>
    <div class="desc-content">
        <?php if (!empty($product['description'])): ?><?php echo $product['description']; ?><?php else: ?><p>এই প্রোডাক্টের কোনো বিবরণ পাওয়া যায়নি।</p><?php endif; ?>
    </div>
</section>

<!-- RELATED PRODUCTS -->
<?php if (!empty($related)): ?>
<section class="related-section">
    <div class="related-header">
        <div class="related-icon"><i class="fas fa-layer-group"></i></div>
        <div><div class="related-title-text">সম্পর্কিত প্রোডাক্ট</div><div class="related-count"><?php echo count($related); ?>টি প্রোডাক্ট পাওয়া গেছে</div></div>
    </div>
    <div class="products-grid">
        <?php foreach ($related as $r): 
            $rDiscount = ($r['discount_price'] > 0 && $r['price'] > 0) ? round((($r['price'] - $r['discount_price']) / $r['price']) * 100) : 0;
            $rRating = isset($r['rating']) ? $r['rating'] : rand(40, 50) / 10;
        ?>
        <a href="product.php?slug=<?php echo $r['slug']; ?>" class="product-card">
            <div class="product-image">
                <?php if ($rDiscount > 0): ?><span class="discount-badge">-<?php echo $rDiscount; ?>% ছাড়</span><?php endif; ?>
                <?php if (!empty($r['primary_image'])): ?><img src="uploads/products/<?php echo $r['primary_image']; ?>" alt="<?php echo $r['title']; ?>" loading="lazy"><?php else: ?><div style="width:100%;height:100%;background:#FDF2F8;display:flex;align-items:center;justify-content:center;"><i class="fas fa-tshirt" style="font-size:40px;color:#F9A8D4;"></i></div><?php endif; ?>
                <div class="product-view-btn"><i class="fas fa-arrow-right"></i></div>
            </div>
            <div class="product-info">
                <h3 class="product-title"><?php echo $r['title']; ?></h3>
                <div class="product-meta-row">
                    <div class="product-prices">
                        <span class="product-price"><?php echo $r['discount_price'] > 0 ? formatPrice($r['discount_price']) : formatPrice($r['price']); ?></span>
                        <?php if ($r['discount_price'] > 0): ?><span class="product-old-price"><?php echo formatPrice($r['price']); ?></span><?php endif; ?>
                    </div>
                    <div class="product-rating"><i class="fas fa-star"></i><span><?php echo number_format($rRating, 1); ?></span></div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- FOOTER -->
<footer class="footer-section">
    <div class="benefit-item"><i class="fas fa-check-circle verify-icon"></i><p>সাইজ বা অন্য যেকোনো সমস্যায় পাচ্ছেন ৩ দিনের মধ্যে এক্সচেঞ্জ সুবিধা</p></div>
    <div class="benefit-item"><i class="fas fa-check-circle verify-icon"></i><p>কোনো কারণে পণ্য রিটার্ন করতে চাইলে, শুধুমাত্র কুরিয়ার চার্জ প্রযোজ্য হবে!</p></div>
    <div class="footer-divider"></div>
    <div class="footer-social">
        <a href="<?php echo getFacebookLink(); ?>" target="_blank" class="social-icon social-facebook" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="https://wa.me/<?php echo getWhatsAppNumber(); ?>" target="_blank" class="social-icon social-whatsapp" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
        <a href="tel:<?php echo getPhoneNumber(); ?>" class="social-icon social-phone" aria-label="Phone"><i class="fas fa-phone"></i></a>
    </div>
    <div class="footer-contact">
        <a href="tel:<?php echo getPhoneNumber(); ?>"><i class="fas fa-headset"></i><span><?php echo getPhoneNumber(); ?> — যোগাযোগ করুন</span></a>
    </div>
    <div class="footer-copyright">&copy; <?php echo date('Y'); ?> <?php echo $siteName; ?>. সর্বস্বত্ব সংরক্ষিত।</div>
    <div class="developer-credit"><p>Developed with <span class="heart-beat">❤️</span> by <a href="https://ah-nayon.github.io/web/" target="_blank" rel="noopener noreferrer">AHN</a></p></div>
</footer>
</div><!-- /page-content -->

<!-- WHATSAPP FLOAT -->
<div class="whatsapp-float">
    <a href="https://wa.me/<?php echo getWhatsAppNumber(); ?>?text=<?php echo urlencode('আমি এই প্রোডাক্টটি অর্ডার করতে চাই: ' . $product['title']); ?>" target="_blank"><i class="fab fa-whatsapp"></i></a>
</div>

<!-- ─── ২৪ ঘণ্টা ফেক অর্ডার ব্লক পপআপ ─── -->
<div class="block-overlay" id="blockOverlay" onclick="if(event.target===this)closeBlockPopup()">
    <div class="block-modal">
        <button class="block-close" onclick="closeBlockPopup()"><i class="fas fa-times"></i></button>
        <div class="block-icon"><i class="fas fa-bell"></i></div>
        <div class="block-title">প্রথম অর্ডার কনফার্ম হয়েছে</div>
        <p class="block-text">
            পরবর্তী অর্ডারের জন্য আমাদের কল করুন<br>অথবা হোয়াটসঅ্যাপে যোগাযোগ করুন।
        </p>
        <a href="https://wa.me/<?php echo getWhatsAppNumber(); ?>?text=<?php echo $waBlockMessage; ?>" target="_blank" class="btn-whatsapp-block">
            <i class="fab fa-whatsapp"></i>
            হোয়াটসঅ্যাপে মেসেজ করুন
        </a>
        <p class="block-note">ক্লিক করলে সরাসরি আমাদের হোয়াটসঅ্যাপ ইনবক্সে নিয়ে যাওয়া হবে</p>
    </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
    <a href="index.php" class="bottom-nav-item"><i class="fas fa-house"></i><span>হোম</span></a>
    <a href="categories.php" class="bottom-nav-item"><i class="fas fa-border-all"></i><span>ক্যাটাগরি</span></a>
    <a href="cart.php" class="bottom-nav-item"><i class="fas fa-bag-shopping"></i><span>কার্ট</span></a>
</nav>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
let selectedSize = '', selectedVariant = '';
let basePrice = <?php echo $product['discount_price'] > 0 ? $product['discount_price'] : $product['price']; ?>;
let variantPrice = 0, sizeAddPrice = 0;

// ─── ডিভাইস টোকেন (২৪ ঘণ্টা ব্লকের ইউনিক আইডি) ───
function getDeviceToken() {
    try {
        let dt = localStorage.getItem('mahi_device_id');
        if (!dt) {
            dt = 'dev_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 14);
            localStorage.setItem('mahi_device_id', dt);
        }
        return dt;
    } catch (e) { return ''; }
}
// পেজ লোড হতেই টোকেন তৈরি করে রাখি
getDeviceToken();

// ─── ব্লক পপআপ ───
function showBlockPopup() {
    document.getElementById('blockOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeBlockPopup() {
    document.getElementById('blockOverlay').classList.remove('show');
    document.body.style.overflow = '';
}

// ─── সার্ভারে ২৪ ঘণ্টার ব্লক চেক ───
let blockCheckPromise = null;
function checkOrderBlocked() {
    if (!blockCheckPromise) {
        blockCheckPromise = fetch('check_order_block.php?device_token=' + encodeURIComponent(getDeviceToken()))
            .then(res => res.json())
            .then(data => data.blocked === true)
            .catch(() => false);
        // ৩০ সেকেন্ড পর ক্যাশ রিসেট (নতুন অর্ডারের পর আবার চেক হবে)
        setTimeout(() => { blockCheckPromise = null; }, 30000);
    }
    return blockCheckPromise;
}
// পেজ লোডেই ব্যাকগ্রাউন্ডে চেক — বাটনে ক্লিকে ইনস্ট্যান্ট রেজাল্ট পাওয়া যাবে
checkOrderBlocked();

function changeImage(thumb, imageName, index) {
    document.querySelectorAll('.thumb-item').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
    document.getElementById('mainImage').src = 'uploads/products/' + imageName;
    document.getElementById('imageCounter').textContent = index + ' / <?php echo count($images); ?>';
}
function selectVariant(btn) {
    document.querySelectorAll('.variant-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    selectedVariant = btn.dataset.name;
    variantPrice = parseFloat(btn.dataset.price) || 0;
    updatePrice();
}
function selectSize(btn) {
    document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    selectedSize = btn.dataset.size;
    sizeAddPrice = parseFloat(btn.dataset.addPrice) || 0;
    updatePrice();
}
function updatePrice() {
    let total = (variantPrice || basePrice) + sizeAddPrice;
    document.getElementById('currentPrice').textContent = '৳ ' + total.toLocaleString('bn-BD');
}
function changeQty(delta) {
    const input = document.getElementById('quantity');
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > <?php echo $product['stock']; ?>) val = <?php echo $product['stock']; ?>;
    input.value = val;
}
// ─── META PIXEL: AddToCart (same eventID as Conversions API for deduplication) ───
function trackAddToCart(price, qty, eventId) {
    if (typeof fbq !== 'undefined') {
        fbq('track', 'AddToCart', {
            content_ids: ['<?php echo $product['id']; ?>'],
            content_type: 'product',
            content_name: '<?php echo addslashes($product['title']); ?>',
            value: price * qty,
            currency: 'BDT',
            num_items: qty
        }, eventId ? {eventID: eventId} : undefined);
    }
}
function showToast(msg, type) {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className = 'toast ' + type + ' show';
    setTimeout(() => toast.classList.remove('show'), 3000);
}
function addToCart() {
    if (!selectedSize) { showToast('প্রথমে সাইজ নির্বাচন করুন!', 'error'); return; }
    checkOrderBlocked().then(blocked => {
        if (blocked) { showBlockPopup(); return; }
        const qty = document.getElementById('quantity').value;
        const price = (variantPrice || basePrice) + sizeAddPrice;
        const cartKey = 'mahi_cart_<?php echo $product['id']; ?>_' + selectedSize;
        if (localStorage.getItem(cartKey)) { showToast('এই সাইজটি ইতিমধ্যে কার্টে আছে!', 'error'); return; }
        const formData = new FormData();
        formData.append('product_id', '<?php echo $product['id']; ?>');
        formData.append('title', '<?php echo addslashes($product['title']); ?>');
        formData.append('image', '<?php echo $images[0]['image'] ?? ''; ?>');
        formData.append('size', selectedSize);
        formData.append('variant', selectedVariant);
        formData.append('price', price);
        formData.append('qty', qty);
        formData.append('action', 'add');
        fetch('cart.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                localStorage.setItem(cartKey, Date.now());
                trackAddToCart(price, parseInt(qty, 10), data && data.event_id);
                showToast('কার্টে যোগ হয়েছে!', 'success');
                setTimeout(() => window.location.href = 'cart.php', 500);
            })
            .catch(() => showToast('কার্টে যোগ করা যায়নি!', 'error'));
    });
}
function orderNow() {
    if (!selectedSize) { showToast('প্রথমে সাইজ নির্বাচন করুন!', 'error'); return; }
    checkOrderBlocked().then(blocked => {
        if (blocked) { showBlockPopup(); return; }
        const orderKey = 'mahi_order_<?php echo $product['id']; ?>_' + selectedSize;
        if (localStorage.getItem(orderKey)) { showToast('আপনি ইতিমধ্যে এই প্রোডাক্ট অর্ডার করেছেন!', 'error'); return; }
        const qty = document.getElementById('quantity').value;
        const price = (variantPrice || basePrice) + sizeAddPrice;
        const formData = new FormData();
        formData.append('product_id', '<?php echo $product['id']; ?>');
        formData.append('title', '<?php echo addslashes($product['title']); ?>');
        formData.append('image', '<?php echo $images[0]['image'] ?? ''; ?>');
        formData.append('size', selectedSize);
        formData.append('variant', selectedVariant);
        formData.append('price', price);
        formData.append('qty', qty);
        formData.append('action', 'add');
        fetch('cart.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            localStorage.setItem(orderKey, Date.now());
            trackAddToCart(price, parseInt(qty), data && data.event_id);
            setTimeout(() => window.location.href = 'checkout.php', 300);
        });
    });
}
function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => { showToast('লিংক কপি হয়েছে!', 'success'); });
}

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