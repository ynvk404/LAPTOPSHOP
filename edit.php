<?php
// edit.php - Cập nhật hồ sơ cá nhân + đổi mật khẩu (giữ giao diện giống home.php)
require_once './config/config_session.php';
require_once './libs/db.php';

// ---- Security headers ----
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");

// Escape
function e($str) {
    return htmlspecialchars($str ?? "", ENT_QUOTES, "UTF-8");
}

// Kiểm tra đăng nhập
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lấy thông tin user hiện tại
$stmt = $conn->prepare("SELECT id, username, email, password FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$msg = '';
$success = false;

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $msg = 'Yêu cầu không hợp lệ (CSRF).';
    } else {
        // Lấy dữ liệu form (safe)
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate username & email
        if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 100) {
            $msg = 'Tên đăng nhập không hợp lệ (3-100 ký tự).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Email không hợp lệ.';
        } else {
            // Optional: check username unique (exclude current user)
            $chk = $conn->prepare("SELECT id FROM users WHERE username=? AND id<>? LIMIT 1");
            if ($chk) {
                $chk->bind_param("si", $username, $_SESSION['user_id']);
                $chk->execute();
                $resChk = $chk->get_result();
                if ($resChk && $resChk->num_rows > 0) {
                    $msg = 'Tên đăng nhập đã được sử dụng bởi người khác.';
                    $chk->close();
                } else {
                    $chk->close();
                    // Determine whether user wants to change password
                    $changePassword = ($new_password !== '' || $confirm_password !== '' || $current_password !== '');

                    if ($changePassword) {
                        // Require current password
                        if ($current_password === '') {
                            $msg = 'Vui lòng nhập mật khẩu hiện tại để thực hiện thay đổi mật khẩu.';
                        } elseif (!password_verify($current_password, $user['password'])) {
                            $msg = 'Mật khẩu hiện tại không đúng.';
                        } elseif (mb_strlen($new_password) < 8) {
                            $msg = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
                        } elseif ($new_password !== $confirm_password) {
                            $msg = 'Mật khẩu mới và xác nhận không khớp.';
                        } else {
                            // All good — hash new password and update username,email,password
                            $newHash = password_hash($new_password, PASSWORD_DEFAULT);
                            $upd = $conn->prepare("UPDATE users SET username=?, email=?, password=? WHERE id=?");
                            if ($upd) {
                                $upd->bind_param("sssi", $username, $email, $newHash, $_SESSION['user_id']);
                                if ($upd->execute()) {
                                    $success = true;
                                    $msg = "Cập nhật thông tin và mật khẩu thành công!";
                                    // security: regenerate session id
                                    session_regenerate_id(true);
                                    $_SESSION['username'] = $username;
                                    // reset csrf token
                                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                                    // update local $user
                                    $user['username'] = $username;
                                    $user['email'] = $email;
                                    $user['password'] = $newHash;
                                } else {
                                    $msg = "Có lỗi khi cập nhật. Vui lòng thử lại.";
                                }
                                $upd->close();
                            } else {
                                $msg = "Không thể chuẩn bị truy vấn cập nhật.";
                            }
                        }
                    } else {
                        // Only update username & email
                        $upd = $conn->prepare("UPDATE users SET username=?, email=? WHERE id=?");
                        if ($upd) {
                            $upd->bind_param("ssi", $username, $email, $_SESSION['user_id']);
                            if ($upd->execute()) {
                                $success = true;
                                $msg = "Cập nhật thông tin thành công!";
                                session_regenerate_id(true);
                                $_SESSION['username'] = $username;
                                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                                $user['username'] = $username;
                                $user['email'] = $email;
                            } else {
                                $msg = "Có lỗi khi cập nhật. Vui lòng thử lại.";
                            }
                            $upd->close();
                        } else {
                            $msg = "Không thể chuẩn bị truy vấn cập nhật.";
                        }
                    }
                }
            } else {
                $msg = 'Lỗi hệ thống khi kiểm tra tên đăng nhập.';
            }
        }
    }
}

