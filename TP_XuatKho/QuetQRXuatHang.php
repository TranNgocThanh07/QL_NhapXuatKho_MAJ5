<?php
// TP_XuatHang/QUetQRXuatHang.php
include '../db_config.php';

require_once '../vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$maXuatHang = isset($_GET['maXuatHang']) ? $_GET['maXuatHang'] : null;

if (!$maXuatHang) {
    echo "<p class='text-red-600 text-center'>Không tìm thấy mã phiếu xuất!</p>";
    exit;
}

try {
    // Xử lý AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'updateStatus') {
            $maCTXHTP = $_POST['maCTXHTP'] ?? '';
            if (empty($maCTXHTP)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Thiếu mã chi tiết xuất hàng']);
                exit;
            }

            // Cập nhật trạng thái
            $sqlUpdate = "UPDATE TP_ChiTietXuatHang SET TrangThai = 1 WHERE MaCTXHTP = :maCTXHTP AND TrangThai = 0";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([':maCTXHTP' => $maCTXHTP]);

            // Lấy thông tin tổng và đã xuất
            $sqlProgress = "SELECT 
                            SUM(ct.SoLuong) as TongSoLuongXuat, 
                            SUM(CASE WHEN ct.TrangThai = 1 THEN ct.SoLuong ELSE 0 END) as SoLuongDaXuat,
                            SUM(CASE WHEN ct.TrangThai = 0 THEN 1 ELSE 0 END) as remaining 
                            FROM TP_ChiTietXuatHang ct 
                            WHERE ct.MaXuatHang = :maXuatHang";
            $stmtProgress = $pdo->prepare($sqlProgress);
            $stmtProgress->execute([':maXuatHang' => $maXuatHang]);
            $progress = $stmtProgress->fetch(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'remaining' => $progress['remaining'],
                'tongSoLuongXuat' => $progress['TongSoLuongXuat'],
                'soLuongDaXuat' => $progress['SoLuongDaXuat'],
                'message' => $progress['remaining'] == 0 ? 'Đã quét thành công toàn bộ đơn xuất hàng!' : 'Cập nhật trạng thái thành công'
            ]);
            exit;
        } elseif ($_POST['action'] === 'updateOrderStatus') {
            $maXuatHang = $_POST['maXuatHang'] ?? '';
            $status = $_POST['status'] ?? '1';

            if (empty($maXuatHang)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Thiếu mã đơn hàng']);
                exit;
            }

            $sql = "UPDATE TP_XuatHang SET TrangThai = :status WHERE MaXuatHang = :maXuatHang";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':status' => $status, ':maXuatHang' => $maXuatHang]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái đơn hàng thành công']);
            exit;
        }
    }

    // Lấy thông tin phiếu xuất (giữ nguyên)
    $sql = "SELECT xh.MaXuatHang, nv.TenNhanVien, xh.NgayXuat, xh.GhiChu,
                   SUM(ct.SoLuong) as TongSoLuongXuat, 
                   SUM(CASE WHEN ct.TrangThai = 1 THEN ct.SoLuong ELSE 0 END) as SoLuongDaXuat,
                   dvt.TenDVT, v.MaVai, v.TenVai, m.TenMau,
                   ct.SoLot, ct.TenThanhPhan, ct.MaDonHang, ct.MaVatTu, ct.Kho
            FROM TP_XuatHang xh
            LEFT JOIN TP_ChiTietXuatHang ct ON xh.MaXuatHang = ct.MaXuatHang
            LEFT JOIN NhanVien nv ON xh.MaNhanVien = nv.MaNhanVien
            LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
            LEFT JOIN Vai v ON ct.MaVai = v.MaVai
            LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
            WHERE xh.MaXuatHang = :maXuatHang
            GROUP BY xh.MaXuatHang, nv.TenNhanVien, xh.NgayXuat, xh.GhiChu, dvt.TenDVT,
                     v.MaVai, v.TenVai, m.TenMau, ct.SoLot, ct.TenThanhPhan, ct.MaDonHang, ct.MaVatTu, ct.Kho";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':maXuatHang', $maXuatHang, PDO::PARAM_STR);
    $stmt->execute();
    $phieuXuat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$phieuXuat) {
        echo "<p class='text-red-600 text-center'>Không tìm thấy thông tin phiếu xuất!</p>";
        exit;
    }

    $sqlChiTiet = "SELECT ct.MaCTXHTP, ct.SoLuong, ct.TrangThai, dvt.TenDVT,
                          v.MaVai, v.TenVai, m.TenMau,
                          ct.SoLot, ct.TenThanhPhan, ct.MaDonHang, ct.MaVatTu, ct.Kho, ct.MaQR
                   FROM TP_ChiTietXuatHang ct
                   LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
                   LEFT JOIN Vai v ON ct.MaVai = v.MaVai
                   LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
                   WHERE ct.MaXuatHang = :maXuatHang";
    $stmtChiTiet = $pdo->prepare($sqlChiTiet);
    $stmtChiTiet->bindValue(':maXuatHang', $maXuatHang, PDO::PARAM_STR);
    $stmtChiTiet->execute();
    $chiTietXuat = $stmtChiTiet->fetchAll(PDO::FETCH_ASSOC);

    $tongXuat = $phieuXuat['TongSoLuongXuat'];
    $daXuat = $phieuXuat['SoLuongDaXuat'];
    $conLai = $tongXuat - $daXuat;
    $percentCompleted = $tongXuat > 0 ? round(($daXuat / $tongXuat) * 100, 1) : 0;

} catch (Exception $e) {
    echo "<p class='text-red-600 text-center'>Lỗi: " . $e->getMessage() . "</p>";
    exit;
}

