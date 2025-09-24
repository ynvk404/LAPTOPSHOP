<?php
require_once './config/config_session.php';
require_once './libs/db.php';

// ---- Security headers ----
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");

// Helper: escape HTML
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$msg = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $posted_token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $msg = 'Yêu cầu không hợp lệ (CSRF).';
    } else {
        // Input
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Validate
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            $msg = 'Tên không hợp lệ.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Email không hợp lệ.';
        } elseif ($message === '' || mb_strlen($message) > 2000) {
            $msg = 'Nội dung góp ý không hợp lệ.';
        } else {
            // Lấy IP & user_id (nếu có đăng nhập)
            $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_id  = $_SESSION['user_id'] ?? null;

            // Save to DB (table feedback đã có cột ip_address, user_id)
            $stmt = $conn->prepare("
                INSERT INTO feedback (name, email, message, created_at, ip_address, user_id) 
                VALUES (?, ?, ?, NOW(), ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param("ssssi", $name, $email, $message, $ip, $user_id);
                if ($stmt->execute()) {
                    $success = true;
                    $msg = 'Cảm ơn bạn đã gửi góp ý!';
                    // Reset token để chống replay
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } else {
                    $msg = 'Có lỗi khi lưu dữ liệu. Vui lòng thử lại sau.';
                }
                $stmt->close();
            } else {
                $msg = 'Không thể kết nối tới hệ thống.';
            }
        }
    }
} else {
    $msg = 'Phương thức không hợp lệ.';
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Phản hồi - LaptopShop.vn</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="./css/style.css">
  <style>
    body {font-family:'Segoe UI',Arial,sans-serif;background:#f6f7f8;display:flex;align-items:center;justify-content:center;min-height:100vh;}
    .box {background:#fff;padding:24px;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,0.08);max-width:480px;width:100%;text-align:center;}
    .box h2 {color:#b70000;margin-bottom:12px;}
    .msg {margin:12px 0;padding:10px;border-radius:6px;}
    .msg.error {background:#ffe6e6;color:#c00;border:1px solid #ffcccc;}
    .msg.success {background:#e6ffe6;color:#060;border:1px solid #b2d8b2;}
    a.btn {display:inline-block;margin-top:14px;padding:10px 16px;border-radius:6px;background:#b70000;color:#fff;text-decoration:none;font-weight:600;}
    a.btn:hover {background:#900;}
  </style>
</head>
<body>
  <div class="box">
    <h2>Phản hồi</h2>
    <?php if ($msg): ?>
      <div class="msg <?= $success ? 'success' : 'error' ?>"><?= e($msg) ?></div>
    <?php endif; ?>
    <a href="lienhe.php" class="btn">Quay lại Liên hệ</a>
  </div>
</body>
</html>
