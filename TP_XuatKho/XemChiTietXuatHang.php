<?php
// TP_XuatHang/XemChiTietXuatHang.php
include '../db_config.php';

$maXuatHang = isset($_GET['maXuatHang']) ? $_GET['maXuatHang'] : null;

if (!$maXuatHang) {
    echo "<p class='text-red-600 text-center'>Không tìm thấy mã phiếu xuất!</p>";
    exit;
}

try {
    // Lấy thông tin phiếu xuất và thông tin khách hàng
    $sql = "SELECT xh.MaXuatHang, xh.MaNhanVien, nv.TenNhanVien, xh.NgayXuat, xh.TrangThai, xh.GhiChu,
                   xh.MaKhachHang, xh.MaNguoiLienHe,
                   kh.TenKhachHang, kh.TenHoatDong, kh.DiaChi,
                   nl.TenNguoiLienHe, nl.SoDienThoai,
                   SUM(ct.SoLuong) as TongSoLuongXuat, dvt.TenDVT,
                   v.MaVai, v.TenVai, m.TenMau,
                   ct.SoLot, ct.TenThanhPhan, ct.MaDonHang, ct.MaVatTu, ct.Kho,
                   SUM(CASE WHEN ct.TrangThai = 1 THEN 1 ELSE 0 END) as DangXuat
            FROM TP_XuatHang xh
            LEFT JOIN TP_ChiTietXuatHang ct ON xh.MaXuatHang = ct.MaXuatHang
            LEFT JOIN NhanVien nv ON xh.MaNhanVien = nv.MaNhanVien
            LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
            LEFT JOIN Vai v ON ct.MaVai = v.MaVai
            LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
            LEFT JOIN TP_KhachHang kh ON xh.MaKhachHang = kh.MaKhachHang
            LEFT JOIN TP_NguoiLienHe nl ON xh.MaNguoiLienHe = nl.MaNguoiLienHe
            WHERE xh.MaXuatHang = :maXuatHang
            GROUP BY xh.MaXuatHang, xh.MaNhanVien, nv.TenNhanVien, xh.NgayXuat, xh.TrangThai, xh.GhiChu,
                    xh.MaKhachHang, xh.MaNguoiLienHe,
                     kh.TenKhachHang, kh.TenHoatDong, kh.DiaChi,
                     nl.TenNguoiLienHe, nl.SoDienThoai,
                     dvt.TenDVT, v.MaVai, v.TenVai, m.TenMau,
                     ct.SoLot, ct.TenThanhPhan, ct.MaDonHang, ct.MaVatTu, ct.Kho";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':maXuatHang', $maXuatHang, PDO::PARAM_STR);
    $stmt->execute();
    $phieuXuat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$phieuXuat) {
        echo "<p class='text-red-600 text-center'>Không tìm thấy thông tin phiếu xuất!</p>";
        exit;
    }

    // Lấy chi tiết phiếu xuất
   $sqlChiTiet = "SELECT ct.MaCTXHTP, ct.SoLuong, ct.SoKgCan, ct.TrangThai, dvt.TenDVT,
                      v.MaVai, v.TenVai, m.TenMau,
                      ct.SoLot, ct.TenThanhPhan, ct.MaDonHang, ct.MaVatTu, ct.Kho
               FROM TP_ChiTietXuatHang ct
               LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
               LEFT JOIN Vai v ON ct.MaVai = v.MaVai
               LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
               WHERE ct.MaXuatHang = :maXuatHang
               ORDER BY ct.SoLot ASC, ct.SoKgCan ASC";
    $stmtChiTiet = $pdo->prepare($sqlChiTiet);
    $stmtChiTiet->bindValue(':maXuatHang', $maXuatHang, PDO::PARAM_STR);
    $stmtChiTiet->execute();
    $chiTietXuat = $stmtChiTiet->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<p class='text-red-600 text-center'>Lỗi: " . $e->getMessage() . "</p>";
    exit;
}

// Xử lý định dạng ngày
$ngayXuat = date('d/m/Y', strtotime($phieuXuat['NgayXuat']));

// Tính tổng xuất, đã xuất, còn lại
$tongXuat = $phieuXuat['TongSoLuongXuat']; // Tổng số lượng xuất từ truy vấn SQL
$daXuat = 0; // Khởi tạo tổng số lượng đã xuất