$ngayXuat = date('d/m/Y', strtotime($phieuXuat['NgayXuat']));

function generateQRCodeBase64($data) {
    $qrCode = new QrCode($data);
    $qrCode->setSize(100);
    $qrCode->setMargin(5);
    $writer = new PngWriter();
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
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
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
        }
        
        .header-gradient {
            background: linear-gradient(135deg, var(--primary-color), #D11F2A);
            padding: 1.5rem 2rem;
           
            box-shadow: 0 4px 20px rgba(255, 59, 48, 0.2);
        }
        .card {
            background: white;
            border-radius: 16px;       
            transition: transform 0.2s ease, box-shadow 0.2s ease;
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
            font-size: 3rem;
            opacity: 0.1;
        }
        .data-table-container {
            max-height: 400px;
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
        }
        th, td {
            padding: 1rem;
            text-align: left;
            white-space: nowrap;
        }
        th {
            background: linear-gradient(to right, #FFF1F1, #FFE5E5);
            position: sticky;
            top: 0;
            z-index: 10;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #6B7280;
        }
        tr:hover {
            background: #FFF5F5;
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .scan-button {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: var(--success-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 9999px;
            box-shadow: 0 6px 15px rgba(52, 199, 89, 0.3);
            z-index:10;
            transition: all 0.2s ease;
        }
        .scan-button:hover {
            background: #2DB847;
            transform: scale(1.05);
        }
        #scanner-container {
            width: 100%;
            height: 320px;
            background: #F0F0F0;
            border-radius: 12px;
            position: relative;
        }
        .qr-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            background: rgba(0, 0, 0, 0.6);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="container ">
        <div class="card">
            <header class="header-gradient text-white flex justify-between items-center">
                <a href="../xuatkho.php" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                    <i class="fas fa-arrow-left text-xl"></i>
                   
                </a>
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fas fa-clipboard-list"></i> Chi Tiết Phiếu Xuất Kho
                </h2>
            </header>

            <div class="">
                <!-- Info Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="card p-5">
                        <h3 class="text-sm font-semibold text-gray-500 mb-4 flex items-center gap-2">
                            <i class="fas fa-file-invoice text-red-500"></i> Thông tin phiếu
                        </h3>
                        <p class="flex justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-hashtag text-red-400 mr-2"></i>Mã phiếu:</span>
                            <span class="font-medium badge bg-red-100 text-red-700"><?php echo htmlspecialchars($phieuXuat['MaXuatHang']); ?></span>
                        </p>
                        <p class="flex justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-shopping-cart text-orange-400 mr-2"></i>Mã đơn hàng:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['MaDonHang']); ?></span>
                        </p>
                        <p class="flex justify-between">
                            <span class="text-gray-600"><i class="fas fa-boxes text-purple-400 mr-2"></i>Mã vật tư:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['MaVatTu']); ?></span>
                        </p>
                    </div>
                    <div class="card p-5">
                        <h3 class="text-sm font-semibold text-gray-500 mb-4 flex items-center gap-2">
                            <i class="fas fa-tshirt text-blue-500"></i> Thông tin sản phẩm
                        </h3>
                        <p class="flex justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-layer-group text-blue-400 mr-2"></i>Vải:</span>
                            <span class="font-medium">
                                <?php                             
                                    echo htmlspecialchars($phieuXuat['MaVai'] . ' (' .htmlspecialchars($phieuXuat['TenVai'] . ')'));
                                ?>
                            </span>
                        </p>
                        <p class="flex justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-palette text-pink-400 mr-2"></i>Màu:</span>
                            <span class="font-medium"><?php    $fabricName = explode('-', $phieuXuat['TenMau'])[0]; // Take part before '-'
                             echo $fabricName; ?></span>
                        </p>
                        <p class="flex justify-between">
                            <span class="text-gray-600"><i class="fas fa-ruler-combined text-yellow-500 mr-2"></i>Khổ:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['Kho']); ?></span>
                        </p>
                    </div>
                    <div class="card p-5">
                        <h3 class="text-sm font-semibold text-gray-500 mb-4 flex items-center gap-2">
                            <i class="fas fa-truck-loading text-green-500"></i> Thông tin xuất kho
                        </h3>
                        <p class="flex justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-user-tie text-indigo-400 mr-2"></i>Nhân viên:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['TenNhanVien']); ?></span>
                        </p>
                        <p class="flex justify-between mb-3">
                            <span class="text-gray-600"><i class="fas fa-box-open text-green-500 mr-2"></i>Tổng SL xuất:</span>
                            <span class="font-medium"><?php echo number_format($phieuXuat['TongSoLuongXuat'], 0, ',', '.') . ' ' . htmlspecialchars($phieuXuat['TenDVT']); ?></span>
                        </p>
                        <p class="flex justify-between">
                            <span class="text-gray-600"><i class="fas fa-calendar-check text-blue-400 mr-2"></i>Ngày xuất:</span>
                            <span class="font-medium"><?php echo $ngayXuat; ?></span>
                        </p>
                    </div>
                </div>

                <!-- Progress Section -->
                <div class="">
                    <h3 class="section-title text-lg font-bold text-gray-800 flex items-center ml-5">
                        <i class="fas fa-chart-line text-indigo-500 mr-2"></i> Tiến Độ Xuất Kho
                    </h3>
                    <div class="bg-white rounded-xl border p-5 card-shadow">
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
                                <p class="font-bold text-xl text-blue-800"><?php echo number_format($tongXuat, 0, ',', '.'); ?></p>
                                <p class="text-xs text-blue-500"><?php echo htmlspecialchars($phieuXuat['TenDVT']); ?></p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-check-circle text-green-300"></i>
                                <p class="text-xs font-medium text-green-600">Đã Xuất</p>
                                <p class="font-bold text-xl text-green-800"><?php echo number_format($daXuat, 0, ',', '.'); ?></p>
                                <p class="text-xs text-green-500"><?php echo htmlspecialchars($phieuXuat['TenDVT']); ?></p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-hourglass-half text-red-300"></i>
                                <p class="text-xs font-medium text-red-600">Còn Lại</p>
                                <p class="font-bold text-xl text-red-800"><?php echo number_format($conLai, 0, ',', '.'); ?></p>
                                <p class="text-xs text-red-500"><?php echo htmlspecialchars($phieuXuat['TenDVT']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Details Table -->
                <div class="card ">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2 mt-5">
                        <i class="fas fa-list text-red-500 ml-5"></i> Chi Tiết Xuất Hàng
                        <span class="badge bg-red-100 text-red-700"><?php echo count($chiTietXuat); ?> cây</span>
                    </h3>
                    <div class="data-table-container card-shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="table-header-sticky bg-red-50">
                                <th class="text-left text-xs font-medium text-gray-600 uppercase tracking-wider p-4">
                                    <i class="fas fa-hashtag text-red-500 mr-2"></i> STT
                                </th>
                                <th class="text-left text-xs font-medium text-gray-600 uppercase tracking-wider p-4">
                                    <i class="fas fa-box-open text-orange-500 mr-2"></i> SL Xuất
                                </th>
                                <th class="text-left text-xs font-medium text-gray-600 uppercase tracking-wider p-4">
                                    <i class="fas fa-barcode text-blue-500 mr-2"></i> Số Lot
                                </th>
                                <th class="text-left text-xs font-medium text-gray-600 uppercase tracking-wider p-4">
                                    <i class="fas fa-puzzle-piece text-purple-500 mr-2"></i> Thành Phần
                                </th>
                                <th class="text-left text-xs font-medium text-gray-600 uppercase tracking-wider p-4">
                                    <i class="fas fa-info-circle text-gray-500 mr-2"></i> Trạng Thái
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100" id="chiTietTable">
                            <?php 
                            $stt = 1;
                            foreach ($chiTietXuatWithQR as $ct): 
                                $trangThaiHienThi = ($ct['TrangThai'] == 1) ? 'Đã xuất' : 'Chưa xuất';
                                $trangThaiClass = ($ct['TrangThai'] == 1) ? 'text-da-xuat' : 'text-chua-xuat';
                                $trangThaiIcon = ($ct['TrangThai'] == 1) ? 'fa-check-circle' : 'fa-times-circle';
                                $trangThaiBg = ($ct['TrangThai'] == 1) ? 'bg-green-300' : 'bg-red-300';
                                $trangThaiBorder = ($ct['TrangThai'] == 1) ? 'border-green-200' : 'border-red-200';
                            ?>
                                <tr class="hover:bg-red-50 transition-colors" data-ma-ctxhtp="<?php echo htmlspecialchars($ct['MaCTXHTP']); ?>">
                                    <td class="text-sm text-gray-700 p-4"><?php echo $stt++; ?></td>
                                    <td class="text-sm text-gray-700 p-4">
                                        <span class="font-medium"><?php echo number_format($ct['SoLuong'], 0, ',', '.'); ?></span>
                                        <span class="text-gray-500 text-xs ml-1"><?php echo htmlspecialchars($ct['TenDVT']); ?></span>
                                    </td>
                                    <td class="text-sm text-gray-700 p-4">
                                        <div class="flex items-center"><i class="fas fa-layer-group text-blue-400 mr-1"></i><?php echo htmlspecialchars($ct['SoLot']); ?></div>
                                    </td>
                                    <td class="text-sm text-gray-700 p-4">
                                        <div class="flex items-center"><i class="fas fa-tag text-purple-400 mr-1"></i><?php echo htmlspecialchars($ct['TenThanhPhan']); ?></div>
                                    </td>
                                    <td class="text-sm p-4">
                                        <span class="<?php echo $trangThaiClass; ?> font-medium flex items-center gap-1 px-3 py-1 rounded-full border <?php echo $trangThaiBg; ?> <?php echo $trangThaiBorder; ?>">
                                            <i class="fas <?php echo $trangThaiIcon; ?>"></i><?php echo htmlspecialchars($trangThaiHienThi); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Scanner Modal -->
    <div id="scannerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg w-full max-w-2xl mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Quét Mã QR Chi Tiết Xuất Hàng</h3>
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
            <p class="text-sm text-gray-600 mt-4 text-center">Chỉnh góc quay để mã QR nằm trong khung.</p>
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
    <button id="btnQuetMa" class="scan-button flex items-center gap-2">
        <i class="fas fa-qrcode"></i> Quét Mã QR
    </button>

    <!-- <div id="logArea" class="fixed bottom-0 left-0 w-full bg-gray-900 text-white p-4 max-h-40 overflow-y-auto z-50 hidden">
        <h4 class="text-sm font-bold mb-2">Log Debug:</h4>
        <div id="logContent" class="text-xs"></div>
    </div> -->

    <script>
document.addEventListener('DOMContentLoaded', function() {
    let html5QrCode = null;
    let currentCameraId = null;
    let camerasAvailable = [];
    let isScanning = false;

    const chiTietList = <?php echo json_encode(array_column($chiTietXuatWithQR, 'MaQR')); ?>;
    const maCTXHTPList = <?php echo json_encode(array_column($chiTietXuatWithQR, 'MaCTXHTP')); ?>;
    const maXuatHang = '<?php echo htmlspecialchars($maXuatHang); ?>';
    const tenDVT = '<?php echo htmlspecialchars($phieuXuat['TenDVT']); ?>';

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}`;
        notification.innerHTML = message;
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.5s ease';
            setTimeout(() => document.body.removeChild(notification), 500);
        }, 3000);
    }

    function isOrderCompleted() {
        const conLaiElement = document.querySelector('.stat-card:nth-child(3) .text-xl');
        if (!conLaiElement) return false;
        const conLai = parseInt(conLaiElement.textContent.replace(/\./g, ''), 10);
        return conLai === 0;
    }

    function updateStatus(maCTXHTP) {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=updateStatus&maCTXHTP=${encodeURIComponent(maCTXHTP)}`
        })
        .then(response => {
            if (!response.ok) throw new Error('Lỗi kết nối server');
            return response.json();
        })
        .then(data => {
            console.log('Dữ liệu từ server:', data); // Debug dữ liệu
            if (data.success) {
                // Cập nhật trạng thái trong bảng chi tiết
                const row = document.querySelector(`tr[data-ma-ctxhtp="${maCTXHTP}"] td:nth-child(5) span`);
                if (row) {
                    row.className = 'text-da-xuat font-medium flex items-center gap-1 px-3 py-1 rounded-full border bg-green-50 border-green-200';
                    row.innerHTML = '<i class="fas fa-check-circle"></i> Đã xuất';
                }

                // Lấy dữ liệu từ AJAX
                const newDaXuat = parseInt(data.soLuongDaXuat || 0, 10);
                const newTongXuat = parseInt(data.tongSoLuongXuat || 0, 10);
                const newConLai = newTongXuat - newDaXuat;
                const newPercent = newTongXuat > 0 ? (newDaXuat / newTongXuat * 100).toFixed(1) : 0;

                // Cập nhật ô thống kê
                const tongXuatElement = document.querySelector('.stat-card:nth-child(1) .text-xl');
                const daXuatElement = document.querySelector('.stat-card:nth-child(2) .text-xl');
                const conLaiElement = document.querySelector('.stat-card:nth-child(3) .text-xl');
                if (tongXuatElement) tongXuatElement.textContent = newTongXuat.toLocaleString('vi-VN');
                if (daXuatElement) daXuatElement.textContent = newDaXuat.toLocaleString('vi-VN');
                if (conLaiElement) conLaiElement.textContent = newConLai.toLocaleString('vi-VN');

                // Cập nhật thanh tiến độ
                const progressPercent = document.getElementById('progressPercent');
                const progressText = document.getElementById('progressText');
                const progressValue = document.querySelector('.progress-value');

                if (progressPercent) progressPercent.textContent = `${newPercent}%`;
                if (progressText) {
                    progressText.innerHTML = `
                        <i class="fas fa-box-open text-indigo-400 mr-2"></i>
                        ${newDaXuat.toLocaleString('vi-VN')} / ${newTongXuat.toLocaleString('vi-VN')} ${tenDVT}
                    `;
                }
                if (progressValue) {
                    progressValue.style.width = `${newPercent}%`;
                    progressValue.classList.toggle('pulse', newPercent < 100);
                }

                // Thông báo thành công
                showNotification(
                    `Quét chi tiết thành công! Tổng: ${newTongXuat.toLocaleString('vi-VN')} ${tenDVT}, Đã xuất: ${newDaXuat.toLocaleString('vi-VN')} ${tenDVT}, Còn lại: ${newConLai.toLocaleString('vi-VN')} ${tenDVT}`,
                    'success'
                );

                // Hoàn thành đơn hàng
                if (data.remaining === 0) {
                    setTimeout(() => {
                        showNotification(
                            `Đã quét thành công toàn bộ đơn xuất hàng! Tổng: ${newTongXuat.toLocaleString('vi-VN')} ${tenDVT}, Đã xuất: ${newDaXuat.toLocaleString('vi-VN')} ${tenDVT}, Còn lại: ${newConLai.toLocaleString('vi-VN')} ${tenDVT}`,
                            'success'
                        );
                        updateOrderStatus();
                        stopScanner();
                        document.getElementById('scannerModal').classList.add('hidden');
                    }, 500);
                }
            } else {
                showNotification(data.message || 'Lỗi khi cập nhật trạng thái', 'error');
            }
        })
        .catch(err => {
            console.error('Lỗi fetch:', err);
            showNotification('Lỗi: ' + err.message, 'error');
        });
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
                showNotification('Lỗi khi cập nhật trạng thái đơn hàng: ' + data.message, 'error');
            }
        })
        .catch(err => {
            showNotification('Lỗi khi cập nhật trạng thái đơn hàng: ' + err.message, 'error');
        });
    }

    document.getElementById('btnQuetMa').addEventListener('click', function() {
        if (isOrderCompleted()) {
            showNotification('Đơn đã hoàn thành!', 'error');
            return;
        }
        document.getElementById('scannerModal').classList.remove('hidden');
        initializeScanner();
    });

    document.getElementById('closeModal').addEventListener('click', function() {
        stopScanner();
        document.getElementById('scannerModal').classList.add('hidden');
    });

    document.getElementById('switchCamera').addEventListener('click', switchCamera);
    document.getElementById('scanButton').addEventListener('click', startScanOnce);

    function initializeScanner() {
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("scanner-container", { verbose: false });
        }
        getCamerasAndInitialize();
    }

    function getCamerasAndInitialize() {
        Html5Qrcode.getCameras()
            .then(devices => {
                camerasAvailable = devices;
                if (devices.length === 0) {
                    const qrGuide = document.getElementById('qrGuide');
                    if (qrGuide) qrGuide.innerHTML = '<p>Không tìm thấy camera</p>';
                    showNotification('Không tìm thấy camera trên thiết bị!', 'error');
                    return;
                }
                if (!currentCameraId) {
                    const rearCamera = devices.find(device => device.label.toLowerCase().includes('back') || device.label.toLowerCase().includes('rear'));
                    currentCameraId = rearCamera ? rearCamera.id : devices[0].id;
                }
                startCameraPreview();
            })
            .catch(err => {
                const qrGuide = document.getElementById('qrGuide');
                if (qrGuide) qrGuide.innerHTML = '<p>Lỗi lấy danh sách camera: ' + err + '</p>';
                showNotification('Không thể lấy danh sách camera: ' + err, 'error');
            });
    }

    function startCameraPreview() {
        const config = { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 };
        html5QrCode.start(
            currentCameraId,
            config,
            () => {},
            () => {}
        ).then(() => {
            const qrGuide = document.getElementById('qrGuide');
            if (qrGuide) qrGuide.innerHTML = '<p>Camera đã sẵn sàng. Nhấn "Quét" để bắt đầu quét mã QR</p>';
            document.getElementById('scanButton').disabled = false;
        }).catch(err => {
            const qrGuide = document.getElementById('qrGuide');
            if (qrGuide) qrGuide.innerHTML = '<p>Lỗi khởi động camera: ' + err + '</p>';
            showNotification('Không thể khởi động camera: ' + err, 'error');
        });
    }

    function stopScanner() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop()
                .then(() => {
                    isScanning = false;
                    document.getElementById('scanButton').disabled = false;
                    document.getElementById('scanButton').classList.remove('bg-gray-500');
                    document.getElementById('scanButton').classList.add('bg-green-600', 'hover:bg-green-700');
                })
                .catch(() => {});
        }
    }

    function switchCamera() {
        if (camerasAvailable.length <= 1) {
            showNotification('Thiết bị chỉ có 1 camera', 'error');
            return;
        }
        stopScanner();
        const nextCameraIndex = camerasAvailable.findIndex(device => device.id === currentCameraId);
        currentCameraId = camerasAvailable[(nextCameraIndex + 1) % camerasAvailable.length].id;
        setTimeout(startCameraPreview, 300);
    }

    function startScanOnce() {
        if (isScanning) return;
        if (isOrderCompleted()) {
            showNotification('Đơn đã hoàn thành!', 'error');
            return;
        }
        const qrGuide = document.getElementById('qrGuide');
        if (qrGuide) qrGuide.innerHTML = '<p>Đang quét... Đặt mã QR vào khung</p>';
        document.getElementById('scanButton').disabled = true;
        document.getElementById('scanButton').classList.remove('bg-green-600', 'hover:bg-green-700');
        document.getElementById('scanButton').classList.add('bg-gray-500');
        isScanning = true;
        if (html5QrCode.isScanning) {
            html5QrCode.stop().then(() => setTimeout(startSingleScan, 300));
        } else {
            startSingleScan();
        }
    }

    function startSingleScan() {
        const config = { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 };
        html5QrCode.start(
            currentCameraId,
            config,
            (decodedText) => {
                html5QrCode.stop().then(() => onScanSuccess(decodedText));
            },
            () => {}
        ).catch(err => {
            const qrGuide = document.getElementById('qrGuide');
            if (qrGuide) qrGuide.innerHTML = '<p>Lỗi bắt đầu quét: ' + err + '</p>';
            isScanning = false;
            document.getElementById('scanButton').disabled = false;
            document.getElementById('scanButton').classList.remove('bg-gray-500');
            document.getElementById('scanButton').classList.add('bg-green-600', 'hover:bg-green-700');
            showNotification('Không thể bắt đầu quét: ' + err, 'error');
        });
    }

    function onScanSuccess(decodedText) {
        decodedText = decodedText.trim();
        const qrGuide = document.getElementById('qrGuide');
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
                        if (qrGuide) qrGuide.innerHTML = '<p>Quét thành công chi tiết! Nhấn "Quét" để tiếp tục.</p>';
                        foundUnscanned = true;
                        break;
                    }
                }
                if (!foundUnscanned) {
                    showNotification('Đơn hàng này chi tiết này đã quét đủ, quét chi tiết khác!', 'error');
                    if (qrGuide) qrGuide.innerHTML = '<p>Đã quét đủ chi tiết với mã này! Nhấn "Quét" để quét mã khác.</p>';
                }
            } else {
                showNotification('QR không khớp với đơn hàng!', 'error');
                if (qrGuide) qrGuide.innerHTML = '<p>QR không khớp! Nhấn "Quét" để thử lại.</p>';
            }
        } else {
            showNotification('Vui lòng đưa vào mã QR để quét', 'error');
            if (qrGuide) qrGuide.innerHTML = '<p>Không phải mã QR! Nhấn "Quét" để thử lại.</p>';
        }
        isScanning = false;
        document.getElementById('scanButton').disabled = false;
        document.getElementById('scanButton').classList.remove('bg-gray-500');
        document.getElementById('scanButton').classList.add('bg-green-600', 'hover:bg-green-700');
        startCameraPreview();
    }
});
</script>
</body>
</html>