<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

include '../convert.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
require_once __DIR__ . '/TemPDF.php';
include '../db_config.php';
require_once '../init.php';

// Xử lý yêu cầu AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'deleteChiTiet') {
    try {
        $maCTNHTP = $_POST['maChiTiet'] ?? '';
        $maNhanVienHienTai = isset($_SESSION['maNhanVien']) ? $_SESSION['maNhanVien'] : null;

        if (empty($maCTNHTP)) {
            echo json_encode(['success' => false, 'message' => 'Thiếu MaCTNHTP']);
            exit;
        }

        if (empty($maNhanVienHienTai)) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy thông tin nhân viên']);
            exit;
        }

        // Kiểm tra nhân viên có quyền xóa chi tiết
        $sqlCheckNhanVien = "SELECT MaNhanVien, MaSoMe FROM TP_ChiTietDonSanXuat WHERE MaCTNHTP = ?";
        $stmtCheckNhanVien = $pdo->prepare($sqlCheckNhanVien);
        $stmtCheckNhanVien->execute([$maCTNHTP]);
        $chiTiet = $stmtCheckNhanVien->fetch(PDO::FETCH_ASSOC);

        if (!$chiTiet) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy chi tiết nhập kho']);
            exit;
        }

        if ($chiTiet['MaNhanVien'] !== $maNhanVienHienTai) {
            echo json_encode(['success' => false, 'message' => 'Chi tiết này do nhân viên khác nhập, bạn không thể xóa']);
            exit;
        }

        // Xóa chi tiết nhập kho
        $sqlDelete = "DELETE FROM TP_ChiTietDonSanXuat WHERE MaCTNHTP = ?";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute([$maCTNHTP]);

        if ($stmtDelete->rowCount() > 0) {
            // Kiểm tra và cập nhật trạng thái đơn sản xuất
            $maSoMe = $chiTiet['MaSoMe'];
            $sqlCheckDon = "SELECT TrangThai, LoaiDon FROM TP_DonSanXuat WHERE MaSoMe = ?";
            $stmtCheckDon = $pdo->prepare($sqlCheckDon);
            $stmtCheckDon->execute([$maSoMe]);
            $don = $stmtCheckDon->fetch(PDO::FETCH_ASSOC);

            if ($don && $don['TrangThai'] == '3' && $don['LoaiDon'] == '0') {
                // Cập nhật trạng thái về 2 nếu TrangThai = 3 và LoaiDon = 0
                $sqlUpdateTrangThai = "UPDATE TP_DonSanXuat SET TrangThai = '2' WHERE MaSoMe = ?";
                $stmtUpdateTrangThai = $pdo->prepare($sqlUpdateTrangThai);
                $stmtUpdateTrangThai->execute([$maSoMe]);
                error_log("[" . date('Y-m-d H:i:s') . "] Cập nhật trạng thái đơn sản xuất MaSoMe=$maSoMe về TrangThai=2");
            }

            echo json_encode(['success' => true, 'message' => 'Xóa chi tiết thành công']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy chi tiết để xóa']);
        }
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Lỗi xóa chi tiết: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
    }
    exit;
} elseif ($_POST['action'] === 'updateGhiChu') {
        try {
            $maCTNHTP = $_POST['maChiTiet'] ?? '';
            $ghiChu = $_POST['ghiChu'] ?? '';
            $maNhanVienHienTai = isset($_SESSION['maNhanVien']) ? $_SESSION['maNhanVien'] : null;

            if (empty($maCTNHTP)) {
                echo json_encode(['success' => false, 'message' => 'Thiếu MaCTNHTP']);
                exit;
            }

            if (empty($maNhanVienHienTai)) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy thông tin nhân viên']);
                exit;
            }

            // Kiểm tra xem chi tiết này có thuộc về nhân viên hiện tại hay không
            $sqlCheckNhanVien = "SELECT MaNhanVien FROM TP_ChiTietDonSanXuat WHERE MaCTNHTP = ?";
            $stmtCheckNhanVien = $pdo->prepare($sqlCheckNhanVien);
            $stmtCheckNhanVien->execute([$maCTNHTP]);
            $chiTiet = $stmtCheckNhanVien->fetch(PDO::FETCH_ASSOC);

            if (!$chiTiet) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy chi tiết nhập kho']);
                exit;
            }

            if ($chiTiet['MaNhanVien'] !== $maNhanVienHienTai) {
                echo json_encode(['success' => false, 'message' => 'Chi tiết này do nhân viên khác nhập, bạn không thể sửa ghi chú']);
                exit;
            }

            // Cập nhật ghi chú
            $sqlUpdate = "UPDATE TP_ChiTietDonSanXuat SET GhiChu = ? WHERE MaCTNHTP = ?";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([$ghiChu, $maCTNHTP]);

            if ($stmtUpdate->rowCount() > 0 || $stmtUpdate->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cập nhật ghi chú thành công']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể cập nhật ghi chú']);
            }
        } catch (Exception $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] Lỗi cập nhật ghi chú: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Truy vấn thông tin đơn sản xuất
$maSoMe = $_GET['maSoMe'] ?? '';
$sql = "SELECT 
            dsx.MaSoMe, dsx.MaDonHang,dsx.MaVatTu, dsx.MaVai, dsx.TenVai, dsx.Kho, 
            dsx.SoLuongDatHang, dsx.NgayNhan, dsx.NgayGiao, dsx.SoKgQuyDoi, 
            dsx.Loss, dsx.TongSoLuongGiao, dsx.DinhMuc, dsx.DoBenMau, 
            dsx.DoLechMau, dsx.DinhLuong, dsx.GhiChu, dsx.TrangThai, 
            dsx.YeuCauKhac, dsx.LoaiDon, kh.TenKhachHang, 
            nlh.TenNguoiLienHe, nv.TenNhanVien, mau.TenMau, dvt.TenDVT, 
            ho.TenSuDungHo, tc.TenTieuChuan
        FROM TP_DonSanXuat dsx
        LEFT JOIN TP_KhachHang kh ON dsx.MaKhachHang = kh.MaKhachHang
        LEFT JOIN TP_NguoiLienHe nlh ON dsx.MaNguoiLienHe = nlh.MaNguoiLienHe
        LEFT JOIN NhanVien nv ON dsx.MaNhanVien = nv.MaNhanVien
        LEFT JOIN TP_Mau mau ON dsx.MaMau = mau.MaMau
        LEFT JOIN TP_DonViTinh dvt ON dsx.MaDVT = dvt.MaDVT
        LEFT JOIN TP_Ho ho ON dsx.MaSuDungHo = ho.MaSuDungHo
        LEFT JOIN TP_TieuChuan tc ON dsx.MaTieuChuan = tc.MaTieuChuan
        WHERE dsx.MaSoMe = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$maSoMe]);
$don = $stmt->fetch(PDO::FETCH_ASSOC);

// Kiểm tra phân quyền và mã nhân viên
$maPhanQuyen = isset($_SESSION['maPhanQuyen']) ? $_SESSION['maPhanQuyen'] : null;
$maNhanVien = isset($_SESSION['maNhanVien']) ? $_SESSION['maNhanVien'] : null;
$canViewChiTiet = false;
$chiTietList = [];

error_log("[" . date('Y-m-d H:i:s') . "] maPhanQuyen: $maPhanQuyen, maNhanVien: $maNhanVien");

