<?php
require_once './libs/db.php';
require_once './config/config_session.php';
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;

// Load biến môi trường từ .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$supabaseUrl = $_ENV['SUPABASE_URL'] ?? '';
$supabaseKey = $_ENV['SUPABASE_KEY'] ?? '';

// Bảo mật header
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: no-referrer");
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline';");

// Escape output
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$msg = '';

// Lấy access_token từ URL (?token=...)
$token = $_GET['token'] ?? '';

if ($token === '') {
    $msg = 'Yêu cầu không hợp lệ. Thiếu token xác thực.';
} else {
    try {
        // Gọi Supabase API để lấy thông tin user từ access_token
        $http = new Client([
            'base_uri' => $supabaseUrl,
            'headers' => [
                'apikey' => $supabaseKey,
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ]
        ]);

        $response = $http->get('/auth/v1/user');
        $data = json_decode($response->getBody(), true);

        if (isset($data['id'], $data['email'])) {
            $supabaseId = $data['id'];
            $email = $data['email'];

            // Cập nhật trạng thái is_active = 1 trong MySQL
            $stmt = $conn->prepare("UPDATE users SET is_active=1, updated_at=NOW() WHERE supabase_id=? OR email=?");
            $stmt->bind_param('ss', $supabaseId, $email);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $msg = 'Xác thực email thành công! Bạn có thể đăng nhập.';
            } else {
                $msg = 'Tài khoản đã xác thực hoặc không tồn tại trong hệ thống.';
            }
            $stmt->close();
        } else {
            $msg = 'Token không hợp lệ hoặc đã hết hạn.';
        }

    } catch (Exception $e) {
        $msg = 'Lỗi xác thực: ' . e($e->getMessage());
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Xác thực Email</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg,#ffffff 0%,#f2f2f2 50%,#e0e0e0 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .verify-box {
      width: 100%;
      max-width: 420px;
      background: #fff;
      padding: 28px 24px;
      border-radius: 12px;
      border: 1px solid #ddd;
      box-shadow: 0 8px 24px rgba(0,0,0,0.15);
      text-align: center;
      animation: fadeIn 0.6s ease;
    }
    @keyframes fadeIn {
      from {opacity:0; transform: translateY(-15px);}
      to {opacity:1; transform: translateY(0);}
    }
    .verify-box h2 {
      font-size: 22px;
      color: #2b2b2b;
      margin-bottom: 16px;
    }
    .verify-box .msg {
      padding: 10px 14px;
      background: #e6f7e6;
      border: 1px solid #b5e0b5;
      border-radius: 6px;
      color: #2a6a2a;
      font-size: 15px;
      margin-bottom: 20px;
    }
    .verify-box a {
      display: inline-block;
      padding: 10px 18px;
      border-radius: 6px;
      background: #b70000;
      color: #fff;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 3px 8px rgba(0,0,0,0.15);
    }
    .verify-box a:hover {
      background: #a00000;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    }
  </style>
</head>
<body>
  <div class="verify-box">
    <h2>Xác thực Email</h2>
    <div class="msg"><?= e($msg) ?></div>
    <a href="login.php">Đăng nhập</a>
  </div>
</body>
</html>