// Lấy danh mục cho sidebar + search select
$catsAll = $conn->query("SELECT id, name FROM category ORDER BY name");
$catsSidebar = $conn->query("SELECT id, name FROM category ORDER BY name");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8" />
    <title>Chỉnh sửa hồ sơ - LaptopShop.vn</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
      .edit-box {
        background:#fff;
        padding:24px;
        border-radius:10px;
        box-shadow:0 6px 20px rgba(0,0,0,0.08);
        max-width:600px;
        margin:20px auto;
      }
      .edit-box h2 {color:#b70000;margin-bottom:16px;}
      .edit-box label {display:block;margin:10px 0 4px;font-weight:600;}
      .edit-box input[type="text"],
      .edit-box input[type="email"],
      .edit-box input[type="password"] {
        width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;
      }
      .note {font-size:0.9rem;color:#666;margin-top:6px;}
      .msg {margin:12px 0;padding:10px;border-radius:6px;}
      .msg.error {background:#ffe6e6;color:#c00;border:1px solid #ffcccc;}
      .msg.success {background:#e6ffe6;color:#060;border:1px solid #b2d8b2;}
      .actions {margin-top:18px;}
      .actions button,.actions a {
        display:inline-block;padding:10px 16px;margin:6px;border-radius:6px;
        text-decoration:none;font-weight:600;
      }
      .btn-primary {background:#b70000;color:#fff;}
      .btn-primary:hover {background:#900;}
      .btn-secondary {background:#eee;color:#333;}
      .btn-secondary:hover {background:#ccc;}
    </style>
</head>

<body>

    <!-- Header -->
    <header>
        <div class="wrap header-flex">
            <a href="home.php" class="logo">
                <img src="images/logo.png" alt="LaptopShop.vn">
            </a>
            <h1>LaptopShop.vn</h1>
        </div>
    </header>

    <!-- Menu + Search + User -->
    <nav>
        <div class="wrap">
            <div class="inner">
                <!-- Menu trái -->
                <div class="menu">
                    <a href="home.php">Trang Chủ</a>
                    <a href="huongdan.php">Hướng Dẫn</a>
                    <a href="gioithieu.php">Giới Thiệu</a>
                    <a href="tuyendung.php">Tuyển Dụng</a>
                    <a href="lienhe.php">Liên Hệ</a>
                </div>

                <!-- Khối phải: Search + User -->
                <div class="right-side">
                    <form action="productSearch.php" method="get" class="search-form">
                        <input type="text" name="keyword" placeholder="Nhập thông tin tìm kiếm..." required>
                        <select name="cat_id">
                            <option value="0">Tất cả danh mục</option>
                            <?php while ($c = $catsAll->fetch_assoc()): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit">Tìm Kiếm</button>
                    </form>

                    <div class="user-menu">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <span>Xin Chào, <b><?= e($_SESSION['username']) ?></b></span>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="admin.php">Quản Trị</a>
                            <?php endif; ?>
                            <a href="hoso.php">Hồ Sơ</a>
                            <a href="cart.php">Giỏ Hàng</a>
                            <a href="logout.php">Đăng Xuất</a>
                        <?php else: ?>
                            <a href="login.php">Đăng Nhập</a>
                            <a href="register.php">Đăng Ký</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h3>Danh mục</h3>
            <ul>
                <?php while ($c = $catsSidebar->fetch_assoc()): ?>
                    <li><a href="productList.php?cat_id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a></li>
                <?php endwhile; ?>
            </ul>
            <div class="ads">
                <a href="#"><img src="images/06_May2883a0e2dbcea7a11b941e02d2852f5d.jpg" alt="Khuyến mãi Dell"></a>
                <a href="#"><img src="images/14_Jula9b69bef163d1be102bba0d850a3ec59.jpg" alt="Sale HP-Compaq"></a>
            </div>
        </aside>

        <!-- Content -->
        <main class="content">
          <div class="edit-box">
            <h2>Chỉnh sửa hồ sơ</h2>
            <?php if ($msg): ?>
              <div class="msg <?= $success ? 'success' : 'error' ?>"><?= e($msg) ?></div>
            <?php endif; ?>
            <form method="post" action="">
              <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
              
              <label for="username">Tên đăng nhập</label>
              <input type="text" id="username" name="username" value="<?= e($user['username']) ?>" maxlength="100" required>

              <label for="email">Email</label>
              <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" maxlength="254" required>

              <hr style="margin:16px 0;border:none;border-top:1px solid #eee;">

              <h3 style="margin-top:0;color:#333;">Đổi mật khẩu (tùy chọn)</h3>
              <label for="current_password">Mật khẩu hiện tại</label>
              <input type="password" id="current_password" name="current_password" autocomplete="current-password">

              <label for="new_password">Mật khẩu mới</label>
              <input type="password" id="new_password" name="new_password" autocomplete="new-password">
              <div class="note">Mật khẩu mới ít nhất 8 ký tự. Nếu không muốn đổi mật khẩu, để trống cả 3 trường.</div>

              <label for="confirm_password">Xác nhận mật khẩu mới</label>
              <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">

              <div class="actions">
                <button type="submit" class="btn-primary">Lưu thay đổi</button>
                <a href="hoso.php" class="btn-secondary">Quay lại</a>
              </div>
            </form>
          </div>
        </main>
    </div>

    <!-- Footer -->
    <footer>
        <div class="wrap">
            <p>&copy; <?= date('Y') ?> LaptopShop.vn - Tất cả các quyền được thuộc về youngnvk.</p>
            <p>Email: <a href="mailto:support@laptopshop.vn">support@laptopshop.vn</a> |
                Hotline: <a href="tel:19001234">1900-1234</a></p>
            <div class="social-links">
                <a href="https://facebook.com" target="_blank"><i class="fab fa-facebook"></i></a>
                <a href="https://youtube.com" target="_blank"><i class="fab fa-youtube"></i></a>
                <a href="https://zalo.me" target="_blank"><i class="fa-solid fa-comment"></i></a>
            </div>
        </div>
    </footer>

</body>
</html>
