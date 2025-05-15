<?php
session_start();
require_once 'db_config.php';

// Kiểm tra nếu người dùng chưa đăng nhập, chuyển hướng về login.php
if (!isset($_SESSION['tenNhanVien'])) {
    header('Location: login.php');
    exit;
}

// Gán giá trị từ session
$tenNhanVien = $_SESSION['tenNhanVien'] ?? '';
$maPhanQuyen = $_SESSION['maPhanQuyen'] ?? '';

// Xác định vai trò dựa trên MaPhanQuyen
// Xác định vai trò dựa trên MaPhanQuyen
$vaiTro = '';
if ($maPhanQuyen == 4) {
    $vaiTro = 'Nhân viên nhập kho';
} elseif ($maPhanQuyen == 5) {
    $vaiTro = 'Nhân viên xuất kho';
} elseif ($maPhanQuyen == 6) {
    $vaiTro = 'Nhân viên kho ';
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
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Header -->
    <nav class="bg-white shadow-nav sticky top-0 z-10">
        <div class="container mx-auto px-4">
            <div class="flex justify-between h-16">
                <!-- Left side - Logo and Brand -->
                <div class="flex items-center">
                    <a href="home.php" class="flex-shrink-0 flex items-center group">
                        <div class="relative overflow-hidden rounded-full p-0.5 bg-gradient-to-r from-red-500 to-red-700 group-hover:from-red-600 group-hover:to-red-800 transition duration-300">
                            <img src="assets/LogoMinhAnh.png" alt="Logo Minh Anh" class="h-10 w-10 rounded-full transition duration-300 transform group-hover:scale-105">
                        </div>
                        <div class="ml-3 transition duration-300">
                            <span class="text-xl font-bold text-brand-dark group-hover:text-red-600">MINH ANH</span>
                            <span class="block text-xs text-gray-500 font-medium">Hệ thống quản lý kho</span>
                        </div>
                    </a>
                </div>

                <!-- Center - Navigation -->
                <div class="hidden md:flex items-center justify-center flex-1">
                    <div class="flex space-x-6">
                        <a href="home.php" class="nav-link px-3 py-2 text-sm font-medium rounded-md transition duration-300 ease-in-out flex items-center space-x-1.5 <?php echo ($current_page == 'home.php' || $current_page == 'index.php') ? 'active text-red-600' : 'text-gray-700 hover:text-red-600'; ?>">
                            <i class="fas fa-home"></i>
                            <span>Trang chủ</span>
                        </a>
                        
                        <?php if ($maPhanQuyen == 4|| $maPhanQuyen == 6): // Chỉ hiển thị Nhập kho cho nhân viên nhập kho ?>
                        <a href="nhapkho.php" class="nav-link px-3 py-2 text-sm font-medium rounded-md transition duration-300 ease-in-out flex items-center space-x-1.5 <?php echo ($current_page == 'nhapkho.php') ? 'active text-red-600' : 'text-gray-700 hover:text-red-600'; ?>">
                            <i class="fas fa-arrow-circle-down"></i>
                            <span>Nhập kho</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($maPhanQuyen == 5|| $maPhanQuyen == 6): // Chỉ hiển thị Xuất kho cho nhân viên xuất kho ?>
                        <a href="xuatkho.php" class="nav-link px-3 py-2 text-sm font-medium rounded-md transition duration-300 ease-in-out flex items-center space-x-1.5 <?php echo ($current_page == 'xuatkho.php') ? 'active text-red-600' : 'text-gray-700 hover:text-red-600'; ?>">
                            <i class="fas fa-arrow-circle-up"></i>
                            <span>Xuất kho</span>
                        </a>
                        <?php endif; ?>
                        
                        <a href="#" class="nav-link px-3 py-2 text-sm font-medium rounded-md transition duration-300 ease-in-out flex items-center space-x-1.5 text-gray-700 hover:text-red-600">
                            <i class="fas fa-chart-bar"></i>
                            <span>Báo cáo</span>
                        </a>
                    </div>
                </div>

                <!-- Right side - User info & Logout -->
                <div class="flex items-center space-x-4">
                    <div class="hidden md:flex items-center bg-gray-100 pl-3 pr-1 py-1.5 rounded-full">
                        <div class="text-right mr-2">
                            <p class="text-xs text-gray-500">Xin chào, <?php echo htmlspecialchars($vaiTro); ?></p>
                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($tenNhanVien); ?></p>
                        </div>
                        <div class="h-8 w-8 bg-red-600 text-white rounded-full flex items-center justify-center shadow">
                            <span class="font-semibold"><?php echo substr($tenNhanVien, 0, 1); ?></span>
                        </div>
                    </div>
                    
                    <a href="logout.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-300 ease-in-out transform hover:-translate-y-0.5">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Đăng xuất
                    </a>
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
            <div class="flex justify-around py-3">
                <a href="home.php" class="text-center px-2 py-1 <?php echo ($current_page == 'home.php' || $current_page == 'index.php') ? 'text-red-600 font-medium' : 'text-gray-600 hover:text-red-600'; ?> transition duration-300">
                    <i class="fas fa-home block text-xl mb-1"></i>
                    <span class="text-xs">Trang chủ</span>
                </a>
                
                <?php if ($maPhanQuyen == 4|| $maPhanQuyen == 6): ?>
                <a href="nhapkho.php" class="text-center px-2 py-1 <?php echo ($current_page == 'nhapkho.php') ? 'text-red-600 font-medium' : 'text-gray-600 hover:text-red-600'; ?> transition duration-300">
                    <i class="fas fa-arrow-circle-down block text-xl mb-1"></i>
                    <span class="text-xs">Nhập kho</span>
                </a>
                <?php endif; ?>
                
                <?php if ($maPhanQuyen == 5|| $maPhanQuyen == 6): ?>
                <a href="xuatkho.php" class="text-center px-2 py-1 <?php echo ($current_page == 'xuatkho.php') ? 'text-red-600 font-medium' : 'text-gray-600 hover:text-red-600'; ?> transition duration-300">
                    <i class="fas fa-arrow-circle-up block text-xl mb-1"></i>
                    <span class="text-xs">Xuất kho</span>
                </a>
                <?php endif; ?>
                
                <a href="#" class="text-center px-2 py-1 text-gray-600 hover:text-red-600 transition duration-300">
                    <i class="fas fa-chart-bar block text-xl mb-1"></i>
                    <span class="text-xs">Báo cáo</span>
                </a>
            </div>
            
            <!-- Mobile user info -->
            <div class="flex items-center justify-between px-4 py-2 bg-gray-50 text-xs">
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
    <!-- <?php if ($current_page == 'nhapkho.php' || $current_page == 'xuatkho.php'): ?>
    <div class="bg-gradient-to-r from-red-600 to-red-800 text-white py-8 shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex items-center">
                <div class="mr-6 bg-white p-3 rounded-full text-red-600 shadow-lg pulse-anim">
                    <i class="fas fa-<?php echo ($current_page == 'nhapkho.php') ? 'arrow-circle-down' : 'arrow-circle-up'; ?> text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold"><?php echo ($current_page == 'nhapkho.php') ? 'NHẬP KHO' : 'XUẤT KHO'; ?></h1>
                    <div class="flex items-center mt-1">
                        <div class="h-1 w-12 bg-white opacity-60 rounded mr-2"></div>
                        <p class="text-red-100 text-sm">Quản lý <?php echo ($current_page == 'nhapkho.php') ? 'nhập' : 'xuất'; ?> kho hiệu quả, dễ dàng</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?> -->

    <!-- Script để cập nhật thời gian theo thời gian thực -->
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
        
        document.addEventListener('DOMContentLoaded', updateClock);
    </script>
</body>
</html>