<?php
require_once '../Config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$uploadDir = __DIR__ . '/../uploads/products/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle Add/Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $categoryId = intval($_POST['category_id'] ?? 0);
        $title = clean($_POST['title'] ?? '');
        $slug = slugify($title);
        $description = $_POST['description'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        $discountPrice = floatval($_POST['discount_price'] ?? 0);
        $stock = intval($_POST['stock'] ?? 100);
        $position = intval($_POST['position'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $sizes = $_POST['sizes'] ?? [];
        $sizePrices = $_POST['size_prices'] ?? [];
        
        if (empty($title)) {
            $error = 'প্রোডাক্ট টাইটেল দিন!';
        } else {
            try {
                if ($id > 0) {
                    query("UPDATE products SET category_id = ?, title = ?, slug = ?, description = ?, price = ?, discount_price = ?, stock = ?, position = ?, status = ? WHERE id = ?",
                        [$categoryId, $title, $slug, $description, $price, $discountPrice, $stock, $position, $status, $id]);
                    $productId = $id;
                    $message = 'প্রোডাক্ট আপডেট হয়েছে!';
                } else {
                    query("INSERT INTO products (category_id, title, slug, description, price, discount_price, stock, position, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$categoryId, $title, $slug, $description, $price, $discountPrice, $stock, $position, $status]);
                    $productId = getDB()->lastInsertId();
                    $message = 'প্রোডাক্ট যোগ হয়েছে!';
                }
                
                // Handle image uploads
                if (!empty($_FILES['images']['name'][0])) {
                    $isFirst = true;
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $ext = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                            $fileName = uniqid() . '_' . time() . '.' . $ext;
                            move_uploaded_file($tmpName, $uploadDir . $fileName);
                            query("INSERT INTO product_images (product_id, image, is_primary) VALUES (?, ?, ?)",
                                [$productId, $fileName, $isFirst ? 1 : 0]);
                            $isFirst = false;
                        }
                    }
                }
                
                // Handle sizes
                query("DELETE FROM product_sizes WHERE product_id = ?", [$productId]);
                foreach ($sizes as $idx => $size) {
                    if (!empty($size)) {
                        query("INSERT INTO product_sizes (product_id, size, additional_price) VALUES (?, ?, ?)",
                            [$productId, $size, floatval($sizePrices[$idx] ?? 0)]);
                    }
                }
                
            } catch (Exception $e) {
                $error = 'ত্রুটি: ' . $e->getMessage();
            }
        }
    }
    
    // Delete product
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $images = fetchAll("SELECT image FROM product_images WHERE product_id = ?", [$id]);
            foreach ($images as $img) {
                if (file_exists($uploadDir . $img['image'])) {
                    unlink($uploadDir . $img['image']);
                }
            }
            query("DELETE FROM products WHERE id = ?", [$id]);
            $message = 'প্রোডাক্ট মুছে ফেলা হয়েছে!';
            header('Location: products.php?deleted=1');
            exit;
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
        }
    }
    
    // Delete single image
    if ($action === 'delete_image') {
        $imgId = intval($_POST['image_id'] ?? 0);
        $img = fetchOne("SELECT image FROM product_images WHERE id = ?", [$imgId]);
        if ($img) {
            if (file_exists($uploadDir . $img['image'])) {
                unlink($uploadDir . $img['image']);
            }
            query("DELETE FROM product_images WHERE id = ?", [$imgId]);
            $message = 'ছবি মুছে ফেলা হয়েছে!';
        }
    }
}

