<?php
session_start();
require_once 'db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taiKhoan = trim($_POST['taiKhoan'] ?? '');
    $matKhau = trim($_POST['matKhau'] ?? '');
    $remember = isset($_POST['remember']);

    if (empty($taiKhoan) || empty($matKhau)) {
        $error = 'Vui lòng nhập đầy đủ thông tin tài khoản và mật khẩu.';
    } else {
        $stmt = $pdo->prepare("
            SELECT nv.*, pq.MaPhanQuyen 
            FROM NhanVien nv 
            JOIN PhanQuyen pq ON nv.MaPhanQuyen = pq.MaPhanQuyen 
            WHERE nv.TaiKhoan = :taiKhoan AND nv.MatKhau = :matKhau
        ");
        $stmt->execute(['taiKhoan' => $taiKhoan, 'matKhau' => $matKhau]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['MaPhanQuyen'] == 4 || $user['MaPhanQuyen'] == 5 || $user['MaPhanQuyen'] == 6) {
                $_SESSION['tenNhanVien'] = $user['TenNhanVien'];
                $_SESSION['maNhanVien'] = $user['MaNhanVien'];
                $_SESSION['maPhanQuyen'] = $user['MaPhanQuyen'];
                $_SESSION['taiKhoan'] = $user['TaiKhoan'];

                if ($remember) {
                    // Lưu cả tài khoản và mật khẩu (đã mã hóa base64 để bảo mật cơ bản)
                    setcookie('saved_taiKhoan', $taiKhoan, time() + 30 * 24 * 3600, '/');
                    setcookie('saved_matKhau', base64_encode($matKhau), time() + 30 * 24 * 3600, '/');
                    setcookie('remember_me', '1', time() + 30 * 24 * 3600, '/');
                } else {
                    // Xóa cookie nếu không chọn nhớ
                    setcookie('saved_taiKhoan', '', time() - 3600, '/');
                    setcookie('saved_matKhau', '', time() - 3600, '/');
                    setcookie('remember_me', '', time() - 3600, '/');
                }

                header('Location: home.php');
                exit;
            } else {
                $error = 'Chỉ có nhân viên nhập kho hoặc xuất kho mới được đăng nhập.';
            }
        } else {
            $error = 'Tài khoản hoặc mật khẩu không chính xác.';
        }
    }
}

// Lấy thông tin đã lưu từ cookie
$savedTaiKhoan = $_COOKIE['saved_taiKhoan'] ?? '';
$savedMatKhau = isset($_COOKIE['saved_matKhau']) ? base64_decode($_COOKIE['saved_matKhau']) : '';
$isRemembered = isset($_COOKIE['remember_me']);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Minh Anh</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    'green-50': '#f0fdf4',
                },
            },
        },
    }
    </script>
    <style>
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes footerBounce {
        0% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-5px);
        }

        100% {
            transform: translateY(0);
        }
    }

    .animate-fadeIn {
        animation: fadeIn 0.3s ease-out forwards;
    }

    .animate-footerBounce {
        animation: footerBounce 2s infinite ease-in-out;
    }
    </style>
</head>

<body class="h-[100vh] w-[100vw] bg-gray-100 flex justify-center items-center relative">
    <div class="h-full w-full max-w-3xl bg-white overflow-hidden shadow-lg relative">
        <div class="bg-red-600 h-48 w-full relative flex flex-col items-center justify-center text-white">
            <div class="w-32 h-32 bg-white rounded-full flex items-center justify-center overflow-hidden">
                <img src="assets/LogoMinhAnh.png" alt="Logo Minh Anh" class="w-40 h-40 object-contain" />
            </div>
            <h1 class="text-xl font-bold mt-2">DỆT KIM MINH ANH</h1>
        </div>

        <div class="px-6 py-6 mt-4">
            <h2 class="text-red-600 text-xl font-medium mb-2 text-center">QUẢN LÝ KHO</h2>
            <p class="text-gray-500 text-sm mb-6 text-center">Đăng nhập vào tài khoản để tiếp tục</p>

            <form method="POST" class="h-[32vh] flex flex-col justify-between space-y-4 mt-4">
                <div class="space-y-4">
                    <div class="bg-green-50 border-2 rounded-md p-3 flex items-center">
                        <img src="assets/User.png" alt="User" class="w-5 h-5 object-contain" />
                        <input type="text" name="taiKhoan" value="<?php echo htmlspecialchars($savedTaiKhoan); ?>"
                            placeholder="Tài khoản"
                            class="w-full  bg-transparent focus:outline-none text-gray-700 pl-2">
                    </div>

                    <div class="bg-green-50 border-2 rounded-md p-3 flex items-center relative">
                        <img src="assets/Lock.png" alt="Lock" class="w-5 h-5 object-contain" />
                        <input type="password" name="matKhau" id="password" 
                            value="<?php echo htmlspecialchars($savedMatKhau); ?>"
                            placeholder="Mật khẩu"
                            class="w-full bg-transparent focus:outline-none text-gray-700 pl-2 pr-10">
                        <button type="button" onclick="togglePassword()"
                            class="absolute right-3 text-gray-500 hover:text-gray-700 focus:outline-none">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                </path>
                            </svg>
                        </button>
                    </div>

                    <div class="flex justify-between items-center mb-4 mt-10">
                        <label class="flex items-center space-x-2 mt-5">
                            <input type="checkbox" name="remember" 
                                <?php echo $isRemembered ? 'checked' : ''; ?>
                                class="w-4 h-4 text-red-600 focus:ring-red-500 border-gray-300 rounded" />
                            <span class="text-sm text-gray-500">Nhớ mật khẩu</span>
                        </label>
                        <!-- <a href="#" class="text-sm text-red-600 font-medium mt-5">Quên mật khẩu?</a> -->
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-red-600 text-white py-2 rounded-full hover:bg-red-700 transition duration-300 text-base mb-4">ĐĂNG
                    NHẬP</button>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
    <div
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 error-modal animate-fadeIn">
        <div class="bg-white rounded-lg w-full max-w-md overflow-hidden shadow-xl">
            <div class="bg-red-600 p-4">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white mr-2" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="text-lg font-medium text-white">Lỗi đăng nhập</h3>
                </div>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-4"><?php echo $error; ?></p>
                <div class="flex justify-end">
                    <button onclick="document.querySelector('.error-modal').style.display='none';"
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-all duration-200">
                        Đóng
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.79m0 0L21 21"></path>
            `;
        } else {
            passwordInput.type = 'password';
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
            `;
        }
    }

    // Auto-fill form khi trang load (hỗ trợ cho Cordova)
    document.addEventListener('DOMContentLoaded', function() {
        // Đảm bảo các giá trị được fill đúng cách
        const taiKhoanInput = document.querySelector('input[name="taiKhoan"]');
        const matKhauInput = document.querySelector('input[name="matKhau"]');
        const rememberCheckbox = document.querySelector('input[name="remember"]');
        
        if (taiKhoanInput && '<?php echo $savedTaiKhoan; ?>') {
            taiKhoanInput.value = '<?php echo htmlspecialchars($savedTaiKhoan); ?>';
        }
        
        if (matKhauInput && '<?php echo $savedMatKhau; ?>') {
            matKhauInput.value = '<?php echo htmlspecialchars($savedMatKhau); ?>';
        }
        
        if (rememberCheckbox && <?php echo $isRemembered ? 'true' : 'false'; ?>) {
            rememberCheckbox.checked = true;
        }
    });
    </script>
</body>

</html>