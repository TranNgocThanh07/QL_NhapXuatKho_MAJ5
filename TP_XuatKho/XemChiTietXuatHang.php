<?php
// TP_XuatHang/XemChiTietXuatHang.php
include '../db_config.php'; // Điều chỉnh đường dẫn nếu cần

$maXuatHang = isset($_GET['maXuatHang']) ? $_GET['maXuatHang'] : null;

if (!$maXuatHang) {
    echo "<p class='text-red-600 text-center'>Không tìm thấy mã phiếu xuất!</p>";
    exit;
}

try {
    // Lấy thông tin phiếu xuất
    $sql = "SELECT xh.MaXuatHang, nv.TenNhanVien, xh.NgayXuat, xh.GhiChu,
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

    // Lấy chi tiết phiếu xuất
    $sqlChiTiet = "SELECT ct.MaCTXHTP, ct.SoLuong, ct.TrangThai, dvt.TenDVT,
                          v.MaVai, v.TenVai, m.TenMau,
                          ct.SoLot, ct.TenThanhPhan, ct.MaDonHang, ct.MaVatTu, ct.Kho
                   FROM TP_ChiTietXuatHang ct
                   LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
                   LEFT JOIN Vai v ON ct.MaVai = v.MaVai
                   LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
                   WHERE ct.MaXuatHang = :maXuatHang";
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
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased"> 
    <!-- Main Content Section -->
    <section class="">
        <div class="container ">
            <div class="bg-white  custom-shadow  overflow-hidden">
                <!-- Header -->
                <header class="sticky top-0 z-20 bg-gradient-to-r from-red-700 to-red-500 text-white w-full flex justify-between items-center py-3 px-6">
                    <a href="../xuatkho.php" class="text-white text-2xl hover:scale-110 transition-transform flex items-center gap-1 hover:bg-red-600 rounded-full p-2">
                        <i class="fas fa-arrow-left"></i>
                       
                    </a>
                    <h2 class="text-xl md:text-2xl font-bold flex items-center gap-2 m mr-20">
                        <i class="fas fa-clipboard-list"></i> Chi Tiết Xuất Kho
                    </h2>
                    
                </header>

                <!-- Status Banner -->
                <div class="bg-blue-50 border-b border-blue-100 p-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-info-circle text-blue-500"></i>
                        <span class="text-blue-700 font-medium">Ngày Tạo Đơn Xuất Hàng 
                           
                        </span>
                    </div>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-calendar-alt"></i> <?php echo $ngayXuat; ?>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
                    <div class="info-card bg-gray-50 p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <div class="text-gray-500 text-sm mb-2 flex items-center gap-1">
                            <i class="fas fa-file-invoice text-red-500"></i> Thông tin phiếu
                        </div>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-hashtag text-gray-400"></i> Mã phiếu:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['MaXuatHang']); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-shopping-cart text-gray-400"></i> Mã đơn hàng:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['MaDonHang']); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-box text-gray-400"></i> Mã vật tư:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['MaVatTu']); ?></span>
                        </p>
                    </div>

                    <div class="info-card bg-gray-50 p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <div class="text-gray-500 text-sm mb-2 flex items-center gap-1">
                            <i class="fas fa-tshirt text-blue-500"></i> Thông tin sản phẩm
                        </div>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-swatch text-gray-400"></i> Vải:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['MaVai'] . ' (' . $phieuXuat['TenVai'] . ')'); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-palette text-gray-400"></i> Màu:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['TenMau']); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-ruler-horizontal text-gray-400"></i> Khổ:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['Kho']); ?></span>
                        </p>
                    </div>

                    <div class="info-card bg-gray-50 p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col">
                        <div class="text-gray-500 text-sm mb-2 flex items-center gap-1">
                            <i class="fas fa-cubes text-green-500"></i> Thông tin xuất kho
                        </div>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-user text-gray-400"></i> Nhân viên:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($phieuXuat['TenNhanVien']); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-boxes text-gray-400"></i> Tổng SL xuất:</span>
                            <span class="font-medium"><?php echo number_format($phieuXuat['TongSoLuongXuat'], 0, ',', '.') . ' ' . htmlspecialchars($phieuXuat['TenDVT']); ?></span>
                        </p>
                        <p class="flex justify-between mb-2">
                            <span class="text-gray-600"><i class="fas fa-calendar-alt text-gray-400"></i> Ngày xuất:</span>
                            <span class="font-medium"><?php echo $ngayXuat; ?></span>
                        </p>
                    </div>
                </div>

                <!-- Ghi chú -->
                <?php if (!empty($phieuXuat['GhiChu'])): ?>
                <div class="px-6 pb-4">
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <h4 class="font-medium flex items-center gap-2 text-yellow-700 mb-2">
                            <i class="fas fa-sticky-note"></i> Ghi chú
                        </h4>
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($phieuXuat['GhiChu'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Danh sách chi tiết -->
                <div class="p-6 pt-2">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-list-ul text-red-500"></i> Chi Tiết Xuất Hàng
                        <span class="text-sm bg-red-100 text-red-700 px-2 py-1 rounded-full">
                            <?php echo count($chiTietXuat); ?> mục
                        </span>
                    </h3>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-red-50 to-red-100">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-hashtag text-red-400"></i> STT
                                        </div>
                                    </th>                                                                     
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-boxes text-red-400"></i> Số Lượng Xuất
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-barcode text-red-400"></i> Số Lot
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-layer-group text-red-400"></i> Thành Phần
                                        </div>
                                    </th>                              
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-check-circle text-red-400"></i> Trạng Thái
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php 
                                $stt = 1;
                                foreach ($chiTietXuat as $ct): 
                                    $trangThaiHienThi = ($ct['TrangThai'] == 1) ? 'Đang xuất' : 'Chưa xuất';
                                    $trangThaiClass = ($ct['TrangThai'] == 1) ? 'text-dang-xuat' : 'text-chua-xuat';
                                    $trangThaiIcon = ($ct['TrangThai'] == 1) ? 'fa-clock' : 'fa-times-circle';
                                ?>
                                    <tr class="hover:bg-red-50 transition-colors duration-200">
                                        <td class="px-6 py-4 text-sm text-gray-700"><?php echo $stt++; ?></td>                                                                                                  
                                        <td class="px-6 py-4 text-sm text-gray-700 font-medium"><?php echo number_format($ct['SoLuong'], 0, ',', '.') . ' ' . htmlspecialchars($ct['TenDVT']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($ct['SoLot']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($ct['TenThanhPhan']); ?></td>                                     
                                        <td class="px-6 py-4 text-sm <?php echo $trangThaiClass; ?> font-medium flex items-center gap-2">
                                            <i class="fas <?php echo $trangThaiIcon; ?>"></i>
                                            <?php echo htmlspecialchars($trangThaiHienThi); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary Footer -->
                    <div class="mt-6 flex flex-col md:flex-row justify-between gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 shadow-sm md:w-1/3">
                            <div class="flex items-center gap-2 text-gray-700 font-medium mb-2">
                                <i class="fas fa-chart-pie text-blue-500"></i> Tổng kết
                            </div>
                            <p class="text-sm text-gray-600 flex justify-between mb-1">
                                <span>Tổng số mục:</span>
                                <span class="font-medium"><?php echo count($chiTietXuat); ?></span>
                            </p>
                            <p class="text-sm text-gray-600 flex justify-between mb-1">
                                <span>Đang xuất:</span>
                                <span class="font-medium text-yellow-600"><?php echo $phieuXuat['DangXuat']; ?> mục</span>
                            </p>
                            <p class="text-sm text-gray-600 flex justify-between">
                                <span>Chưa xuất:</span>
                                <span class="font-medium text-red-600"><?php echo count($chiTietXuat) - $phieuXuat['DangXuat']; ?> mục</span>
                            </p>
                        </div>

                       
                    </div>
                </div>
            </div>
        </div>
    </section>

    
</body>
</html>