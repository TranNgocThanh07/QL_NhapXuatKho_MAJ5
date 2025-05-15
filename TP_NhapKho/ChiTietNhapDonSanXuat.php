<?php
ob_start();
header('Content-Type: application/json');

// Cấu hình báo lỗi
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

include('../db_config.php');

// Hàm tạo mã CTNHTP tự động
function generateMaCTNHTP($index) {
    $prefix = 'CTNHTP';
    $random = mt_rand(100000, 999999);
    $date = date('YmdHis');
    return $prefix . $date . $random . '_' . str_pad($index, 3, '0', STR_PAD_LEFT);
}

// Hàm gửi lỗi
function sendError($message, $exception = null) {
    ob_end_clean();
    $errorDetails = $message;
    if ($exception instanceof Exception) {
        $errorDetails .= "\nChi tiết lỗi: " . $exception->getMessage() .
                         "\nMã lỗi: " . $exception->getCode() .
                         "\nDòng: " . $exception->getLine() .
                         "\nTệp: " . $exception->getFile() .
                         "\nStack trace:\n" . $exception->getTraceAsString();
    }
    error_log($errorDetails); // Ghi log chi tiết
    echo json_encode([
        'success' => false,
        'message' => '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> ' . nl2br(htmlspecialchars($errorDetails)) . '</div>'
    ]);
    exit;
}

// Validate dữ liệu bắt buộc
$requiredFields = ['MaSoMe', 'SoLuong', 'MaVai', 'SoCay', 'MaDonHang', 'MaNguoiLienHe'];
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        sendError("Thiếu trường bắt buộc: $field");
    }
}

// Chuẩn bị dữ liệu từ POST
$params = [
    'maSoMe'        => $_POST['MaSoMe'] ?? null,
    'soLuongNhap'   => floatval($_POST['SoLuong']),
    'soCayNhap'     => intval($_POST['SoCay']),
    'maVai'         => $_POST['MaVai'] ?? null,
    'maDonHang'     => $_POST['MaDonHang'] ?? null,
    'maNguoiLienHe' => $_POST['MaNguoiLienHe'] ?? null,
    'maKhachHang'   => $_POST['MaKhachHang'] ?? null,
    'maVatTu'       => $_POST['MaVatTu'] ?? null,
    'tenVai'        => $_POST['TenVai'] ?? null,
    'maMau'         => $_POST['MaMau'] ?? null,
    'maDVT'         => $_POST['MaDVT'] ?? null, // Lấy từ form hoặc từ TP_DonSanXuat
    'kho'           => $_POST['Kho'] ?? null,
    'soLot'         => $_POST['SoLot'] ?? null,
    'thanhPhan'     => $_POST['ThanhPhan'] ?? null,
    'maNhanVien'    => $_POST['MaNhanVien'] ?? null,
    'ngayTao'       => date('Y-m-d H:i:s'),
    'trangThai'     => 0
];

