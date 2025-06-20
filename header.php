<?php
require_once 'init.php';
require_once 'db_config.php';

// Kiểm tra nếu người dùng chưa đăng nhập, chuyển hướng về login.php
if (!isset($_SESSION['tenNhanVien'])) {
    header('Location: login.php');
    exit;
}

// Gán giá trị từ session
$tenNhanVien = $_SESSION['tenNhanVien'] ?? '';
$maPhanQuyen = $_SESSION['maPhanQuyen'] ?? '';
$taiKhoan = $_SESSION['taiKhoan'] ?? ''; // Lưu tài khoản từ session

// Xác định vai trò dựa trên MaPhanQuyen
$vaiTro = '';
if ($maPhanQuyen == 4) {
    $vaiTro = 'Nhân viên nhập kho';
} elseif ($maPhanQuyen == 5) {
    $vaiTro = 'Nhân viên xuất kho';
} elseif ($maPhanQuyen == 6) {
    $vaiTro = 'Nhân viên kho';
}

// Thiết lập múi giờ Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');
$currentTime = date('d/m/Y H:i:s');

// Xác định trang hiện tại
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
            if ($current_page == 'home.php') echo 'Trang chủ';
            elseif ($current_page == 'nhapkho.php') echo 'Nhập Kho';
            elseif ($current_page == 'xuatkho.php') echo 'Xuất Kho';
            else echo 'Minh Anh';
            ?> - Minh Anh</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    'brand-red': '#dc2626',
                    'brand-dark': '#1e293b',
                },
                boxShadow: {
                    'nav': '0 4px 12px rgba(0, 0, 0, 0.05)',
                }
            },
        },
    }
    </script>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    body {
        font-family: 'Inter', sans-serif;
    }

    .nav-link {
        position: relative;
    }

    .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 2px;
        background-color: #dc2626;
        transition: transform 0.3s ease;
    }

    .nav-link:not(.active)::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 2px;
        background-color: #dc2626;
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .nav-link:hover::after {
        transform: scaleX(1);
    }

    .pulse-anim {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(220, 38, 38, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(220, 38, 38, 0);
        }
    }

    /* Dropdown styles */
    .dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        background-color: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        z-index: 50;
        min-width: 10rem;
    }

    .dropdown-menu.show {
        display: block;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        padding: 0.5rem 1rem;
        color: #1e293b;
        font-size: 0.875rem;
        transition: background-color 0.2s ease;
    }

    .dropdown-item:hover {
        background-color: #f3f4f6;
    }

    /* Responsive styles */
    @media (max-width: 768px) {
        .nav-link {
            font-size: 0.75rem;
        }

        .container {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .mobile-menu {
            display: flex;
            justify-content: space-around;
        }

        .mobile-menu a {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
        }

        .mobile-menu i {
            font-size: 1.25rem;
        }

        .mobile-menu span {
            font-size: 0.65rem;
        }

        .user-info-mobile {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .dropdown-menu {
            right: 1rem;
        }
    }

    @media (max-width: 640px) {
        .logo-container img {
            height: 2rem;
            width: auto;
        }

        .logo-container span {
            font-size: 1rem;
        }

        .user-info-mobile span {
            font-size: 0.7rem;
        }
    }
    </style>
</head>

<body class="min-h-screen bg-gray-50">
    <!-- Header -->
    <nav class="bg-white shadow-nav sticky top-0 z-10">
        <div class="container mx-auto px-4">
            <div class="flex justify-between h-16">
                <!-- Left side - Logo and Brand -->
                <div class="flex items-center logo-container">
                    <a href="home.php" class="flex-shrink-0 flex items-center group">
                        <div
                            class="relative overflow-hidden rounded-full p-0.5 group-hover:from-red-600 group-hover:to-red-800 transition duration-300">
                            <img src="assets/LogoMinhAnh.png" alt="Logo Minh Anh"
                                class="h-10 w-16 rounded-full transition duration-300 transform group-hover:scale-105">
                        </div>
                        <div class="ml-1 transition duration-300">
                            <span class="text-xl font-bold text-brand-dark group-hover:text-red-600">MINH ANH</span>
                            <span class="block text-xs text-gray-500 font-medium">Hệ thống quản lý kho</span>
                        </div>
                    </a>
                </div>

                <!-- Center - Navigation -->
                <div class="hidden md:flex items-center justify-center flex-1">
                    <div class="flex space-x-6">
                        <a href="home.php"
                            class="nav-link px-3 py-2 text-sm font-medium rounded-md transition duration-300 ease-in-out flex items-center space-x-1.5 <?php echo ($current_page == 'home.php' || $current_page == 'index.php') ? 'active text-red-600' : 'text-gray-700 hover:text-red-600'; ?>">
                            <i class="fas fa-home"></i>
                            <span>Trang chủ</span>
                        </a>

                        <?php if ($maPhanQuyen == 4 || $maPhanQuyen == 6): ?>
                        <a href="nhapkho.php"
                            class="nav-link px-3 py-2 text-sm font-medium rounded-md transition duration-300 ease-in-out flex items-center space-x-1.5 <?php echo ($current_page == 'nhapkho.php') ? 'active text-red-600' : 'text-gray-700 hover:text-red-600'; ?>">
                            <i class="fas fa-arrow-circle-down"></i>
                            <span>Nhập kho</span>
                        </a>
                        <?php endif; ?>

                    
                        <a href="xuatkho.php"
                            class="nav-link px-3 py-2 text-sm font-medium rounded-md transition duration-300 ease-in-out flex items-center space-x-1.5 <?php echo ($current_page == 'xuatkho.php') ? 'active text-red-600' : 'text-gray-700 hover:text-red-600'; ?>">
                            <i class="fas fa-arrow-circle-up"></i>
                            <span>Xuất kho</span>
                        </a>

                    </div>
                </div>

                <!-- Right side - User info & Settings -->
                <div class="flex items-center space-x-4">
                    <div class="hidden md:flex items-center bg-gray-100 pl-3 pr-1 py-1.5 rounded-full">
                        <div class="text-right mr-2">
                            <p class="text-xs text-gray-500">Xin chào, <?php echo htmlspecialchars($vaiTro); ?></p>
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($tenNhanVien); ?>
                            </p>
                        </div>
                    </div>

                    <div class="relative">
                        <button id="settings-button"
                            class="inline-flex items-center px-2 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-300 ease-in-out transform hover:-translate-y-0.5">
                            <i class="fas fa-cog"></i>
                        </button>
                        <div id="settings-menu" class="dropdown-menu">
                          <a href="#" onclick="showChangePasswordPopup('<?php echo htmlspecialchars($taiKhoan); ?>')" class="dropdown-item">
                            <i class="fas fa-lock mr-2 text-red-500"></i> Đổi mật khẩu
                        </a>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2 text-red-500"></i> Đăng xuất
                        </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time and date information -->
            <div class="hidden md:flex justify-end items-center text-xs py-2 text-gray-500">
                <i class="far fa-clock mr-1.5"></i>
                <span id="current-time"><?php echo $currentTime; ?></span>
            </div>
        </div>

        <!-- Mobile menu -->
        <div class="md:hidden border-t border-gray-100 bg-white shadow-md">
            <div class="mobile-menu border-b-2">
                <a href="home.php"
                    class="text-center px-2 py-1 <?php echo ($current_page == 'home.php' || $current_page == 'index.php') ? 'text-red-600 font-medium' : 'text-gray-600 hover:text-red-600'; ?> transition duration-300">
                    <i class="fas fa-home block text-xl mb-1"></i>
                    <span class="text-xs">Trang chủ</span>
                </a>

                <?php if ($maPhanQuyen == 4 || $maPhanQuyen == 6): ?>
                <a href="nhapkho.php"
                    class="text-center px-2 py-1 <?php echo ($current_page == 'nhapkho.php') ? 'text-red-600 font-medium' : 'text-gray-600 hover:text-red-600'; ?> transition duration-300">
                    <i class="fas fa-arrow-circle-down block text-xl mb-1"></i>
                    <span class="text-xs">Nhập kho</span>
                </a>
                <?php endif; ?>

                <?php if ($maPhanQuyen == 5 || $maPhanQuyen == 6): ?>
                <a href="xuatkho.php"
                    class="text-center px-2 py-1 <?php echo ($current_page == 'xuatkho.php') ? 'text-red-600 font-medium' : 'text-gray-600 hover:text-red-600'; ?> transition duration-300">
                    <i class="fas fa-arrow-circle-up block text-xl mb-1"></i>
                    <span class="text-xs">Xuất kho</span>
                </a>
                <?php endif; ?>
            </div>

            <!-- Mobile user info -->
            <div class="user-info-mobile flex items-center justify-between px-4 bg-gray-50 text-xs">
                <div class="flex items-center">
                    <span class="text-gray-500">Xin chào, <?php echo htmlspecialchars($vaiTro); ?></span>
                    <span class="font-semibold text-gray-800 ml-1"><?php echo htmlspecialchars($tenNhanVien); ?></span>
                </div>
                <div class="flex items-center text-gray-500">
                    <i class="far fa-clock mr-1.5"></i>
                    <span id="mobile-current-time"><?php echo $currentTime; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header Banner -->
    <?php if ($current_page == 'nhapkho.php' || $current_page == 'xuatkho.php'): ?>
    <div class="bg-gradient-to-r from-red-600 to-red-800 text-white py-6 md:py-8 shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex items-center flex-col md:flex-row">
                <div class="mr-0 md:mr-6 bg-white p-3 rounded-full text-red-600 shadow-lg pulse-anim">
                    <i class="fas fa-<?php echo ($current_page == 'nhapkho.php') ? 'arrow-circle-down' : 'arrow-circle-up'; ?> text-xl md:text-2xl"></i>
                </div>
                <div class="mt-4 md:mt-0">
                    <h1 class="text-2xl md:text-3xl font-bold"><?php echo ($current_page == 'nhapkho.php') ? 'NHẬP KHO' : 'XUẤT KHO'; ?></h1>
                    <div class="flex items-center mt-1">
                        <div class="h-1 w-12 bg-white opacity-60 rounded mr-2"></div>
                        <p class="text-red-100 text-xs md:text-sm">Quản lý <?php echo ($current_page == 'nhapkho.php') ? 'nhập' : 'xuất'; ?> kho hiệu quả, dễ dàng</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Change Password Popup -->
    <div id="change-password-popup" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg w-full max-w-md overflow-hidden shadow-xl">
            <div class="bg-red-600 p-4">
                <div class="flex items-center">
                    <i class="fas fa-lock text-white mr-2 text-xl"></i>
                    <h3 class="text-lg font-medium text-white">Đổi mật khẩu</h3>
                </div>
            </div>
            <div class="p-6">
                <form id="change-password-form" method="POST" action="change_password.php">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tài khoản</label>
                        <input type="text" name="taiKhoan" value="<?php echo htmlspecialchars($taiKhoan); ?>" readonly
                            class="w-full bg-gray-100 border border-gray-300 rounded-md p-2 text-gray-700">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu cũ</label>
                        <input type="password" name="matKhauCu" id="matKhauCu"
                            class="w-full border border-gray-300 rounded-md p-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu mới</label>
                        <input type="password" name="matKhauMoi" id="matKhauMoi"
                            class="w-full border border-gray-300 rounded-md p-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Xác nhận mật khẩu mới</label>
                        <input type="password" name="xacNhanMatKhau" id="xacNhanMatKhau"
                            class="w-full border border-gray-300 rounded-md p-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="hideChangePasswordPopup()"
                            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-all duration-200">
                            Hủy
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-all duration-200">
                            Đổi mật khẩu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Script để cập nhật thời gian và xử lý dropdown -->
    <script>
    function updateClock() {
        const now = new Date();
        const day = String(now.getDate()).padStart(2, '0');
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const year = now.getFullYear();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const timeString = `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;

        document.getElementById('current-time').textContent = timeString;
        const mobileTimeElement = document.getElementById('mobile-current-time');
        if (mobileTimeElement) {
            mobileTimeElement.textContent = timeString;
        }
        setTimeout(updateClock, 1000);
    }

    // Toggle dropdown menu
    document.getElementById('settings-button').addEventListener('click', function() {
        const menu = document.getElementById('settings-menu');
        menu.classList.toggle('show');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('settings-menu');
        const button = document.getElementById('settings-button');
        if (!menu.contains(event.target) && !button.contains(event.target)) {
            menu.classList.remove('show');
        }
    });

    // Show change password popup
    function showChangePasswordPopup(taiKhoan) {
        document.getElementById('change-password-popup').classList.remove('hidden');
        document.getElementById('settings-menu').classList.remove('show');
    }

    // Hide change password popup
    function hideChangePasswordPopup() {
        document.getElementById('change-password-popup').classList.add('hidden');
        document.getElementById('change-password-form').reset();
    }

    // Validate change password form
    document.getElementById('change-password-form').addEventListener('submit', function(event) {
        event.preventDefault();
        const matKhauCu = document.getElementById('matKhauCu').value;
        const matKhauMoi = document.getElementById('matKhauMoi').value;
        const xacNhanMatKhau = document.getElementById('xacNhanMatKhau').value;

        if (!matKhauCu || !matKhauMoi || !xacNhanMatKhau) {
            Swal.fire({
                icon: 'warning',
                title: 'Thiếu thông tin',
                text: 'Vui lòng nhập đầy đủ mật khẩu cũ, mật khẩu mới và xác nhận mật khẩu.',
            });
            return;
        }

        if (matKhauMoi !== xacNhanMatKhau) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi xác nhận',
                text: 'Mật khẩu mới và xác nhận mật khẩu không khớp.',
            });
            return;
        }

        if (matKhauMoi.length < 6) {
            Swal.fire({
                icon: 'warning',
                title: 'Mật khẩu yếu',
                text: 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            });
            return;
        }

        // Submit form via AJAX
        const formData = new FormData(this);
        fetch('change_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Thành công',
                    text: 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'logout.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: result.message || 'Không thể đổi mật khẩu. Vui lòng thử lại.',
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi server',
                text: 'Đã xảy ra lỗi khi xử lý yêu cầu. Vui lòng thử lại.',
            });
            console.error('Error:', error);
        });
    });

    document.addEventListener('DOMContentLoaded', updateClock);
    </script>
</body>

</html>