// Fetch data
$categories = fetchAll("SELECT * FROM categories WHERE status = 1 ORDER BY sort_order ASC");
$products = fetchAll("SELECT p.*, c.name as category_name, 
    (SELECT COUNT(*) FROM product_images WHERE product_id = p.id) as image_count
    FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.position ASC, p.id DESC");

$editProduct = null;
$editImages = [];
$editSizes = [];
if (isset($_GET['edit'])) {
    $editProduct = fetchOne("SELECT * FROM products WHERE id = ?", [intval($_GET['edit'])]);
    if ($editProduct) {
        $editImages = fetchAll("SELECT * FROM product_images WHERE product_id = ?", [$editProduct['id']]);
        $editSizes = fetchAll("SELECT * FROM product_sizes WHERE product_id = ?", [$editProduct['id']]);
    }
}

$pendingCount = fetchOne("SELECT COUNT(*) as c FROM orders WHERE status = 'pending'")['c'] ?? 0;
$siteName = getSetting('site_name', 'Mahi Fashion House');
$adminName = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#667eea">
    <title>প্রোডাক্টস – <?php echo $siteName; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== ROOT VARIABLES ===== */
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

        /* ===== RESET & BASE ===== */
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

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }

        /* ===== APP SHELL ===== */
        .app {
            max-width: 1400px;
            margin: 0 auto;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

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
        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .menu-btn {
            color: #fff;
            font-size: 20px;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s cubic-bezier(0.22,1,0.36,1);
            display: none;
            cursor: pointer;
            border: none;
            outline: none;
            -webkit-tap-highlight-color: transparent;
            z-index: 101;
        }
        .menu-btn:active { transform: scale(0.88); background: rgba(255,255,255,0.25); }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
        }
        .brand i { font-size: 24px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        .brand h1 {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }
        .brand span {
            font-size: 11px;
            font-weight: 400;
            opacity: 0.85;
            display: block;
            margin-top: -2px;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-btn {
            color: #fff;
            font-size: 18px;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.2s;
        }
        .header-btn:active { transform: scale(0.88); background: rgba(255,255,255,0.22); }
        .badge-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 10px;
            height: 10px;
            background: var(--secondary);
            border-radius: 50%;
            border: 2px solid var(--gradient-start);
            box-shadow: 0 0 0 2px rgba(255,107,107,0.3);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(255,255,255,0.20);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            color: #fff;
            backdrop-filter: blur(10px);
            border: 1.5px solid rgba(255,255,255,0.25);
            transition: transform 0.2s;
        }
        .admin-avatar:active { transform: scale(0.9); }

        /* ===== SIDEBAR (Redesigned) ===== */
        .sidebar {
            position: fixed;
            top: var(--header-height);
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: #fff;
            box-shadow: 4px 0 30px rgba(0,0,0,0.06);
            padding: 0;
            overflow-y: auto;
            z-index: 90;
            transition: transform 0.35s cubic-bezier(0.32,0.72,0,1);
            border-right: 1px solid rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
        }
        .sidebar-profile {
            padding: 24px 20px 20px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            position: relative;
            overflow: hidden;
        }
        .sidebar-profile::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .sidebar-profile::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: -20px;
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .profile-top {
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
            z-index: 1;
        }
        .profile-avatar {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            color: #fff;
            border: 2px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .profile-info h4 {
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .profile-info p {
            color: rgba(255,255,255,0.8);
            font-size: 12px;
            font-weight: 500;
        }
        .profile-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            background: rgba(255,255,255,0.15);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            color: #fff;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .profile-status .dot {
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(74,222,128,0.3);
        }
        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }
        .sidebar .nav-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            padding: 12px 14px 6px;
            font-weight: 700;
            opacity: 0.7;
        }
        .sidebar .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            margin: 3px 0;
            border-radius: 14px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 14px;
            transition: all 0.25s cubic-bezier(0.22,1,0.36,1);
            position: relative;
            overflow: hidden;
        }
        .sidebar .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            opacity: 0.08;
            transition: width 0.3s;
            border-radius: 14px;
        }
        .sidebar .nav-item:hover::before { width: 100%; }
        .sidebar .nav-item i {
            width: 22px;
            font-size: 18px;
            text-align: center;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar .nav-item:hover {
            color: var(--primary);
            transform: translateX(4px);
        }
        .sidebar .nav-item:hover i { transform: scale(1.1); }
        .sidebar .nav-item.active {
            background: linear-gradient(135deg, rgba(108,99,255,0.12), rgba(108,99,255,0.06));
            color: var(--primary);
            font-weight: 600;
            box-shadow: 0 2px 12px rgba(108,99,255,0.1);
        }
        .sidebar .nav-item.active i { color: var(--primary); }
        .sidebar .nav-item .badge {
            margin-left: auto;
            background: linear-gradient(135deg, var(--secondary), #ff8e8e);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(255,107,107,0.3);
        }
        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid rgba(0,0,0,0.05);
            background: #fafbfc;
        }
        .sidebar-footer .nav-item {
            color: #dc3545;
            margin: 0;
        }
        .sidebar-footer .nav-item:hover {
            background: rgba(220, 53, 69, 0.06);
            color: #dc3545;
            transform: translateX(4px);
        }
        .sidebar-footer .nav-item::before {
            background: rgba(220, 53, 69, 0.08);
        }
        .sidebar-bottom-info {
            padding: 12px 16px;
            text-align: center;
            font-size: 11px;
            color: var(--text-secondary);
            opacity: 0.6;
            border-top: 1px solid rgba(0,0,0,0.04);
        }

        /* ===== OVERLAY ===== */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 85;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .sidebar-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px 28px 100px;
            flex: 1;
            transition: margin 0.3s;
        }

        /* ===== TOP BAR ===== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
            animation: fadeUp 0.5s ease forwards;
        }
        .page-title {
            font-size: 24px;
            font-weight: 700;
        }
        .page-title small {
            font-size: 14px;
            font-weight: 400;
            color: var(--text-secondary);
            margin-left: 8px;
        }
        .btn {
            padding: 10px 22px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Hind Siliguri', sans-serif;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            touch-action: manipulation;
        }
        .btn:active { transform: scale(0.95); }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 12px rgba(108,99,255,0.25);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108,99,255,0.35);
        }
        .btn-secondary {
            background: #f0f2f5;
            color: var(--text-secondary);
        }
        .btn-secondary:hover { background: #e0e0e0; }
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #fff;
            box-shadow: 0 4px 12px rgba(231,76,60,0.2);
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
        }

        /* ===== ALERT ===== */
        .alert {
            padding: 14px 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeUp 0.5s ease 0.1s both;
        }
        .alert-success {
            background: #e6f7ed;
            color: #0caa5e;
        }
        .alert-error {
            background: #fee;
            color: #e74c3c;
        }

        /* ===== FORM ===== */
        .form-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 28px 32px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(108,99,255,0.04);
            margin-bottom: 28px;
            animation: fadeUp 0.5s ease forwards;
        }
        .form-section h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 24px;
            color: var(--text-primary);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group.full { grid-column: 1 / -1; }
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .form-input, .form-select, .form-textarea {
            padding: 12px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Hind Siliguri', sans-serif;
            outline: none;
            transition: all 0.2s;
            background: #fff;
            width: 100%;
            -webkit-appearance: none;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108,99,255,0.1);
        }
        .form-textarea { min-height: 120px; resize: vertical; }
        .form-input[type="file"] { padding: 10px; }

        .existing-images {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .existing-image {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            border: 2px solid #eee;
            transition: transform 0.2s;
        }
        .existing-image:active { transform: scale(0.95); }
        .existing-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .img-delete {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 26px;
            height: 26px;
            background: rgba(231,76,60,0.9);
            color: #fff;
            border: none;
            border-radius: 50%;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .img-delete:active { transform: scale(0.9); }

        .size-rows {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .size-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
            align-items: center;
        }
        .add-size-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: linear-gradient(135deg, #f0f2f5, #e8eaed);
            border: none;
            border-radius: 12px;
            font-size: 13px;
            font-family: 'Hind Siliguri', sans-serif;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.2s;
            margin-top: 6px;
            font-weight: 600;
        }
        .add-size-btn:active { transform: scale(0.95); }
        .add-size-btn:hover { background: #e0e0e0; }
        .form-actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* ===== PRODUCT CARDS (Mobile) ===== */
        .products-mobile {
            display: none;
            flex-direction: column;
            gap: 12px;
        }
        .product-card {
            background: var(--card-bg);
            border-radius: var(--radius-sm);
            padding: 16px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(108,99,255,0.04);
            display: flex;
            gap: 14px;
            transition: all 0.25s;
            animation: fadeUp 0.5s ease forwards;
            opacity: 0;
        }
        .product-card:active { transform: scale(0.98); }
        .product-card .card-thumb {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            object-fit: cover;
            background: #f5f5f5;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .product-card .card-thumb i { font-size: 24px; color: #ccc; }
        .product-card .card-body { flex: 1; min-width: 0; }
        .product-card .card-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-card .card-meta {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        .product-card .card-price {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            margin-top: 4px;
        }
        .product-card .card-price .old {
            font-size: 12px;
            color: var(--text-secondary);
            text-decoration: line-through;
            font-weight: 400;
            margin-left: 4px;
        }
        .product-card .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }
        .product-card .card-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .product-card .card-status .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .product-card .card-actions {
            display: flex;
            gap: 6px;
        }

        /* ===== PRODUCT TABLE (Desktop) ===== */
        .products-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(108,99,255,0.04);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            animation: fadeUp 0.5s ease forwards;
        }
        .products-section h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 18px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }
        .data-table th {
            text-align: left;
            padding: 14px 16px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f0f2f5;
            background: #fafbfc;
        }
        .data-table td {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid #f0f2f5;
            vertical-align: middle;
        }
        .data-table tr:hover td { background: rgba(108,99,255,0.02); }
        .product-thumb {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            object-fit: cover;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .product-thumb i { font-size: 20px; color: #ccc; }
        .price-cell { font-weight: 700; }
        .old-price {
            text-decoration: line-through;
            color: var(--text-secondary);
            font-size: 12px;
            display: block;
        }
        .stock-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
        }
        .stock-high { background: #e6f7ed; color: #0caa5e; }
        .stock-low { background: #fef0e6; color: #f57c00; }
        .stock-out { background: #fee; color: #e74c3c; }
        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 4px;
        }
        .status-dot.active { background: #0caa5e; box-shadow: 0 0 0 2px rgba(12,170,94,0.2); }
        .status-dot.inactive { background: #e74c3c; box-shadow: 0 0 0 2px rgba(231,76,60,0.2); }

        .actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        /* ===== BOTTOM NAV (Mobile) ===== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: calc(var(--bottom-nav-height) + var(--safe-bottom));
            padding-bottom: var(--safe-bottom);
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            display: none;
            align-items: center;
            justify-content: space-around;
            box-shadow: 0 -4px 24px rgba(0,0,0,0.08);
            border-top: 1px solid rgba(0,0,0,0.04);
            z-index: 200;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            color: var(--text-secondary);
            font-size: 10px;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 14px;
            transition: all 0.2s;
            position: relative;
            min-width: 56px;
            flex: 1;
        }
        .bottom-nav .nav-item i {
            font-size: 20px;
            transition: all 0.3s cubic-bezier(0.22,1,0.36,1);
        }
        .bottom-nav .nav-item.active {
            color: var(--primary);
        }
        .bottom-nav .nav-item.active i {
            transform: translateY(-2px);
        }
        .bottom-nav .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            width: 20px;
            height: 4px;
            background: var(--primary);
            border-radius: 4px;
        }
        .bottom-nav .nav-item .badge {
            position: absolute;
            top: 0;
            right: 6px;
            background: var(--secondary);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 20px;
            line-height: 1.5;
            box-shadow: 0 2px 6px rgba(255,107,107,0.3);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 300px;
                --header-height: 60px;
                --bottom-nav-height: 68px;
            }
            body { padding-bottom: calc(var(--bottom-nav-height) + var(--safe-bottom) + 12px); }
            .menu-btn { display: flex; }
            .sidebar {
                top: 0;
                transform: translateX(-100%);
                box-shadow: 8px 0 40px rgba(0,0,0,0.15);
                border-right: none;
                width: var(--sidebar-width);
                border-radius: 0 24px 24px 0;
            }
            .sidebar.open { transform: translateX(0); }
            .sidebar-profile { border-radius: 0 24px 0 0; padding: 28px 20px 24px; }
            .main-content {
                margin-left: 0;
                padding: 16px 16px 90px;
            }
            .bottom-nav { display: flex; }
            .header {
                padding: 10px 14px;
                min-height: var(--header-height);
            }
            .brand h1 { font-size: 16px; }
            .brand span { font-size: 10px; }
            .admin-avatar { width: 36px; height: 36px; font-size: 14px; }
            .header-btn { width: 36px; height: 36px; font-size: 16px; }
            
            .page-title { font-size: 20px; }
            .page-title small { font-size: 12px; }
            
            .form-section { padding: 18px; border-radius: var(--radius-sm); }
            .form-grid { grid-template-columns: 1fr; gap: 14px; }
            .size-row { grid-template-columns: 1fr 1fr auto; }
            .existing-image { width: 70px; height: 70px; }
            
            .products-section { display: none; }
            .products-mobile { display: flex; }
            
            .top-bar { flex-direction: row; align-items: center; }
            .btn { padding: 10px 16px; font-size: 13px; }
        }

        @media (max-width: 420px) {
            .brand h1 { font-size: 14px; }
            .bottom-nav .nav-item { font-size: 9px; min-width: 48px; padding: 4px 6px; }
            .bottom-nav .nav-item i { font-size: 18px; }
            .form-section { padding: 14px; }
            .size-row { grid-template-columns: 1fr; gap: 8px; }
            .size-row .btn { width: 100%; justify-content: center; }
            .product-card { padding: 12px; gap: 10px; }
            .product-card .card-thumb { width: 52px; height: 52px; }
            .product-card .card-title { font-size: 14px; }
            .btn-sm { padding: 5px 10px; font-size: 11px; }
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeUp {
            from { transform: translateY(24px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .form-section, .products-section {
            animation: fadeUp 0.5s ease forwards;
        }
        .bottom-nav {
            animation: slideUp 0.5s cubic-bezier(0.22,1,0.36,1) forwards;
        }

        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: var(--text-secondary);
            animation: fadeUp 0.5s ease forwards;
        }
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            display: block;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

<div class="app">

    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="header-left">
            <button class="menu-btn" id="menuToggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="brand">
                <i class="fas fa-store-alt"></i>
                <div>
                    <h1><?php echo $siteName; ?></h1>
                    <span>Admin Panel</span>
                </div>
            </div>
        </div>
        <div class="header-right">
            <button class="header-btn" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="badge-dot"></span>
            </button>
            <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 2)); ?></div>
        </div>
    </header>

    <!-- ===== SIDEBAR OVERLAY ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== SIDEBAR (Redesigned) ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-profile">
            <div class="profile-top">
                <div class="profile-avatar"><?php echo strtoupper(substr($adminName, 0, 2)); ?></div>
                <div class="profile-info">
                    <h4><?php echo $adminName; ?></h4>
                    <p>Administrator</p>
                    <div class="profile-status">
                        <span class="dot"></span>
                        <span>Online</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="index.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a href="orders.php" class="nav-item">
                <i class="fas fa-shopping-bag"></i> <span>Orders</span>
                <?php if ($pendingCount > 0): ?>
                <span class="badge"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="products.php" class="nav-item active">
                <i class="fas fa-box"></i> <span>Products</span>
            </a>
            <a href="categories.php" class="nav-item">
                <i class="fas fa-tags"></i> <span>Categories</span>
            </a>
            <div class="nav-label" style="margin-top:8px;">Management</div>
            <a href="banners.php" class="nav-item">
                <i class="fas fa-image"></i> <span>Banners</span>
            </a>
            <a href="settings.php" class="nav-item">
                <i class="fas fa-cog"></i> <span>Settings</span>
            </a>
            <div class="sidebar-footer" style="margin-top:8px;">
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </div>
        <div class="sidebar-bottom-info">
            v1.0.0 &middot; Mahi Fashion House
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                All Products <small>(<?php echo count($products); ?>)</small>
            </h1>
            <div>
                <?php if ($editProduct || isset($_GET['add'])): ?>
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> List View
                </a>
                <?php else: ?>
                <a href="products.php?add=1" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Product
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alert -->
        <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ===== FORM ===== -->
        <?php if ($editProduct || isset($_GET['add'])): ?>
        <div class="form-section">
            <h2><?php echo $editProduct ? 'Edit Product' : 'Add New Product'; ?></h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $editProduct['id'] ?? 0; ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Product Title *</label>
                        <input type="text" name="title" class="form-input" value="<?php echo $editProduct['title'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="0">-- Select --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($editProduct['category_id'] ?? 0) == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $cat['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price (৳) *</label>
                        <input type="number" name="price" class="form-input" step="0.01" value="<?php echo $editProduct['price'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Discount Price (৳)</label>
                        <input type="number" name="discount_price" class="form-input" step="0.01" value="<?php echo $editProduct['discount_price'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock</label>
                        <input type="number" name="stock" class="form-input" value="<?php echo $editProduct['stock'] ?? '100'; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position (lower = higher)</label>
                        <input type="number" name="position" class="form-input" value="<?php echo $editProduct['position'] ?? '0'; ?>">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Description (HTML supported)</label>
                        <textarea name="description" class="form-textarea"><?php echo $editProduct['description'] ?? ''; ?></textarea>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Product Images (multiple)</label>
                        <input type="file" name="images[]" multiple accept="image/*" class="form-input">
                        <small style="color: var(--text-secondary); font-size: 12px;">First image will be primary</small>
                        <?php if (!empty($editImages)): ?>
                        <div class="existing-images" id="existingImages">
                            <?php foreach ($editImages as $img): ?>
                            <div class="existing-image" data-img-id="<?php echo $img['id']; ?>">
                                <img src="../uploads/products/<?php echo $img['image']; ?>" alt="">
                                <button type="button" class="img-delete" onclick="deleteImage(<?php echo $img['id']; ?>, this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Sizes & Extra Price</label>
                        <div class="size-rows" id="sizeRows">
                            <?php if (!empty($editSizes)): ?>
                                <?php foreach ($editSizes as $sz): ?>
                                <div class="size-row">
                                    <input type="text" name="sizes[]" class="form-input" placeholder="Size (S, M, L)" value="<?php echo $sz['size']; ?>">
                                    <input type="number" name="size_prices[]" class="form-input" step="0.01" placeholder="Extra price" value="<?php echo $sz['additional_price']; ?>">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeSizeRow(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div class="size-row">
                                <input type="text" name="sizes[]" class="form-input" placeholder="Size (S, M, L)">
                                <input type="number" name="size_prices[]" class="form-input" step="0.01" placeholder="Extra price">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeSizeRow(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="add-size-btn" onclick="addSizeRow()">
                            <i class="fas fa-plus"></i> Add Size
                        </button>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="status" <?php echo ($editProduct['status'] ?? 1) ? 'checked' : ''; ?> style="width:18px; height:18px; accent-color: var(--primary);">
                            Active
                        </label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $editProduct ? 'Update' : 'Save'; ?></button>
                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- ===== PRODUCT TABLE (Desktop) ===== -->
        <?php if (!$editProduct && !isset($_GET['add'])): ?>
        <div class="products-section">
            <h2>All Products</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): 
                        $img = fetchOne("SELECT image FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1", [$p['id']]);
                        $imgSrc = $img ? '../uploads/products/' . $img['image'] : '';
                        $stockClass = $p['stock'] > 20 ? 'stock-high' : ($p['stock'] > 0 ? 'stock-low' : 'stock-out');
                        $stockLabel = $p['stock'] > 20 ? 'Available' : ($p['stock'] > 0 ? 'Low' : 'Out');
                        $statusClass = $p['status'] ? 'active' : 'inactive';
                    ?>
                    <tr>
                        <td>
                            <?php if ($imgSrc): ?>
                            <img src="<?php echo $imgSrc; ?>" class="product-thumb" alt="">
                            <?php else: ?>
                            <div class="product-thumb"><i class="fas fa-tshirt"></i></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:600;"><?php echo $p['title']; ?></td>
                        <td><?php echo $p['category_name'] ?? 'Uncategorized'; ?></td>
                        <td>
                            <div class="price-cell">৳<?php echo number_format($p['discount_price'] > 0 ? $p['discount_price'] : $p['price'], 0); ?></div>
                            <?php if ($p['discount_price'] > 0): ?>
                            <div class="old-price">৳<?php echo number_format($p['price'], 0); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="stock-badge <?php echo $stockClass; ?>"><?php echo $p['stock']; ?> (<?php echo $stockLabel; ?>)</span></td>
                        <td><span class="status-dot <?php echo $statusClass; ?>"></span> <?php echo $p['status'] ? 'Active' : 'Inactive'; ?></td>
                        <td>
                            <div class="actions">
                                <a href="products.php?edit=<?php echo $p['id']; ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <p>No products found. Click "Add Product" to create one.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ===== PRODUCT CARDS (Mobile) ===== -->
        <div class="products-mobile">
            <?php foreach ($products as $index => $p): 
                $img = fetchOne("SELECT image FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1", [$p['id']]);
                $imgSrc = $img ? '../uploads/products/' . $img['image'] : '';
                $stockClass = $p['stock'] > 20 ? 'stock-high' : ($p['stock'] > 0 ? 'stock-low' : 'stock-out');
                $stockLabel = $p['stock'] > 20 ? 'Available' : ($p['stock'] > 0 ? 'Low' : 'Out');
                $statusClass = $p['status'] ? 'active' : 'inactive';
                $statusColor = $p['status'] ? '#0caa5e' : '#e74c3c';
            ?>
            <div class="product-card" style="animation-delay: <?php echo $index * 0.05; ?>s;">
                <div class="card-thumb">
                    <?php if ($imgSrc): ?>
                    <img src="<?php echo $imgSrc; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:12px;" alt="">
                    <?php else: ?>
                    <i class="fas fa-tshirt"></i>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="card-title"><?php echo $p['title']; ?></div>
                    <div class="card-meta"><?php echo $p['category_name'] ?? 'Uncategorized'; ?> • Stock: <?php echo $p['stock']; ?></div>
                    <div class="card-price">
                        ৳<?php echo number_format($p['discount_price'] > 0 ? $p['discount_price'] : $p['price'], 0); ?>
                        <?php if ($p['discount_price'] > 0): ?>
                        <span class="old">৳<?php echo number_format($p['price'], 0); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <div class="card-status">
                            <span class="dot" style="background:<?php echo $statusColor; ?>"></span>
                            <?php echo $p['status'] ? 'Active' : 'Inactive'; ?>
                        </div>
                        <div class="card-actions">
                            <a href="products.php?edit=<?php echo $p['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No products found. Click "Add Product" to create one.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>

    <!-- ===== BOTTOM NAV (Mobile) ===== -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="index.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>ড্যাশ</span>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-shopping-bag"></i>
            <span>অর্ডার</span>
            <?php if ($pendingCount > 0): ?>
            <span class="badge"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="products.php" class="nav-item active">
            <i class="fas fa-box"></i>
            <span>প্রোডাক্ট</span>
        </a>
        <a href="settings.php" class="nav-item">
            <i class="fas fa-cog"></i>
            <span>সেটিংস</span>
        </a>
        <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>লগআউট</span>
        </a>
    </nav>

</div>

<script>
    // Sidebar Toggle
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var menuToggle = document.getElementById('menuToggle');

    function openSidebar() {
        if (sidebar) {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    function toggleSidebar(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        if (sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    if (menuToggle) {
        menuToggle.onclick = toggleSidebar;
        menuToggle.ontouchstart = toggleSidebar;
    }

    if (overlay) {
        overlay.onclick = closeSidebar;
        overlay.ontouchstart = closeSidebar;
    }

    // Close on Escape key
    document.onkeydown = function(e) {
        if (e.keyCode === 27 && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    };

    // Close sidebar when clicking nav items on mobile
    var sidebarItems = document.querySelectorAll('.sidebar .nav-item');
    for (var i = 0; i < sidebarItems.length; i++) {
        sidebarItems[i].onclick = function() {
            if (window.innerWidth <= 768) closeSidebar();
        };
    }

    // Active State Sync
    var currentPath = window.location.pathname.split('/').pop() || 'products.php';
    var allNavItems = document.querySelectorAll('.bottom-nav .nav-item, .sidebar .nav-item');
    for (var j = 0; j < allNavItems.length; j++) {
        var item = allNavItems[j];
        var href = item.getAttribute('href');
        if (href === currentPath) {
            item.classList.add('active');
            var siblings = item.parentElement.querySelectorAll('.nav-item');
            for (var k = 0; k < siblings.length; k++) {
                if (siblings[k] !== item) siblings[k].classList.remove('active');
            }
        }
    }

    // Add Size Row
    function addSizeRow() {
        var container = document.getElementById('sizeRows');
        if (!container) return;
        var row = document.createElement('div');
        row.className = 'size-row';
        row.innerHTML = '<input type="text" name="sizes[]" class="form-input" placeholder="Size (S, M, L)">' +
            '<input type="number" name="size_prices[]" class="form-input" step="0.01" placeholder="Extra price">' +
            '<button type="button" class="btn btn-danger btn-sm" onclick="removeSizeRow(this)">' +
            '<i class="fas fa-trash"></i></button>';
        container.appendChild(row);
    }

    // Remove Size Row
    function removeSizeRow(btn) {
        var row = btn.parentNode;
        if (row) row.parentNode.removeChild(row);
    }

    // Delete Image with AJAX (fallback to form if fetch not supported)
    function deleteImage(imgId, btn) {
        if (!confirm('Delete this image?')) return;

        // Try fetch first
        if (window.fetch && window.FormData) {
            var formData = new FormData();
            formData.append('action', 'delete_image');
            formData.append('image_id', imgId);

            fetch('products.php', {
                method: 'POST',
                body: formData
            }).then(function() {
                var imgDiv = btn.parentNode;
                if (imgDiv) imgDiv.parentNode.removeChild(imgDiv);
            }).catch(function() {
                // Fallback: submit form
                submitDeleteImage(imgId);
            });
        } else {
            // Fallback for old browsers
            submitDeleteImage(imgId);
        }
    }

    function submitDeleteImage(imgId) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        form.style.display = 'none';

        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_image';
        form.appendChild(actionInput);

        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'image_id';
        idInput.value = imgId;
        form.appendChild(idInput);

        document.body.appendChild(form);
        form.submit();
    }
</script>

</body>
</html>