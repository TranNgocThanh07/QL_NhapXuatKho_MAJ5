<?php
include '../db_config.php';

$maSoMe = $_GET['maSoMe'];

// Truy vấn thông tin đơn sản xuất
$sql = "SELECT 
            dsx.MaSoMe, dsx.MaDonHang, dsx.MaVai, dsx.TenVai, dsx.Kho, 
            dsx.SoLuongDatHang, dsx.NgayNhan, dsx.NgayGiao, dsx.SoKgQuyDoi, 
            dsx.Loss, dsx.TongSoLuongGiao,
            dsx.DinhMuc, dsx.DoBenMau, dsx.DoLechMau, dsx.DinhLuong, 
            dsx.GhiChu, dsx.TrangThai, dsx.YeuCauKhac, dsx.LoaiDon,
            kh.TenKhachHang, nlh.TenNguoiLienHe, nv.TenNhanVien, 
            mau.TenMau, dvt.TenDVT, ho.TenSuDungHo, tc.TenTieuChuan
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

// Tính tổng đã nhập từ chi tiết
if ($don) {
    $sqlDaNhap = "SELECT SUM(SoLuong) as DaNhap 
                  FROM TP_ChiTietDonSanXuat 
                  WHERE MaSoMe = ? AND TrangThai = 0";
    $stmtDaNhap = $pdo->prepare($sqlDaNhap);
    $stmtDaNhap->execute([$maSoMe]);
    $daNhap = $stmtDaNhap->fetch(PDO::FETCH_ASSOC)['DaNhap'] ?? 0;

    $tongNhap = $don['SoLuongDatHang'] ?? 0;
    $don['DaNhap'] = $daNhap;
    $don['ConLai'] = number_format($tongNhap - $daNhap, 2, '.', '');
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Đơn Sản Xuất</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    </style>
</head>
<body class="bg-gray-100 min-h-screen font-sans">
    <div class="relative min-h-screen">
        <!-- Header with improved appearance -->
        <header class="sticky top-0 z-20 bg-gradient-to-r from-blue-800 to-indigo-600 p-3 shadow-lg">
            <div class="flex items-center justify-between max-w-4xl mx-auto">
                <div class="flex items-center">
                    <a href="../nhapkho.php" class="text-white text-xl hover:scale-110 transition-transform p-2">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h2 class="text-white font-bold text-2xl flex items-center ml-7">
                        <div class="p-1.5 ">
                            <i class="fas fa-file-lines text-white"></i>
                        </div>
                        Chi Tiết Đơn Sản Xuất
                    </h2>
                </div>
            </div>
        </header>

        <?php if ($don): ?>
            <?php
            function safeHtml($value) {
                return $value !== null && $value !== '' ? htmlspecialchars($value) : '';
            }
            
            function getStatusClass($status) {
                return $status === '0' ? 'bg-yellow-100 text-yellow-800 border border-yellow-300' : 'bg-green-100 text-green-800 border border-green-300';
            }
            
            function getStatusText($status) {
                return $status === '0' ? 'Chưa nhập đủ hàng' : ($status === '2' ? 'Đã nhập đủ hàng' : $status);
            }
            
            $percentCompleted = $don['SoLuongDatHang'] > 0 ? min(100, round(($don['DaNhap'] / $don['SoLuongDatHang']) * 100)) : 0;
            ?>

            <main class="max-w-4xl mx-auto p-4 space-y-5 pb-20">
                <!-- Order ID Card with visual enhancement -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow p-4 border-l-4 border-blue-500 card-hover">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2 mb-3">
                                <div class="icon-circle bg-blue-100 text-blue-600">
                                    <i class="fas fa-hashtag"></i>
                                </div>
                                <span>Mã Số Mẻ: <span class="text-red-600"><?php echo safeHtml($don['MaSoMe']); ?></span></span>
                            </h3>
                            <div class="space-y-2 text-sm pl-2 border-l-2 border-blue-200">
                                <p class="flex items-start"><i class="fas fa-tag text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Mã Đơn Hàng:</span> <?php echo safeHtml($don['MaDonHang']); ?></span></p>
                                <p class="flex items-start"><i class="fas fa-user text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Khách Hàng:</span> <?php echo safeHtml($don['TenKhachHang']); ?></span></p>
                                <p class="flex items-start"><i class="fas fa-tshirt text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Vải:</span> <?php echo safeHtml($don['TenVai']); ?> (<?php echo safeHtml($don['MaVai']); ?>)</span></p>
                                <p class="flex items-start"><i class="fas fa-ruler text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Khổ:</span> <?php echo safeHtml($don['Kho']); ?></span></p>                                                                            
                                <div class="flex items-center">
                                        <p class="text-xs text-gray-500 mr-2 mb-0"><i class="fas fa-palette text-blue-500 mr-2 mt-1"></i><span class="font-semibold text-gray-700">Màu:</span></p>
                                        <div class="w-6 h-6 rounded-full mr-2 shadow-sm" style="background: linear-gradient(to right, red, orange, yellow, green, blue, indigo, violet);"></div>

                                        <p class="font-semibold text-gray-700 mb-0"><?php echo safeHtml($don['TenMau']); ?></p>
                                </div>                             
                                <span class="status-badge mb-2 <?php echo getStatusClass($don['TrangThai']); ?>">
                                    <i class="fas <?php echo $don['TrangThai'] === '0' ? 'fa-clock mr-1' : 'fa-check-circle mr-1'; ?>"></i>
                                    <?php echo getStatusText($don['TrangThai']); ?>
                                 </span>
                            </div>
                        </div>                      
                    </div>
                </div>

                <!-- Progress Summary with animation -->
                <div class="bg-white rounded-xl shadow-sm p-4 card tergt-hover border border-gray-100">
                    <h3 class="section-header text-base font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-indigo-100 text-indigo-600">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <span>Tiến Độ Nhập Hàng</span>
                    </h3>
                    
                    <div class="text-sm">
                        <div class="flex justify-between mb-2">
                            <span class="font-semibold text-gray-700 flex items-center">
                                <i class="fas fa-spinner text-indigo-500 mr-1"></i>
                                Hoàn thành: <span class="ml-1 text-indigo-600 font-bold"><?php echo $percentCompleted; ?>%</span>
                            </span>
                            <span class="font-semibold text-gray-700"><?php echo number_format($don['DaNhap'], 2, '.', ''); ?> / <?php echo number_format($don['SoLuongDatHang'], 2, '.', ''); ?> <?php echo safeHtml($don['TenDVT']); ?></span>
                        </div>
                        
                        <div class="w-full bg-gray-200 rounded-full h-4 mb-4 overflow-hidden">
                            <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 h-4 rounded-full progress-animate relative" style="width: <?php echo $percentCompleted; ?>%">
                                <?php if ($percentCompleted > 25): ?>
                                <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-white"><?php echo $percentCompleted; ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-3 mt-3">
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-3 rounded-lg shadow-sm border border-blue-200 text-center transform transition-all hover:scale-105">
                                <div class="icon-circle bg-blue-500 text-white mx-auto mb-2">
                                    <i class="fas fa-box-open"></i>
                                </div>
                                <p class="text-xs text-blue-700 font-medium mb-1">Tổng Nhập</p>
                                <p class="font-bold text-blue-800 text-lg"><?php echo number_format($don['SoLuongDatHang'], 2, '.', ''); ?></p>
                            </div>
                            
                            <div class="bg-gradient-to-br from-green-50 to-green-100 p-3 rounded-lg shadow-sm border border-green-200 text-center transform transition-all hover:scale-105">
                                <div class="icon-circle bg-green-500 text-white mx-auto mb-2">
                                    <i class="fas fa-check"></i>
                                </div>
                                <p class="text-xs text-green-700 font-medium mb-1">Đã Nhập</p>
                                <p class="font-bold text-green-800 text-lg"><?php echo number_format($don['DaNhap'], 2, '.', ''); ?></p>
                            </div>
                            
                            <div class="bg-gradient-to-br from-amber-50 to-amber-100 p-3 rounded-lg shadow-sm border border-amber-200 text-center transform transition-all hover:scale-105">
                                <div class="icon-circle bg-amber-500 text-white mx-auto mb-2">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <p class="text-xs text-amber-700 font-medium mb-1">Còn Lại</p>
                                <p class="font-bold text-amber-800 text-lg"><?php echo number_format($don['ConLai'], 2, '.', ''); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thông Tin Chi Tiết with improved layout -->
                <div class="bg-white rounded-xl shadow-sm p-4 card-hover border border-gray-100">
                    <h3 class="section-header text-base font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-indigo-100 text-indigo-600">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <span>Thông Tin Chi Tiết</span>
                    </h3>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <!-- Số lượng đặt -->
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-box"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Số Lượng Đặt</p>
                                <p class="font-semibold"><?php echo number_format($don['SoLuongDatHang'], 2, '.', '') . ' ' . safeHtml($don['TenDVT']); ?></p>
                            </div>
                        </div> 

                        <!-- Số Kg Quy Đổi -->
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-weight-hanging"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Kg Quy Đổi</p>
                                <p class="font-semibold"><?php echo number_format((float)$don['SoKgQuyDoi'], 2, '.', '') . ' Kg'; ?></p>
                            </div>
                        </div>

                        <!-- Ngày Nhận -->
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Ngày Nhận</p>
                                <p class="font-semibold"><?php echo safeHtml($don['NgayNhan']); ?></p>
                            </div>
                        </div>

                        <!-- Ngày Giao -->
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Ngày Giao</p>
                                <p class="font-semibold"><?php echo safeHtml($don['NgayGiao']); ?></p>
                            </div>
                        </div>

                        <!-- Loss -->
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Loss</p>
                                <p class="font-semibold"><?php echo number_format((float)$don['Loss'], 2, '.', '') . ' Kg'; ?></p>
                            </div>
                        </div>

                        <!-- Loại Đơn -->
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Loại Đơn</p>
                                <p class="font-semibold"><?php echo safeHtml($don['LoaiDon']); ?></p>
                            </div>
                        </div>

                        <!-- Người Liên Hệ -->
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Người Liên Hệ</p>
                                <p class="font-semibold"><?php echo safeHtml($don['TenNguoiLienHe']); ?></p>
                            </div>
                        </div>

                        <!-- Nhân Viên -->
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

                    <!-- Yêu Cầu Khác -->
                    <div class="mt-4 bg-blue-50 p-3 rounded-lg border border-blue-100">
                        <p class="flex items-start">
                            <i class="fas fa-comment-dots text-blue-500 mr-2 mt-1"></i>
                            <span>
                                <span class="font-semibold text-blue-700">Yêu Cầu Khác:</span><br>
                                <span class="text-gray-700"><?php echo safeHtml($don['YeuCauKhac']) ?: 'Không có yêu cầu khác.'; ?></span>
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Technical Information with improved layout -->
                <div class="bg-white rounded-xl shadow-sm p-4 card-hover border border-gray-100">
                    <h3 class="section-header text-base font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-indigo-100 text-indigo-600">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <span>Thông Tin Kỹ Thuật</span>
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm">
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
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Sử Dụng Hộ</p>
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

                <!-- Note Card with improved styling -->
                <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl shadow-sm p-4 card-hover border border-amber-200">
                    <h3 class="text-base font-bold text-amber-800 flex items-center gap-2 mb-3 pb-2 border-b border-amber-200">
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
            </main>
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

        <!-- View Detail Button with animation -->
        <div class="fixed-back-btn">
            <a href="XemChiTietNhapDonSanXuat.php?maSoMe=<?php echo $maSoMe; ?>"
            class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-3 py-1.5 rounded-full text-sm font-medium shadow-md flex items-center hover:shadow-lg transition-all">
                <div class="mr-1 bg-white/20 p-0.5 rounded-full">
                    <i class="fas fa-eye text-xs"></i>
                </div>
                Xem Chi Tiết Nhập Kho
            </a>
        </div>

    </div>
</body>
</html>