// Truy vấn chi tiết nhập kho và nhóm theo Lot
if ($maPhanQuyen && $maNhanVien) {
    if ($maPhanQuyen == 6) {
        $canViewChiTiet = true;
        $sqlChiTiet = "SELECT ct.*, m.TenMau, dvt.TenDVT 
                        FROM TP_ChiTietDonSanXuat ct
                        LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
                        LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
                        WHERE ct.MaSoMe = ?
                        ORDER BY ct.SoLot ASC, ct.SoKgCan ASC";
        $stmtChiTiet = $pdo->prepare($sqlChiTiet);
        if (!$stmtChiTiet) {
            error_log("[" . date('Y-m-d H:i:s') . "] Lỗi chuẩn bị truy vấn quyền 6: " . print_r($pdo->errorInfo(), true));
            die("Lỗi chuẩn bị truy vấn SQL.");
        }
        $stmtChiTiet->execute([$maSoMe]);
        $chiTietList = $stmtChiTiet->fetchAll(PDO::FETCH_ASSOC);
        error_log("[" . date('Y-m-d H:i:s') . "] Quyền 6 - Số chi tiết nhập kho tìm thấy: " . count($chiTietList));
    } elseif ($maPhanQuyen == 4) {
        $sqlCheckChiTiet = "SELECT COUNT(*) as count 
                            FROM TP_ChiTietDonSanXuat 
                            WHERE MaSoMe = ? AND MaNhanVien = ?";
        $stmtCheckChiTiet = $pdo->prepare($sqlCheckChiTiet);
        if (!$stmtCheckChiTiet) {
            error_log("[" . date('Y-m-d H:i:s') . "] Lỗi chuẩn bị truy vấn quyền 4: " . print_r($pdo->errorInfo(), true));
            die("Lỗi chuẩn bị truy vấn SQL.");
        }
        $stmtCheckChiTiet->execute([$maSoMe, $maNhanVien]);
        $hasChiTiet = $stmtCheckChiTiet->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        error_log("[" . date('Y-m-d H:i:s') . "] Quyền 4 - Có chi tiết nhập bởi nhân viên: " . ($hasChiTiet ? 'true' : 'false'));

        if ($hasChiTiet) {
            $canViewChiTiet = true;
            $sqlChiTiet = "SELECT ct.*, m.TenMau, dvt.TenDVT 
                           FROM TP_ChiTietDonSanXuat ct
                           LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
                           LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
                           WHERE ct.MaSoMe = ?
                           ORDER BY ct.SoLot ASC, ct.SoKgCan ASC";
            $stmtChiTiet = $pdo->prepare($sqlChiTiet);
            if (!$stmtChiTiet) {
                error_log("[" . date('Y-m-d H:i:s') . "] Lỗi chuẩn bị truy vấn quyền 4: " . print_r($pdo->errorInfo(), true));
                die("Lỗi chuẩn bị truy vấn SQL.");
            }
            $stmtChiTiet->execute([$maSoMe]);
            $chiTietList = $stmtChiTiet->fetchAll(PDO::FETCH_ASSOC);
            error_log("[" . date('Y-m-d H:i:s') . "] Quyền 4 - Số chi tiết nhập kho tìm thấy: " . count($chiTietList));
        }
    }
}

// Nhóm chi tiết theo Lot và tính tổng số lượng mỗi Lot
$lotGroups = [];
$tongSoLuong = 0; // Khởi tạo $tongSoLuong
if ($canViewChiTiet && !empty($chiTietList)) {
    // Truy vấn GhiChuLot từ bảng TP_SoLotMe
    $sqlGhiChuLot = "SELECT SoLot, GhiChu FROM TP_SoLotMe WHERE MaSoMe = ?";
    $stmtGhiChuLot = $pdo->prepare($sqlGhiChuLot);
    $stmtGhiChuLot->execute([$maSoMe]);
    $ghiChuLotData = $stmtGhiChuLot->fetchAll(PDO::FETCH_KEY_PAIR); // Lấy SoLot làm key, GhiChu làm value

    foreach ($chiTietList as $chiTiet) {
        $lot = $chiTiet['SoLot'];
        if (!isset($lotGroups[$lot])) {
            $lotGroups[$lot] = [
                'items' => [],
                'totalQuantity' => 0,
                'totalKg' => 0, // Khởi tạo tổng kg cho lot
                'totalCay' => 0, // Khởi tạo tổng cây cho lot
                'ghiChuLot' => isset($ghiChuLotData[$lot]) ? $ghiChuLotData[$lot] : '' // Gán GhiChuLot
            ];
        }
        $lotGroups[$lot]['items'][] = $chiTiet;
        $lotGroups[$lot]['totalQuantity'] += (float)$chiTiet['SoLuong'];
        $lotGroups[$lot]['totalKg'] += (float)$chiTiet['SoKgCan']; // Tính tổng kg cân
        $lotGroups[$lot]['totalCay'] += 1; // Mỗi chi tiết là 1 cây
        $tongSoLuong += (float)$chiTiet['SoLuong']; // Cộng dồn vào $tongSoLuong
    }
}

// Tính tổng đã nhập từ chi tiết
if ($don) {
    $sqlDaNhap = "SELECT SUM(SoLuong) as DaNhap 
                  FROM TP_ChiTietDonSanXuat 
                  WHERE MaSoMe = ? ";
    $stmtDaNhap = $pdo->prepare($sqlDaNhap);
    $stmtDaNhap->execute([$maSoMe]);
    $daNhap = $stmtDaNhap->fetch(PDO::FETCH_ASSOC)['DaNhap'] ?? 0;

    $tongNhap = $don['SoLuongDatHang'] ?? 0;
    $don['DaNhap'] = $daNhap;
    $don['ConLai'] = number_format($tongNhap - $daNhap, 2, '.', '');
}

// Tính số lượng nhập hàng, nhập tồn và số kg thực tế
$soLuongNhapHang = 0;
$soLuongNhapTon = 0;
$soKgThucTe = 0;

// Truy vấn tổng số kg thực tế từ cơ sở dữ liệu
$sqlSoKgThucTe = "SELECT SUM(SoKgCan) as SoKgThucTe 
                  FROM TP_ChiTietDonSanXuat 
                  WHERE MaSoMe = ?";
$stmtSoKgThucTe = $pdo->prepare($sqlSoKgThucTe);
$stmtSoKgThucTe->execute([$maSoMe]);
$soKgThucTe = $stmtSoKgThucTe->fetch(PDO::FETCH_ASSOC)['SoKgThucTe'] ?? 0;

// Tính số lượng nhập hàng và nhập tồn từ chiTietList (nếu có quyền)
foreach ($chiTietList as $chiTiet) {
    if ($chiTiet['TrangThai'] == '0') {
        $soLuongNhapHang += (float)$chiTiet['SoLuong'];
    } elseif ($chiTiet['TrangThai'] == '2') {
        $soLuongNhapTon += (float)$chiTiet['SoLuong'];
    }
}
$tongSoLuong = $soLuongNhapHang + $soLuongNhapTon;

