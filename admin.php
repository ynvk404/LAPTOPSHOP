<?php
require_once './config/config_session.php';
require_once './libs/db.php';

/* -------------------- Security headers -------------------- */
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: no-referrer-when-downgrade");
header("Permissions-Policy: interest-cohort=()");
header("X-XSS-Protection: 1; mode=block"); // với browser cũ

/* -------------------- Helpers -------------------- */
function e($str)
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* Whitelist sanitizer cho CKEditor */
function sanitize_html($html)
{
    // Whitelist tag đơn giản (mở rộng tùy nhu cầu)
    $allowed_tags = '<p><b><strong><i><em><u><ul><ol><li><br><hr><blockquote><code><pre><h1><h2><h3><h4><h5><h6><a><img><span><small>';
    $html = strip_tags($html, $allowed_tags);

    // Loại bỏ on* event, javascript:, data: độc hại
    // Bỏ attribute event
    $html = preg_replace('/\son\w+="[^"]*"/i', '', $html);
    $html = preg_replace("/\son\w+='[^']*'/i", '', $html);
    // Bỏ javascript:, vbscript:, data:text/html
    $html = preg_replace('/(?:javascript|vbscript|data):/i', '', $html);
    // Cho phép img src, a href http(s) và đường dẫn tương đối
    $html = preg_replace_callback(
        '/<(a|img)\s+[^>]*(href|src)\s*=\s*([\'"])(.*?)\3/iu',
        function ($m) {
            $tag = strtolower($m[1]);
            $attr = strtolower($m[2]);
            $q = $m[3];
            $val = $m[4];
            // chỉ cho http, https, /, ./, ../
            if (!preg_match('#^(https?:)?\/\/#i', $val) && !preg_match('#^(\.|\/)#', $val)) {
                // không phải http(s) hoặc đường dẫn tương đối => loại bỏ
                return "<$tag ";
            }
            return "<$tag $attr=$q$val$q";
        },
        $html
    );
    return $html;
}

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_field()
{
    echo '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}
function csrf_check_or_die()
{
    $posted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted)) {
        http_response_code(400);
        exit('CSRF token không hợp lệ.');
    }
}

/* Role check: admin full; editor: chỉ quản lý product & category (không xóa user/không xem user list) */
function require_role($roles = ['admin'])
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    $r = $_SESSION['role'] ?? 'user';
    if (!in_array($r, (array)$roles, true)) {
        http_response_code(403);
        exit('Bạn không có quyền thực hiện thao tác này.');
    }
}

/* Xử lý upload ảnh an toàn */
function handle_image_upload($field = 'image_file', $destDir = 'uploads/products', $maxSize = 2_000_000)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // không upload file
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) return null;
    if ($f['size'] <= 0 || $f['size'] > $maxSize) return null;

    // Kiểm tra mime thực
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($f['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];
    if (!isset($allowed[$mime])) return null;

    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
    $basename = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $target   = rtrim($destDir, '/') . '/' . $basename;

    if (!move_uploaded_file($f['tmp_name'], $target)) return null;

    // Trả về path tương đối để lưu DB
    return $target;
}

/* -------------------- Gatekeeping -------------------- */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? 'user', ['admin', 'editor'], true)) {
    http_response_code(403);
    exit("Bạn không có quyền truy cập!");
}

/* -------------------- Router -------------------- */
$module = $_GET['module'] ?? 'dashboard';
$action = $_GET['action'] ?? 'list';

