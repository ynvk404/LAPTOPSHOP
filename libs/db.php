<?php
// Cấu hình kết nối CSDL
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "laptopshop";

// Bật chế độ báo lỗi chi tiết của MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset("utf8mb4"); // đảm bảo hỗ trợ Unicode đầy đủ
} catch (Exception $e) {
    http_response_code(500);
    die("Không thể kết nối CSDL: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// Hàm escape dữ liệu chống XSS
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? "", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }
}
