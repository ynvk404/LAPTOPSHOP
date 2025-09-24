<?php
// login.php - đăng nhập an toàn
require_once './libs/db.php';
require_once './config/config_session.php';
header("Content-Security-Policy: script-src 'self'; object-src 'none'; frame-ancestors 'self';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

// ---- CSRF ----
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---- Escape output ----
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---- Brute force tracking ----
if (!isset($_SESSION['failed_login'])) {
    $_SESSION['failed_login'] = ['count' => 0, 'last_attempt' => 0];
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $msg = 'Yêu cầu không hợp lệ (CSRF).';
    } else {
        $now    = time();
        $failed = &$_SESSION['failed_login'];

        // Kiểm tra khóa tạm thời
        if ($failed['count'] >= $LIMIT_ATTEMPTS && ($now - $failed['last_attempt']) < $LOCKOUT_SECONDS) {
            $wait = $LOCKOUT_SECONDS - ($now - $failed['last_attempt']);
            $msg  = "Bạn đã thử sai quá nhiều. Thử lại sau $wait giây.";
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);

            if ($username === '' || $password === '') {
                $msg = 'Vui lòng nhập đầy đủ.';
            } else {
                // Lấy user có thêm is_active
                $stmt = $conn->prepare("SELECT id, username, password, role, is_active FROM users WHERE username = ? LIMIT 1");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password'])) {
                    // Nếu hash cũ -> rehash
                    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $uStmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                        $uStmt->bind_param('si', $newHash, $user['id']);
                        $uStmt->execute();
                        $uStmt->close();
                    }

                    // Reset session
                    session_regenerate_id(true);
                    $fingerprint = hash_hmac(
                        'sha256',
                        $_SERVER['REMOTE_ADDR'] . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                        $secretKey
                    );

                    $_SESSION['user_id']      = (int)$user['id'];
                    $_SESSION['username']     = $user['username'];
                    $_SESSION['role']         = $user['role'] ?? 'user';
                    $_SESSION['logged_in_at'] = $now;
                    $_SESSION['fingerprint']  = $fingerprint;
                    $_SESSION['failed_login'] = ['count' => 0, 'last_attempt' => 0];

                    // Cookie last_login
                    setcookie('last_login', (string)$now, [
                        'expires'  => $now + 60*60*24*30,
                        'path'     => '/',
                        'domain'   => '',
                        'secure'   => $secureFlag,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);

                    // Cookie remember_me an toàn hơn (hash user_id + secret)
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $tokenHash = hash_hmac('sha256', $token, $secretKey);

                        // Lưu vào DB (bảng user_tokens cần có cột: user_id, token_hash, expires)
                        $expires = date('Y-m-d H:i:s', $now + 60*60*24*30);
                        $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token_hash, expires) VALUES (?,?,?)");
                        $stmt->bind_param('iss', $user['id'], $tokenHash, $expires);
                        $stmt->execute();
                        $stmt->close();

                        setcookie('remember_me', $user['id'] . ':' . $token, [
                            'expires'  => $now + 60*60*24*30,
                            'path'     => '/',
                            'domain'   => '',
                            'secure'   => $secureFlag,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    }

                    header('Location: home.php');
                    exit;
                } else {
                    if ($user && (int)$user['is_active'] === 0) {
                        $msg = 'Tài khoản chưa được kích hoạt. Vui lòng xác thực email trước khi đăng nhập.';
                    } else {
                        $failed['count']++;
                        $failed['last_attempt'] = $now;
                        $remain = max(0, $LIMIT_ATTEMPTS - $failed['count']);
                        $msg    = "Sai tài khoản hoặc mật khẩu.";
                        if ($remain <= 0) {
                            $msg .= " Bạn đã bị khóa $LOCKOUT_SECONDS giây.";
                        } else {
                            $msg .= " Còn $remain lần thử.";
                        }
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đăng nhập - LaptopShop.vn</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="./css/style.css">
  <style>
    body {
      font-family:'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg, #ffffff 0%, #e6e6e6 50%, #cccccc 100%);
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
    }

    .auth-box {
      width:100%;
      max-width:420px;
      background:#fff;
      padding:30px 26px;
      border-radius:12px;
      border:1px solid #ddd;
      box-shadow:0 8px 24px rgba(0,0,0,0.15);
      animation:fadeIn 0.6s ease;
      text-align:center;
    }

    @keyframes fadeIn {
      from { opacity:0; transform:translateY(-15px); }
      to { opacity:1; transform:translateY(0); }
    }

    .auth-box .logo img {
      height:60px;
      margin-bottom:12px;
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

    .auth-box .remember {
      margin-top:10px;
      font-size:13px;
      color:#555;
      text-align:left;
    }

    .auth-box .actions {
      margin-top:20px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
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
      color:#fff;
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

    .auth-box .forgot {
      margin-top:18px;
      font-size:13px;
    }
    .auth-box .forgot a {
      color:#0066cc;
      text-decoration:none;
      transition:color 0.3s;
    }
    .auth-box .forgot a:hover {
      color:#b70000;
      text-decoration:underline;
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
  <div class="logo">
    <img src="images/logo.png" alt="LaptopShop.vn">
  </div>
  <h2>Đăng nhập</h2>

  <?php if (!empty($msg)): ?>
    <div class="msg"><?= e($msg) ?></div>
  <?php endif; ?>

  <form method="post" action="" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

    <label for="username">Tên đăng nhập</label>
    <input id="username" name="username" type="text" maxlength="100" required autocomplete="username" />

    <label for="password">Mật khẩu</label>
    <input id="password" name="password" type="password" maxlength="255" required autocomplete="current-password" />

    <div class="remember">
      <label><input type="checkbox" name="remember"> Ghi nhớ đăng nhập</label>
    </div>

    <div class="actions">
      <button type="submit">Đăng nhập</button>
      <a href="register.php" class="link-btn">Đăng ký</a>
      <a href="home.php" class="link-btn">Trở về trang chủ</a>
    </div>
  </form>

  <div class="forgot">
    <a href="forgot.php">Quên mật khẩu?</a>
  </div>
</div>
</body>
</html>