// Xử lý yêu cầu in PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generatePDF') {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    $pdfData = isset($_POST['pdfData']) ? json_decode($_POST['pdfData'], true) : null;
    $maSoMe = $pdfData[0]['MaSoMe'] ?? '';
    $labelType = $_POST['labelType'] ?? 'system';

    if (empty($pdfData) || !is_array($pdfData) || empty($maSoMe)) {
        sendError("Dữ liệu đầu vào không đủ hoặc không hợp lệ (thiếu pdfData hoặc MaSoMe).");
    }

    error_log("[" . date('Y-m-d H:i:s') . "] Bắt đầu generatePDF: maSoMe=$maSoMe, labelType=$labelType, pdfDataItems=" . count($pdfData));

    try {
        $sqlDon = "SELECT ds.*, dvt.TenDVT
                   FROM TP_DonSanXuat ds
                   LEFT JOIN TP_DonViTinh dvt ON ds.MaDVT = dvt.MaDVT
                   WHERE ds.MaSoMe = ?";
        $stmtDon = $pdo->prepare($sqlDon);
        if (!$stmtDon) {
            sendError("Lỗi chuẩn bị truy vấn SQL đơn hàng: " . print_r($pdo->errorInfo(), true));
        }
        $stmtDon->execute([$maSoMe]);
        $don = $stmtDon->fetch(PDO::FETCH_ASSOC);

        if (!$don) {
            sendError("Không tìm thấy đơn hàng với MaSoMe: " . htmlspecialchars($maSoMe));
        }
        $tenDVT = $don['TenDVT'] ?? 'kg';

        error_log("[" . date('Y-m-d H:i:s') . "] Truy vấn đơn hàng thành công: tenDVT=$tenDVT");

        $sqlMau = "SELECT TenMau FROM TP_Mau WHERE MaMau = ?";
        $stmtMau = $pdo->prepare($sqlMau);
        if (!$stmtMau) {
            sendError("Lỗi chuẩn bị truy vấn SQL màu: " . print_r($pdo->errorInfo(), true));
        }
        $stmtMau->execute([$pdfData[0]['MaMau']]);
        $mau = $stmtMau->fetch(PDO::FETCH_ASSOC);
        $tenMau = $mau['TenMau'] ?? 'N/A';

        error_log("[" . date('Y-m-d H:i:s') . "] Truy vấn màu thành công: tenMau=$tenMau");

        $pdf = new TCPDF('P', 'pt', [297.63, 419.53], true, 'UTF-8', false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(false);
        $pdf->setFontSubsetting(true);

        if ($labelType === 'khachle') {
            generateRetailLabel($pdf, $pdfData, $don, $tenMau, $tenDVT, $maSoMe);
        } else {
            generateSystemLabel($pdf, $pdfData, $don, $tenMau, $tenDVT, $maSoMe);
        }

        $timestamp = date('YmdHis');
        $safeMaSoMe = preg_replace('/[^A-Za-z0-9_-]/', '_', $maSoMe);
        $pdfFileName = "Tem_NhapKhoTon_" . ($labelType === 'khachle' ? 'KhachLe_' : 'HeThong_') . "{$safeMaSoMe}_{$timestamp}.pdf";

        $pdfDataOutput = $pdf->Output('', 'S');
        if (strlen($pdfDataOutput) < 100 || strpos($pdfDataOutput, '%PDF') !== 0) {
            error_log("[" . date('Y-m-d H:i:s') . "] Dữ liệu PDF không hợp lệ: length=" . strlen($pdfDataOutput));
            sendError("Dữ liệu PDF đầu ra không hợp lệ");
        }

        $bmpDataArray = convertPdfToBmpAllPagesInMemory($pdfDataOutput);
        if (!$bmpDataArray || empty($bmpDataArray)) {
            error_log("[" . date('Y-m-d H:i:s') . "] Chuyển đổi PDF sang BMP thất bại");
            sendError("Không thể chuyển đổi PDF sang BMP");
        }

        error_log("[" . date('Y-m-d H:i:s') . "] Chuyển đổi BMP thành công: pages=" . count($bmpDataArray));

        $bmpBase64Array = array_map(function($bmpData) {
            $base64 = base64_encode($bmpData);
            error_log("[" . date('Y-m-d H:i:s') . "] BMP base64: size=" . strlen($base64));
            return $base64;
        }, $bmpDataArray);

        $response = [
            'status' => 'success',
            'bmpData' => $bmpBase64Array,
            'labelType' => $labelType,
            'redirect' => 'printer_interface.php'
        ];

        error_log("[" . date('Y-m-d H:i:s') . "] Trả về JSON thành công: bmpCount=" . count($bmpBase64Array));
        echo json_encode($response);
        exit;
    } catch (Throwable $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Lỗi nghiêm trọng: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        sendError("Lỗi nghiêm trọng khi tạo hoặc xuất BMP", $e);
    }
}

// Hàm hỗ trợ hiển thị HTML an toàn
function safeHtml($value) {
    return $value !== null && $value !== '' ? htmlspecialchars($value) : '';
}