// Tính tổng số lượng đã xuất từ chi tiết
foreach ($chiTietXuat as $ct) {
    if ($ct['TrangThai'] == 1) {
        $daXuat += $ct['SoLuong'];
    }
}

// Tính số lượng còn lại
$conLai = $tongXuat - $daXuat;

// Tính phần trăm hoàn thành
$percentCompleted = ($tongXuat > 0) ? ($daXuat / $tongXuat * 100) : 0;

// Hàm hỗ trợ hiển thị HTML an toàn
function safeHtml($value) {
    return $value !== null && $value !== '' ? htmlspecialchars($value) : 'N/A';
}

// Nhóm chi tiết theo Lot và tính tổng số kg thực tế
$lotGroups = [];
foreach ($chiTietXuat as $chiTiet) {
    $lot = $chiTiet['SoLot'];
    if (!isset($lotGroups[$lot])) {
        $lotGroups[$lot] = [
            'items' => [],
            'totalQuantity' => 0,
            'totalKg' => 0,
            'totalCay' => 0
        ];
    }
    $lotGroups[$lot]['items'][] = $chiTiet;
    $lotGroups[$lot]['totalQuantity'] += (float)$chiTiet['SoLuong'];
    $lotGroups[$lot]['totalKg'] += (float)$chiTiet['SoKgCan'];
    $lotGroups[$lot]['totalCay'] += 1;
}

// Tính tổng số kg thực tế chỉ cho các cây có trạng thái = 1
$sqlSoKgThucTe = "SELECT SUM(SoKgCan) as SoKgThucTe 
                  FROM TP_ChiTietXuatHang 
                  WHERE MaXuatHang = :maXuatHang AND TrangThai = 1";
