<?php
// hoso.php - Hồ sơ người dùng
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

// Lấy thông tin user
$stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Lấy danh mục cho sidebar + search select
$catsAll = $conn->query("SELECT id, name FROM category ORDER BY name");
$catsSidebar = $conn->query("SELECT id, name FROM category ORDER BY name");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8" />
  <title>Hồ sơ cá nhân - LaptopShop.vn</title>
  <link rel="stylesheet" href="./css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .profile-box {
      background:#fff;
      padding:24px;
      border-radius:10px;
      box-shadow:0 6px 20px rgba(0,0,0,0.08);
      max-width:600px;
      margin:0 auto;
    }
    .profile-box h2 {
      color:#b70000;
      margin-bottom:16px;
      border-bottom:2px solid #b70000;
      padding-bottom:6px;
    }
    .profile-box dl {margin:0;}
    .profile-box dt {font-weight:600;margin-top:12px;color:#333;}
    .profile-box dd {margin:4px 0 12px 0;color:#555;}
    .profile-actions {margin-top:20px;}
    .profile-actions a {
      display:inline-block;
      padding:10px 16px;
      margin:6px;
      border-radius:6px;
      font-weight:600;
      text-decoration:none;
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

        <!-- Khối phải -->
        <div class="right-side">
          <!-- Search -->
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

          <!-- User menu -->
          <div class="user-menu">
            <?php if (!empty($_SESSION['user_id'])): ?>
              <span>Xin Chào, <b><?= e($_SESSION['username']) ?></b></span>
              <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin/dashboard.php">Quản Trị</a>
              <?php endif; ?>
              <a href="hoso.php" class="active">Hồ Sơ</a>
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
      <div class="profile-box">
        <h2>Hồ sơ cá nhân</h2>
        <dl>
          <dt>Tên đăng nhập:</dt>
          <dd><?= e($user['username']) ?></dd>

          <dt>Email:</dt>
          <dd><?= e($user['email']) ?></dd>

          <dt>Quyền hạn:</dt>
          <dd><?= e($user['role']) ?></dd>

          <dt>Ngày tạo tài khoản:</dt>
          <dd><?= e($user['created_at']) ?></dd>
        </dl>

        <div class="profile-actions">
          <a href="edit.php" class="btn-primary">Thay đổi thông tin</a>
          <a href="home.php" class="btn-secondary">Trang chủ</a>
        </div>
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
