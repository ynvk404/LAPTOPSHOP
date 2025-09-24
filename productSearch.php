<?php
require_once './config/config_session.php';

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");

include './libs/db.php';

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$cat_id  = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;

if ($keyword === '') {
  header("Location: home.php");
  exit;
}

$like = "%" . mb_strtolower($keyword, 'UTF-8') . "%";

// Query sản phẩm
if ($cat_id > 0) {
  $stmt = $conn->prepare("SELECT id,name,price,image,description 
                             FROM product
                             WHERE category_id=? AND LOWER(name) LIKE ?");
  $stmt->bind_param("is", $cat_id, $like);
} else {
  $stmt = $conn->prepare("SELECT id,name,price,image,description 
                             FROM product
                             WHERE LOWER(name) LIKE ?");
  $stmt->bind_param("s", $like);
}
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

// dữ liệu sidebar + search select
$catsAll = $conn->query("SELECT id, name FROM category ORDER BY name");
$catsSidebar = $conn->query("SELECT id, name FROM category ORDER BY name");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8" />
  <title>Kết quả tìm kiếm - LaptopShop.vn</title>
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

        <!-- khối phải -->
        <div class="right-side">
          <!-- search -->
          <form action="productSearch.php" method="get" class="search-form">
            <input type="text" name="keyword" placeholder="Nhập thông tin tìm kiếm..."
              value="<?= htmlspecialchars($keyword) ?>" required>
            <select name="cat_id">
              <option value="0">Tất cả danh mục</option>
              <?php while ($c = $catsAll->fetch_assoc()): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $c['id'] == $cat_id ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['name']) ?>
                </option>
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
      <div class="block-title">Kết quả cho “<?= htmlspecialchars($keyword) ?>”</div>
      <?php if ($products->num_rows > 0): ?>
        <?php while ($p = $products->fetch_assoc()): ?>
          <div class="product">
            <a href="productDetail.php?id=<?= (int)$p['id'] ?>">
              <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
            </a>
            <div>
              <h3><a href="productDetail.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></h3>
              <div class="desc"><?= htmlspecialchars($p['description']) ?></div>
            </div>
            <div class="price"><?= number_format((float)$p['price'], 0, ',', '.') ?> đ</div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p>Không tìm thấy sản phẩm phù hợp.</p>
      <?php endif; ?>

      <!-- Banner -->
      <div class="ad-banner">
        <a href="#"><img src="images/OIP.webp" alt="Khuyến mãi đặc biệt"></a>
      </div>
    </main>
  </div>

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