<?php
require_once './config/config_session.php';

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");

include './libs/db.php';

// Hàm escape
function e($str)
{
    return htmlspecialchars($str ?? "", ENT_QUOTES, "UTF-8");
}

// Lấy danh mục cho sidebar + search select
$catsAll = $conn->query("SELECT id, name FROM category ORDER BY name");
$catsSidebar = $conn->query("SELECT id, name FROM category ORDER BY name");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8" />
    <title>Trang chủ - LaptopShop.vn</title>
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

    <!-- Menu + f + User -->
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
                    <!-- Thanh tìm kiếm -->
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
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <span>Xin Chào, <b><?= e($_SESSION['username']) ?></b></span>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="admin.php">Quản Trị</a>
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
                    <li><a href="productList.php?cat_id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a></li>
                <?php endwhile; ?>
            </ul>

            <!-- Quảng cáo bên sidebar -->
            <div class="ads">
                <a href="#"><img src="images/06_May2883a0e2dbcea7a11b941e02d2852f5d.jpg" alt="Khuyến mãi Dell"></a>
                <a href="#"><img src="images/14_Jula9b69bef163d1be102bba0d850a3ec59.jpg" alt="Sale HP-Compaq"></a>
            </div>
        </aside>

        <!-- Content -->
        <main class="content">
            <!-- Banner lớn giữa trang -->
            <div class="ad-banner">
                <a href="#"><img src="images/download.webp" alt="Big Sale Laptop 2025"></a>
            </div>

            <?php
            // Cho mỗi category → lấy 2 sp mới nhất
            $catsForBlock = $conn->query("SELECT id, name FROM category ORDER BY name");
            while ($cat = $catsForBlock->fetch_assoc()):
                $stmt = $conn->prepare("SELECT id,name,price,image,description 
                                    FROM product 
                                    WHERE category_id=? 
                                    ORDER BY created_at DESC, id DESC LIMIT 2");
                $stmt->bind_param("i", $cat['id']);
                $stmt->execute();
                $rs = $stmt->get_result();
                if ($rs->num_rows === 0) continue;
            ?>
                <div class="block">
                    <div class="block-title"><?= e($cat['name']) ?></div>
                    <?php while ($p = $rs->fetch_assoc()): ?>
                        <div class="product">
                            <a href="productDetail.php?id=<?= (int)$p['id'] ?>">
                                <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>">
                            </a>
                            <div>
                                <h3><a href="productDetail.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></h3>
                                <div class="desc"><?= e($p['description']) ?></div>
                            </div>
                            <div class="price"><?= number_format((float)$p['price'], 0, ',', '.') ?> đ</div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Banner nhỏ chèn giữa các danh mục -->
                <div class="ad-banner">
                    <a href="#"><img src="images/OIP.webp" alt="Khuyến mãi giữa danh mục"></a>
                </div>
            <?php endwhile; ?>
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