try {
    $pdo->beginTransaction();

    // Lấy STT lớn nhất
    $sqlMaxSTT = "SELECT MAX(STT) as maxSTT FROM TP_ChiTietDonSanXuat WHERE MaSoMe = ?";
    $stmtMaxSTT = $pdo->prepare($sqlMaxSTT);
    $stmtMaxSTT->execute([$params['maSoMe']]);
    $currentMaxSTT = $stmtMaxSTT->fetch(PDO::FETCH_ASSOC)['maxSTT'] ?? 0;

    // Lấy thông tin đơn sản xuất
    $sqlDon = "SELECT 
        ds.MaSoMe, ds.MaDonHang, ds.MaKhachHang, kh.TenKhachHang, 
        ds.MaVatTu, ds.MaVai, ds.TenVai, ds.MaMau, m.TenMau, 
        ds.MaDVT, dvt.TenDVT, ds.Kho, ds.MaNguoiLienHe, nlh.TenNguoiLienHe, 
        ds.SoKgQuyDoi 
    FROM TP_DonSanXuat ds
    LEFT JOIN TP_KhachHang kh ON ds.MaKhachHang = kh.MaKhachHang
    LEFT JOIN TP_Mau m ON ds.MaMau = m.MaMau
    LEFT JOIN TP_DonViTinh dvt ON ds.MaDVT = dvt.MaDVT
    LEFT JOIN TP_NguoiLienHe nlh ON ds.MaNguoiLienHe = nlh.MaNguoiLienHe
    WHERE ds.MaSoMe = ?";
    $stmtDon = $pdo->prepare($sqlDon);
    $stmtDon->execute([$params['maSoMe']]);
    $don = $stmtDon->fetch(PDO::FETCH_ASSOC);

    if (!$don) {
        sendError('Không tìm thấy đơn sản xuất');
    }

    // Tính tổng số lượng đã nhập
    $sqlTongLuong = "SELECT SUM(SoLuong) as TongLuongDaNhap FROM TP_ChiTietDonSanXuat WHERE MaSoMe = ?";
    $stmtTongLuong = $pdo->prepare($sqlTongLuong);
    $stmtTongLuong->execute([$params['maSoMe']]);
    $tongSoLuongHienTai = floatval($stmtTongLuong->fetch(PDO::FETCH_ASSOC)['TongLuongDaNhap'] ?? 0);

    $tongSoLuongNhapMoi = $params['soLuongNhap'] * $params['soCayNhap'];
    $tongSoLuongMoi = $tongSoLuongHienTai + $tongSoLuongNhapMoi;

    if ($tongSoLuongMoi > $soLuongQuyDoi) {
        $luongConLai = $soLuongQuyDoi - $tongSoLuongHienTai;
        sendError("Bạn đã nhập quá số lượng đơn hàng yêu cầu: $soLuongQuyDoi $tenDVT\nBạn đang nhập: $tongSoLuongNhapMoi $tenDVT\nBạn chỉ được phép nhập tối đa: $luongConLai $tenDVT");
    }

    // Tạo MaQR
    $maQRBase = implode('_', [
        $params['maSoMe'],
        $params['maVai'],
        $params['tenVai'],
        $params['soLuongNhap'],
        $params['soCayNhap'],
        $params['soLot'],
        $params['thanhPhan']
    ]);

    // Thêm chi tiết đơn
    $sqlInsert = "INSERT INTO TP_ChiTietDonSanXuat (
        STT, MaSoMe, MaNguoiLienHe, MaCTNHTP, MaDonHang, MaVai, MaVatTu, TenVai, 
        MaMau, MaDVT, Kho, SoLuong, MaQR, TrangThai, SoLot, NgayTao, MaKhachHang, MaNhanVien, ThanhPhan
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtInsert = $pdo->prepare($sqlInsert);

    for ($i = 1; $i <= $params['soCayNhap']; $i++) {
        $newSTT = $currentMaxSTT + $i;
        $maCTNHTPForCay = generateMaCTNHTP($i);
        $maQRForCay = $maQRBase ;

        $stmtInsert->execute([
            $newSTT, $params['maSoMe'], $params['maNguoiLienHe'], $maCTNHTPForCay,
            $params['maDonHang'], $params['maVai'], $params['maVatTu'], $params['tenVai'],
            $params['maMau'], $params['maDVT'], $params['kho'], $params['soLuongNhap'],
            $maQRForCay, $params['trangThai'], $params['soLot'], $params['ngayTao'],
            $params['maKhachHang'], $params['maNhanVien'], $params['thanhPhan']
        ]);
    }

    $pdo->commit();

    $soLuongConLai = $soLuongQuyDoi - $tongSoLuongMoi;
    $message = "Nhập thành công!\n" .
               "Tổng số lượng đơn sản xuất: $soLuongQuyDoi $tenDVT\n" .
               "Tổng số lượng đã nhập:     $tongSoLuongMoi $tenDVT\n" .
               "Số lượng còn lại:         $soLuongConLai $tenDVT";

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'soCay' => $params['soCayNhap'],
            'soLuongMoiCay' => $params['soLuongNhap'],
            'tongSoLuongNhapMoi' => $tongSoLuongNhapMoi,
            'soLuongQuyDoi' => $soLuongQuyDoi,
            'tongSoLuongNhap' => $tongSoLuongMoi,
            'soLuongConLai' => $soLuongConLai,
            'donViTinh' => $tenDVT
        ]
    ]);
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    sendError('Lỗi cơ sở dữ liệu:', $e);
} catch (Exception $e) {
    $pdo->rollBack();
    sendError('Lỗi hệ thống:', $e);
}
?>