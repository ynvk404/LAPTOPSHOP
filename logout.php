<?php
// logout.php - đăng xuất an toàn
require_once './libs/db.php';
require_once './config/config_session.php'; // khởi session và các config cần thiết

// Nếu không có session CSRF token, tạo mới (để form logout GET có token)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Kiểm tra xem user đã đăng nhập hay chưa (tuỳ theo app của bạn)
$loggedIn = !empty($_SESSION['user_id']);

$msg = '';
// Xử lý khi người dùng submit POST để logout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        // CSRF không hợp lệ — không thực hiện logout
        $msg = 'Yêu cầu không hợp lệ (CSRF).';
    } else {
        // Optionally: kiểm tra fingerprint để tránh hijack session
        $expected_fp = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''), $secretKey);
        if (!empty($_SESSION['fingerprint']) && hash_equals($_SESSION['fingerprint'], $expected_fp)) {
            // Xóa token remember_me trên DB nếu có cookie
            if (!empty($_COOKIE['remember_me'])) {
                // Cookie định dạng: "<user_id>:<token>"
                $parts = explode(':', $_COOKIE['remember_me'], 2);
                if (count($parts) === 2) {
                    $cookie_user_id = (int)$parts[0];
                    $cookie_token = $parts[1];

                    // Tính token_hash tương ứng (cách bạn đã lưu)
                    $tokenHash = hash_hmac('sha256', $cookie_token, $secretKey);

                    // Xoá token trong DB (prepared statement)
                    $stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ? AND token_hash = ?");
                    if ($stmt) {
                        $stmt->bind_param('is', $cookie_user_id, $tokenHash);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            // Xóa cookie remember_me và last_login (đặt thời gian về quá khứ)
            $past = time() - 3600;
            setcookie('remember_me', '', [
                'expires'  => $past,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secureFlag,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            setcookie('last_login', '', [
                'expires'  => $past,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secureFlag,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            // Hủy session an toàn
            $_SESSION = [];
            // Xóa session cookie nếu có
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?? '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => $params['secure'] ?? $secureFlag,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => $params['samesite'] ?? 'Lax'
                ]);
            }
            // Destroy session data on server
            session_destroy();

            // Tạo session mới id để tránh session fixation (nếu cần khởi lại)
            session_start();
            session_regenerate_id(true);

            // Chuyển hướng về trang login
            header('Location: login.php');
            exit;
        } else {
            // Fingerprint không khớp: vẫn tiến hành hủy session để an toàn
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '');
            }
            session_destroy();
            header('Location: login.php');
            exit;
        }
    }
}

// Nếu GET: hiển thị form xác nhận logout
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đăng xuất - LaptopShop.vn</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="./css/style.css">
  <style>
    body { font-family:'Segoe UI', Arial, sans-serif; background:#f6f7f8; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .box { background:#fff; padding:22px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); max-width:420px; width:100%; text-align:center; }
    .box h2 { color:#b70000; margin-bottom:12px; }
    .box p { color:#333; margin-bottom:18px; }
    .box form { display:flex; gap:10px; justify-content:center; }
    .btn { padding:10px 16px; border-radius:6px; border:none; cursor:pointer; font-weight:600; }
    .btn-danger { background:#b70000; color:#fff; }
    .btn-cancel { background:#f1f1f1; color:#333; text-decoration:none; display:inline-block; padding:10px 14px; border-radius:6px; }
    .msg { color:#c00; margin-bottom:10px; background:#ffecec; padding:8px; border-radius:6px; border:1px solid #ffd6d6; }
  </style>
</head>
<body>
  <div class="box" role="main" aria-labelledby="logout-title">
    <h2 id="logout-title">Đăng xuất</h2>

    <?php if ($msg): ?>
      <div class="msg"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($loggedIn): ?>
      <p>Bạn có chắc muốn đăng xuất khỏi tài khoản <strong><?= e($_SESSION['username'] ?? '') ?></strong> không?</p>
      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <button type="submit" class="btn btn-danger">Đăng xuất</button>
        <a href="home.php" class="btn-cancel">Hủy</a>
      </form>
    <?php else: ?>
      <p>Bạn hiện chưa đăng nhập.</p>
      <a href="login.php" class="btn-cancel">Quay về đăng nhập</a>
    <?php endif; ?>
  </div>
</body>
</html>