// Hàm lấy class và text trạng thái
function getStatusClass($status, $loaiDon = null) {
    if ($loaiDon === '3') {
        return 'bg-orange-100 text-orange-800 border border-orange-300';
    }
    return in_array($status, ['0', '2']) ? 'bg-yellow-100 text-yellow-800 border border-yellow-300' : ($status === '3' ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-gray-100 text-gray-800 border border-gray-300');
}

function getStatusText($status, $loaiDon = null) {
    if ($loaiDon === '3') {
        return 'Nhập hàng tồn';
    }
    return in_array($status, ['0', '2']) ? 'Chưa nhập đủ hàng' : ($status === '3' ? 'Đã nhập đủ hàng' : $status);
}

function getStatusClassChiTiet($status) {
    switch ($status) {
        case '0':
            return 'bg-blue-100 text-blue-800';
        case '1':
            return 'bg-yellow-100 text-yellow-800';
        case '2':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getStatusTextChiTiet($status) {
    switch ($status) {
        case '0':
            return 'Hàng Mới';
        case '1':
            return 'Hàng Xuất';
        case '2':
            return 'Hàng Tồn';
        default:
            return 'N/A';
    }
}

// Tính phần trăm hoàn thành
$percentCompleted = $don && $don['SoLuongDatHang'] > 0 ? min(100, round(($don['DaNhap'] / $don['SoLuongDatHang']) * 100)) : 0;

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Đơn Sản Xuất & Nhập Kho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f4f8;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        @media (hover: hover) {
            .card-hover:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            }
        }

        .icon-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .fixed-back-btn {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 50;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .progress-animate {
            animation: fillBar 1.5s ease-out;
        }

        @keyframes fillBar {
            from { width: 0%; }
            to { width: attr(data-percentage); }
        }

        .section-header {
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }

        .section-header::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -1px;
            height: 3px;
            width: 60px;
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #b91c1c;
            border-radius: 10px;
        }

        .responsive-table {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .responsive-table table {
            width: 100%;
            min-width: 1000px;
        }

        @media (max-width: 640px) {
            .responsive-table th, .responsive-table td {
                font-size: 0.75rem;
                padding: 0.5rem;
            }

            .icon-circle {
                width: 28px;
                height: 28px;
            }

            .status-badge {
                font-size: 0.65rem;
                padding: 0.2rem 0.5rem;
            }

            .grid-cols-2 {
                grid-template-columns: 1fr;
            }

            .section-header {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 768px) {
            .md\:grid-cols-2 {
                grid-template-columns: 1fr;
            }

            .progress-animate span {
                font-size: 0.65rem;
            }
        }

        th, td {
            white-space: nowrap;
        }

        @media (max-width: 640px) {
            th, td {
                white-space: normal;
                word-break: break-word;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-gray-100 font-sans">
    <div class="relative min-h-screen">
        <header class="sticky top-0 z-20 bg-gradient-to-r from-blue-800 to-indigo-600 p-3 shadow-lg">
            <div class="flex items-center justify-between max-w-7xl mx-auto">
                <div class="flex items-center">
                    <a href="../nhapkho.php" class="text-white text-xl hover:scale-110 transition-transform p-2">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h2 class="text-white font-bold text-lg sm:text-xl flex items-center ml-2 sm:ml-4">
                        <i class="fas fa-file-lines mr-2"></i> Chi Tiết Đơn Sản Xuất
                    </h2>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto p-2 sm:p-4 space-y-5 pb-20">
            <?php if ($don): ?>
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow p-3 sm:p-4 border-l-4 border-blue-500 card-hover">
                    <h3 class="section-header text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-blue-100 text-blue-600">
                            <i class="fas fa-hashtag"></i>
                        </div>
                        <span>Thông Tin Đơn Sản Xuất</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="flex items-start mb-2"><i class="fas fa-tag text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Mã Số Mẻ:</span> <span class="text-red-600"><?php echo safeHtml($don['MaSoMe']); ?></span></p>
                            <p class="flex items-start mb-2"><i class="fas fa-file-alt text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Mã Đơn Hàng:</span> <?php echo safeHtml($don['MaDonHang']); ?></p>
                            <p class="flex items-start mb-2"><i class="fas fa-user text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Mã Vật Tư:</span> <?php echo safeHtml($don['MaVatTu']); ?></p>
                            <p class="flex items-start mb-2"><i class="fas fa-user text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Khách Hàng:</span> <?php echo safeHtml($don['TenKhachHang']); ?></p>
                            <p class="flex items-start mb-2"><i class="fas fa-tshirt text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Vải:</span> <?php echo safeHtml($don['TenVai']); ?> (<?php echo safeHtml($don['MaVai']); ?>)</p>
                            <p class="flex items-start mb-2"><i class="fas fa-ruler text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Khổ:</span> <?php echo safeHtml($don['Kho']); ?></p>
                            <div class="flex items-center mb-2">
                                <i class="fas fa-palette text-blue-500 mr-2 mt-1"></i>
                                <span class="font-semibold text-gray-700">Màu: </span>
                                <span><?php echo safeHtml($don['TenMau']); ?></span>
                            </div>
                            <span class="status-badge <?php echo getStatusClass($don['TrangThai'], $don['LoaiDon']); ?>">
                                <i class="fas <?php echo in_array($don['TrangThai'], ['0', '2']) ? 'fa-clock mr-1' : ($don['TrangThai'] === '3' ? 'fa-check-circle mr-1' : 'fa-info-circle mr-1'); ?>"></i>
                                <?php echo getStatusText($don['TrangThai'], $don['LoaiDon']); ?>
                            </span>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 border border-gray-100">
                                <h4 class="text-base font-bold text-gray-800 flex items-center gap-2 mb-4">
                                    <div class="icon-circle bg-indigo-100 text-indigo-600">
                                        <i class="fas fa-chart-pie"></i>
                                    </div>
                                    <span>Tiến Độ <span class="ml-1 text-indigo-600 font-bold"><?php echo $percentCompleted; ?>%</span></span>
                                </h4>
                                <div class="text-sm">
                                    <div class="flex justify-between mb-2">
                                        <span class="font-semibold text-gray-700 flex items-center">Hoàn thành: </span>
                                        <?php if ($don['TrangThai'] == '3' || $don['LoaiDon'] == '3'): ?>
                                            <span class="font-semibold text-gray-700"><?php echo number_format($tongSoLuong, 2, '.', ''); ?> <?php echo safeHtml($don['TenDVT']); ?></span>
                                        <?php else: ?>
                                            <span class="font-semibold text-gray-700"><?php echo number_format($don['DaNhap'], 2, '.', ''); ?> / <?php echo number_format($don['SoLuongDatHang'], 2, '.', ''); ?> <?php echo safeHtml($don['TenDVT']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-4 mb-4 overflow-hidden">
                                        <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 h-4 rounded-full progress-animate relative" style="width: <?php echo $percentCompleted; ?>%">
                                            <?php if ($percentCompleted > 25): ?>
                                                <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-white"><?php echo $percentCompleted; ?>%</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($don['TenDVT'] == 'KG'): ?>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <?php if ($don['TrangThai'] == '3' || $don['LoaiDon'] == '3'): ?>
                                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-3 rounded-lg shadow-sm border border-blue-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-blue-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-box-open"></i>
                                                    </div>
                                                    <p class="text-xs text-blue-700 font-medium mb-1">Số Lượng Nhập Hàng</p>
                                                    <p class="font-bold text-blue-800 text-lg"><?php echo number_format($soLuongNhapHang, 2, '.', ''); ?></p>
                                                    <p class="text-xs text-blue-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                                <div class="bg-gradient-to-br from-green-50 to-green-100 p-3 rounded-lg shadow-sm border border-green-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-green-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-check"></i>
                                                    </div>
                                                    <p class="text-xs text-green-700 font-medium mb-1">Số Lượng Nhập Tồn</p>
                                                    <p class="font-bold text-green-800 text-lg"><?php echo number_format($soLuongNhapTon, 2, '.', ''); ?></p>
                                                    <p class="text-xs text-green-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                                <div class="bg-gradient-to-br from-amber-50 to-amber-100 p-3 rounded-lg shadow-sm border border-amber-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-amber-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-calculator"></i>
                                                    </div>
                                                    <p class="text-xs text-amber-700 font-medium mb-1">Tổng Số Lượng</p>
                                                    <p class="font-bold text-amber-800 text-lg"><?php echo number_format($tongSoLuong, 2, '.', ''); ?></p>
                                                    <p class="text-xs text-amber-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                            <?php else: ?>
                                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-3 rounded-lg shadow-sm border border-blue-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-blue-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-box-open"></i>
                                                    </div>
                                                    <p class="text-xs text-blue-700 font-medium mb-1">Tổng Nhập</p>
                                                    <p class="font-bold text-blue-800 text-lg"><?php echo number_format($don['SoLuongDatHang'], 2, '.', ''); ?></p>
                                                     <p class="text-xs text-blue-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                                <div class="bg-gradient-to-br from-green-50 to-green-100 p-3 rounded-lg shadow-sm border border-green-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-green-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-check"></i>
                                                    </div>
                                                    <p class="text-xs text-green-700 font-medium mb-1">Đã Nhập</p>
                                                    <p class="font-bold text-green-800 text-lg"><?php echo number_format($don['DaNhap'], 2, '.', ''); ?></p>
                                                     <p class="text-xs text-green-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                                <div class="bg-gradient-to-br from-amber-50 to-amber-100 p-3 rounded-lg shadow-sm border border-amber-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-amber-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-hourglass-half"></i>
                                                    </div>
                                                    <p class="text-xs text-amber-700 font-medium mb-1">Còn Lại</p>
                                                    <p class="font-bold text-amber-800 text-lg"><?php echo number_format($don['ConLai'], 2, '.', ''); ?></p>
                                                     <p class="text-xs text-amber-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                            <?php if ($don['TrangThai'] == '3' || $don['LoaiDon'] == '3'): ?>
                                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-3 rounded-lg shadow-sm border border-blue-200 text-center transform transition-all">
                                                    <div class="icon-circle bg-blue-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-box-open"></i>
                                                    </div>
                                                    <p class="text-xs text-blue-700 font-medium mb-1">Số Lượng Nhập Hàng</p>
                                                    <p class="font-bold text-blue-800 text-lg"><?php echo number_format($soLuongNhapHang, 2, '.', ''); ?></p>
                                                     <p class="text-xs text-blue-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                                <div class="bg-gradient-to-br from-green-50 to-green-100 p-3 rounded-lg shadow-sm border border-green-200 text-center transform transition-all">
                                                    <div class="icon-circle bg-green-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-check"></i>
                                                    </div>
                                                    <p class="text-xs text-green-700 font-medium mb-1">Số Lượng Nhập Tồn</p>
                                                    <p class="font-bold text-green-800 text-lg"><?php echo number_format($soLuongNhapTon, 2, '.', ''); ?></p>
                                                     <p class="text-xs text-green-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                                <div class="bg-gradient-to-br from-amber-50 to-amber-100 p-3 rounded-lg shadow-sm border border-amber-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-amber-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-calculator"></i>
                                                    </div>
                                                    <p class="text-xs text-amber-700 font-medium mb-1">Tổng Số Lượng</p>
                                                    <p class="font-bold text-amber-800 text-lg"><?php echo number_format($tongSoLuong, 2, '.', ''); ?></p>
                                                     <p class="text-xs text-amber-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                                <div class="bg-gradient-to-br from-red-50 to-red-100 p-3 rounded-lg shadow-sm border border-red-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-red-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-weight-hanging"></i>
                                                    </div>
                                                    <p class="text-xs text-red-700 font-medium mb-1">Số Kg Cân Thực Tế Đã Nhập</p>
                                                    <p class="font-bold text-red-800 text-lg"><?php echo number_format($soKgThucTe, 2, '.', ''); ?></p>
                                                     <p class="text-xs text-red-500">KG</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-3 rounded-lg shadow-sm border border-blue-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-blue-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-box-open"></i>
                                                    </div>
                                                    <p class="text-xs text-blue-700 font-medium mb-1">Tổng Nhập</p>
                                                    <p class="font-bold text-blue-800 text-lg"><?php echo number_format($don['SoLuongDatHang'], 2, '.', ''); ?></p>
                                                     <p class="text-xs text-blue-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                                <div class="bg-gradient-to-br from-green-50 to-green-100 p-3 rounded-lg shadow-sm border border-green-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-green-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-check"></i>
                                                    </div>
                                                    <p class="text-xs text-green-700 font-medium mb-1">Đã Nhập</p>
                                                    <p class="font-bold text-green-800 text-lg"><?php echo number_format($don['DaNhap'], 2, '.', ''); ?></p>
                                                     <p class="text-xs text-green-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>
                                                <div class="bg-gradient-to-br from-red-50 to-red-100 p-3 rounded-lg shadow-sm border border-red-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-red-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-weight-hanging"></i>
                                                    </div>
                                                    <p class="text-xs text-red-700 font-medium mb-1">Số Kg Cân Thực Đã Nhập</p>
                                                    <p class="font-bold text-red-800 text-lg"><?php echo number_format($soKgThucTe, 2, '.', ''); ?></p>
                                                     <p class="text-xs text-red-500">KG</p>
                                                </div>
                                                <div class="bg-gradient-to-br from-amber-50 to-amber-100 p-3 rounded-lg shadow-sm border border-amber-200 text-center transform transition-all hover:scale-105">
                                                    <div class="icon-circle bg-amber-500 text-white mx-auto mb-2">
                                                        <i class="fas fa-hourglass-half"></i>
                                                    </div>
                                                    <p class="text-xs text-amber-700 font-medium mb-1">Còn Lại</p>
                                                    <p class="font-bold text-amber-800 text-lg"><?php echo number_format($don['ConLai'], 2, '.', ''); ?></p>
                                                     <p class="text-xs text-amber-500"><?php echo htmlspecialchars($don['TenDVT']); ?></p>
                                                </div>                                               
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 card-hover border border-gray-100">
                    <h3 class="section-header text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-indigo-100 text-indigo-600">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <span>Thông Tin Chi Tiết</span>
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-2 gap-4 text-xs">
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-box"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Số Lượng Đặt</p>
                                <p class="font-semibold"><?php echo number_format($don['SoLuongDatHang'], 2, '.', '') . ' ' . safeHtml($don['TenDVT']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-weight-hanging"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Kg Quy Đổi</p>
                                <p class="font-semibold"><?php echo number_format((float)$don['SoKgQuyDoi'], 2, '.', '') . ' kg'; ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Ngày Nhận</p>
                                <p class="font-semibold">
                                    <?php
                                        $ngayNhan = safeHtml($don['NgayNhan']);
                                        if ($ngayNhan && strtotime($ngayNhan)) {
                                            echo date('d/m/Y', strtotime($ngayNhan));
                                        } else {
                                            echo $ngayNhan;
                                        }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Ngày Giao</p>
                                <p class="font-semibold">
                                    <?php
                                        $ngayGiao = safeHtml($don['NgayGiao']);
                                        if ($ngayGiao && strtotime($ngayGiao)) {
                                            echo date('d/m/Y', strtotime($ngayGiao));
                                        } else {
                                            echo $ngayGiao;
                                        }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Loss</p>
                                <p class="font-semibold"><?php echo number_format((float)$don['Loss'], 2, '.', '') . ' Kg'; ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Loại Đơn</p>
                                <p class="font-semibold">
                                    <?php
                                        switch ($don['LoaiDon']) {
                                            case '0':
                                                echo 'QTSX';
                                                break;
                                            case '1':
                                                echo 'HT';
                                                break;
                                            case '2':
                                                echo 'QTSX+HT';
                                                break;
                                            default:
                                                echo safeHtml($don['LoaiDon']);
                                        }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Người Liên Hệ</p>
                                <p class="font-semibold"><?php echo safeHtml($don['TenNguoiLienHe']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Nhân Viên</p>
                                <p class="font-semibold"><?php echo safeHtml($don['TenNhanVien']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 bg-blue-50 p-3 rounded-lg border border-blue-100">
                        <p class="flex items-start">
                            <i class="fas fa-comment-t text-blue-500 mr-2 mt-1"></i>
                            <span>
                                <span class="font-semibold text-blue-700">Yêu cầu Khác:</span><br>
                                    <span class="text-gray-700"><?php echo safeHtml($don['YeuCauKhac']) ?: 'Không có yêu cầu khác.'; ?></span>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 card-hover border border-gray-100">
                    <h3 class="section-header text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-indigo-100 text-indigo-600">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <span>Thông Tin Kỹ Thuật</span>
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-2 gap-4 text-xs">
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-ruler"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Định Mức</p>
                                <p class="font-semibold"><?php echo safeHtml($don['DinhMuc']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-weight"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Định Lượng</p>
                                <p class="font-semibold"><?php echo safeHtml($don['DinhLuong']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-tint"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Độ Bền Màu</p>
                                <p class="font-semibold"><?php echo safeHtml($don['DoBenMau']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-tint-slash"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Độ Lệch Màu</p>
                                <p class="font-semibold"><?php echo safeHtml($don['DoLechMau']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Sử Dụng Hồ</p>
                                <p class="font-semibold"><?php echo safeHtml($don['TenSuDungHo']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Tiêu Chuẩn</p>
                                <p class="font-semibold"><?php echo safeHtml($don['TenTieuChuan']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl shadow-sm p-3 sm:p-4 card-hover border border-amber-200">
                    <h3 class="section-header text-base sm:text-lg font-bold text-amber-800 flex items-center gap-2 mb-3">
                        <div class="icon-circle bg-amber-200 text-amber-700">
                            <i class="fas fa-sticky-note"></i>
                        </div>
                        <span>Ghi Chú</span>
                    </h3>
                    <div class="bg-white p-3 rounded-lg shadow-sm">
                        <p class="text-sm text-gray-700 flex items-start">
                            <i class="fas fa-quote-left text-amber-500 mr-2 mt-1"></i>
                            <span><?php echo safeHtml($don['GhiChu']) ?: 'Không có ghi chú.'; ?></span>
                        </p>
                    </div>
                </div>

                <!-- chi tiết nhập kho -->
               <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 card-hover border border-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="section-header text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2">
                            <div class="icon-circle bg-indigo-100 text-indigo-600">
                                <i class="fas fa-list"></i>
                            </div>
                            <span>Chi Tiết Nhập Kho</span>
                        </h3>
                        <?php if ($canViewChiTiet && !empty($chiTietList)): ?>
                            <div>
                                <select id="filterChiTiet" onchange="filterChiTiet()" class="border border-gray-300 rounded-lg p-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="all">Tất cả chi tiết</option>
                                    <option value="hasNote">Chi tiết có ghi chú</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($canViewChiTiet && !empty($chiTietList)): ?>                      
                        <div class="responsive-table custom-scrollbar">
                            <table id="chiTietTable" class="w-full border-collapse">
                                <thead class="bg-red-50 text-red-800 sticky top-0 z-10">
                                    <tr>
                                        <th class="text-left sticky left-0 bg-red-50 z-20 p-2 sm:p-3 font-semibold">STT</th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">Số Lượng</th>
                                        <?php if (!empty($don) && isset($don['TenDVT']) && $don['TenDVT'] != 'KG'): ?>
                                            <th class="text-left p-2 sm:p-3 font-semibold">Số Kg Cân</th>
                                        <?php endif; ?>
                                        <th class="text-left p-2 sm:p-3 font-semibold">Thành Phần</th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">Trạng Thái</th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">Ngày Tạo</th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">Khu Vực</th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">Ghi Chú</th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">In Tem</th>
                                         <th class="text-left p-2 sm:p-3 font-semibold">Sửa Ghi Chú</th>
                                        <th class="text-left p-2 sm:p-3 font-semibold">Xóa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $stt = 1;
                                    foreach ($lotGroups as $lot => $group): 
                                    ?>
                                        <tr class="bg-indigo-100 text-indigo-800 font-bold border-b border-gray-200">
                                            <td colspan="<?php echo (!empty($don) && isset($don['TenDVT']) && $don['TenDVT'] != 'KG') ? 10 : 9; ?>" class="p-2 sm:p-3">
                                                 <div class="flex items-center gap-2">
                                                    <i class="fas fa-folder-open"></i>
                                                    <span>Số Lot: <?php echo safeHtml($lot); ?> (Tổng nhập: <?php echo number_format($group['totalQuantity'], 2, '.', '') . ' ' . safeHtml($don['TenDVT']); ?>
                                                    <?php if (!empty($don) && isset($don['TenDVT']) && $don['TenDVT'] != 'KG'): ?>
                                                        , Tổng kg thực tế: <?php echo number_format($group['totalKg'], 2, '.', '') . ' KG'; ?>
                                                    <?php endif; ?>
                                                    , Tổng cây: <?php echo $group['totalCay'] . ' Cây'; ?>
                                                    <?php if (!empty($group['ghiChuLot'])): ?>
                                                        , Ghi chú Lot: <?php echo safeHtml($group['ghiChuLot']); ?>
                                                    <?php endif; ?>
                                                    )</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php foreach ($group['items'] as $chiTiet): ?>
                                            <tr class="border-b border-gray-200 hover:bg-red-100 transition-colors" data-note="<?php echo safeHtml($chiTiet['GhiChu']); ?>">
                                                <td class="sticky left-0 bg-white p-2 sm:p-3"><?php echo safeHtml($chiTiet['STT']); ?></td>
                                                <td class="font-bold p-2 sm:p-3 whitespace-normal <?php echo intval($chiTiet['SoLuong']) > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                    <?php echo safeHtml($chiTiet['SoLuong']) . ' ' . safeHtml($chiTiet['TenDVT']); ?>
                                                </td>
                                                <?php if (!empty($don) && isset($don['TenDVT']) && $don['TenDVT'] != 'KG'): ?>
                                                    <td class="p-2 sm:p-3"><?php echo safeHtml($chiTiet['SoKgCan']); ?></td>
                                                <?php endif; ?>
                                                <td class="p-2 sm:p-3"><?php echo safeHtml($chiTiet['TenThanhPhan']); ?></td>
                                                <td class="p-2 sm:p-3">
                                                    <span class="px-2 py-1 rounded-full text-xs <?php echo getStatusClassChiTiet($chiTiet['TrangThai']); ?>">
                                                        <?php echo getStatusTextChiTiet($chiTiet['TrangThai']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-gray-600 p-2 sm:p-3">
                                                    <?php
                                                        $ngayTao = safeHtml($chiTiet['NgayTao']);
                                                        if ($ngayTao && strtotime($ngayTao)) {
                                                            echo date('d/m/Y', strtotime($ngayTao));
                                                        } else {
                                                            echo $ngayTao;
                                                        }
                                                    ?>
                                                </td>
                                                <td class="text-gray-600 p-2 sm:p-3"><?php echo safeHtml($chiTiet['MaKhuVuc']); ?></td>
                                                <td class="text-gray-600 p-2 sm:p-3"><?php echo safeHtml($chiTiet['GhiChu']); ?></td>
                                                <td class="p-2 sm:p-3">
                                                    <button onclick='generatePDF(<?php echo json_encode([$chiTiet], JSON_HEX_QUOT | JSON_HEX_APOS); ?>)' class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 text-sm">
                                                        <i class="ri-printer-line"></i> In Tem
                                                    </button>
                                                </td>
                                                <td class="p-2 sm:p-3">
                                                    <?php if ($chiTiet['TrangThai'] != '1'): ?>
                                                        <button onclick="editGhiChu('<?php echo safeHtml($chiTiet['MaCTNHTP'] ?: ''); ?>', '<?php echo addslashes(safeHtml($chiTiet['GhiChu'])); ?>')" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm">
                                                            <i class="fas fa-edit"></i> Sửa
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="p-2 sm:p-3">
                                                    <?php if ($chiTiet['TrangThai'] != '1'): ?>
                                                        <button onclick="deleteChiTiet('<?php echo safeHtml($chiTiet['MaCTNHTP'] ?: ''); ?>')" class="bg-gray-600 text-white px-3 py-1 rounded hover:bg-gray-700 text-sm">
                                                            <i class="fas fa-trash-alt"></i> Xóa
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($maPhanQuyen == 4 && !$canViewChiTiet): ?>
                        <div class="text-center bg-red-50 rounded-lg p-6">
                            <i class="ri-error-warning-line text-4xl text-red-500 mb-3"></i>
                            <p class="text-red-600 text-base font-semibold">Bạn chưa nhập hàng cho đơn này nên không thể xem chi tiết nhập.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center bg-red-50 rounded-lg p-6">
                            <i class="ri-error-warning-line text-4xl text-red-500 mb-3"></i>
                            <p class="text-red-600 text-base font-semibold">Không tìm thấy chi tiết nhập kho cho đơn này.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-[calc(100vh-64px)]">
                    <div class="text-center p-6 bg-white rounded-lg shadow-lg border-t-4 border-red-500">
                        <div class="w-20 h-20 mx-auto mb-4 flex items-center justify-center bg-red-100 rounded-full">
                            <i class="fas fa-exclamation-triangle text-4xl text-red-500"></i>
                        </div>
                        <p class="text-lg font-semibold text-gray-800 mb-1">Không tìm thấy đơn sản xuất</p>
                        <p class="text-sm text-gray-600 mb-4">Mã số mẻ không tồn tại hoặc đã bị xóa.</p>
                        <a href="../nhapkho.php" class="inline-block bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Quay lại Trang Chính
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // console.log('[XemChiTietDonSanXuat] Trang đã tải, maSoMe:', '<?php echo safeHtml($maSoMe); ?>');
        // console.log('[XemChiTietDonSanXuat] canViewChiTiet:', <?php echo json_encode($canViewChiTiet); ?>);
        // console.log('[XemChiTietDonSanXuat] Số chi tiết nhập kho:', <?php echo count($chiTietList); ?>);
        // console.log('[XemChiTietDonSanXuat] maPhanQuyen:', '<?php echo safeHtml($maPhanQuyen); ?>');
        // console.log('[XemChiTietDonSanXuat] maNhanVien:', '<?php echo safeHtml($maNhanVien); ?>');

        function filterChiTiet() {
            const filterValue = document.getElementById('filterChiTiet').value;
            const table = document.getElementById('chiTietTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let row of rows) {
                const note = row.getAttribute('data-note');
                if (filterValue === 'hasNote') {
                    row.style.display = (note && note.trim() !== '') ? '' : 'none';
                } else {
                    row.style.display = '';
                }
            }
        }

        function logToScreen(message, type = 'info') {
            console[type === 'error' ? 'error' : 'log'](message);
        }

        function checkCordovaEnvironment() {
            logToScreen('[checkCordova] Kiểm tra môi trường Cordova...');
            return typeof cordova !== 'undefined' && typeof cordova.plugins !== 'undefined' && typeof cordova.file !== 'undefined' && typeof cordova.plugins.fileOpener2 !== 'undefined';
        }

        document.addEventListener('deviceready', onDeviceReady, false);
        function onDeviceReady() {
            logToScreen('[deviceready] Cordova đã sẵn sàng.');
            checkCordovaEnvironment();
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof cordova === 'undefined') {
                logToScreen('[DOMContentLoaded] Không phát hiện Cordova, chạy như trình duyệt.');
                checkCordovaEnvironment();
            }
        });

        //sửa ghi chú của chi tiết
        async function editGhiChu(maChiTiet, ghiChuHienTai) {
        logToScreen(`[editGhiChu] Bắt đầu sửa ghi chú: maChiTiet=${maChiTiet}`);

        const { value: newGhiChu } = await Swal.fire({
            title: 'Sửa ghi chú',
            input: 'textarea',
            inputLabel: 'Nhập ghi chú mới',
            inputValue: ghiChuHienTai,
            showCancelButton: true,
            confirmButtonText: 'Cập nhật',
            cancelButtonText: 'Hủy',
            inputValidator: (value) => {
                if (value.length > 255) {
                    return 'Ghi chú không được vượt quá 255 ký tự!';
                }
            }
        });

        if (newGhiChu === undefined) {
            logToScreen('[editGhiChu] Người dùng hủy sửa ghi chú.');
            return;
        }

        Swal.fire({
            title: 'Đang cập nhật...',
            text: 'Vui lòng chờ trong giây lát.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const formData = new FormData();
            formData.append('action', 'updateGhiChu');
            formData.append('maChiTiet', maChiTiet);
            formData.append('ghiChu', newGhiChu);

            logToScreen(`[editGhiChu] Gửi yêu cầu POST với maChiTiet=${maChiTiet}`);
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                logToScreen(`[editGhiChu] Lỗi server: ${response.status} - ${errorText}`, 'error');
                throw new Error(`Phản hồi server không thành công: ${response.status}`);
            }

            const result = await response.json();
            logToScreen(`[editGhiChu] Phản hồi JSON: ${JSON.stringify(result).substring(0, 100)}...`);

            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Cập nhật thành công',
                    text: result.message || 'Ghi chú đã được cập nhật.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    logToScreen('[editGhiChu] Làm mới trang.');
                    window.location.reload(true);
                });
            } else {
                throw new Error(result.message || 'Cập nhật ghi chú không thành công');
            }
        } catch (error) {
            logToScreen(`[editGhiChu] Lỗi: ${error.message}`, 'error');
            console.error('Chi tiết lỗi:', error);
            Swal.fire({
                icon: 'error',
                title: 'Lỗi cập nhật',
                text: `Không thể cập nhật ghi chú: ${error.message}`,
                footer: 'Vui lòng kiểm tra console để biết chi tiết.'
            });
        }
    }
        //xóa chi tiết
        async function deleteChiTiet(maChiTiet) {
            logToScreen(`[deleteChiTiet] Bắt đầu xóa chi tiết: maChiTiet=${maChiTiet}`);

            const { isConfirmed } = await Swal.fire({
                title: 'Xác nhận xóa',
                text: 'Bạn có chắc chắn muốn xóa chi tiết nhập kho này? Hành động này không thể hoàn tác.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy'
            });

            if (!isConfirmed) {
                logToScreen('[deleteChiTiet] Người dùng hủy xóa.');
                return;
            }

            Swal.fire({
                title: 'Đang xóa...',
                text: 'Vui lòng chờ trong giây lát.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const formData = new FormData();
                formData.append('action', 'deleteChiTiet');
                formData.append('maChiTiet', maChiTiet);

                logToScreen(`[deleteChiTiet] Gửi yêu cầu POST với maChiTiet=${maChiTiet}`);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    logToScreen(`[deleteChiTiet] Lỗi server: ${response.status} - ${errorText}`, 'error');
                    throw new Error(`Phản hồi server không thành công: ${response.status}`);
                }

                const result = await response.json();
                logToScreen(`[deleteChiTiet] Phản hồi JSON: ${JSON.stringify(result).substring(0, 100)}...`);

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Xóa thành công',
                        text: result.message || 'Chi tiết nhập kho đã bị xóa.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        logToScreen('[deleteChiTiet] Làm mới trang.');
                        window.location.reload(true);
                    });
                } else {
                    throw new Error(result.message || 'Xóa không thành công');
                }
            } catch (error) {
                logToScreen(`[deleteChiTiet] Lỗi: ${error.message}`, 'error');
                console.error('Chi tiết lỗi:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi xóa',
                    text: `Không thể xóa chi tiết: ${error.message}`,                 
                });
            }
        }

        window.generatePDF = async function(data) {
            const startTime = performance.now();
            logToScreen(`[generatePDF] Bắt đầu hàm generatePDF, dữ liệu: ${JSON.stringify(data).substring(0, 100)}...`);

            if (!data || !Array.isArray(data) || data.length === 0) {
                logToScreen('[generatePDF] Dữ liệu đầu vào không hợp lệ hoặc rỗng.', 'error');
                Swal.fire({
                    icon: 'warning',
                    title: 'Dữ liệu không hợp lệ',
                    text: 'Vui lòng kiểm tra dữ liệu nhập kho.'
                });
                return;
            }

            if (typeof cordova !== 'undefined') {
                await new Promise(resolve => {
                    if (document.readyState === 'complete' && typeof cordova.plugins !== 'undefined') {
                        resolve();
                    } else {
                        document.addEventListener('deviceready', resolve, { once: true });
                    }
                });
                logToScreen('[generatePDF] Môi trường Cordova đã sẵn sàng.');
            } else {
                logToScreen('[generatePDF] Chạy trong môi trường trình duyệt.');
            }

            const { value: labelType } = await Swal.fire({
                title: 'Chọn loại tem',
                text: 'Vui lòng chọn loại tem bạn muốn in:',
                icon: 'question',
                input: 'select',
                inputOptions: {
                    'system': 'Tem Hệ Thống',
                    'khachle': 'Tem Khách Lẻ'
                },
                inputPlaceholder: 'Chọn loại tem',
                showCancelButton: true,
                confirmButtonText: 'In Tem',
                cancelButtonText: 'Hủy',
                inputValidator: value => !value && 'Bạn phải chọn một loại tem!'
            });

            if (!labelType) {
                logToScreen('[generatePDF] Người dùng hủy chọn loại tem.');
                return;
            }

            Swal.fire({
                title: 'Đang tạo BMP...',
                text: 'Vui lòng chờ trong giây lát.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData();
            formData.append('action', 'generatePDF');
            formData.append('pdfData', JSON.stringify(data));
            formData.append('labelType', labelType);

            sessionStorage.setItem('previousPage', window.location.href);
            logToScreen(`[generatePDF] Lưu previousPage: ${window.location.href}`);

            try {
                logToScreen('[generatePDF] Gửi yêu cầu POST tới server.');
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    logToScreen(`[generatePDF] Lỗi server: ${response.status} - ${errorText}`, 'error');
                    throw new Error(`Phản hồi server không thành công: ${response.status}`);
                }

                const result = await response.json();
                logToScreen(`[generatePDF] Phản hồi JSON: ${JSON.stringify(result).substring(0, 100)}...`);

                if (result.status !== 'success' || !result.bmpData || !Array.isArray(result.bmpData)) {
                    logToScreen('[generatePDF] Phản hồi không hợp lệ hoặc thiếu bmpData.', 'error');
                    throw new Error(result.message || 'Dữ liệu BMP không hợp lệ từ server.');
                }

                const bmpDataArray = result.bmpData;
                const maSoMe = data[0].MaSoMe || 'Unknown';
                const timestamp = new Date().toISOString().replace(/[-:.]/g, '');
                logToScreen(`[generatePDF] Số lượng BMP: ${bmpDataArray.length}, MaSoMe: ${maSoMe}`);

                if (typeof cordova !== 'undefined' && cordova.file) {
                    logToScreen('[generatePDF] Xử lý trong môi trường Cordova.');
                    const directory = cordova.file.dataDirectory || cordova.file.externalDataDirectory;

                    const filePaths = await Promise.all(bmpDataArray.map(async (bmpBase64, index) => {
                        try {
                            const bmpBlob = await (await fetch(`data:image/bmp;base64,${bmpBase64}`)).blob();
                            const fileName = `Tem_NhapKhoTon_${labelType}_${maSoMe}_${timestamp}_page${index + 1}.bmp`;
                            logToScreen(`[generatePDF] Tạo file BMP: ${fileName}`);

                            return await new Promise((resolve, reject) => {
                                window.resolveLocalFileSystemURL(directory, dirEntry => {
                                    dirEntry.getFile(fileName, { create: true, exclusive: false }, fileEntry => {
                                        fileEntry.createWriter(fileWriter => {
                                            fileWriter.onwriteend = () => {
                                                logToScreen(`[generatePDF] Lưu file thành công: ${fileEntry.fullPath}`);
                                                resolve(fileEntry.fullPath);
                                            };
                                            fileWriter.onerror = error => {
                                                logToScreen(`[generatePDF] Lỗi ghi file ${fileName}: ${error}`, 'error');
                                                reject(error);
                                            };
                                            fileWriter.write(bmpBlob);
                                        }, reject);
                                    }, reject);
                                }, reject);
                            });
                        } catch (error) {
                            logToScreen(`[generatePDF] Lỗi xử lý BMP ${index + 1}: ${error.message}`, 'error');
                            throw error;
                        }
                    }));

                    const redirectUrl = `printer_interface.php?filePath=${encodeURIComponent(filePaths.join(','))}&labelType=${encodeURIComponent(labelType)}`;
                    logToScreen(`[generatePDF] Chuyển hướng tới: ${redirectUrl}`);
                    Swal.close();
                    setTimeout(() => window.location.href = redirectUrl, 100);
                } else {
                    logToScreen('[generatePDF] Xử lý trong môi trường trình duyệt.');
                    const bmpDataUrls = bmpDataArray.map(bmpBase64 => `data:image/bmp;base64,${bmpBase64}`);
                    sessionStorage.setItem('bmpFiles', JSON.stringify(bmpDataUrls));
                    sessionStorage.setItem('labelType', labelType);
                    logToScreen('[generatePDF] Lưu BMP vào sessionStorage.');

                    Swal.close();
                    setTimeout(() => {
                        logToScreen('[generatePDF] Chuyển hướng tới printer_interface.php');
                        window.location.href = 'printer_interface.php';
                    }, 100);
                }

                const duration = (performance.now() - startTime).toFixed(2);
                logToScreen(`[generatePDF] Hoàn thành trong ${duration}ms`);

            } catch (error) {
                logToScreen(`[generatePDF] Lỗi: ${error.message}`, 'error');
                console.error('Chi tiết lỗi:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi tạo BMP',
                    text: `Không thể tạo file BMP: ${error.message}`,
                    footer: 'Vui lòng kiểm tra console để biết chi tiết.'
                });
            }
        };

        async function saveAndOpenPDF(pdfBlob, fileName) {
            logToScreen('[saveAndOpenPDF] Bắt đầu lưu và mở PDF...');
            if (!cordova.file || !cordova.plugins.fileOpener2) {
                throw new Error('Plugin Cordova không sẵn sàng (file hoặc fileOpener2).');
            }

            await requestPermissions();

            return new Promise((resolve, reject) => {
                const directory = cordova.file.externalDataDirectory || cordova.file.documentsDirectory || cordova.file.dataDirectory;
                logToScreen('[saveAndOpenPDF] Thư mục lưu trữ: ' + directory);

                window.resolveLocalFileSystemURL(directory, function(dirEntry) {
                    dirEntry.getFile(fileName, { create: true, exclusive: false }, function(fileEntry) {
                        fileEntry.createWriter(function(fileWriter) {
                            fileWriter.onwriteend = function() {
                                logToScreen('[saveAndOpenPDF] Đã lưu file tại: ' + fileEntry.nativeURL);
                                cordova.plugins.fileOpener2.open(
                                    fileEntry.nativeURL,
                                    'application/pdf',
                                    {
                                        error: function(e) {
                                            logToScreen('[saveAndOpenPDF] Lỗi khi mở file: ' + JSON.stringify(e), 'error');
                                            reject(new Error('Không thể mở file: ' + e.message));
                                        },
                                        success: function() {
                                            logToScreen('[saveAndOpenPDF] Đã mở file thành công.');
                                            resolve();
                                        }
                                    }
                                );
                            };
                            fileWriter.onerror = function(e) {
                                logToScreen('[saveAndOpenPDF] Lỗi khi ghi file: ' + e.toString(), 'error');
                                reject(new Error('Không thể ghi file: ' + e.toString()));
                            };
                            fileWriter.write(pdfBlob);
                        }, reject);
                    }, reject);
                }, reject);
            });
        }

        async function requestPermissions() {
            if (!cordova.plugins.permissions) {
                logToScreen('[requestPermissions] Plugin permissions không sẵn sàng.');
                return;
            }

            const permissions = cordova.plugins.permissions;
            const perms = [permissions.WRITE_EXTERNAL_STORAGE, permissions.READ_EXTERNAL_STORAGE];

            return new Promise((resolve, reject) => {
                permissions.checkPermission(perms[0], function(status) {
                    if (status.hasPermission) {
                        logToScreen('[requestPermissions] Quyền đã được cấp.');
                        resolve();
                    } else {
                        permissions.requestPermissions(perms, function(status) {
                            if (status.hasPermission) {
                                logToScreen('[requestPermissions] Quyền được cấp sau khi yêu cầu.');
                                resolve();
                            } else {
                                logToScreen('[requestPermissions] Quyền bị từ chối.', 'error');
                                reject(new Error('Quyền truy cập bộ nhớ bị từ chối.'));
                            }
                        }, reject);
                    }
                }, reject);
            });
        }
    </script>
</body>

</html>