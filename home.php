<?php
include 'header.php';
include 'db_config.php';

// Lấy giá trị từ form, mặc định là ngày hiện tại
$selectedDay = isset($_GET['day']) ? $_GET['day'] : date('d');
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Tạo filterValue theo định dạng YYYY-MM-DD
$filterValue = "$selectedYear-$selectedMonth-$selectedDay";
$filterType = 'day'; // Chỉ lọc theo ngày cụ thể

// Điều kiện lọc theo thời gian
$timeCondition = "CAST(NgayNhan AS DATE) = :filterValue";
$timeConditionXuat = "CAST(NgayXuat AS DATE) = :filterValue";

// Thống kê tổng sản phẩm (từ bảng DTO_Vai)
$sqlVai = "SELECT COUNT(*) as totalVai FROM Vai";
$stmtVai = $pdo->prepare($sqlVai);
$stmtVai->execute();
$totalVai = $stmtVai->fetch(PDO::FETCH_ASSOC)['totalVai'];

// Thống kê nhập kho (từ bảng DTO_TP_DonSanXuat, TrangThai = 3)
$sqlNhapKho = "SELECT COUNT(*) as totalNhapKho 
               FROM TP_DonSanXuat 
               WHERE TrangThai = 3 AND $timeCondition";
$stmtNhapKho = $pdo->prepare($sqlNhapKho);
$stmtNhapKho->bindParam(':filterValue', $filterValue);
$stmtNhapKho->execute();
$totalNhapKho = $stmtNhapKho->fetch(PDO::FETCH_ASSOC)['totalNhapKho'];

// Thống kê xuất kho (từ bảng DTO_TP_XuatHang, TrangThai = 1)
$sqlXuatKho = "SELECT COUNT(*) as totalXuatKho 
               FROM TP_XuatHang 
               WHERE TrangThai = 1 AND $timeConditionXuat";
$stmtXuatKho = $pdo->prepare($sqlXuatKho);
$stmtXuatKho->bindParam(':filterValue', $filterValue);
$stmtXuatKho->execute();
$totalXuatKho = $stmtXuatKho->fetch(PDO::FETCH_ASSOC)['totalXuatKho'];

// Truy vấn 5 đơn nhập kho gần nhất từ TP_DonSanXuat, join với TP_KhachHang và NhanVien
$sqlNhapKhoRecent = "
SELECT TOP 5 
    ds.NgayNhan, 
    ds.MaSoMe, 
    ds.MaNhanVien, 
    nv.TenNhanVien, 
    ds.MaKhachHang, 
    kh.TenKhachHang, 
    kh.TenHoatDong, 
    kh.DiaChi, 
    ds.TrangThai, 
    ds.GhiChu 
FROM TP_DonSanXuat ds
LEFT JOIN NhanVien nv ON ds.MaNhanVien = nv.MaNhanVien
LEFT JOIN TP_KhachHang kh ON ds.MaKhachHang = kh.MaKhachHang
WHERE ds.NgayNhan IS NOT NULL 
ORDER BY ds.NgayNhan DESC";
$stmtNhapKhoRecent = $pdo->prepare($sqlNhapKhoRecent);
$stmtNhapKhoRecent->execute();
$recentNhapKho = $stmtNhapKhoRecent->fetchAll(PDO::FETCH_ASSOC);

// Truy vấn 5 đơn xuất kho gần nhất từ TP_XuatHang, join với TP_KhachHang và NhanVien
$sqlXuatKhoRecent = "
    SELECT TOP 5 
        xh.NgayXuat, 
        xh.MaXuatHang, 
        xh.MaNhanVien, 
        nv.TenNhanVien,      
        xh.TrangThai, 
        xh.GhiChu 
    FROM TP_XuatHang xh
    LEFT JOIN NhanVien nv ON xh.MaNhanVien = nv.MaNhanVien
    WHERE xh.NgayXuat IS NOT NULL 
    ORDER BY xh.NgayXuat DESC";
$stmtXuatKhoRecent = $pdo->prepare($sqlXuatKhoRecent);
$stmtXuatKhoRecent->execute();
$recentXuatKho = $stmtXuatKhoRecent->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống quản lý kho</title>
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome CDN for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
      
    </style>
