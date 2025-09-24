<?php
// register.php - Đăng ký an toàn với Supabase + lưu MySQL
require_once './libs/db.php';
require_once './config/config_session.php';
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

// Load biến môi trường từ .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$supabaseUrl = $_ENV['SUPABASE_URL'];
$supabaseKey = $_ENV['SUPABASE_KEY'];

// ---- CSRF ----
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Escape output
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $msg = 'Yêu cầu không hợp lệ (CSRF).';
    } else {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if ($username === '' || $fullname === '' || $email === '' || $password === '' || $confirm === '') {
            $msg = 'Vui lòng nhập đầy đủ.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Email không hợp lệ.';
        } elseif ($password !== $confirm) {
            $msg = 'Mật khẩu xác nhận không khớp.';
        } elseif (strlen($password) < 8) {
            $msg = 'Mật khẩu phải có ít nhất 8 ký tự.';
        } else {
            try {
                // Gọi Supabase Auth REST API để đăng ký
                $http = new Client([
                    'base_uri' => $supabaseUrl,
                    'headers' => [
                        'apikey' => $supabaseKey,
                        'Authorization' => 'Bearer ' . $supabaseKey,
                        'Content-Type' => 'application/json'
                    ]
                ]);

                $response = $http->post('/auth/v1/signup', [
                    'json' => [
                        'email'    => $email,
                        'password' => $password,
                        'data'     => [
                            'username' => $username,
                            'fullname' => $fullname
                        ]
                    ]
                ]);

                $data = json_decode($response->getBody(), true);

                if (isset($data['id'])) {
                    $supabaseId = $data['id'];
                    $role = 'user';

                    // Hash mật khẩu để lưu vào MySQL
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // Kiểm tra trùng email hoặc supabase_id trong MySQL
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? OR supabase_id=? LIMIT 1");
                    $stmt->bind_param('ss', $email, $supabaseId);
                    $stmt->execute();
                    $exist = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($exist) {
                        $msg = 'Email đã tồn tại trong hệ thống.';
                    } else {
                        $stmt = $conn->prepare(
                            "INSERT INTO users (username, fullname, email, password, supabase_id, role, is_active) 
                             VALUES (?,?,?,?,?,?,0)"
                        );
                        $stmt->bind_param('ssssss', $username, $fullname, $email, $hashedPassword, $supabaseId, $role);

                        if ($stmt->execute()) {
                            $msg = 'Đăng ký thành công! Vui lòng kiểm tra email để xác nhận tài khoản.';
                        } else {
                            $msg = 'Có lỗi khi lưu vào CSDL nội bộ.';
                        }
                        $stmt->close();
                    }
                } else {
                    $msg = 'Lỗi không xác định từ Supabase.';
                }

            } catch (ClientException $e) {
                $resp = $e->getResponse()->getBody()->getContents();
                $data = json_decode($resp, true);
                if (isset($data['code']) && $data['code'] === '23505') {
                    $msg = 'Email đã tồn tại trên Supabase. Vui lòng đăng nhập hoặc dùng email khác.';
                } else {
                    $msg = 'Supabase error: ' . e($resp);
                }
            } catch (Exception $e) {
                $msg = 'Lỗi kết nối Supabase: ' . e($e->getMessage());
            }
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đăng ký - LaptopShop.vn</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="./css/style.css">
  <style>
    body {
      font-family:'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg,#ffffff 0%,#f2f2f2 50%,#e0e0e0 100%);
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .auth-box {
      width:100%;
      max-width:460px;
      background:#fff;
      padding:30px 26px;
      border-radius:12px;
      border:1px solid #ddd;
      box-shadow:0 8px 24px rgba(0,0,0,0.15);
      animation:fadeIn 0.6s ease;
      text-align:center;
    }
    @keyframes fadeIn {
      from{opacity:0;transform:translateY(-15px);}
      to{opacity:1;transform:translateY(0);}
    }
    .auth-box h2 {
      margin-bottom:20px;
      color:#b70000;
      font-size:24px;
      border-bottom:2px solid #b70000;
      padding-bottom:8px;
    }
    .auth-box label {
      display:block;
      margin:10px 0 6px;
      font-size:14px;
      font-weight:600;
      color:#333;
      text-align:left;
    }
    .auth-box input[type="text"],
    .auth-box input[type="email"],
    .auth-box input[type="password"] {
      width:100%;
      padding:10px 12px;
      border:1px solid #ccc;
      border-radius:6px;
      font-size:14px;
      transition:all 0.3s ease;
    }
    .auth-box input:focus {
      border-color:#b70000;
      box-shadow:0 0 6px rgba(183,0,0,0.4);
      outline:none;
    }
    .auth-box .actions {
      margin-top:20px;
      display:flex;
      justify-content:space-between;
      flex-wrap:wrap;
      gap:10px;
    }
    .auth-box button {
      background:#b70000;
      color:#fff;
      border:none;
      padding:10px 18px;
      border-radius:6px;
      cursor:pointer;
      font-size:14px;
      font-weight:600;
      transition:all 0.3s ease;
      box-shadow:0 3px 8px rgba(0,0,0,0.15);
    }
    .auth-box button:hover {
      background:#a00000;
      transform:translateY(-2px);
      box-shadow:0 4px 12px rgba(0,0,0,0.25);
    }
    .auth-box .link-btn {
      display:inline-block;
      padding:9px 14px;
      border-radius:6px;
      background:#f1f1f1;
      color:#333;
      font-size:13px;
      text-decoration:none;
      transition:all 0.3s ease;
    }
    .auth-box .link-btn:hover {
      background:#b70000;
      color:#fff;
    }
    .auth-box .msg {
      margin-top:10px;
      color:#c00;
      font-size:14px;
      text-align:center;
      background:#ffe6e6;
      border:1px solid #ffcccc;
      padding:6px;
      border-radius:6px;
    }
  </style>
</head>
<body>
<div class="auth-box">
  <h2>Đăng ký</h2>

  <?php if (!empty($msg)): ?>
    <div class="msg"><?= e($msg) ?></div>
  <?php endif; ?>

  <form method="post" action="" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

    <label for="fullname">Họ và Tên</label>
    <input id="fullname" name="fullname" type="text" maxlength="150" required>

    <label for="username">Tên đăng nhập</label>
    <input id="username" name="username" type="text" maxlength="100" required>

    <label for="email">Email</label>
    <input id="email" name="email" type="email" maxlength="150" required>

    <label for="password">Mật khẩu</label>
    <input id="password" name="password" type="password" maxlength="255" required>

    <label for="confirm">Xác nhận mật khẩu</label>
    <input id="confirm" name="confirm" type="password" maxlength="255" required>

    <div class="actions">
      <button type="submit">Đăng ký</button>
      <a href="login.php" class="link-btn">Đăng nhập</a>
      <a href="home.php" class="link-btn">Trang chủ</a>
    </div>
  </form>
</div>
</body>
<script>
document.addEventListener("DOMContentLoaded", function() {
  if (window.location.hash) {
    const params = new URLSearchParams(window.location.hash.substring(1));
    const accessToken = params.get("access_token");
    if (accessToken) {
      // Chuyển qua verify.php kèm token để xử lý cập nhật DB
      window.location.href = "verify.php?token=" + encodeURIComponent(accessToken);
    }
  }
});
</script>
</html>
