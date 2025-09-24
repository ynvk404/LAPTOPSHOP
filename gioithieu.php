<?php
require_once './config/config_session.php';

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");

include './libs/db.php';

// Lấy danh mục
$catsSidebar = $conn->query("SELECT id, name FROM category ORDER BY name");
$catsAll = $conn->query("SELECT id, name FROM category ORDER BY name");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Giới thiệu - LaptopShop.vn</title>
  <link rel="stylesheet" href="./css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
          <a href="gioithieu.php" class="active">Giới Thiệu</a>
          <a href="tuyendung.php">Tuyển Dụng</a>
          <a href="lienhe.php">Liên Hệ</a>
        </div>

        <!-- Khối phải: search + user -->
        <div class="right-side">
          <!-- Tìm kiếm -->
          <form action="productSearch.php" method="get" class="search-form">
            <input type="text" name="keyword" placeholder="Nhập thông tin tìm kiếm..." required>
            <select name="cat_id">
              <option value="0">Tất cả danh mục</option>
              <?php while ($c = $catsAll->fetch_assoc()): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endwhile; ?>
            </select>
            <button type="submit">Tìm Kiếm</button>
          </form>

          <!-- User menu -->
          <div class="user-menu">
            <?php if (isset($_SESSION['user_id'])): ?>
              <span>Xin Chào, <b><?= htmlspecialchars($_SESSION['username']) ?></b></span>

              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
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

  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h3>Danh mục</h3>
      <ul>
        <?php while ($c = $catsSidebar->fetch_assoc()): ?>
          <li><a href="productList.php?cat_id=<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></li>
        <?php endwhile; ?>
      </ul>

      <!-- Quảng cáo bên sidebar -->
      <div class="ads">
        <a href="#"><img src="images/06_May2883a0e2dbcea7a11b941e02d2852f5d.jpg" alt="Khuyến mãi Dell"></a>
        <a href="#"><img src="images/14_Jula9b69bef163d1be102bba0d850a3ec59.jpg" alt="Sale HP-Compaq"></a>
      </div>
    </aside>

    <!-- Content -->
    <main class="content-gioithieu">
      <h2>Về chúng tôi</h2>
      <p>LaptopShop.vn được thành lập năm 2012 với sứ mệnh mang đến cho khách hàng những sản phẩm laptop chất lượng cao, giá cả hợp lý và dịch vụ hậu mãi tận tâm.</p>

      <h3>Thành tựu</h3>
      <ul>
        <li>Hơn 50.000 khách hàng đã tin tưởng và mua sắm tại LaptopShop.vn.</li>
        <li>Top 10 cửa hàng laptop trực tuyến uy tín nhất Việt Nam năm 2023.</li>
        <li>Đối tác chiến lược của Dell, HP, Asus, Acer và Apple.</li>
      </ul>

      <h3>Giá trị cốt lõi</h3>
      <p>
        <strong>Uy tín:</strong> Luôn đặt chất lượng và sự hài lòng của khách hàng lên hàng đầu.<br>
        <strong>Chuyên nghiệp:</strong> Đội ngũ nhân viên kỹ thuật, tư vấn được đào tạo bài bản.<br>
        <strong>Tận tâm:</strong> Hỗ trợ khách hàng 24/7.
      </p>

      <!-- Banner quảng cáo lớn -->
      <div class="ad-banner">
        <a href="#"><img src="images/download.webp" alt="Big Sale Laptop 2025"></a>
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