</head>
<body>
    <!-- GioiThieu Section -->
    <section class="hero-gradient text-red py-8 sm:py-12 md:py-16 lg:py-20">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="w-full md:w-1/2 mb-6 md:mb-0">
                <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold mb-4">Hệ thống quản lý kho</h1>
                <p class="text-base sm:text-lg opacity-90 mb-6">Quản lý hàng hóa hiệu quả, tối ưu vận hành doanh nghiệp</p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="nhapkho.php" class="inline-flex items-center px-4 py-2 sm:px-6 sm:py-3 border border-transparent text-sm sm:text-base font-medium rounded-md shadow-sm text-red-600 bg-white hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-150 ease-in-out">
                        <i class="fas fa-arrow-circle-down mr-2"></i>
                        Nhập kho ngay
                    </a>
                    <a href="xuatkho.php" class="inline-flex items-center px-4 py-2 sm:px-6 sm:py-3 border border-white text-sm sm:text-base font-medium rounded-md shadow-sm text-red-600 bg-transparent hover:bg-white hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white transition duration-150 ease-in-out">
                        <i class="fas fa-arrow-circle-up mr-2"></i>
                        Xuất kho ngay
                    </a>
                </div>
            </div>
            <!-- Nếu muốn thêm hình ảnh -->
            <!-- <div class="w-full md:w-1/2 flex justify-center">
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRU2OJYIIRPZ0gr7vWKtLO-mx6p62rg1VkzpQ&s" alt="Warehouse Illustration" class="w-full max-w-xs sm:max-w-sm md:max-w-md h-auto object-contain">
            </div> -->
        </div>
    </div>
</section>

    <!-- ThongKe Section -->
    <section class="py-10 -mt-10">
    <div class="container mx-auto px-4">
        <!-- Bộ lọc -->
        <div class="mb-6 flex justify-end">
            <form method="GET" class="flex gap-4">
                <!-- Combobox Ngày -->
                <select name="day" id="day" class="p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    <?php
                    $selectedDay = isset($_GET['day']) ? $_GET['day'] : date('d');
                    $selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
                    $selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

                    $daysInMonth = 31; // Mặc định 31 ngày
                    if (in_array($selectedMonth, ['04', '06', '09', '11'])) {
                        $daysInMonth = 30; // Tháng 4, 6, 9, 11 có 30 ngày
                    } elseif ($selectedMonth === '02') {
                        $daysInMonth = 28; // Tháng 2 có 28 ngày
                    }
                    for ($i = 1; $i <= $daysInMonth; $i++) {
                        $dayValue = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $selected = $selectedDay == $dayValue ? 'selected' : '';
                        echo "<option value='$dayValue' $selected>$i</option>";
                    }
                    ?>
                </select>

                <!-- Combobox Tháng -->
                <select name="month" id="month" class="p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500" onchange="updateDays()">
                    <?php
                    for ($i = 1; $i <= 12; $i++) {
                        $monthValue = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $selected = $selectedMonth == $monthValue ? 'selected' : '';
                        echo "<option value='$monthValue' $selected>Tháng $i</option>";
                    }
                    ?>
                </select>

                <!-- Input Năm -->
                <input type="number" name="year" value="<?php echo htmlspecialchars($selectedYear); ?>" class="p-2 border border-gray-300 rounded-lg w-24 focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="YYYY" required min="2000" max="2100">

                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">Lọc</button>
            </form>
        </div>

        <!-- Thống kê -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6">
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 border-l-4 border-red-600">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600 mr-3 sm:mr-4">
                        <i class="fas fa-box text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs sm:text-sm">Tổng sản phẩm</p>
                        <h3 class="text-lg sm:text-2xl font-bold text-gray-800"><?php echo number_format($totalVai); ?></h3>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-blue-600">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-arrow-circle-down text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Tổng Nhập kho </p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($totalNhapKho); ?></h3>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-green-600">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-arrow-circle-up text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Tổng Xuất kho </p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($totalXuatKho); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

    <!-- Recent Activities Section -->
