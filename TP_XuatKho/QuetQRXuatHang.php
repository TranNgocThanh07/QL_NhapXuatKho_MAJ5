<?php
// TP_XuatHang/QuetQRXuatHang.php
include '../db_config.php';
require_once '../vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Input validation
$maXuatHang = trim($_GET['maXuatHang'] ?? '');
if (!$maXuatHang) {
    http_response_code(400);
    echo "<p class='text-red-600 text-center'>Không tìm thấy mã phiếu xuất!</p>";
    exit;
}

try {
    // Xử lý AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        $action = trim($_POST['action']);
        $validActions = ['updateOrderStatus', 'updateStatus', 'updateDonSanXuat'];
        
        if (!in_array($action, $validActions)) {
            echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
            exit;
        }

        $pdo->beginTransaction();

        if ($action === 'updateOrderStatus') {
            $maXuatHang = trim($_POST['maXuatHang'] ?? '');
            $status = trim($_POST['status'] ?? '');
            if (!$maXuatHang || !$status) {
                echo json_encode(['success' => false, 'message' => 'Thiếu thông tin mã xuất hàng hoặc trạng thái']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE TP_XuatHang SET TrangThai = ? WHERE MaXuatHang = ?");
            $stmt->execute([$status, $maXuatHang]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái đơn hàng']);
                exit;
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái đơn hàng thành công']);
            exit;
        }

        if ($action === 'updateStatus') {
            $maCTXHTP = trim($_POST['maCTXHTP'] ?? '');
            if (!$maCTXHTP) {
                echo json_encode(['success' => false, 'message' => 'Thiếu mã chi tiết xuất hàng']);
                exit;
            }

            // Kết hợp kiểm tra trạng thái đơn hàng và chi tiết, thay FOR UPDATE bằng WITH (UPDLOCK, ROWLOCK)
            $stmt = $pdo->prepare("
                SELECT xh.TrangThai AS OrderStatus, ctxh.TrangThai AS DetailStatus
                FROM TP_XuatHang xh WITH (UPDLOCK, ROWLOCK)
                JOIN TP_ChiTietXuatHang ctxh WITH (UPDLOCK, ROWLOCK) ON xh.MaXuatHang = ctxh.MaXuatHang
                WHERE ctxh.MaCTXHTP = ? AND xh.MaXuatHang = ?
            ");
            $stmt->execute([$maCTXHTP, $maXuatHang]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy chi tiết hoặc đơn hàng']);
                exit;
            }

            if ($result['OrderStatus'] == 1) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Đơn hàng đã hoàn tất, không thể quét thêm!']);
                exit;
            }

            if ($result['DetailStatus'] == 1) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Chi tiết đã được quét']);
                exit;
            }

            // Cập nhật trạng thái chi tiết và lấy tiến độ
            $stmtUpdate = $pdo->prepare("
                UPDATE TP_ChiTietXuatHang SET TrangThai = 1 WHERE MaCTXHTP = ?;
                SELECT 
                    COALESCE(SUM(SoLuong), 0) AS TongSoLuongXuat, 
                    COALESCE(SUM(CASE WHEN TrangThai = 1 THEN SoLuong ELSE 0 END), 0) AS SoLuongDaXuat,
                    COALESCE(SUM(CASE WHEN TrangThai = 0 THEN SoLuong ELSE 0 END), 0) AS SoLuongConLai,
                    COUNT(CASE WHEN TrangThai = 0 THEN 1 END) AS remaining 
                FROM TP_ChiTietXuatHang 
                WHERE MaXuatHang = ?
            ");
            $stmtUpdate->execute([$maCTXHTP, $maXuatHang]);
            $stmtUpdate->nextRowset();
            $progress = $stmtUpdate->fetch(PDO::FETCH_ASSOC);

            // Cập nhật trạng thái đơn hàng nếu cần
            if ($progress['remaining'] == 0) {
                $stmtUpdateOrder = $pdo->prepare("UPDATE TP_XuatHang SET TrangThai = 1 WHERE MaXuatHang = ? AND TrangThai = 0");
                $stmtUpdateOrder->execute([$maXuatHang]);
                if ($stmtUpdateOrder->rowCount() === 0 && $progress['remaining'] == 0) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái đơn hàng']);
                    exit;
                }
            }

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'remaining' => (int)$progress['remaining'],
                'tongSoLuongXuat' => (float)$progress['TongSoLuongXuat'],
                'soLuongDaXuat' => (float)$progress['SoLuongDaXuat'],
                'soLuongConLai' => (float)$progress['SoLuongConLai'],
                'message' => $progress['remaining'] == 0 ? 'Đã quét hết chi tiết và hoàn tất đơn hàng!' : 'Cập nhật trạng thái chi tiết thành công'
            ]);
            exit;
        }

        if ($action === 'updateDonSanXuat') {
            $maCTXHTP = trim($_POST['maCTXHTP'] ?? '');
            if (!$maCTXHTP) {
                echo json_encode(['success' => false, 'message' => 'Thiếu mã chi tiết xuất hàng']);
                exit;
            }

            // Lấy dữ liệu chi tiết, thay FOR UPDATE bằng WITH (UPDLOCK, ROWLOCK)
            $stmt = $pdo->prepare("
                SELECT ctxh.SoLuong, ctxh.MaCTNHTP, dsx.MaSoMe
                FROM TP_ChiTietXuatHang ctxh WITH (UPDLOCK, ROWLOCK)
                JOIN TP_ChiTietDonSanXuat dsx WITH (UPDLOCK, ROWLOCK) ON ctxh.MaCTNHTP = dsx.MaCTNHTP
                WHERE ctxh.MaCTXHTP = ?
            ");
            $stmt->execute([$maCTXHTP]);
            $chiTiet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$chiTiet) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy chi tiết hoặc mã mẻ sản xuất']);
                exit;
            }

            // Cập nhật DaGiao và ConLai
            $stmtUpdate = $pdo->prepare("
                UPDATE TP_DonSanXuat 
                SET DaGiao = COALESCE(DaGiao, 0) + ?, 
                    ConLai = TongSoLuongGiao - (COALESCE(DaGiao, 0) + ?)
                WHERE MaSoMe = ?
            ");
            $stmtUpdate->execute([$chiTiet['SoLuong'], $chiTiet['SoLuong'], $chiTiet['MaSoMe']]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Cập nhật đơn sản xuất thành công']);
            exit;
        }
    }

    // Lấy thông tin phiếu xuất và chi tiết trong một truy vấn
    $stmt = $pdo->prepare("
        SELECT 
            xh.MaXuatHang, xh.TrangThai AS TrangThaiDon, nv.TenNhanVien, xh.NgayXuat, xh.GhiChu,
            COALESCE(SUM(ct.SoLuong), 0) AS TongSoLuongXuat, 
            COALESCE(SUM(CASE WHEN ct.TrangThai = 1 THEN ct.SoLuong ELSE 0 END), 0) AS SoLuongDaXuat,
            COALESCE(SUM(CASE WHEN ct.TrangThai = 0 THEN ct.SoLuong ELSE 0 END), 0) AS SoLuongConLai,
            MIN(dvt.TenDVT) AS TenDVT, MIN(v.MaVai) AS MaVai, MIN(v.TenVai) AS TenVai, 
            MIN(m.TenMau) AS TenMau, MIN(ct.SoLot) AS SoLot, MIN(ct.TenThanhPhan) AS TenThanhPhan, 
            MIN(ct.MaDonHang) AS MaDonHang, MIN(ct.MaVatTu) AS MaVatTu, MIN(ct.Kho) AS Kho,
            kh.TenKhachHang, kh.TenHoatDong, kh.DiaChi, nlh.TenNguoiLienHe, nlh.SoDienThoai,
            STRING_AGG(
                ISNULL(ct.MaCTXHTP, '') + ':' + ISNULL(CAST(ct.SoLuong AS NVARCHAR), '') + ':' + 
                ISNULL(CAST(ct.TrangThai AS NVARCHAR), '') + ':' + ISNULL(dvt.TenDVT, '') + ':' + 
                ISNULL(v.MaVai, '') + ':' + ISNULL(v.TenVai, '') + ':' + ISNULL(m.TenMau, '') + ':' + 
                ISNULL(ct.SoLot, '') + ':' + ISNULL(ct.TenThanhPhan, '') + ':' + ISNULL(ct.MaDonHang, '') + ':' + 
                ISNULL(ct.MaVatTu, '') + ':' + ISNULL(ct.Kho, '') + ':' + ISNULL(ct.MaQR, ''), '|'
            ) AS ChiTietXuat
        FROM TP_XuatHang xh
        LEFT JOIN TP_ChiTietXuatHang ct ON xh.MaXuatHang = ct.MaXuatHang
        LEFT JOIN NhanVien nv ON xh.MaNhanVien = nv.MaNhanVien
        LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
        LEFT JOIN Vai v ON ct.MaVai = v.MaVai
        LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
        LEFT JOIN TP_KhachHang kh ON xh.MaKhachHang = kh.MaKhachHang
        LEFT JOIN TP_NguoiLienHe nlh ON xh.MaNguoiLienHe = nlh.MaNguoiLienHe
        WHERE xh.MaXuatHang = ?
        GROUP BY xh.MaXuatHang, xh.TrangThai, nv.TenNhanVien, xh.NgayXuat, xh.GhiChu, 
                 kh.TenKhachHang, kh.TenHoatDong, kh.DiaChi, nlh.TenNguoiLienHe, nlh.SoDienThoai
    ");
    $stmt->execute([$maXuatHang]);
    $phieuXuat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$phieuXuat) {
        http_response_code(404);
        echo "<p class='text-red-600 text-center'>Không tìm thấy thông tin phiếu xuất!</p>";
        exit;
    }

    // Xử lý chi tiết xuất hàng từ STRING_AGG
    $chiTietXuat = [];
    if ($phieuXuat['ChiTietXuat']) {
        $chiTietRows = explode('|', $phieuXuat['ChiTietXuat']);
        foreach ($chiTietRows as $row) {
            $fields = explode(':', $row);
            $chiTietXuat[] = [
                'MaCTXHTP' => $fields[0],
                'SoLuong' => $fields[1],
                'TrangThai' => $fields[2],
                'TenDVT' => $fields[3],
                'MaVai' => $fields[4],
                'TenVai' => $fields[5],
                'TenMau' => $fields[6],
                'SoLot' => $fields[7],
                'TenThanhPhan' => $fields[8],
                'MaDonHang' => $fields[9],
                'MaVatTu' => $fields[10],
                'Kho' => $fields[11],
                'MaQR' => $fields[12]
            ];
        }
    }

    $tongXuat = (float)$phieuXuat['TongSoLuongXuat'];
    $daXuat = (float)$phieuXuat['SoLuongDaXuat'];
    $conLai = (float)$phieuXuat['SoLuongConLai'];
    $percentCompleted = $tongXuat > 0 ? round(($daXuat / $tongXuat) * 100, 1) : 0;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo "<p class='text-red-600 text-center'>Lỗi: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

$ngayXuat = date('d/m/Y', strtotime($phieuXuat['NgayXuat']));
$trangThaiDon = $phieuXuat['TrangThaiDon'] == 1 ? 'Hoàn tất' : 'Đang xử lý';

function generateQRCodeBase64($data) {
    static $writer = null;
    if ($writer === null) {
        $writer = new PngWriter();
    }
    $qrCode = new QrCode($data);
    $qrCode->setSize(100);
    $qrCode->setMargin(5);
    $result = $writer->write($qrCode);
    return "data:image/png;base64," . base64_encode($result->getString());
}

$chiTietXuatWithQR = array_map(function($ct) {
    $ct['qrCode'] = generateQRCodeBase64($ct['MaCTXHTP']);
    return $ct;
}, $chiTietXuat);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Phiếu Xuất - MAJ5</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<style>
    :root {
        --primary-color: #FF3B30;
        --secondary-color: #5850EC;
        --success-color: #34C759;
        --warning-color: #FF9500;
        --danger-color: #FF3B30;
        --info-color: #007AFF;
        --background-color: #F7F8FA;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--background-color);
        color: #1F2A44;
        line-height: 1.6;
        margin: 0;
        padding: 0;
    }

    .container{
        max-width: 100%;
        margin: 0 auto;
    }

    .header-gradient {
        background: linear-gradient(135deg, var(--primary-color), #D11F2A);
        padding: 1rem;
        box-shadow: 0 4px 20px rgba(255, 59, 48, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .header-gradient h2 {
        font-size: 1rem;
        margin: 0;
        gap: 0.5rem;
    }

    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        margin-bottom: 1rem;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
    }

    .progress-bar {
        height: 12px;
        background: #E5E7EB;
        border-radius: 12px;
        overflow: hidden;
    }

    .progress-value {
        height: 100%;
        background: linear-gradient(90deg, var(--secondary-color), #818CF8);
        border-radius: 12px;
        transition: width 0.5s ease;
    }

    .pulse {
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(88, 80, 236, 0.7); }
        70% { box-shadow: 0 0 0 12px rgba(88, 80, 236, 0); }
        100% { box-shadow: 0 0 0 0 rgba(88, 80, 236, 0); }
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
        position: relative;
        overflow: hidden;
        border: 1px solid #E5E7EB;
    }

    .stat-card i {
        position: absolute;
        right: -10px;
        bottom: -10px;
        font-size: 2.5rem;
        opacity: 0.1;
    }

    .data-table-container {
        max-height: 1000px;
        overflow-y: auto;
        border-radius: 16px;
        border: 1px solid #E5E7EB;
        position: relative;
    }

    .table-container {
        overflow-x: auto;
        border-radius: 16px;
        border: 1px solid #E5E7EB;
        max-height: 450px;
        overflow-y: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px; /* Đảm bảo bảng có chiều rộng tối thiểu */
    }

    th, td {
        padding: 0.5rem;
        text-align: left;
        font-size: 0.75rem;
    }

    th {
        background: linear-gradient(to right, #FFF1F1, #FFE5E5);
        position: sticky;
        top: 0;
        z-index: 10;
        font-weight: 600;
        text-transform: uppercase;
        color: #6B7280;
    }

    tr:hover {
        background: #FFF5F5;
    }

    .badge {
        padding: 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .scan-button {
        background: var(--success-color);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        box-shadow: 0 6px 15px rgba(52, 199, 89, 0.3);
        transition: all 0.2s ease;
        font-size: 0.75rem;
    }

    .scan-button:hover {
        background: #2DB847;
        transform: scale(1.05);
    }

    #scanner-container {
        width: 100%;
        height: 280px;
        background: #F0F0F0;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }

    .qr-guide {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: white;
        background: rgba(0, 0, 0, 0.6);
        padding: 0.75rem;
        border-radius: 8px;
        text-align: center;
        z-index: 10;
        font-size: 0.75rem;
    }

    .wrap-text {
        white-space: normal;
        word-break: break-word;
        max-width: 150px;
    }

    /* Responsive Adjustments */
    @media (max-width: 1024px) {
        .grid-cols-3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .info-card {
            padding: 1rem;
        }

        .header-gradient h2 {
            font-size: 0.9rem;
        }

        .scan-button {
            padding: 0.5rem 0.75rem;
            font-size: 0.7rem;
        }

        .stat-card p {
            font-size: 0.7rem;
        }

        .stat-card .text-lg {
            font-size: 1rem;
        }
    }

    @media (max-width: 768px) {
        .grid-cols-3, .grid-cols-1 {
            grid-template-columns: 1fr;
        }

        .header-gradient {
            padding: 0.75rem;
            gap: 0.5rem;
        }

        .header-gradient h2 {
            font-size: 0.85rem;
        }

        .info-card {
            padding: 0.75rem;
        }

        .info-card p {
            font-size: 0.7rem;
        }

        th, td {
            font-size: 0.5rem;
        }

        .scan-button {
            padding: 0.5rem 0.75rem;
            font-size: 0.65rem;
        }
   
        .qr-guide {
            font-size: 0.7rem;
            padding: 0.5rem;
        }

        .data-table-container {
            max-height: 1000px;
        }

        .table-container {
            max-height: 350px;
        }
    }

    @media (max-width: 480px) {
        .header-gradient {
            padding: 0.5rem;
        }

        .header-gradient h2 {
            font-size: 0.8rem;
        }

        .info-card {
            padding: 0.5rem;
        }

        .info-card h3 {
            font-size: 0.75rem;
        }

        .info-card p {
            font-size: 0.65rem;
        }

        .stat-card {
            padding: 0.75rem;
        }

        .stat-card p {
            font-size: 0.65rem;
        }

        .stat-card .text-lg {
            font-size: 0.9rem;
        }

        .scan-button {
            padding: 0.4rem 0.6rem;
            font-size: 0.6rem;
        }     

        .qr-guide {
            font-size: 0.65rem;
            padding: 0.4rem;
        }

        th, td {
            padding: 0.25rem;
            font-size: 0.6rem;
        }

        .wrap-text {
            max-width: 120px;
        }

       #successModal {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        #successModal.show {
            opacity: 1;
        }
        #successModal > div {
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }
        #successModal.show > div {
            transform: scale(1);
        }
        .notification-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.5s ease;
            font-size: 0.875rem;
            max-width: 300px;
        }
        .notification-container.success {
            background-color: #34C759;
            color: white;
        }
        .notification-container.error {
            background-color: #FF3B30;
            color: white;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <div class="card">
            <header class="sticky top-0 z-20 bg-gradient-to-r from-red-700 to-red-500 text-white w-full flex items-center py-3 px-6">
                <a href="../xuatkho.php" class="text-white text-xl hover:scale-110 transition-transform flex items-center gap-1 hover:bg-red-600 rounded-full p-2">
                    <i class="fas fa-arrow-left text-xl"></i>
                   
                </a>
                <h2 class="text-lg md:text-2xl font-bold gap-2">
                    <i class="fas fa-clipboard-list"></i> Chi Tiết Phiếu Xuất Kho
                </h2>
            </header>
            <div class="bg-blue-50 border-b border-blue-100 p-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-info-circle icon-blue"></i>
                        <span class="text-blue-700 font-medium">Ngày tạo phiếu</span>
                    </div>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-calendar-alt icon-gray"></i> <?php echo $ngayXuat; ?>
                    </div>
                </div>

            <div class="card">
                <!-- Info Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
                    <div class="info-card bg-gray-50 p-2 rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <h3 class="text-sm font-semibold text-gray-500 mb-4 flex items-center gap-2">
                            <i class="fas fa-file-invoice font-bold text-red-500"></i> Thông tin phiếu
                        </h3>
                        <p class="flex justify-between text-xs mb-3">
                            <span class="text-gray-600"><i class="fas fa-hashtag text-red-400 mr-2"></i>Mã phiếu:</span>
                            <span class="font-bold badge bg-red-100 text-red-700"><?php echo htmlspecialchars($phieuXuat['MaXuatHang']); ?></span>
                        </p>
                        <p class="flex text-xs justify-between mb-3">
                            <span class="text-gray-600 "><i class="fas fa-shopping-cart text-orange-400 mr-2"></i>Mã đơn hàng:</span>
                            <span class="font-bold"><?php echo htmlspecialchars($phieuXuat['MaDonHang']); ?></span>
                        </p>
                        <p class="flex text-xs justify-between">
                            <span class="text-gray-600 "><i class="fas fa-boxes text-purple-400 mr-2"></i>Mã vật tư:</span>
                            <span class="font-bold"><?php echo htmlspecialchars($phieuXuat['MaVatTu']); ?></span>
                        </p>
                    </div>
                    <div class="info-card bg-gray-50 p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <h3 class="text-sm font-semibold text-gray-500 mb-4 flex items-center gap-2">
                            <i class="fas fa-tshirt text-blue-500"></i> Thông tin sản phẩm
                        </h3>
                        <p class="flex text-xs justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-layer-group text-blue-400 mr-2"></i>Vải:</span>
                            <span class="font-bold">
                                <?php                             
                                    echo htmlspecialchars($phieuXuat['MaVai'] . ' (' .htmlspecialchars($phieuXuat['TenVai'] . ')'));
                                ?>
                            </span>
                        </p>
                        <p class="flex text-xs justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-palette text-pink-400 mr-2"></i>Màu:</span>
                            <span class="font-bold"><?php    $fabricName = explode('-', $phieuXuat['TenMau'])[0]; // Take part before '-'
                             echo $fabricName; ?></span>
                        </p>
                        <p class="flex text-xs justify-between">
                            <span class="text-gray-600"><i class="fas fa-ruler-combined text-yellow-500 mr-2"></i>Khổ:</span>
                            <span class="font-bold"><?php echo htmlspecialchars($phieuXuat['Kho']); ?></span>
                        </p>
                    </div>
                    <div class="info-card bg-gray-50 p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <h3 class="text-sm font-semibold text-gray-500 mb-4 flex items-center gap-2">
                            <i class="fas fa-truck-loading text-green-500"></i> Thông tin xuất kho
                        </h3>
                        <p class="flex text-xs justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-user-tie text-indigo-400 mr-2"></i>Nhân viên:</span>
                            <span class="font-bold"><?php echo htmlspecialchars($phieuXuat['TenNhanVien']); ?></span>
                        </p>
                        <p class="flex text-xs justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-box-open text-green-500 mr-2"></i>Tổng SL xuất:</span>
                            <span class="font-bold"><?php echo number_format($phieuXuat['TongSoLuongXuat'], 0, ',', '.') . ' ' . htmlspecialchars($phieuXuat['TenDVT']); ?></span>
                        </p>
                        <p class="flex text-xs justify-between">
                            <span class="text-gray-600"><i class="fas fa-calendar-check text-blue-400 mr-2"></i>Ngày xuất:</span>
                            <span class="font-bold"><?php echo $ngayXuat; ?></span>
                        </p>
                    </div>

                    <div class="info-card bg-gray-50 p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <h3 class="text-sm font-semibold text-gray-500 mb-4 flex items-center gap-2">
                            <i class="fas fa-user-friends text-yellow-500"></i> Thông tin khách hàng
                        </h3>
                        <p class="flex text-xs justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-building text-red-400 mr-2"></i>Tên khách hàng:</span>
                            <span class="font-bold"><?php echo htmlspecialchars($phieuXuat['TenKhachHang'] ?? 'Không xác định'); ?></span>
                        </p>
                        <p class="flex text-xs justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-briefcase text-blue-400 mr-2"></i>Tên hoạt động:</span>
                            <span class="font-bold text-right wrap-text"><?php echo htmlspecialchars($phieuXuat['TenHoatDong'] ?? 'Không xác định'); ?></span>
                        </p>
                        <p class="flex text-xs justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-map-marker-alt text-red-400 mr-2"></i>Địa chỉ:</span>
                            <span class="font-bold text-right wrap-text"><?php echo htmlspecialchars($phieuXuat['DiaChi'] ?? 'Không xác định'); ?></span>
                        </p>
                        <p class="flex text-xs justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-user text-green-400 mr-2"></i>Người liên hệ:</span>
                            <span class="font-bold"><?php echo htmlspecialchars($phieuXuat['TenNguoiLienHe'] ?? 'Không xác định'); ?></span>
                        </p>
                        <p class="flex text-xs justify-between">
                            <span class="text-gray-600"><i class="fas fa-phone text-yellow-400 mr-2"></i>Số điện thoại:</span>
                            <span class="font-bold"><?php echo htmlspecialchars($phieuXuat['SoDienThoai'] ?? 'Không xác định'); ?></span>
                        </p>
                    </div>
                </div>
            
                <!-- Progress Section -->
                <div class="">
                    <h3 class="section-title text-sm font-bold text-gray-800 flex items-center ml-5 mb-5">
                        <i class="fas fa-chart-line text-indigo-500 mr-2"></i> Tiến Độ Xuất Kho
                    </h3>
                    <div class="bg-white rounded-xl text-xs border p-5 card-shadow">
                        <div class="flex justify-between mb-3">
                            <span class="font-semibold text-gray-700 flex items-center">
                                <span class="bg-indigo-100 p-1 rounded-md text-indigo-500 mr-2"><i class="fas fa-chart-line"></i></span>
                                Tiến độ : 
                                <span id="progressPercent" class="ml-2 bg-indigo-100 text-indigo-700 px-2 py-1 rounded-lg font-bold"><?php echo $percentCompleted; ?>%</span>
                            </span>
                            <span id="progressText" class="font-semibold text-gray-700 flex items-center">
                                <i class="fas fa-box-open text-indigo-400 mr-2"></i>
                                <?php echo number_format($daXuat, 0, ',', '.'); ?> / <?php echo number_format($tongXuat, 0, ',', '.'); ?> <?php echo htmlspecialchars($phieuXuat['TenDVT']); ?>
                            </span>
                        </div>
                        <div class="progress-bar mb-6">
                            <div class="progress-value <?php echo ($percentCompleted < 100) ? 'pulse' : ''; ?>" style="width: <?php echo $percentCompleted; ?>%"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="stat-card">
                                <i class="fas fa-boxes text-blue-300"></i>
                                <p class="text-xs font-medium text-blue-600">Tổng</p>
                                <p class="font-bold text-lg text-blue-800"><?php echo number_format($tongXuat, 0, ',', '.'); ?></p>
                                <p class="text-xs text-blue-500"><?php echo htmlspecialchars($phieuXuat['TenDVT']); ?></p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-check-circle text-green-300"></i>
                                <p class="text-xs font-medium text-green-600">Đã Xuất</p>
                                <p class="font-bold text-lg text-green-800"><?php echo number_format($daXuat, 0, ',', '.'); ?></p>
                                <p class="text-xs text-green-500"><?php echo htmlspecialchars($phieuXuat['TenDVT']); ?></p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-hourglass-half text-red-300"></i>
                                <p class="text-xs font-medium text-red-600">Còn Lại</p>
                                <p class="font-bold text-lg text-red-800"><?php echo number_format($conLai, 0, ',', '.'); ?></p>
                                <p class="text-xs text-red-500"><?php echo htmlspecialchars($phieuXuat['TenDVT']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                 <!-- Details Table -->
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 card-hover border border-gray-100">
                   <h3 class="section-header text-base sm:text-lg font-bold text-gray-800 flex items-center justify-between gap-2 mb-4">
                        <div class="flex items-center gap-2">
                            <div class="icon-circle bg-indigo-100 text-indigo-600">
                                <i class="fas fa-list"></i>
                            </div>
                            <span>Chi Tiết Xuất Kho</span>
                            <span class="text-sm bg-red-100 text-red-700 px-2 py-1 rounded-full"><?php echo count($chiTietXuat); ?> mục</span>
                        </div>
                        <button id="btnQuetMa" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm flex items-center gap-2">
                            <i class="fas fa-qrcode"></i> Quét Mã QR
                        </button>
                    </h3>
                    <?php if ($chiTietXuatWithQR): ?>
                        <div class="responsive-table custom-scrollbar" style="overflow-x: auto;">
                            <table class="w-full border-collapse" id="chiTietTable" style="min-width: 700px;">
                                <thead class="bg-red-50 text-red-800 sticky top-0 z-10">
                                    <tr>
                                        <th class="text-left sticky left-0 bg-red-50 z-20 p-2 sm:p-3 font-semibold">
                                            <i class="fas fa-list-ol mr-2 text-blue-600"></i>STT
                                        </th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">
                                            <i class="fas fa-boxes mr-2 text-green-600"></i>Số Lượng
                                        </th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">
                                            <i class="fas fa-tag mr-2 text-yellow-600"></i>Số Lot
                                        </th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">
                                            <i class="fas fa-cubes mr-2 text-purple-600"></i>Thành Phần
                                        </th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">
                                            <i class="fas fa-check-circle mr-2 text-green-500"></i>Trạng Thái
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $stt = 1;
                                    foreach ($chiTietXuatWithQR as $ct): 
                                        $trangThaiHienThi = ($ct['TrangThai'] == 1) ? 'Đã xuất' : 'Chưa xuất';
                                        $trangThaiClass = ($ct['TrangThai'] == 1) ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600';
                                        $soLuongClass = ($ct['SoLuong'] > 0) ? 'text-green-600' : 'text-red-600';
                                    ?>
                                        <tr class="border-b border-gray-200 hover:bg-red-100 transition-colors" data-ma-ctxhtp="<?php echo htmlspecialchars($ct['MaCTXHTP']); ?>">
                                            <td class="sticky left-0 bg-white p-2 sm:p-3"><?php echo $stt++; ?></td>
                                            <td class="font-bold p-2 sm:p-3 whitespace-normal <?php echo $soLuongClass; ?>">
                                                <?php echo number_format($ct['SoLuong'], 0, ',', '.') . ' ' . htmlspecialchars($ct['TenDVT']); ?>
                                            </td>
                                            <td class="p-2 sm:p-3"><?php echo htmlspecialchars($ct['SoLot']); ?></td>
                                            <td class="p-2 sm:p-3"><?php echo htmlspecialchars($ct['TenThanhPhan']); ?></td>
                                            <td class="p-2 sm:p-3">
                                                <span class="px-2 py-1 rounded-full text-xs <?php echo $trangThaiClass; ?> <?php echo ($ct['TrangThai'] == 1) ? 'text-da-xuat' : 'text-chua-xuat'; ?>">
                                                    <?php echo htmlspecialchars($trangThaiHienThi); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>       
                    <?php else: ?>
                        <div class="text-center bg-red-50 rounded-lg p-6">
                            <i class="ri-error-warning-line text-4xl text-red-500 mb-3"></i>
                            <p class="text-red-600 text-base font-semibold">Không tìm thấy chi tiết xuất kho cho đơn này.</p>
                        </div>
                    <?php endif; ?>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden transition-opacity duration-300">
        <div class="bg-white p-6 rounded-lg w-full max-w-md mx-4 transform scale-95 transition-transform duration-300">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold text-green-600 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> Hoàn Tất Đơn Hàng
                </h3>
                <button id="closeSuccessModal" class="text-gray-500 hover:text-gray-700">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-sm text-gray-600 mb-4">Đã quét thành công toàn bộ đơn xuất hàng!</p>
            <div class="flex justify-center">
                <button id="successOkButton" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors duration-200">
                    <i class="fas fa-check mr-2"></i> OK
                </button>
            </div>
        </div>
    </div>
    <!-- QR Scanner Modal -->
    <div id="scannerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg w-full max-w-2xl mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold">Quét Mã QR</h3>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="scanner-container">
                <div class="qr-guide">
                    <p>Đang khởi động camera...</p>
                    <p>Vui lòng chờ và cho phép quyền truy cập camera.</p>
                </div>
            </div>
            <p class="text-xs text-gray-600 mt-4 text-center">Chỉnh góc quay để mã QR nằm trong khung.</p>
            <div class="flex justify-center gap-4 mt-4">
                <button id="switchCamera" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i> Đổi Camera
                </button>
                <button id="scanButton" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-qrcode mr-2"></i> Quét
                </button>
            </div>
        </div>
    </div>

    <!-- Scan Button -->
    

    <!-- <div id="logArea" class="fixed bottom-0 left-0 w-full bg-gray-900 text-white p-4 max-h-40 overflow-y-auto z-50 hidden">
        <h4 class="text-xs font-bold mb-2">Log Debug:</h4>
        <div id="logContent" class="text-xs"></div>
    </div> -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- KHỞI TẠO ÂM THANH VỚI FILE .MP3 ---
        const sounds = {
        thanh_cong: new Audio('../TP_XuatKho/sounds/thanh_cong.mp3'),
        hoan_tat_don_hang: new Audio('../TP_XuatKho/sounds/hoan_tat_don_hang.mp3'),
        da_quet_roi: new Audio('../TP_XuatKho/sounds/da_quet_roi.mp3'),
        ma_khong_dung: new Audio('../TP_XuatKho/sounds/ma_khong_dung.mp3'),
        khong_thay_ma: new Audio('../TP_XuatKho/sounds/khong_thay_ma.mp3'),
        thao_tac_loi: new Audio('../TP_XuatKho/sounds/thao_tac_loi.mp3'),
        play: async function(soundKey) {
            // Dừng tất cả âm thanh khác
            Object.keys(this).forEach(key => {
                if (key !== 'play' && this[key].pause) {
                    this[key].pause();
                    this[key].currentTime = 0;
                }
            });
            const sound = this[soundKey];
            if (!sound) {
                console.error(`Âm thanh ${soundKey} không tồn tại`);
                showNotification(`Âm thanh ${soundKey} không tồn tại`, 'error');
                return false;
            }
            try {
                sound.preload = 'auto';
                if (sound.readyState < 2) { // Chưa tải xong
                    await new Promise((resolve, reject) => {
                        sound.oncanplaythrough = resolve;
                        sound.onerror = () => reject(new Error(`Không thể tải âm thanh ${soundKey}`));
                        sound.load();
                    });
                }
                await sound.play();
                console.log(`Phát âm thanh: ${soundKey}`);
                return true;
            } catch (err) {
                console.error(`Lỗi phát âm thanh ${soundKey}:`, err);
                showNotification(`Không thể phát âm thanh ${soundKey}: ${err.message}`, 'error');
                return false;
            }
        }
    };

    // Tải trước tất cả âm thanh
    Object.keys(sounds).forEach(key => {
        if (key !== 'play' && sounds[key]) {
            sounds[key].preload = 'auto';
            sounds[key].load();
            sounds[key].onerror = () => {
                console.error(`Lỗi tải âm thanh ${key}`);
                showNotification(`Không thể tải âm thanh ${key}`, 'error');
            };
        }
    });

    // --- CÁC BIẾN VÀ THAM CHIẾU DOM ---
    const successModal = document.getElementById('successModal');
    const successOkButton = document.getElementById('successOkButton');
    const closeSuccessModal = document.getElementById('closeSuccessModal');
    const scannerModal = document.getElementById('scannerModal');
    const scanButton = document.getElementById('scanButton');
    const switchCameraButton = document.getElementById('switchCamera');
    const closeModalButton = document.getElementById('closeModal');
    const btnQuetMa = document.getElementById('btnQuetMa');
    const progressPercent = document.getElementById('progressPercent');
    const progressText = document.getElementById('progressText');
    const progressValue = document.querySelector('.progress-value');
    const qrGuide = document.querySelector('#scanner-container .qr-guide');

    let html5QrCode = null;
    let currentCameraId = null;
    let camerasAvailable = [];
    let isScanningActive = false;
    let notificationTimeout = null;
    let isOrderComplete = <?php echo $phieuXuat['TrangThaiDon'] == 1 ? 'true' : 'false'; ?>;
    let isScannerInitialized = false;

    let scanRequestHandler = null;

    const chiTietList = <?php echo json_encode(array_column($chiTietXuatWithQR, 'MaQR')); ?>;
    const maCTXHTPList = <?php echo json_encode(array_column($chiTietXuatWithQR, 'MaCTXHTP')); ?>;
    const maXuatHang = '<?php echo htmlspecialchars($maXuatHang); ?>';
    const tenDVT = '<?php echo htmlspecialchars($phieuXuat['TenDVT']); ?>';

    const notificationContainer = document.createElement('div');
    notificationContainer.className = 'fixed top-4 right-4 p-4 rounded-lg shadow-lg z-[1000] hidden';
    notificationContainer.style.transition = 'opacity 0.5s ease';
    document.body.appendChild(notificationContainer);

    // --- HÀM HIỂN THỊ THÔNG BÁO VỚI ÂM THANH ---
    async function showNotification(message, type, isOrderCompleted = false) {
        console.log('Thông báo:', message, 'Loại:', type); // Debug thông báo
        let soundKey;

        // Xác định âm thanh dựa trên thông báo và loại
        if (isOrderCompleted && successModal) {
            soundKey = 'hoan_tat_don_hang';
        } else if (type === 'success') {
            soundKey = 'thanh_cong';
        } else {
            if (message.includes('đã được quét rồi') || message.includes('Chi tiết đã được quét')) {
                soundKey = 'da_quet_roi';
            } else if (message.includes('không khớp') || message.includes('Mã QR không khớp')) {
                soundKey = 'ma_khong_dung';
            } else if (message.includes('Không tìm thấy mã QR')) {
                soundKey = 'khong_thay_ma';
            } else {
                soundKey = 'thao_tac_loi';
            }
        }

        // Phát âm thanh và chờ hoàn thành
        console.log('Chuẩn bị phát âm thanh:', soundKey);
        const soundPlayed = await sounds.play(soundKey);

        // Hiển thị thông báo
        if (isOrderCompleted && successModal) {
            successModal.classList.remove('hidden');
            requestAnimationFrame(() => successModal.classList.add('show'));
        } else {
            if (notificationTimeout) clearTimeout(notificationTimeout);
            notificationContainer.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-[1000] ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notificationContainer.innerHTML = message || 'Không có thông báo';
            notificationContainer.classList.remove('hidden');
            requestAnimationFrame(() => {
                notificationContainer.style.opacity = '1';
                notificationTimeout = setTimeout(() => {
                    notificationContainer.style.opacity = '0';
                    setTimeout(() => notificationContainer.classList.add('hidden'), 200);
                }, 2000);
            });
        }
    }

    if (successOkButton) {
        successOkButton.addEventListener('click', () => {
            successModal.classList.remove('show');
            setTimeout(() => {
                successModal.classList.add('hidden');
                window.location.href = '../xuatkho.php';
            }, 300);
        });
    }

    if (closeSuccessModal) {
        closeSuccessModal.addEventListener('click', () => {
            successModal.classList.remove('show');
            setTimeout(() => successModal.classList.add('hidden'), 300);
        });
    }

    function isOrderCompleted() {
        const conLaiElement = document.querySelector('.stat-card:nth-child(3) .text-lg');
        return conLaiElement ? parseInt(conLaiElement.textContent.replace(/\./g, ''), 10) === 0 : false;
    }

    function updateStatus(maCTXHTP) {     
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=updateStatus&maCTXHTP=${encodeURIComponent(maCTXHTP)}`
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP error! Status: ${response.status}, Message: ${text}`);
                });
            }
            return response.json().catch(err => {
                throw new Error(`Lỗi parse JSON: ${err.message}`);
            });
        })
        .then(data => {
            if (data.success) {
                const row = document.querySelector(`tr[data-ma-ctxhtp="${maCTXHTP}"] td:nth-child(5) span`);
                if (row) {
                    row.className = 'text-da-xuat font-medium flex items-center gap-1 px-3 py-1 rounded-full border bg-green-50 border-green-200';
                    row.innerHTML = '<i class="fas fa-check-circle"></i> Đã xuất';
                }

                const newDaXuat = parseInt(data.soLuongDaXuat || 0, 10);
                const newTongXuat = parseInt(data.tongSoLuongXuat || 0, 10);
                const newConLai = parseInt(data.soLuongConLai || 0, 10);

                const tongXuatElement = document.querySelector('.stat-card:nth-child(1) .text-lg');
                const daXuatElement = document.querySelector('.stat-card:nth-child(2) .text-lg');
                const conLaiElement = document.querySelector('.stat-card:nth-child(3) .text-lg');

                if (!tongXuatElement || !daXuatElement || !conLaiElement || !progressPercent || !progressText || !progressValue) {
                    showNotification('Lỗi: Không tìm thấy phần tử để cập nhật tiến độ', 'error');
                    return;
                }

                requestAnimationFrame(() => {
                    tongXuatElement.textContent = newTongXuat.toLocaleString('vi-VN');
                    daXuatElement.textContent = newDaXuat.toLocaleString('vi-VN');
                    conLaiElement.textContent = newConLai.toLocaleString('vi-VN');

                    const newPercent = newTongXuat > 0 ? (newDaXuat / newTongXuat * 100).toFixed(1) : 0;
                    progressPercent.textContent = `${newPercent}%`;
                    progressText.innerHTML = `
                        <i class="fas fa-box-open text-indigo-400 mr-2"></i>
                        ${newDaXuat.toLocaleString('vi-VN')} / ${newTongXuat.toLocaleString('vi-VN')} ${tenDVT}
                    `;
                    progressValue.style.width = `${newPercent}%`;
                    progressValue.classList.toggle('pulse', newPercent < 100);
                });

                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=updateDonSanXuat&maCTXHTP=${encodeURIComponent(maCTXHTP)}`
                })
                .then(response => response.json())
                .then(dataDonSanXuat => {
                    if (dataDonSanXuat.success) {
                        showNotification(
                           `Quét chi tiết thành công! Tổng: ${newTongXuat.toLocaleString('vi-VN')} ${tenDVT}, Đã xuất: ${newDaXuat.toLocaleString('vi-VN')} ${tenDVT}, Còn lại: ${newConLai.toLocaleString('vi-VN')} ${tenDVT}`,
                            'success'
                        );
                        if (data.remaining === 0 || isOrderCompleted()) {
                            isOrderComplete = true;
                            stopScanner();
                            scannerModal.classList.remove('show');
                            setTimeout(() => scannerModal.classList.add('hidden'), 300);
                            showNotification('', 'success', true);
                            updateOrderStatus();
                        }
                    } else {
                        showNotification(dataDonSanXuat.message || 'Lỗi khi cập nhật đơn sản xuất', 'error');
                    }
                })
                .catch(err => handleFetchError(err, 'Lỗi khi cập nhật đơn sản xuất'));
            } else {
                showNotification(data.message || 'Lỗi khi cập nhật trạng thái', 'error');
            }
        })
        .catch(err => handleFetchError(err, 'Lỗi khi cập nhật trạng thái'));
    }

    function updateOrderStatus() {     
        if (!maXuatHang) {
            showNotification('Không tìm thấy mã đơn hàng để cập nhật trạng thái!', 'error');
            return;
        }
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=updateOrderStatus&maXuatHang=${encodeURIComponent(maXuatHang)}&status=1`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showNotification(data.message || 'Lỗi khi cập nhật trạng thái đơn hàng', 'error');
            }
        })
        .catch(err => handleFetchError(err, 'Lỗi khi cập nhật trạng thái đơn hàng'));
    }

    function handleFetchError(err, defaultMessage) {
        let message;
        if (err.message.includes('HTTP error')) {
            message = `${defaultMessage}: ${err.message}`;
        } else if (err.message.includes('Lỗi parse JSON')) {
            message = `${defaultMessage}: Phản hồi từ server không đúng định dạng`;
        } else if (err.message.includes('Failed to fetch')) {
            message = `${defaultMessage}: Không thể kết nối đến server. Vui lòng kiểm tra kết nối mạng.`;
        } else {
            message = `${defaultMessage}: ${err.message}`;
        }
        showNotification(message, 'error');
    }

    // --- CÁC HÀM ĐIỀU KHIỂN SCANNER ---

    btnQuetMa.addEventListener('click', function() {
        if (isOrderCompleted() || isOrderComplete) {
            showNotification('Đơn đã hoàn thành!', 'success');
            return;
        }
        scannerModal.classList.remove('hidden');
        requestAnimationFrame(() => scannerModal.classList.add('show'));
        
        if (!isScannerInitialized) {
            initializeAndStartScanner();
        }
    });

    closeModalButton.addEventListener('click', function() {
        stopScanner();
        scannerModal.classList.remove('show');
        setTimeout(() => scannerModal.classList.add('hidden'), 300);
    });

    scanButton.addEventListener('click', triggerScan);
    switchCameraButton.addEventListener('click', switchCamera);

    function initializeAndStartScanner() {
        if (typeof Html5Qrcode === 'undefined') {
            showNotification('Lỗi: Thư viện QR code chưa được tải!', 'error');
            return;
        }
        
        const config = {
            verbose: false,
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            }
        };
        
        html5QrCode = new Html5Qrcode("scanner-container", config);
        isScannerInitialized = true;
        
        Html5Qrcode.getCameras()
            .then(devices => {
                camerasAvailable = devices;
                if (devices.length === 0) {
                    qrGuide.innerHTML = '<p>Không tìm thấy camera</p>';
                    showNotification('Không tìm thấy camera trên thiết bị!', 'error');
                    return;
                }
                
                if (!currentCameraId) {
                    const rearCamera = devices.find(device => 
                        device.label.toLowerCase().includes('back') || 
                        device.label.toLowerCase().includes('rear') ||
                        device.label.toLowerCase().includes('environment')
                    );
                    currentCameraId = rearCamera ? rearCamera.id : devices[0].id;
                }
                
                startContinuousScanner();
            })
            .catch(err => {
                qrGuide.innerHTML = `<p>Lỗi lấy danh sách camera: ${err}</p>`;
                showNotification('Không thể lấy danh sách camera: ' + err, 'error');
                console.error('Camera initialization error:', err);
            });
    }

    function onContinuousScan(decodedText, decodedResult) {
        if (scanRequestHandler) {
            scanRequestHandler(decodedText);
            scanRequestHandler = null;
        }
    }

    function startContinuousScanner() {
        if (isOrderComplete || !html5QrCode) return;

        const config = {
            fps: 20,
            qrbox: { width: 250, height: 500 },
            videoConstraints: {
                facingMode: "environment",
                aspectRatio: 1.777,
                width: { ideal: 1920 },
                height: { ideal: 1080 },
                focusMode: "continuous",
                advanced: [
                    { zoom: 1.5 },
                    { torch: true }
                ]
            }
        };

        html5QrCode.start(
            currentCameraId,
            config,
            onContinuousScan,
            (errorMessage) => {}
        ).then(() => {
            qrGuide.innerHTML = '<p>Camera đã sẵn sàng. Đặt mã QR vào khung và nhấn "Quét".</p>';
            scanButton.disabled = false;
        }).catch(err => {
            qrGuide.innerHTML = `<p>Lỗi khởi động camera: ${err}</p>`;
            showNotification(`Không thể khởi động camera: ${err}`, 'error');
        });
    }

    function triggerScan() {
        if (isScanningActive || isOrderComplete) {
            if (isOrderComplete) {
                showNotification('Đơn đã hoàn thành!', 'success');
            }
            return;
        }

        isScanningActive = true;
        scanButton.disabled = true;
        qrGuide.innerHTML = '<p>Đang tìm mã QR...</p>';
        scanButton.classList.add('bg-gray-500');

        const scanPromise = new Promise((resolve, reject) => {
            scanRequestHandler = resolve;
            setTimeout(() => {
                reject(new Error("Timeout"));
            }, 500);
        });

        scanPromise.then(decodedText => {
            qrGuide.innerHTML = '<p>Đã tìm thấy mã! Đang xử lý...</p>';
            onScanSuccess(decodedText);
            isScanningActive = false;
            scanButton.disabled = false;
            scanButton.classList.remove('bg-gray-500');
            qrGuide.innerHTML = '<p>Xử lý xong! Nhấn "Quét" để tiếp tục.</p>';
        }).catch(error => {
            if (error.message === "Timeout") {
                showNotification('Không tìm thấy mã QR. Vui lòng thử lại!', 'error');
            }
            scanRequestHandler = null;
            isScanningActive = false;
            scanButton.disabled = false;
            scanButton.classList.remove('bg-gray-500');
        });
    }

    function onScanSuccess(decodedText) {
        decodedText = decodedText.trim();

        if (isOrderComplete) {
            showNotification('Đơn đã hoàn thành!', 'success');
            return;
        }

        if (decodedText.length > 0) {
            const matchingIndices = [];
            chiTietList.forEach((maQR, index) => {
                if (maQR === decodedText) matchingIndices.push(index);
            });

            if (matchingIndices.length > 0) {
                let foundUnscanned = false;
                for (let index of matchingIndices) {
                    const maCTXHTP = maCTXHTPList[index];
                    const row = document.querySelector(`tr[data-ma-ctxhtp="${maCTXHTP}"]`);
                    if (row && row.querySelector('.text-chua-xuat')) {
                        updateStatus(maCTXHTP);
                        foundUnscanned = true;
                        return;
                    }
                }
                if (!foundUnscanned) {
                    showNotification('Chi tiết này đã được quét rồi!', 'error');
                }
            } else {
                showNotification('Mã QR không khớp với đơn hàng!', 'error');
            }
        } else {
            showNotification('Mã QR không hợp lệ!', 'error');
        }
    }
    
    function stopScanner() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().catch(err => {});
        }
        isScannerInitialized = false;
        scanRequestHandler = null;
        isScanningActive = false;
        scanButton.disabled = false;
    }

    function switchCamera() {
        if (camerasAvailable.length <= 1) {
            showNotification('Chỉ có một camera!', 'error');
            return;
        }
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                const currentIndex = camerasAvailable.findIndex(d => d.id === currentCameraId);
                currentCameraId = camerasAvailable[(currentIndex + 1) % camerasAvailable.length].id;
                startContinuousScanner();
            });
        }
    }
});
</script>
</body>
</html>