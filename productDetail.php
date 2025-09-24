<?php
require_once './config/config_session.php';

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");

include './libs/db.php';

// Lấy id sản phẩm
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT * FROM product WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Danh mục cho sidebar + search
$catsAll = $conn->query("SELECT id, name FROM category ORDER BY name");
$catsSidebar = $conn->query("SELECT id, name FROM category ORDER BY name");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8" />
  <title><?= $product ? htmlspecialchars($product['name']) : "Chi tiết sản phẩm" ?> - LaptopShop.vn</title>
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

  <!-- Nav -->
  <nav>
    <div class="wrap">
      <div class="inner">
        <!-- menu trái -->
        <div class="menu">
          <a href="home.php">Trang chủ</a>
          <a href="huongdan.php">Hướng dẫn</a>
          <a href="gioithieu.php">Giới thiệu</a>
          <a href="tuyendung.php">Tuyển dụng</a>
          <a href="lienhe.php">Liên hệ</a>
        </div>

        <!-- khối phải: search + user -->
        <div class="right-side">
          <!-- search -->
          <form action="productSearch.php" method="get" class="search-form">
            <input type="text" name="keyword" placeholder="Nhập thông tin tìm kiếm..." required>
            <select name="cat_id">
              <option value="0">Tất cả danh mục</option>
              <?php while ($c = $catsAll->fetch_assoc()): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endwhile; ?>
            </select>
            <button type="submit">Tìm kiếm</button>
          </form>

          <!-- user menu -->
          <div class="user-menu">
            <?php if (isset($_SESSION['user_id'])): ?>
              <span>Xin chào, <b><?= htmlspecialchars($_SESSION['username']) ?></b></span>

              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin/dashboard.php">Quản trị</a>
              <?php endif; ?>

              <a href="profile.php">Hồ sơ</a>
              <a href="cart.php">Giỏ hàng</a>
              <a href="logout.php">Đăng xuất</a>
            <?php else: ?>
              <a href="login.php">Đăng nhập</a>
              <a href="register.php">Đăng ký</a>
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

      <!-- ads -->
      <div class="ads">
        <a href="#"><img src="images/06_May2883a0e2dbcea7a11b941e02d2852f5d.jpg" alt="Khuyến mãi Dell"></a>
        <a href="#"><img src="images/14_Jula9b69bef163d1be102bba0d850a3ec59.jpg" alt="Sale HP-Compaq"></a>
      </div>
    </aside>

    <!-- Content -->
    <main class="content">
      <?php if ($product): ?>
        <div class="detail-wrap">
          <div class="detail-img">
            <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
          </div>
          <div class="detail-info">
            <h2><?= htmlspecialchars($product['name']) ?></h2>
            <div class="price-big"><?= number_format((float)$product['price'], 0, ',', '.') ?> đ</div>
            <div class="desc"><?= nl2br(htmlspecialchars($product['description'])) ?></div>

            <!-- specs -->
            <table class="specs">
              <tr>
                <th>CPU</th>
                <td>Intel / AMD tuỳ model</td>
              </tr>
              <tr>
                <th>RAM</th>
                <td>8GB / 16GB DDR4</td>
              </tr>
              <tr>
                <th>Ổ cứng</th>
                <td>SSD NVMe 256GB - 1TB</td>
              </tr>
              <tr>
                <th>Màn hình</th>
                <td>13" - 16" Full HD / Retina</td>
              </tr>
              <tr>
                <th>Trọng lượng</th>
                <td>1.2kg - 2.3kg</td>
              </tr>
              <tr>
                <th>Bảo hành</th>
                <td>12 - 24 tháng chính hãng</td>
              </tr>
            </table>

            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user' || 'admin'): ?>
              <!-- Form thêm giỏ hàng -->
              <form action="cart.php" method="post" style="margin-top:15px;">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <label for="qty">Số lượng:</label>
                <input type="number" name="quantity" id="qty" value="1" min="1" style="width:60px;">
                <button type="submit" class="btn-add-cart">
                  <i class="fas fa-cart-plus"></i> Thêm vào giỏ hàng
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <p>Không tìm thấy sản phẩm!</p>
      <?php endif; ?>

      <!-- Banner -->
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
  </footer>>

</body>

</html>