<section class="py-10 bg-gray-50">
    <div class="container mx-auto px-4">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-history mr-2 text-blue-500"></i> Hoạt động gần đây
        </h2>
        
        <!-- Nhập kho -->
        <div class="mb-10">
            <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-warehouse mr-2 text-indigo-600"></i> Nhập kho (5 đơn gần nhất)
            </h3>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-clock mr-1 text-blue-500"></i> Thời gian
                                </th>

                                <th scope="col" class="hidden sm:table-cell px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-barcode mr-1 text-gray-500"></i> Mã Phiếu
                                </th>
                                <th scope="col" class="hidden md:table-cell px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-user mr-1 text-purple-500"></i> Nhân Viên
                                </th>
                                <th scope="col" class="hidden lg:table-cell px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-users mr-1 text-teal-500"></i> Khách Hàng
                                </th>
                                <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-info-circle mr-1 text-green-500"></i> Trạng Thái
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            foreach ($recentNhapKho as $row) {
                                $thoiGian = date('d/m/Y', strtotime($row['NgayNhan']));
                                $maPhieu = $row['MaSoMe'];
                                $tenNhanVien = $row['TenNhanVien'] ?? $row['MaNhanVien'];
                                $tenKhachHang = $row['TenKhachHang'] ?? $row['MaKhachHang'];
                                $tenHoatDong = $row['TenHoatDong'] ?? 'Không có';
                                $diaChi = $row['DiaChi'] ?? 'Không có';
                                $trangThai = $row['TrangThai'] == 0 ? 'Đơn hàng mới' : 
                                ($row['TrangThai'] == 2 ? 'Đơn nhập hàng' : 
                                ($row['TrangThai'] == 1 ? 'Đơn đã hủy' : 
                                ($row['TrangThai'] == 3 ? 'Đơn hoàn tất' : 'Đang xử lý')));
                                $trangThaiClass = $row['TrangThai'] == 0 ? 'bg-blue-100 text-blue-800' : 
                                ($row['TrangThai'] == 1 ? 'bg-red-100 text-red-800' : 
                                ($row['TrangThai'] == 2 ? 'bg-yellow-100 text-yellow-800' : 
                                ($row['TrangThai'] == 3 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')));
                                $ghiChu = $row['GhiChu'] ?? 'Không có ghi chú';
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-clock mr-1 text-blue-500"></i> <?php echo $thoiGian; ?>
                                </td>
                                <!-- <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                            <i class="fas fa-arrow-circle-down"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <i class="fas fa-list mr-1 text-indigo-500"></i> Nhập kho
                                            </div>
                                            
                                        </div>
                                    </div>
                                </td> -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-barcode mr-1 text-gray-500"></i> <?php echo htmlspecialchars($maPhieu); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-user mr-1 text-purple-500"></i> <?php echo htmlspecialchars($tenNhanVien); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-users mr-1 text-teal-500"></i> <?php echo htmlspecialchars($tenKhachHang); ?><br>
                                    <span class="text-xs text-gray-400"><?php echo htmlspecialchars($tenHoatDong); ?></span><br>
                                    <span class="text-xs text-gray-400"><?php echo htmlspecialchars($diaChi); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $trangThaiClass; ?>">
                                        <i class="fas fa-info-circle mr-1 text-green-500"></i> <?php echo $trangThai; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Xuất kho -->
        <div>
            <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-truck mr-2 text-green-600"></i> Xuất kho (5 đơn gần nhất)
            </h3>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/5">
                                    <i class="fas fa-clock mr-1 text-blue-500"></i> Thời gian
                                </th>
                                <!-- <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">
                                    <i class="fas fa-list mr-1 text-indigo-500"></i> Hoạt động
                                </th> -->
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/5">
                                    <i class="fas fa-barcode mr-1 text-gray-500"></i> Mã Phiếu
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/5">
                                    <i class="fas fa-user mr-1 text-purple-500"></i> Nhân Viên
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/5">
                                    <i class="fas fa-info-circle mr-1 text-green-500"></i> Trạng Thái
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            foreach ($recentXuatKho as $row) {
                                $thoiGian = date('d/m/Y H:i', strtotime($row['NgayXuat']));
                                $maPhieu = $row['MaXuatHang'];
                                $tenNhanVien = $row['TenNhanVien'] ?? $row['MaNhanVien'];
                                $trangThai = $row['TrangThai'] == 1 ? 'Hoàn tất' : 'Đang xử lý';
                                $trangThaiClass = $row['TrangThai'] == 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                $ghiChu = $row['GhiChu'] ?? 'Không có ghi chú';
                            ?>
                    <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-clock mr-1 text-blue-500"></i> <?php echo $thoiGian; ?>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-barcode mr-1 text-gray-500"></i> <?php echo htmlspecialchars($maPhieu); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-user mr-1 text-purple-500"></i> <?php echo htmlspecialchars($tenNhanVien); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $trangThaiClass; ?>">
                                        <i class="fas fa-info-circle mr-1 text-green-500"></i> <?php echo $trangThai; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
    <!-- Footer -->
<?php
include 'footer.php';
?>

<script>
function updateDays() {
    const month = document.getElementById('month').value;
    const daySelect = document.getElementById('day');
    let maxDays = 31;

    if (['04', '06', '09', '11'].includes(month)) {
        maxDays = 30;
    } else if (month === '02') {
        maxDays = 28;
    }

    daySelect.innerHTML = '';
    for (let i = 1; i <= maxDays; i++) {
        const dayValue = String(i).padStart(2, '0');
        const option = document.createElement('option');
        option.value = dayValue;
        option.text = i;
        daySelect.appendChild(option);
    }
}
</script>
</body>
</html>