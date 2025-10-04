# BASIC WEB APPLICATION: LaptopShop.vn

Website bán laptop demo cơ bản, xây dựng bằng **PHP + MySQL** và tích hợp **Supabase Authentication** để quản lý.

---

## 🚀 Tính năng

- Danh mục sản phẩm laptop (DELL, HP, SONY, LENOVO, …)  
- Trang chi tiết sản phẩm  
- Hệ thống đăng ký/đăng nhập an toàn (**CSRF, Hash password, Brute-force protection**)  
- Tích hợp Supabase cho email xác thực tài khoản  
- Hỗ trợ **Remember me** với bảng `user_tokens`  
- Hệ thống phản hồi khách hàng (**feedback**)  

---

## 📦 Yêu cầu hệ thống

- PHP >= 8.0  
- MySQL / MariaDB  
- Composer (quản lý thư viện PHP)  
- XAMPP / Laragon hoặc môi trường web server tương tự  
- Tài khoản Supabase  

---

## ⚙️ Cài đặt

### 1. Clone project
```bash
git clone https://github.com/<your-username>/laptopshop.git
cd laptopshop
2. Cài đặt thư viện PHP
bash
Copy code
composer install
3. Tạo file môi trường .env
Tạo file .env tại thư mục gốc dự án:

env

SUPABASE_URL=https://<project-id>.supabase.co
SUPABASE_KEY=<anon-or-service-role-key>
🔑 Lấy URL và Key tại Project Settings → API trong Supabase.

4. Import cơ sở dữ liệu
Mở phpMyAdmin hoặc MySQL CLI, sau đó chạy file database.sql (có sẵn trong repo).

File này sẽ tạo CSDL laptopshop với các bảng: category, product. users, user_tokens, feedback

5. Cấu hình Supabase
Vào Authentication → Providers → Email → bật Enable email signup

Vào Authentication → URL Configuration → đặt Site URL = http://localhost/web-laptop/

Vào Authentication → Templates → chỉnh email confirm theo nhu cầu (ví dụ: “Xác thực tài khoản LaptopShop.vn”)

▶️ Chạy dự án
Copy project vào thư mục htdocs (XAMPP) hoặc www (Laragon).

Mở trình duyệt và truy cập:
👉 http://localhost/web-laptop/

👤 Tài khoản mẫu
username: admin
password: 12345678
User

username: khai123
password: 12345678
📂 Cấu trúc thư mục

web-laptop/
├── config/
│   └── config_session.php       # Cấu hình session, bảo mật
│
├── css/
│   └── style.css                # CSS chính
│
├── images/                      # Thư mục ảnh
│
├── libs/
│   ├── db.php                   # Kết nối MySQL
│   └── database.txt             # Script SQL hoặc notes
│
├── vendor/                      # Composer (Supabase, Guzzle...)
│
├── .env                         # Biến môi trường (Supabase key, DB info)
├── composer.json
├── composer.lock
│
├── admin.php                    # Quản trị
├── cart.php                     # Giỏ hàng
├── checkout.php                 # Trang thanh toán
├── order_success.php             # Trang xác nhận đặt hàng
│
├── edit.php                     # Sửa thông tin
├── gioithieu.php                # Giới thiệu
├── home.php                     # Trang chủ
├── huongdan.php                 # Hướng dẫn
├── lienhe.php                   # Liên hệ
│
├── login.php                    # Đăng nhập
├── logout.php                   # Đăng xuất
├── register.php                 # Đăng ký (Supabase + MySQL)
├── verify.php                   # Xác thực email
│
├── forgot.php                   # Quên mật khẩu (nhập email)
├── reset.php                    # Đặt lại mật khẩu bằng token
│
├── productDetail.php             # Chi tiết sản phẩm
├── productList.php               # Danh sách sản phẩm
├── productSearch.php             # Tìm kiếm sản phẩm
│
├── profile.php                   # Hồ sơ cá nhân
├── sendfeedback.php              # Gửi feedback
├── tuyendung.php                 # Tuyển dụng
```
### © 2025 youngnvk - LaptopShop.vn. All rights reserved.