$stmtSoKgThucTe = $pdo->prepare($sqlSoKgThucTe);
$stmtSoKgThucTe->bindValue(':maXuatHang', $maXuatHang, PDO::PARAM_STR);
$stmtSoKgThucTe->execute();
$soKgThucTe = $stmtSoKgThucTe->fetch(PDO::FETCH_ASSOC)['SoKgThucTe'] ?? 0;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Phiếu Xuất - MAJ5</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>    
        th, td {
            white-space: nowrap;
        }
        .text-chua-xuat {
            color: #EF4444;
        }
        .text-dang-xuat {
            color: #F59E0B;
        }
        .custom-shadow {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .info-card {
            transition: all 0.3s ease;
        }
        .info-card:hover {
            transform: translateY(-5px);
        }
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
        /* Màu cho biểu tượng */
        .icon-red {
            color: #DC2626;
        }
        .icon-blue {
            color: #2563EB;
        }
        .icon-green {
            color: #16A34A;
        }
        .icon-purple {
            color: #7C3AED;
        }
        
        /* Cho phép xuống dòng cho dữ liệu dài */
        .long-text {
            white-space: normal;
            word-wrap: break-word;
            max-width: 180px;
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
            0% {
                box-shadow: 0 0 0 0 rgba(88, 80, 236, 0.7);
            }

            70% {
                box-shadow: 0 0 0 12px rgba(88, 80, 236, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(88, 80, 236, 0);
            }
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

.card-hover:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
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
    background: linear-gradient(90deg, #b91c1c, #dc2626);
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

th, td {
    white-space: nowrap;
}

@media (max-width: 640px) {
    th, td {
        white-space: normal;
        word-break: break-word;
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
}
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased"> 
    <!-- Main Content Section -->
    <section class="">
        <div class="container">
            <div class="bg-white custom-shadow overflow-hidden">
                <!-- Header -->
                <header class="sticky top-0 z-20 bg-gradient-to-r from-red-700 to-red-500 text-white w-full flex items-center py-3 px-6">
                    <a href="../xuatkho.php" class="text-white text-xl hover:scale-110 transition-transform flex items-center gap-1 hover:bg-red-600 rounded-full p-2">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h2 class="text-lg md:text-2xl font-bold gap-2">
                        <i class="fas fa-clipboard-list "></i> Chi Tiết Xuất Kho
                    </h2>
                </header>

                <!-- Status Banner -->
                <div class="bg-blue-50 border-b border-blue-100 p-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-info-circle icon-blue"></i>
                        <span class="text-blue-700 font-medium">Ngày tạo phiếu</span>
                    </div>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-calendar-alt icon-gray"></i> <?php echo $ngayXuat; ?>
                    </div>
                </div>

                <!-- Summary Cards -->       
                 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 p-6">
                    <!-- Thông tin phiếu -->
                    <div class="info-card bg-gray-50 text-xs p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <div class="text-gray-500 mb-2 flex items-center gap-1">
                            <i class="fas fa-file-invoice" style="color: #EF4444;"></i> Thông tin phiếu
                        </div>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-hashtag" style="color: #F59E0B;"></i> Mã phiếu:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['MaXuatHang']); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-shopping-cart" style="color: #8B5CF6;"></i> Mã đơn hàng:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['MaDonHang']); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-box" style="color: #10B981;"></i> Mã vật tư:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['MaVatTu']); ?></span>
                        </p>                      
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-info-circle" style="color: #9333EA;"></i> Trạng thái:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['TrangThai'] == 1 ? 'Đã xuất' : 'Chưa xuất'); ?></span>
                        </p>
                    </div>

                    <!-- Thông tin sản phẩm -->
                    <div class="info-card bg-gray-50 text-xs p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <div class="text-gray-500 text-xs mb-2 flex items-center gap-1">
                            <i class="fas fa-tshirt" style="color: #F43F5E;"></i> Thông tin sản phẩm
                        </div>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-tshirt" style="color: #EC4899;"></i> Vải:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['MaVai'] . ' (' . $phieuXuat['TenVai'] . ')'); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-palette" style="color: #06B6D4;"></i> Màu:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['TenMau']); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-ruler-horizontal" style="color: #84CC16;"></i> Khổ:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['Kho']); ?></span>
                        </p>
                    </div>

                    <!-- Thông tin xuất kho -->
                    <div class="info-card bg-gray-50 p-4 text-xs rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <div class="text-gray-500 text-xs mb-2 flex items-center gap-1">
                            <i class="fas fa-cubes" style="color: #22C55E;"></i> Thông tin xuất kho
                        </div>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-user" style="color: #14B8A6;"></i> Nhân viên:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['TenNhanVien']); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-boxes" style="color: #D946EF;"></i> Tổng SL xuất:</span>
                            <span class="font-medium"><?php echo number_format($phieuXuat['TongSoLuongXuat'], 0, ',', '.') . ' ' . htmlspecialchars($phieuXuat['TenDVT']); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-calendar-alt" style="color: #EAB308;"></i> Ngày xuất:</span>
                            <span class="font-medium"><?php echo $ngayXuat; ?></span>
                        </p>
                    </div>

                    <!-- Thông tin khách hàng -->
                    <div class="info-card bg-gray-50 p-4  text-xs rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <div class="text-gray-500 text-xs mb-2 flex items-center gap-1">
                            <i class="fas fa-user-tie" style="color: #A855F7;"></i> Thông tin khách hàng
                        </div>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-user" style="color: #F472B6;"></i> Tên khách hàng:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['TenKhachHang'] ?? 'N/A'); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-briefcase" style="color: #64748B;"></i>Tên hoạt động:</span>
                            <span class="font-medium text-right long-text"><?php echo htmlspecialchars($phieuXuat['TenHoatDong'] ?? 'N/A'); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-map-marker-alt" style="color: #F97316;"></i> Địa chỉ:</span>
                            <span class="font-medium text-right long-text"><?php echo htmlspecialchars($phieuXuat['DiaChi'] ?? 'N/A'); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-user-check" style="color: #6EE7B7;"></i> Người liên hệ:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['TenNguoiLienHe'] ?? 'N/A'); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-phone" style="color: #C084FC;"></i> Số điện thoại:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['SoDienThoai'] ?? 'N/A'); ?></span>
                        </p>
                    </div>
                </div>

                <!-- Ghi chú -->
                <?php if (!empty($phieuXuat['GhiChu'])): ?>
                <div class="px-6 pb-4">
                    <div class="bg-yellow-50 p-4 text-xs rounded-lg border border-yellow-200">
                        <h4 class="font-medium flex items-center gap-2 text-yellow-700 mb-2">
                            <i class="fas fa-sticky-note icon-yellow"></i> Ghi chú
                        </h4>
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($phieuXuat['GhiChu'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Hiển thị tiến độ xuất kho -->
                <div class="px-6 pb-4">
                    <h3 class="section-header text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-indigo-100 text-indigo-600">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <span>Tiến Độ Xuất Kho</span>
                    </h3>
                    <div class="bg-white rounded-xl text-xs border p-5 card-hover shadow-sm">
                        <div class="flex justify-between mb-3">
                            <span class="font-semibold text-gray-700 flex items-center">
                                <span class="bg-indigo-100 p-1 rounded-md text-indigo-500 mr-2"><i class="fas fa-chart-line"></i></span>
                                Tiến độ:
                                <span id="progressPercent" class="ml-2 bg-indigo-100 text-indigo-700 px-2 py-1 rounded-lg font-bold"><?php echo round($percentCompleted, 2); ?>%</span>
                            </span>
                            <span id="progressText" class="font-semibold text-gray-700 flex items-center">
                                <i class="fas fa-box-open text-indigo-400 mr-2"></i>
                                <?php echo number_format($daXuat, 2, '.', ''); ?> / <?php echo number_format($tongXuat, 2, '.', ''); ?> <?php echo safeHtml($phieuXuat['TenDVT']); ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4 mb-4 overflow-hidden">
                            <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 h-4 rounded-full progress-animate relative" style="width: <?php echo $percentCompleted; ?>%">
                                <?php if ($percentCompleted > 25): ?>
                                    <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-white"><?php echo $percentCompleted; ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-<?php echo ($phieuXuat['TenDVT'] != 'KG') ? 4 : 3; ?> gap-3">
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-3 rounded-lg shadow-sm border border-blue-200 text-center transform transition-all hover:scale-105">
                                <div class="icon-circle bg-blue-500 text-white mx-auto mb-2">
                                    <i class="fas fa-box-open"></i>
                                </div>
                                <p class="text-xs text-blue-700 font-medium mb-1">Tổng Xuất</p>
                                <p class="font-bold text-blue-800 text-lg"><?php echo number_format($tongXuat, 2, '.', ''); ?></p>
                                <p class="text-xs text-blue-500"><?php echo safeHtml($phieuXuat['TenDVT']); ?></p>
                            </div>
                            <div class="bg-gradient-to-br from-green-50 to-green-100 p-3 rounded-lg shadow-sm border border-green-200 text-center transform transition-all hover:scale-105">
                                <div class="icon-circle bg-green-500 text-white mx-auto mb-2">
                                    <i class="fas fa-check"></i>
                                </div>
                                <p class="text-xs text-green-700 font-medium mb-1">Đã Xuất</p>
                                <p class="font-bold text-green-800 text-lg"><?php echo number_format($daXuat, 2, '.', ''); ?></p>
                                <p class="text-xs text-green-500"><?php echo safeHtml($phieuXuat['TenDVT']); ?></p>
                            </div>
                             <?php if ($phieuXuat['TenDVT'] != 'KG'): ?>
                                <div class="bg-gradient-to-br from-red-50 to-red-100 p-3 rounded-lg shadow-sm border border-red-200 text-center transform transition-all hover:scale-105">
                                    <div class="icon-circle bg-red-500 text-white mx-auto mb-2">
                                        <i class="fas fa-weight-hanging"></i>
                                    </div>
                                    <p class="text-xs text-red-700 font-medium mb-1">Số Kg Cân Thực Đã Xuất</p>
                                    <p class="font-bold text-red-800 text-lg"><?php echo number_format($soKgThucTe, 2, '.', ''); ?></p>
                                     <p class="text-xs text-red-500">KG</p>
                                </div>
                            <?php endif; ?>
                            <div class="bg-gradient-to-br from-amber-50 to-amber-100 p-3 rounded-lg shadow-sm border border-amber-200 text-center transform transition-all hover:scale-105">
                                <div class="icon-circle bg-amber-500 text-white mx-auto mb-2">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <p class="text-xs text-amber-700 font-medium mb-1">Còn Lại</p>
                                <p class="font-bold text-amber-800 text-lg"><?php echo number_format($conLai, 2, '.', ''); ?></p>
                                <p class="text-xs text-amber-500"><?php echo safeHtml($phieuXuat['TenDVT']); ?></p>
                            </div>                         
                        </div>
                    </div>
                </div>

                 <!-- Summary Footer -->
                    <div class="mt-1 flex flex-col text-xs md:flex-row justify-between gap-4 p-4 pt-2">
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 shadow-sm md:w-1/3">
                            <div class="flex items-center gap-2 text-gray-700 font-medium mb-2">
                                <i class="fas fa-chart-pie icon-blue"></i> Tổng kết
                            </div>
                            <p class="text-sm text-gray-600 flex justify-between mb-1">
                                <span>Tổng số mục:</span>
                                <span class="font-medium"><?php echo count($chiTietXuat); ?></span>
                            </p>
                            <p class="text-sm text-gray-600 flex justify-between mb-1">
                                <span>Đã xuất:</span>
                                <span class="font-medium text-green-600"><?php echo $phieuXuat['DangXuat']; ?> mục</span>
                            </p>
                            <p class="text-sm text-gray-600 flex justify-between">
                                <span>Chưa xuất:</span>
                                <span class="font-medium text-red-600"><?php echo count($chiTietXuat) - $phieuXuat['DangXuat']; ?> mục</span>
                            </p>
                        </div>
                    </div>
                <!-- Danh sách chi tiết -->
               <div class="p-4 pt-2 mt-1">
                    <h3 class="section-header text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-indigo-100 text-indigo-600">
                            <i class="fas fa-list"></i>
                        </div>
                        <span>Chi Tiết Xuất Hàng</span>
                        <span class="text-sm bg-red-100 text-red-700 px-2 py-1 rounded-full">
                            <?php echo count($chiTietXuat); ?> mục
                        </span>
                    </h3>
                    <div class="responsive-table custom-scrollbar">
                        <table class="w-full border-collapse">
                            <thead class="bg-red-50 text-red-800 sticky top-0 z-10">
                                <tr>
                                    <th class="text-left sticky left-0 bg-red-50 z-20 p-2 sm:p-3 font-semibold">STT</th>
                                    <th class="text-left p-2 sm:p-3 font-semibold">Số Lượng Xuất</th>
                                    <?php if ($phieuXuat['TenDVT'] != 'KG'): ?>
                                        <th class="text-left p-2 sm:p-3 font-semibold">Số Kg Cân</th>
                                    <?php endif; ?>
                                    <th class="text-left p-2 sm:p-3 font-semibold">Số Lot</th>
                                    <th class="text-left p-2 sm:p-3 font-semibold">Thành Phần</th>
                                    <th class="text-left p-2 sm:p-3 font-semibold">Trạng Thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $stt = 1;
                                foreach ($lotGroups as $lot => $group): 
                                ?>
                                    <tr class="bg-indigo-100 text-indigo-800 font-bold border-b border-gray-200">
                                        <td colspan="<?php echo ($phieuXuat['TenDVT'] != 'KG') ? 5 : 4; ?>" class="p-2 sm:p-3">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-folder-open"></i>
                                                <span>Số Lot: <?php echo safeHtml($lot); ?> (Tổng xuất: <?php echo number_format($group['totalQuantity'], 2, '.', '') . ' ' . safeHtml($phieuXuat['TenDVT']); ?>
                                                <?php if ($phieuXuat['TenDVT'] != 'KG'): ?>
                                                    , Tổng kg thực tế: <?php echo number_format($group['totalKg'], 2, '.', '') . ' KG'; ?>
                                                <?php endif; ?>
                                                , Tổng cây: <?php echo $group['totalCay'] . ' Cây'; ?>)</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php foreach ($group['items'] as $chiTiet): 
                                        $trangThaiHienThi = ($chiTiet['TrangThai'] == 1) ? 'Đã xuất' : 'Chưa xuất';
                                        $trangThaiClass = ($chiTiet['TrangThai'] == 1) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                    ?>
                                        <tr class="border-b border-gray-200 hover:bg-red-100 transition-colors">
                                            <td class="sticky left-0 bg-white p-2 sm:p-3"><?php echo $stt++; ?></td>
                                            <td class="font-bold p-2 sm:p-3 <?php echo intval($chiTiet['SoLuong']) > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo number_format($chiTiet['SoLuong'], 2, '.', '') . ' ' . safeHtml($chiTiet['TenDVT']); ?>
                                            </td>
                                            <?php if ($phieuXuat['TenDVT'] != 'KG'): ?>
                                                <td class="p-2 sm:p-3"><?php echo number_format($chiTiet['SoKgCan'], 2, '.', ''); ?></td>
                                            <?php endif; ?>
                                            <td class="p-2 sm:p-3"><?php echo safeHtml($chiTiet['SoLot']); ?></td>
                                            <td class="p-2 sm:p-3"><?php echo safeHtml($chiTiet['TenThanhPhan']); ?></td>
                                            <td class="p-2 sm:p-3">
                                                <span class="px-2 py-1 rounded-full text-xs <?php echo $trangThaiClass; ?>">
                                                    <?php echo $trangThaiHienThi; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>