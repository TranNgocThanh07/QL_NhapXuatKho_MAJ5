<?php
require_once 'init.php';
require_once 'db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

if (!isset($_SESSION['taiKhoan']) || !isset($_SESSION['maNhanVien'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập.']);
    exit;
}

$taiKhoan = trim($_POST['taiKhoan'] ?? '');
$matKhauCu = trim($_POST['matKhauCu'] ?? '');
$matKhauMoi = trim($_POST['matKhauMoi'] ?? '');
$xacNhanMatKhau = trim($_POST['xacNhanMatKhau'] ?? '');

if ($taiKhoan !== $_SESSION['taiKhoan']) {
    echo json_encode(['success' => false, 'message' => 'Tài khoản không hợp lệ.']);
    exit;
}

if (empty($matKhauCu) || empty($matKhauMoi) || empty($xacNhanMatKhau)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin.']);
    exit;
}

if ($matKhauMoi !== $xacNhanMatKhau) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu mới và xác nhận mật khẩu không khớp.']);
    exit;
}

if (strlen($matKhauMoi) < 6) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự.']);
    exit;
}

try {
    // Kiểm tra mật khẩu cũ
    $stmt = $pdo->prepare("SELECT MatKhau FROM NhanVien WHERE TaiKhoan = :taiKhoan");
    $stmt->execute(['taiKhoan' => $taiKhoan]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Tài khoản không tồn tại.']);
        exit;
    }

    if ($user['MatKhau'] !== $matKhauCu) { // Giả sử mật khẩu lưu dạng plain text
        echo json_encode(['success' => false, 'message' => 'Mật khẩu cũ không đúng.']);
        exit;
    }

    // Cập nhật mật khẩu mới
    $stmt = $pdo->prepare("UPDATE NhanVien SET MatKhau = :matKhauMoi WHERE TaiKhoan = :taiKhoan");
    $stmt->execute(['matKhauMoi' => $matKhauMoi, 'taiKhoan' => $taiKhoan]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Đổi mật khẩu thành công.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể cập nhật mật khẩu.']);
    }
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Lỗi đổi mật khẩu: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi server. Vui lòng thử lại sau.']);
}
?>