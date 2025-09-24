<?php
require_once './config/config_session.php';
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");

include './libs/db.php';

// Khởi tạo giỏ hàng nếu chưa có
if (!isset($_SESSION['cart'])) {
  $_SESSION['cart'] = [];
}

// Xử lý thêm vào giỏ hàng (chỉ cho phép khi đã đăng nhập)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {

  if (!isset($_SESSION['user_id'])) {
    // Nếu chưa đăng nhập thì chuyển về login
    header("Location: login.php");
    exit;
  }

  $pid = (int)$_POST['product_id'];
  $qty = max(1, (int)$_POST['quantity']); // ít nhất 1

  // Lấy thông tin sản phẩm từ DB
  $stmt = $conn->prepare("SELECT id, name, price, image, description FROM product WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $pid);
  $stmt->execute();
  $product = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($product) {
    if (isset($_SESSION['cart'][$pid])) {
      $_SESSION['cart'][$pid]['quantity'] += $qty;
    } else {
      $_SESSION['cart'][$pid] = [
        'id'          => $product['id'],
        'name'        => $product['name'],
        'price'       => $product['price'],
        'image'       => $product['image'],
        'description' => $product['description'],
        'quantity'    => $qty
      ];
    }
  }

  header("Location: cart.php");
  exit;
}

// Xử lý cập nhật số lượng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
  foreach ($_POST['qty'] as $pid => $qty) {
    $pid = (int)$pid;
    $qty = max(0, (int)$qty);
    if ($qty === 0) {
      unset($_SESSION['cart'][$pid]);
    } else {
      $_SESSION['cart'][$pid]['quantity'] = $qty;
    }
  }
  header("Location: cart.php");
  exit;
}

// Xử lý xóa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove'])) {
  $pid = (int)$_POST['remove'];
  unset($_SESSION['cart'][$pid]);
  header("Location: cart.php");
  exit;
}

// Danh mục sidebar + search
$catsAll = $conn->query("SELECT id, name FROM category ORDER BY name");
$catsSidebar = $conn->query("SELECT id, name FROM category ORDER BY name");

// Tính tổng
$total = 0;
foreach ($_SESSION['cart'] as $item) {
  $total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Giỏ hàng - LaptopShop.vn</title>
  <link rel="stylesheet" href="./css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

  <!-- Header -->
  <header>
    <div class="wrap header-flex">
      <a href="home.php" class="logo"><img src="images/logo.png" alt="LaptopShop.vn"></a>
      <h1>LaptopShop.vn</h1>
    </div>
  </header>

  <!-- Nav -->
  <nav>
    <div class="wrap">
      <div class="inner">
        <div class="menu">
          <a href="home.php">Trang chủ</a>
          <a href="huongdan.php">Hướng dẫn</a>
          <a href="gioithieu.php">Giới thiệu</a>
          <a href="tuyendung.php">Tuyển dụng</a>
          <a href="lienhe.php">Liên hệ</a>
        </div>

        <div class="right-side">
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

          <div class="user-menu">
            <?php if (isset($_SESSION['user_id'])): ?>
              <span>Xin chào, <b><?= htmlspecialchars($_SESSION['username']) ?></b></span>

              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin/dashboard.php"> Quản trị</a>
              <?php endif; ?>

              <a href="profile.php">Hồ sơ</a>
              <a href="cart.php" class="active">Giỏ hàng</a>
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
      <div class="ads">
        <a href="#"><img src="images/06_May2883a0e2dbcea7a11b941e02d2852f5d.jpg" alt="Khuyến mãi Dell"></a>
        <a href="#"><img src="images/14_Jula9b69bef163d1be102bba0d850a3ec59.jpg" alt="Sale HP-Compaq"></a>
      </div>
    </aside>

    <!-- Content -->
    <main class="content">
      <h2>Giỏ hàng của bạn</h2>
      <?php if (!empty($_SESSION['cart'])): ?>
        <form method="post">
          <table class="cart-table">
            <tr>
              <th>Ảnh</th>
              <th>Sản phẩm</th>
              <th>Mô tả</th>
              <th>Giá</th>
              <th>Số lượng</th>
              <th>Tổng</th>
              <th>Xóa</th>
            </tr>
            <?php foreach ($_SESSION['cart'] as $item): ?>
              <tr>
                <td><img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>"></td>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td style="max-width:200px;"><?= htmlspecialchars($item['description'] ?? '') ?></td>
                <td><?= number_format($item['price'], 0, ',', '.') ?> đ</td>
                <td><input type="number" name="qty[<?= $item['id'] ?>]" value="<?= $item['quantity'] ?>" min="0" style="width:60px"></td>
                <td><?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?> đ</td>
                <td>
                  <button type="submit" name="remove" value="<?= $item['id'] ?>" class="btn btn-remove">
                    <i class="fas fa-trash"></i> Xóa
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td colspan="5" style="text-align:right"><b>Tổng cộng:</b></td>
              <td><b><?= number_format($total, 0, ',', '.') ?> đ</b></td>
              <td></td>
            </tr>
          </table>
          <div class="cart-actions">
            <button type="submit" name="update" class="btn btn-update"><i class="fas fa-sync"></i> Cập nhật</button>
            <a href="checkout.php" class="btn btn-checkout"><i class="fas fa-credit-card"></i> Thanh toán</a>
          </div>
        </form>
      <?php else: ?>
        <p>Giỏ hàng của bạn đang trống. <a href="home.php">Mua sắm ngay</a>.</p>
      <?php endif; ?>
    </main>
  </div>

  <!-- Footer -->
  <footer>
    <div class="wrap">
      <p>&copy; <?= date('Y') ?> LaptopShop.vn - Tất cả các quyền được thuộc về youngnvk.</p>
      <p>Email: <a href="mailto:support@laptopshop.vn">support@laptopshop.vn</a> | Hotline: <a href="tel:19001234">1900-1234</a></p>
      <div class="social-links">
        <a href="https://facebook.com" target="_blank"><i class="fab fa-facebook"></i></a>
        <a href="https://youtube.com" target="_blank"><i class="fab fa-youtube"></i></a>
        <a href="https://zalo.me" target="_blank"><i class="fa-solid fa-comment"></i></a>
      </div>
    </div>
  </footer>

</body>

</html>
