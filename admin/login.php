<?php
require_once '../Config.php';

// Already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $admin = fetchOne("SELECT * FROM admin_users WHERE username = ?", [$username]);
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'ভুল ইউজারনেম বা পাসওয়ার্ড!';
        }
    } catch (Exception $e) {
        $error = 'লগইন ত্রুটি! ডাটাবেস চেক করুন।';
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Mahi Fashion House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-dark: #152a45;
            --accent: #c9a227;
            --accent-light: #e8d5a3;
            --text: #1a1a2e;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --input-bg: #f9fafb;
            --error: #dc2626;
            --error-bg: #fef2f2;
            --success: #059669;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Hind Siliguri', 'Kalpurush', sans-serif;
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .brand-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .brand-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(30, 58, 95, 0.2);
        }

        .brand-icon i {
            font-size: 28px;
            color: #fff;
        }

        .brand-section h1 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 6px;
        }

        .brand-section p {
            font-size: 14px;
            color: var(--text-muted);
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .login-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }

        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .login-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
        }

        .login-header span {
            font-size: 13px;
            color: var(--text-muted);
        }

        .divider {
            width: 40px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 2px;
            margin: 0 auto 28px;
        }

        .form-group { margin-bottom: 20px; }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .input-box {
            position: relative;
        }

        .input-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            transition: color 0.2s;
        }

        .input-box:focus-within i {
            color: var(--primary);
        }

        .form-input {
            width: 100%;
            padding: 13px 14px 13px 44px;
            background: var(--input-bg);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Hind Siliguri', sans-serif;
            color: var(--text);
            transition: all 0.2s ease;
        }

        .form-input::placeholder {
            color: #9ca3af;
            font-size: 14px;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(30, 58, 95, 0.08);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Hind Siliguri', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
            letter-spacing: 0.5px;
        }

        .login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(30, 58, 95, 0.25);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .error-msg {
            background: var(--error-bg);
            color: var(--error);
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid #fecaca;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        .back-link {
            text-align: center;
            margin-top: 24px;
        }

        .back-link a {
            color: var(--text-muted);
            font-size: 13px;
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-link a:hover {
            color: var(--primary);
        }

        .back-link a i {
            margin-right: 6px;
        }

        @media (max-width: 480px) {
            .login-card { padding: 28px 22px; }
            .brand-section h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="brand-section">
            <div class="brand-icon">
                <i class="fas fa-shield-halved"></i>
            </div>
            <h1>Mahi Fashion House</h1>
            <p>এডমিন প্যানেল</p>
        </div>

        <div class="login-card">
            <div class="login-header">
                <h2>লগইন করুন</h2>
                <span>আপনার এডমিন একাউন্টে প্রবেশ করুন</span>
            </div>
            <div class="divider"></div>

            <?php if ($error): ?>
            <div class="error-msg">
                <i class="fas fa-circle-exclamation"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">ইউজারনেম</label>
                    <div class="input-box">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" class="form-input" placeholder="আপনার ইউজারনেম" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">পাসওয়ার্ড</label>
                    <div class="input-box">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-input" placeholder="আপনার পাসওয়ার্ড" required>
                    </div>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-arrow-right-to-bracket" style="margin-right: 8px;"></i>
                    লগইন
                </button>
            </form>
        </div>

        <div class="back-link">
            <a href="../index.php"><i class="fas fa-arrow-left"></i> ওয়েবসাইটে ফিরে যান</a>
        </div>
    </div>
</body>
</html>