/* Dùng sẵn categories cho select ở Product add/edit */
function fetch_categories($conn)
{
    return $conn->query("SELECT id, name FROM category ORDER BY name");
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <title>Admin - LaptopShop.vn</title>
    <link rel="stylesheet" href="./css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
    <style>
        /* ====== ADMIN UI (nhẹ, không phá layout hiện tại) ====== */
        .admin-toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 12px 0;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0;
            background: #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .08);
        }

        .admin-table th,
        .admin-table td {
            border: 1px solid #e5e5e5;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        .admin-table th {
            background: #f7f7f9;
            white-space: nowrap;
        }

        /* Base button */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 6px;
            border: 1px solid transparent;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            /* giữ font-weight đồng đều */
            cursor: pointer;
            transition: 0.2s;
            height: 42px;
            /* fix chiều cao để các nút đều nhau */
            line-height: 1;
            /* text căn giữa tuyệt đối */
            box-sizing: border-box;
        }

        /* Hover effect */
        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.95;
        }

        /* Variants */
        .btn-add {
            background: #28a745;
            color: #fff;
            border-color: #28a745;
        }

        .btn-view {
            background: #17a2b8;
            color: #fff;
            border-color: #17a2b8;
        }

        .btn-edit {
            background: #ffc107;
            color: #111;
            border-color: #ffc107;
        }

        .btn-delete {
            background: #dc3545;
            color: #fff;
            border-color: #dc3545;
        }

        .btn-ghost {
            background: #fff;
            color: #333;
            border: 1px solid #ddd;
            font-weight: 500;
            margin-top: 15px;
            /* đồng nhất với nút màu */
        }

        /* nút Sửa có margin riêng */
        .btn-edit.btn-edit-top {
            margin-top: 15px !important;
        }


        /* Group actions */
        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
        }

        .admin-form {
            max-width: 780px;
            margin: 22px auto;
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .08);
        }

        .admin-form h3 {
            margin: 0 0 10px;
            color: #b70000;
            text-align: center;
        }

        .admin-form label {
            font-weight: 600;
            display: block;
            margin: 10px 0 6px;
        }

        .admin-form input[type="text"],
        .admin-form input[type="number"],
        .admin-form input[type="email"],
        .admin-form input[type="file"],
        .admin-form select,
        .admin-form textarea {
            width: 100%;
            padding: 9px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        .admin-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .admin-form .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }

        .admin-form .actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 10px;
        }

        .dash-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }

        .dash-card {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 18px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .06);
        }

        .dash-card h4 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }

        .dash-card p {
            margin: 6px 0 0;
            font-weight: 800;
            font-size: 22px;
            color: #007bff;
        }

        .note {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>

<body>

    <header>
        <div class="wrap header-flex">
            <a href="home.php" class="logo"><img src="images/logo.png" alt="LaptopShop.vn" /></a>
            <h1>Quản trị - LaptopShop.vn</h1>
        </div>
    </header>

    <nav>
        <div class="wrap">
            <div class="inner">
                <div class="menu">
                    <a href="admin.php?module=dashboard">Dashboard</a>
                    <a href="admin.php?module=product">Sản phẩm</a>
                    <a href="admin.php?module=category">Danh mục</a>
                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                        <a href="admin.php?module=users">Người dùng</a>
                    <?php endif; ?>
                    <a href="admin.php?module=feedback">Phản hồi</a>
                </div>
                <div class="user-menu">
                    <span>Xin chào, <b><?= e($_SESSION['username']) ?></b> (<?= e($_SESSION['role']) ?>)</span>
                    <a href="home.php" class="btn btn-ghost" style="color: #111; margin-bottom: 15px; padding: -10px;"><i class="fa-solid fa-house"></i> Trang chính</a>
                    <a href="logout.php" class="btn btn-delete"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <main class="content">
            <?php
            /* ======================= DASHBOARD ======================= */
            if ($module === 'dashboard') {
                require_role(['admin', 'editor']);
                $totalProduct  = $conn->query("SELECT COUNT(*) c FROM product")->fetch_assoc()['c'] ?? 0;
                $totalCategory = $conn->query("SELECT COUNT(*) c FROM category")->fetch_assoc()['c'] ?? 0;
                $totalUsers    = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0;
                $totalFeedback = $conn->query("SELECT COUNT(*) c FROM feedback")->fetch_assoc()['c'] ?? 0;

                echo '<h2>Bảng điều khiển</h2>
        <p>Xin chào! Đây là tổng quan hệ thống.</p>
        <div class="dash-grid">
          <div class="dash-card"><h4>Sản phẩm</h4><p>' . (int)$totalProduct . '</p></div>
          <div class="dash-card"><h4>Danh mục</h4><p>' . (int)$totalCategory . '</p></div>
          <div class="dash-card"><h4>Người dùng</h4><p>' . (int)$totalUsers . '</p></div>
          <div class="dash-card"><h4>Phản hồi</h4><p>' . (int)$totalFeedback . '</p></div>
        </div>
        <div class="note">Gợi ý: dùng menu trên để truy cập các phân hệ quản trị.</div>';
            }

            /* ======================= CATEGORY ======================= */ elseif ($module === 'category') {
                require_role(['admin', 'editor']);

                if ($action === 'list') {
                    echo '<h2>Quản lý danh mục</h2>
          <div class="admin-toolbar">
            <a class="btn btn-add" href="admin.php?module=category&action=add"><i class="fa fa-plus"></i> Thêm danh mục</a>
          </div>';
                    $rs = $conn->query("SELECT * FROM category ORDER BY id DESC");
                    echo '<table class="admin-table">
            <tr><th>ID</th><th>Tên danh mục</th><th>Hành động</th></tr>';
                    while ($row = $rs->fetch_assoc()) {
                        echo '<tr>
              <td>' . (int)$row['id'] . '</td>
              <td>' . e($row['name']) . '</td>
              <td>
                <a class="btn btn-edit" href="admin.php?module=category&action=edit&id=' . (int)$row['id'] . '"><i class="fa fa-pen"></i> Sửa</a>
                <a class="btn btn-delete" href="admin.php?module=category&action=delete&id=' . (int)$row['id'] . '" onclick="return confirm(\'Xóa danh mục?\')"><i class="fa fa-trash"></i> Xóa</a>
              </td>
            </tr>';
                    }
                    echo '</table>';
                } elseif ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                    csrf_check_or_die();
                    $name = trim($_POST['name'] ?? '');
                    if ($name !== '') {
                        $stmt = $conn->prepare("INSERT INTO category(name) VALUES(?)");
                        $stmt->bind_param('s', $name);
                        $stmt->execute();
                    }
                    header('Location: admin.php?module=category');
                    exit;
                } elseif ($action === 'add') {
                    echo '<div class="admin-form">
            <h3>Thêm danh mục</h3>
            <form method="post">
              ' . csrf_field() . '
              <label>Tên danh mục</label>
              <input type="text" name="name" required />
              <div class="actions">
                <button class="btn btn-add" type="submit"><i class="fa fa-save"></i> Lưu</button>
                <a class="btn btn-ghost" href="admin.php?module=category">Hủy</a>
              </div>
            </form>
          </div>';
                } elseif ($action === 'edit' && isset($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        csrf_check_or_die();
                        $name = trim($_POST['name'] ?? '');
                        if ($name !== '') {
                            $stmt = $conn->prepare("UPDATE category SET name=? WHERE id=?");
                            $stmt->bind_param('si', $name, $id);
                            $stmt->execute();
                        }
                        header('Location: admin.php?module=category');
                        exit;
                    }
                    $st = $conn->prepare("SELECT * FROM category WHERE id=?");
                    $st->bind_param('i', $id);
                    $st->execute();
                    $cat = $st->get_result()->fetch_assoc();
                    if (!$cat) {
                        echo '<p>Không tìm thấy danh mục.</p>';
                    } else {
                        echo '<div class="admin-form">
              <h3>Sửa danh mục</h3>
              <form method="post">
                ' . csrf_field() . '
                <label>Tên danh mục</label>
                <input type="text" name="name" value="' . e($cat['name']) . '" required />
                <div class="actions">
                  <button class="btn btn-edit" type="submit"><i class="fa fa-save"></i> Cập nhật</button>
                  <a class="btn btn-ghost" href="admin.php?module=category">Hủy</a>
                </div>
              </form>
            </div>';
                    }
                } elseif ($action === 'delete' && isset($_GET['id'])) {
                    // admin & editor đều được xóa category (nếu muốn hạn chế, chuyển require_role(['admin']))
                    $id = (int)$_GET['id'];
                    $stmt = $conn->prepare("DELETE FROM category WHERE id=?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    header('Location: admin.php?module=category');
                    exit;
                }
            }

            /* ======================= PRODUCT ======================= */ elseif ($module === 'product') {
                require_role(['admin', 'editor']);

                if ($action === 'list') {
                    echo '<h2>Quản lý sản phẩm</h2>
          <div class="admin-toolbar">
            <a class="btn btn-add" href="admin.php?module=product&action=add"><i class="fa fa-plus"></i> Thêm sản phẩm</a>
          </div>';
                    $rs = $conn->query("SELECT p.id,p.name,c.name AS cat,p.price FROM product p LEFT JOIN category c ON p.category_id=c.id ORDER BY p.id DESC");
                    echo '<table class="admin-table">
            <tr><th>ID</th><th>Tên</th><th>Danh mục</th><th>Giá</th><th>Hành động</th></tr>';
                    while ($row = $rs->fetch_assoc()) {
                        echo '<tr>
              <td>' . (int)$row['id'] . '</td>
              <td>' . e($row['name']) . '</td>
              <td>' . e($row['cat']) . '</td>
              <td>' . number_format((float)$row['price'], 0, ',', '.') . ' đ</td>
              <td>
                <a class="btn btn-view" href="admin.php?module=product&action=view&id=' . (int)$row['id'] . '"><i class="fa fa-eye"></i> Xem</a>
                <a class="btn btn-edit" href="admin.php?module=product&action=edit&id=' . (int)$row['id'] . '"><i class="fa fa-pen"></i> Sửa</a>
                <a class="btn btn-delete" href="admin.php?module=product&action=delete&id=' . (int)$row['id'] . '" onclick="return confirm(\'Xóa sản phẩm?\')"><i class="fa fa-trash"></i> Xóa</a>
              </td>
            </tr>';
                    }
                    echo '</table>';
                } elseif ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                    csrf_check_or_die();
                    $name = trim($_POST['name'] ?? '');
                    $price = (float)($_POST['price'] ?? 0);
                    $cat_id = (int)($_POST['cat_id'] ?? 0);
                    $desc_raw = $_POST['description'] ?? '';
                    $desc = sanitize_html($desc_raw); // lọc CKEditor
                    $image_link = trim($_POST['image_link'] ?? '');
                    $image_file = handle_image_upload('image_file', 'uploads/products');

                    // Ưu tiên file upload; nếu không có dùng link (chỉ chấp nhận http/https hoặc đường dẫn tương đối)
                    $image = $image_file;
                    if (!$image) {
                        if ($image_link !== '' && (preg_match('#^https?://#i', $image_link) || preg_match('#^(\.|/)#', $image_link))) {
                            $image = $image_link;
                        } else {
                            $image = ''; // để trống
                        }
                    }

                    $stmt = $conn->prepare("INSERT INTO product(name,price,category_id,description,image,created_at) VALUES(?,?,?,?,?,NOW())");
                    $stmt->bind_param('sdiss', $name, $price, $cat_id, $desc, $image);
                    $stmt->execute();
                    header('Location: admin.php?module=product');
                    exit;
                } elseif ($action === 'add') {
                    $cats = fetch_categories($conn);
                    echo '<div class="admin-form">
            <h3>Thêm sản phẩm</h3>
            <form method="post" enctype="multipart/form-data">
              ' . csrf_field() . '
              <div class="form-row">
                <div>
                  <label>Tên sản phẩm</label>
                  <input type="text" name="name" required />
                </div>
                <div>
                  <label>Giá (đ)</label>
                  <input type="number" step="1000" min="0" name="price" required />
                </div>
              </div>
              <div class="form-row">
                <div>
                  <label>Danh mục</label>
                  <select name="cat_id">';
                    while ($c = $cats->fetch_assoc()) {
                        echo '<option value="' . (int)$c['id'] . '">' . e($c['name']) . '</option>';
                    }
                    echo '      </select>
                </div>
                <div>
                  <label>Ảnh (chọn 1 trong 2)</label>
                  <input type="file" name="image_file" accept="image/*" />
                  <div class="note">Hoặc nhập link ảnh:</div>
                  <input type="text" name="image_link" placeholder="https://... hoặc /images/a.jpg" />
                </div>
              </div>
              <label>Mô tả (Richtext)</label>
              <textarea id="desc" name="description"></textarea>
              <div class="actions">
                <button class="btn btn-add" type="submit"><i class="fa fa-save"></i> Lưu</button>
                <a class="btn btn-ghost" href="admin.php?module=product">Hủy</a>
              </div>
            </form>
          </div>
          <script>CKEDITOR.replace("desc");</script>';
                } elseif ($action === 'edit' && isset($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        csrf_check_or_die();
                        $name = trim($_POST['name'] ?? '');
                        $price = (float)($_POST['price'] ?? 0);
                        $cat_id = (int)($_POST['cat_id'] ?? 0);
                        $desc_raw = $_POST['description'] ?? '';
                        $desc = sanitize_html($desc_raw);
                        $image_link = trim($_POST['image_link'] ?? '');
                        $image_file = handle_image_upload('image_file', 'uploads/products');

                        // Lấy ảnh cũ
                        $st = $conn->prepare("SELECT image FROM product WHERE id=?");
                        $st->bind_param('i', $id);
                        $st->execute();
                        $old = $st->get_result()->fetch_assoc()['image'] ?? '';

                        $image = $old;
                        if ($image_file) $image = $image_file;
                        elseif ($image_link !== '' && (preg_match('#^https?://#i', $image_link) || preg_match('#^(\.|/)#', $image_link))) {
                            $image = $image_link;
                        }

                        $stmt = $conn->prepare("UPDATE product SET name=?,price=?,category_id=?,description=?,image=? WHERE id=?");
                        $stmt->bind_param('sdissi', $name, $price, $cat_id, $desc, $image, $id);
                        $stmt->execute();
                        header('Location: admin.php?module=product');
                        exit;
                    }
                    $st = $conn->prepare("SELECT * FROM product WHERE id=?");
                    $st->bind_param('i', $id);
                    $st->execute();
                    $p = $st->get_result()->fetch_assoc();
                    if (!$p) {
                        echo '<p>Không tìm thấy sản phẩm.</p>';
                    } else {
                        $cats = fetch_categories($conn);
                        echo '<div class="admin-form">
              <h3>Sửa sản phẩm</h3>
              <form method="post" enctype="multipart/form-data">
                ' . csrf_field() . '
                <div class="form-row">
                  <div>
                    <label>Tên sản phẩm</label>
                    <input type="text" name="name" value="' . e($p['name']) . '" required />
                  </div>
                  <div>
                    <label>Giá (đ)</label>
                    <input type="number" step="1000" min="0" name="price" value="' . e($p['price']) . '" required />
                  </div>
                </div>
                <div class="form-row">
                  <div>
                    <label>Danh mục</label>
                    <select name="cat_id">';
                        while ($c = $cats->fetch_assoc()) {
                            $sel = ((int)$c['id'] === (int)$p['category_id']) ? 'selected' : '';
                            echo '<option value="' . (int)$c['id'] . '" ' . $sel . '>' . e($c['name']) . '</option>';
                        }
                        echo '       </select>
                  </div>
                  <div>
                    <label>Ảnh hiện tại</label>
                    <div class="note">' . e($p['image']) . '</div>
                    <label>Upload ảnh mới</label>
                    <input type="file" name="image_file" accept="image/*" />
                    <div class="note">Hoặc link ảnh:</div>
                    <input type="text" name="image_link" placeholder="https://... hoặc /images/a.jpg" />
                  </div>
                </div>
                <label>Mô tả (Richtext)</label>
                <textarea id="desc" name="description">' . e($p['description']) . '</textarea>
                <div class="actions">
                  <button class="btn btn-edit" type="submit"><i class="fa fa-save"></i> Cập nhật</button>
                  <a class="btn btn-ghost" href="admin.php?module=product">Hủy</a>
                </div>
              </form>
            </div>
            <script>CKEDITOR.replace("desc");</script>';
                    }
                } elseif ($action === 'view' && isset($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $st = $conn->prepare("SELECT p.*, c.name AS cat FROM product p LEFT JOIN category c ON p.category_id=c.id WHERE p.id=?");
                    $st->bind_param('i', $id);
                    $st->execute();
                    $p = $st->get_result()->fetch_assoc();
                    if (!$p) {
                        echo '<p>Không tìm thấy sản phẩm.</p>';
                    } else {
                        echo '<h2>Chi tiết sản phẩm</h2>
            <div class="admin-form" style="max-width:900px">
              <div class="form-row">
                <div><label>Tên:</label><div>' . e($p['name']) . '</div></div>
                <div><label>Giá:</label><div>' . number_format((float)$p['price'], 0, ',', '.') . ' đ</div></div>
              </div>
              <div class="form-row">
                <div><label>Danh mục:</label><div>' . e($p['cat']) . '</div></div>
                <div><label>Ảnh:</label><div>' . ($p['image'] ? '<img src="' . e($p['image']) . '" style="max-width:200px;border:1px solid #ddd;border-radius:6px;padding:4px">' : '(chưa có)') . '</div></div>
              </div>
              <label>Mô tả:</label>
              <div style="border:1px solid #eee;border-radius:6px;padding:10px;background:#fafafa">' . sanitize_html($p['description']) . '</div>
              <div class="actions" style="margin-top:12px">
                <a class="btn btn-edit btn-edit-top" href="admin.php?module=product&action=edit&id=' . (int)$p['id'] . '"><i class="fa fa-pen"></i> Sửa</a>
                <a class="btn btn-ghost" href="admin.php?module=product">Đóng</a>
              </div>
            </div>';
                    }
                } elseif ($action === 'delete' && isset($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $stmt = $conn->prepare("DELETE FROM product WHERE id=?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    header('Location: admin.php?module=product');
                    exit;
                }
            }

            /* ======================= USERS (Admin only) ======================= */ elseif ($module === 'users') {
                require_role(['admin']);

                if ($action === 'list') {
                    echo '<h2>Quản lý người dùng</h2>
          <div class="admin-toolbar">
            <a class="btn btn-add" href="admin.php?module=users&action=add"><i class="fa fa-user-plus"></i> Thêm người dùng</a>
          </div>';
                    $rs = $conn->query("SELECT id,username,email,role FROM users ORDER BY id DESC");
                    echo '<table class="admin-table">
            <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Hành động</th></tr>';
                    while ($u = $rs->fetch_assoc()) {
                        echo '<tr>
              <td>' . (int)$u['id'] . '</td>
              <td>' . e($u['username']) . '</td>
              <td>' . e($u['email']) . '</td>
              <td>' . e($u['role']) . '</td>
              <td>
                <a class="btn btn-view" href="admin.php?module=users&action=view&id=' . (int)$u['id'] . '"><i class="fa fa-eye"></i> Xem</a>
                <a class="btn btn-edit" href="admin.php?module=users&action=edit&id=' . (int)$u['id'] . '"><i class="fa fa-pen"></i> Sửa</a>
                <a class="btn btn-delete" href="admin.php?module=users&action=delete&id=' . (int)$u['id'] . '" onclick="return confirm(\'Xóa user?\')"><i class="fa fa-trash"></i> Xóa</a>
              </td>
            </tr>';
                    }
                    echo '</table>';
                } elseif ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                    csrf_check_or_die();
                    $username = trim($_POST['username'] ?? '');
                    $email    = trim($_POST['email'] ?? '');
                    $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : (($_POST['role'] ?? 'user') === 'editor' ? 'editor' : 'user');
                    $password = $_POST['password'] ?? '';
                    if ($username !== '' && $email !== '' && $password !== '') {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users(username,email,role,password) VALUES(?,?,?,?)");
                        $stmt->bind_param('ssss', $username, $email, $role, $hash);
                        $stmt->execute();
                    }
                    header('Location: admin.php?module=users');
                    exit;
                } elseif ($action === 'add') {
                    echo '<div class="admin-form">
            <h3>Thêm người dùng</h3>
            <form method="post">
              ' . csrf_field() . '
              <div class="form-row">
                <div>
                  <label>Username</label>
                  <input type="text" name="username" required />
                </div>
                <div>
                  <label>Email</label>
                  <input type="email" name="email" required />
                </div>
              </div>
              <div class="form-row">
                <div>
                  <label>Role</label>
                  <select name="role">
                    <option value="user">user</option>
                    <option value="editor">editor</option>
                    <option value="admin">admin</option>
                  </select>
                </div>
                <div>
                  <label>Mật khẩu</label>
                  <input type="text" name="password" required />
                </div>
              </div>
              <div class="actions">
                <button class="btn btn-add" type="submit"><i class="fa fa-save"></i> Lưu</button>
                <a class="btn btn-ghost" href="admin.php?module=users">Hủy</a>
              </div>
            </form>
          </div>';
                } elseif ($action === 'edit' && isset($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        csrf_check_or_die();
                        $username = trim($_POST['username'] ?? '');
                        $email    = trim($_POST['email'] ?? '');
                        $role     = ($_POST['role'] ?? 'user');
                        if (!in_array($role, ['user', 'editor', 'admin'], true)) $role = 'user';

                        $password = $_POST['password'] ?? '';
                        if ($password !== '') {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("UPDATE users SET username=?,email=?,role=?,password=? WHERE id=?");
                            $stmt->bind_param('ssssi', $username, $email, $role, $hash, $id);
                        } else {
                            $stmt = $conn->prepare("UPDATE users SET username=?,email=?,role=? WHERE id=?");
                            $stmt->bind_param('sssi', $username, $email, $role, $id);
                        }
                        $stmt->execute();
                        header('Location: admin.php?module=users');
                        exit;
                    }
                    $st = $conn->prepare("SELECT id,username,email,role FROM users WHERE id=?");
                    $st->bind_param('i', $id);
                    $st->execute();
                    $u = $st->get_result()->fetch_assoc();
                    if (!$u) {
                        echo '<p>Không tìm thấy user.</p>';
                    } else {
                        echo '<div class="admin-form">
              <h3>Sửa người dùng</h3>
              <form method="post">
                ' . csrf_field() . '
                <div class="form-row">
                  <div>
                    <label>Username</label>
                    <input type="text" name="username" value="' . e($u['username']) . '" required />
                  </div>
                  <div>
                    <label>Email</label>
                    <input type="email" name="email" value="' . e($u['email']) . '" required />
                  </div>
                </div>
                <div class="form-row">
                  <div>
                    <label>Role</label>
                    <select name="role">
                      <option value="user" ' . ($u['role'] === 'user' ? 'selected' : '') . '>user</option>
                      <option value="editor" ' . ($u['role'] === 'editor' ? 'selected' : '') . '>editor</option>
                      <option value="admin" ' . ($u['role'] === 'admin' ? 'selected' : '') . '>admin</option>
                    </select>
                  </div>
                  <div>
                    <label>Đổi mật khẩu (để trống nếu giữ nguyên)</label>
                    <input type="text" name="password" />
                  </div>
                </div>
                <div class="actions">
                  <button class="btn btn-edit" type="submit"><i class="fa fa-save"></i> Cập nhật</button>
                  <a class="btn btn-ghost" href="admin.php?module=users">Hủy</a>
                </div>
              </form>
            </div>';
                    }
                } elseif ($action === 'view' && isset($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $st = $conn->prepare("SELECT id,username,email,role FROM users WHERE id=?");
                    $st->bind_param('i', $id);
                    $st->execute();
                    $u = $st->get_result()->fetch_assoc();
                    if (!$u) {
                        echo '<p>Không tìm thấy user.</p>';
                    } else {
                        echo '<div class="admin-form">
              <h3>Chi tiết người dùng</h3>
              <div class="form-row">
                <div><label>ID</label><div>' . (int)$u['id'] . '</div></div>
                <div><label>Role</label><div>' . e($u['role']) . '</div></div>
              </div>
              <div class="form-row">
                <div><label>Username</label><div>' . e($u['username']) . '</div></div>
                <div><label>Email</label><div>' . e($u['email']) . '</div></div>
              </div>
              <div class="actions">
                <a class="btn btn-edit btn-edit-top" href="admin.php?module=users&action=edit&id=' . (int)$u['id'] . '"><i class="fa fa-pen"></i> Sửa</a>
                <a class="btn btn-ghost" href="admin.php?module=users">Đóng</a>
              </div>
            </div>';
                    }
                } elseif ($action === 'delete' && isset($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    // Không cho xóa chính mình (tránh lockout)
                    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
                        echo '<p>Không thể xóa chính bạn.</p>';
                    } else {
                        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                    }
                    header('Location: admin.php?module=users');
                    exit;
                }
            }

            /* ======================= FEEDBACK ======================= */ elseif ($module === 'feedback') {
                require_role(['admin', 'editor']);

                if ($action === 'list') {
                    echo '<h2>Phản hồi khách hàng</h2>';
                    $rs = $conn->query("SELECT id,name,email,message,created_at FROM feedback ORDER BY id DESC");
                    echo '<table class="admin-table">
            <tr><th>ID</th><th>Khách hàng</th><th>Email</th><th>Nội dung</th><th>Ngày</th><th>Hành động</th></tr>';
                    while ($f = $rs->fetch_assoc()) {
                        echo '<tr>
              <td>' . (int)$f['id'] . '</td>
              <td>' . e($f['name']) . '</td>
              <td>' . e($f['email']) . '</td>
              <td>' . e($f['message']) . '</td>
              <td>' . e($f['created_at']) . '</td>
              <td>
                <a class="btn btn-view" href="admin.php?module=feedback&action=view&id=' . (int)$f['id'] . '"><i class="fa fa-eye"></i> Xem</a>
                <a class="btn btn-delete" href="admin.php?module=feedback&action=delete&id=' . (int)$f['id'] . '" onclick="return confirm(\'Xóa phản hồi?\')"><i class="fa fa-trash"></i> Xóa</a>
              </td>
            </tr>';
                    }
                    echo '</table>';
                } elseif ($action === 'view' && isset($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $st = $conn->prepare("SELECT * FROM feedback WHERE id=?");
                    $st->bind_param('i', $id);
                    $st->execute();
                    $f = $st->get_result()->fetch_assoc();
                    if (!$f) {
                        echo '<p>Không tìm thấy phản hồi.</p>';
                    } else {
                        echo '<div class="admin-form">
              <h3>Chi tiết phản hồi</h3>
              <div class="form-row">
                <div><label>Tên</label><div>' . e($f['name']) . '</div></div>
                <div><label>Email</label><div>' . e($f['email']) . '</div></div>
              </div>
              <label>Nội dung</label>
              <div style="white-space:pre-wrap;border:1px solid #eee;padding:10px;border-radius:6px;background:#fafafa">' . e($f['message']) . '</div>
              <div class="actions" style="margin-top:10px">
                <a class="btn btn-ghost" href="admin.php?module=feedback">Đóng</a>
              </div>
            </div>';
                    }
                } elseif ($action === 'delete' && isset($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $stmt = $conn->prepare("DELETE FROM feedback WHERE id=?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    header('Location: admin.php?module=feedback');
                    exit;
                }
            }

            /* ======================= FALLBACK ======================= */ else {
                echo '<p>Module không hợp lệ.</p>';
            }
            ?>
        </main>
    </div>

    <footer>
        <div class="wrap">
            <p>&copy; <?= date('Y') ?> LaptopShop.vn - Tất cả các quyền thuộc về youngnvk.</p>
            <p>Email: <a href="mailto:support@laptopshop.vn">support@laptopshop.vn</a> | Hotline: <a href="tel:19001234">1900-1234</a></p>
            <div class="social-links">
                <a class="facebook" href="https://facebook.com" target="_blank"><i class="fab fa-facebook"></i></a>
                <a class="youtube" href="https://youtube.com" target="_blank"><i class="fab fa-youtube"></i></a>
                <a class="zalo" href="https://zalo.me" target="_blank"><i class="fa-solid fa-comment"></i></a>
            </div>
        </div>
    </footer>

</body>

</html>