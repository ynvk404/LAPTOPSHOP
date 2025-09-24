<?php
// lienhe.php - trang Liên hệ (đã harden cơ bản)
require_once './config/config_session.php'; // đảm bảo session_start(), $secretKey, $secureFlag...
include './libs/db.php'; // $conn là mysqli connection

// SECURITY HEADERS (giữ các header hiện có + CSP cơ bản)
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");
// Content Security Policy cơ bản — điều chỉnh nguồn extern nếu cần
header("Content-Security-Policy: default-src 'self'; frame-src https://www.google.com; img-src 'self' data: https:; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com;");

// Helper: escape output
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Ensure CSRF token exists for feedback form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch categories (simple select). Use try/catch-like protections and free result
$catsSidebar = [];
$catsAll = [];

$query = "SELECT id, name FROM category ORDER BY name";
if ($res = $conn->query($query)) {
    while ($row = $res->fetch_assoc()) {
        // Normalize types
        $row['id'] = (int)$row['id'];
        $catsAll[] = $row;
        $catsSidebar[] = $row;
    }
    $res->free();
} else {
    // Nếu lỗi DB: log (file/app log) và tiếp tục hiển thị trang rỗng danh mục
    error_log("DB error fetching categories: " . $conn->error);
}

// Determine selected category for search (sanitise)
$selected_cat = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$old_keyword = isset($_GET['keyword']) ? substr((string)$_GET['keyword'], 0, 200) : ''; // limit length

?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Liên hệ - LaptopShop.vn</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="./css/style.css">
  <!-- Chỉ load fontawesome từ CDN tin cậy đã được whitelist trong CSP -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
          <a href="lienhe.php" class="active">Liên Hệ</a>
        </div>

        <!-- Khối phải: search + user -->
        <div class="right-side">
          <!-- Tìm kiếm -->
          <form action="productSearch.php" method="get" class="search-form" autocomplete="off" role="search" aria-label="Tìm kiếm sản phẩm">
            <input type="text" name="keyword" placeholder="Nhập thông tin tìm kiếm..." required maxlength="200" value="<?= e($old_keyword) ?>">
            <select name="cat_id" aria-label="Danh mục">
              <option value="0" <?= $selected_cat === 0 ? 'selected' : '' ?>>Tất cả danh mục</option>
              <?php foreach ($catsAll as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $selected_cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit">Tìm Kiếm</button>
          </form>

          <!-- User menu -->
          <div class="user-menu" aria-live="polite">
            <?php if (!empty($_SESSION['user_id'])): ?>
              <span>Xin Chào, <b><?= e($_SESSION['username'] ?? '') ?></b></span>

              <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin/dashboard.php">Quản Trị</a>
              <?php endif; ?>

              <a href="profile.php">Hồ Sơ</a>
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

  <div class="container" role="main">
    <!-- Sidebar -->
    <aside class="sidebar" aria-labelledby="danhmuc-title">
      <h3 id="danhmuc-title">Danh Mục</h3>
      <ul>
        <?php foreach ($catsSidebar as $c): ?>
          <li><a href="productList.php?cat_id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>

      <!-- Quảng cáo: chỉ dùng ảnh từ server -->
      <div class="ads" aria-hidden="true">
        <a href="#"><img src="images/06_May2883a0e2dbcea7a11b941e02d2852f5d.jpg" alt="Khuyến mãi Dell"></a>
        <a href="#"><img src="images/14_Jula9b69bef163d1be102bba0d850a3ec59.jpg" alt="Sale HP-Compaq"></a>
      </div>
    </aside>

    <!-- Content -->
    <main class="content-lienhe">
      <div class="lienhe-container">
        <!-- Thông tin liên hệ bên trái -->
        <div class="lienhe-info">
          <h2>Liên hệ với LaptopShop.vn</h2>
          <p><strong>Địa chỉ:</strong> 123 Trần Duy Hưng, Cầu Giấy, Hà Nội</p>
          <p><strong>Hotline:</strong> 0909 123 456 (Hỗ trợ 24/7)</p>
          <p><strong>Email:</strong> <a href="mailto:<?= e('support@laptopshop.vn') ?>"><?= e('support@laptopshop.vn') ?></a></p>

          <h3>Giờ làm việc</h3>
          <ul>
            <li>Thứ 2 - Thứ 7: 8h00 - 21h00</li>
            <li>Chủ nhật &amp; ngày lễ: 9h00 - 18h00</li>
          </ul>
        </div>

        <!-- Bản đồ bên phải (iframe sandboxed) -->
        <div class="lienhe-map">
          <h3><strong style="color: #900000;">Bản đồ</strong></h3>
          <p><strong>Bạn có thể ghé trực tiếp cửa hàng theo bản đồ dưới đây:</strong></p>
          <br>
          <!--
            Lưu ý: thay src bằng URL embed chính xác từ Google Maps.
            Thêm sandbox để giới hạn permission. Nếu cần interactive map đầy đủ, cân nhắc whitelist domain trong CSP.
          -->
          <iframe
            title="Bản đồ LaptopShop.vn"
            src="https://www.google.com/maps/embed?pb=..." 
            width="100%" height="300" style="border:0;"
            allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"
            sandbox="allow-scripts allow-same-origin allow-popups">
          </iframe>
        </div>
      </div>

      <!-- Form góp ý: có CSRF token, maxlength, và aria -->
      <div class="lienhe-form" aria-labelledby="form-gopy-title">
        <h3 id="form-gopy-title">Góp ý / Liên hệ trực tuyến</h3>
        <form action="sendfeedback.php" method="post" novalidate autocomplete="off" aria-describedby="form-note">
          <!-- CSRF token -->
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

          <div class="form-group">
            <label for="name">Họ và tên</label>
            <input type="text" id="name" name="name" required maxlength="150" pattern=".{2,150}" title="Nhập họ tên (từ 2 đến 150 ký tự)">
          </div>

          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required maxlength="254">
          </div>

          <div class="form-group">
            <label for="message">Nội dung góp ý</label>
            <textarea id="message" name="message" rows="5" required maxlength="2000" title="Nội dung tối đa 2000 ký tự"></textarea>
          </div>

          <p id="form-note" style="font-size:0.9rem;color:#555;">Chúng tôi sẽ kiểm duyệt nội dung trước khi hiển thị. Vui lòng không gửi mã độc hoặc thông tin nhạy cảm.</p>

          <button type="submit" class="btn-submit">Gửi góp ý</button>
        </form>
      </div>
    </main>

  </div>

  <!-- Footer -->
  <footer>
    <div class="wrap">
      <p>&copy; <?= date('Y') ?> LaptopShop.vn - Tất cả các quyền được thuộc về youngnvk.</p>
      <p>Email: <a href="mailto:<?= e('support@laptopshop.vn') ?>">support@laptopshop.vn</a> |
        Hotline: <a href="tel:19001234">1900-1234</a></p>

      <div class="social-links" aria-hidden="true">
        <a href="https://facebook.com" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook"></i></a>
        <a href="https://youtube.com" target="_blank" rel="noopener noreferrer"><i class="fab fa-youtube"></i></a>
        <a href="https://zalo.me" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-comment"></i></a>
      </div>
    </div>
  </footer>

</body>

</html>
