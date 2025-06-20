<?php
// xuatkho.php
include 'db_config.php'; // File cấu hình kết nối database
require_once 'init.php';
$maPhanQuyen = $_SESSION['maPhanQuyen'] ?? '';
// Xử lý yêu cầu AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'getData') {
    try {
       $recordsPerPage = 10;
        $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
        $offset = ($page - 1) * $recordsPerPage;

        $maXuatHang = isset($_POST['maXuatHang']) ? "%" . $_POST['maXuatHang'] . "%" : "%";
        $tenKhachHang = isset($_POST['tenKhachHang']) ? "%" . $_POST['tenKhachHang'] . "%" : "%";
        $trangThaiFilter = isset($_POST['trangThai']) && in_array($_POST['trangThai'], ['chua_xuat', 'dang_xuat', 'hoan_tat']) ? $_POST['trangThai'] : 'chua_xuat';

        // Đếm tổng số bản ghi
        $sqlCount = "SELECT COUNT(DISTINCT xh.MaXuatHang) as total 
                    FROM TP_XuatHang xh
                    LEFT JOIN TP_ChiTietXuatHang ct ON xh.MaXuatHang = ct.MaXuatHang
                    LEFT JOIN TP_KhachHang kh ON xh.MaKhachHang = kh.MaKhachHang
                    WHERE xh.MaXuatHang LIKE :maXuatHang
                    AND kh.TenKhachHang LIKE :tenKhachHang";
        if ($trangThaiFilter === 'chua_xuat') {
            $sqlCount .= " AND xh.TrangThai = 0 AND NOT EXISTS (
                            SELECT 1 
                            FROM TP_ChiTietXuatHang ct2 
                            WHERE ct2.MaXuatHang = xh.MaXuatHang 
                            AND ct2.TrangThai = 1
                          )";
        } elseif ($trangThaiFilter === 'dang_xuat') {
            $sqlCount .= " AND xh.TrangThai = 0 AND EXISTS (
                            SELECT 1 
                            FROM TP_ChiTietXuatHang ct2 
                            WHERE ct2.MaXuatHang = xh.MaXuatHang 
                            AND ct2.TrangThai = 1
                          )
                          AND EXISTS (
                            SELECT 1 
                            FROM TP_ChiTietXuatHang ct3 
                            WHERE ct3.MaXuatHang = xh.MaXuatHang 
                            AND ct3.TrangThai = 0
                          )";
        } elseif ($trangThaiFilter === 'hoan_tat') {
            $sqlCount .= " AND xh.TrangThai = 1";
        }
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->bindValue(':maXuatHang', $maXuatHang, PDO::PARAM_STR);
        $stmtCount->bindValue(':tenKhachHang', $tenKhachHang, PDO::PARAM_STR);
        $stmtCount->execute();
        $totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalRecords / $recordsPerPage);

       // Lấy dữ liệu phiếu xuất
        $sql = "SELECT xh.MaXuatHang, nv.TenNhanVien, kh.TenKhachHang, xh.NgayXuat, xh.TrangThai, xh.GhiChu,
               SUM(ct.SoLuong) as TongSoLuongXuat,
               dvt.TenDVT,
               SUM(CASE WHEN ct.TrangThai = 1 THEN 1 ELSE 0 END) as DangXuat,
               SUM(CASE WHEN ct.TrangThai = 0 THEN 1 ELSE 0 END) as ChuaXuat
                FROM TP_XuatHang xh
                LEFT JOIN TP_ChiTietXuatHang ct ON xh.MaXuatHang = ct.MaXuatHang
                LEFT JOIN NhanVien nv ON xh.MaNhanVien = nv.MaNhanVien
                LEFT JOIN TP_KhachHang kh ON xh.MaKhachHang = kh.MaKhachHang
                LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
                WHERE xh.MaXuatHang LIKE :maXuatHang
                AND kh.TenKhachHang LIKE :tenKhachHang";
        if ($trangThaiFilter === 'chua_xuat') {
            $sql .= " AND xh.TrangThai = 0 AND NOT EXISTS (
                        SELECT 1 
                        FROM TP_ChiTietXuatHang ct2 
                        WHERE ct2.MaXuatHang = xh.MaXuatHang 
                        AND ct2.TrangThai = 1
                    )";
        } elseif ($trangThaiFilter === 'dang_xuat') {
            $sql .= " AND xh.TrangThai = 0 AND EXISTS (
                        SELECT 1 
                        FROM TP_ChiTietXuatHang ct2 
                        WHERE ct2.MaXuatHang = xh.MaXuatHang 
                        AND ct2.TrangThai = 1
                    )
                    AND EXISTS (
                        SELECT 1 
                        FROM TP_ChiTietXuatHang ct3 
                        WHERE ct3.MaXuatHang = xh.MaXuatHang 
                        AND ct3.TrangThai = 0
                    )";
        } elseif ($trangThaiFilter === 'hoan_tat') {
            $sql .= " AND xh.TrangThai = 1";
        }
        $sql .= " GROUP BY xh.MaXuatHang, nv.TenNhanVien, kh.TenKhachHang, xh.NgayXuat, xh.TrangThai, xh.GhiChu, dvt.TenDVT
                ORDER BY xh.NgayXuat DESC
                OFFSET :offset ROWS 
                FETCH NEXT :limit ROWS ONLY";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':maXuatHang', $maXuatHang, PDO::PARAM_STR);
        $stmt->bindValue(':tenKhachHang', $tenKhachHang, PDO::PARAM_STR);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $xuatHang = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Trả về JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $xuatHang,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'offset' => $offset
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

include 'header.php';
$recordsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;
$trangThaiFilter = isset($_GET['trangThai']) && in_array($_GET['trangThai'], ['chua_xuat', 'dang_xuat', 'hoan_tat']) ? $_GET['trangThai'] : 'chua_xuat';
$tenKhachHang = isset($_GET['tenKhachHang']) ? "%" . $_GET['tenKhachHang'] . "%" : "%";
$maXuatHang = isset($_GET['maXuatHang']) ? "%" . $_GET['maXuatHang'] . "%" : "%";
// Lấy dữ liệu ban đầu
// Đếm tổng số bản ghi
$sqlCount = "SELECT COUNT(DISTINCT xh.MaXuatHang) as total 
             FROM TP_XuatHang xh
             LEFT JOIN TP_ChiTietXuatHang ct ON xh.MaXuatHang = ct.MaXuatHang
             LEFT JOIN TP_KhachHang kh ON xh.MaKhachHang = kh.MaKhachHang
             WHERE xh.MaXuatHang LIKE :maXuatHang
             AND kh.TenKhachHang LIKE :tenKhachHang";
if ($trangThaiFilter === 'chua_xuat') {
    $sqlCount .= " AND xh.TrangThai = 0 AND NOT EXISTS (
                    SELECT 1 
                    FROM TP_ChiTietXuatHang ct2 
                    WHERE ct2.MaXuatHang = xh.MaXuatHang 
                    AND ct2.TrangThai = 1
                  )";
} elseif ($trangThaiFilter === 'dang_xuat') {
    $sqlCount .= " AND xh.TrangThai = 0 AND EXISTS (
                    SELECT 1 
                    FROM TP_ChiTietXuatHang ct2 
                    WHERE ct2.MaXuatHang = xh.MaXuatHang 
                    AND ct2.TrangThai = 1
                  )
                  AND EXISTS (
                    SELECT 1 
                    FROM TP_ChiTietXuatHang ct3 
                    WHERE ct3.MaXuatHang = xh.MaXuatHang 
                    AND ct3.TrangThai = 0
                  )";
} elseif ($trangThaiFilter === 'hoan_tat') {
    $sqlCount .= " AND xh.TrangThai = 1";
}
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->bindValue(':maXuatHang', $maXuatHang, PDO::PARAM_STR);
$stmtCount->bindValue(':tenKhachHang', $tenKhachHang, PDO::PARAM_STR);
$stmtCount->execute();
$totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

$sql = "SELECT xh.MaXuatHang, nv.TenNhanVien, kh.TenKhachHang, xh.NgayXuat, xh.TrangThai, xh.GhiChu,
               SUM(ct.SoLuong) as TongSoLuongXuat,
               dvt.TenDVT,
               SUM(CASE WHEN ct.TrangThai = 1 THEN 1 ELSE 0 END) as DangXuat,
               SUM(CASE WHEN ct.TrangThai = 0 THEN 1 ELSE 0 END) as ChuaXuat
        FROM TP_XuatHang xh
        LEFT JOIN TP_ChiTietXuatHang ct ON xh.MaXuatHang = ct.MaXuatHang
        LEFT JOIN NhanVien nv ON xh.MaNhanVien = nv.MaNhanVien
        LEFT JOIN TP_KhachHang kh ON xh.MaKhachHang = kh.MaKhachHang
        LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
        WHERE xh.MaXuatHang LIKE :maXuatHang
        AND kh.TenKhachHang LIKE :tenKhachHang";
if ($trangThaiFilter === 'chua_xuat') {
    $sql .= " AND xh.TrangThai = 0 AND NOT EXISTS (
                SELECT 1 
                FROM TP_ChiTietXuatHang ct2 
                WHERE ct2.MaXuatHang = xh.MaXuatHang 
                AND ct2.TrangThai = 1
              )";
} elseif ($trangThaiFilter === 'dang_xuat') {
    $sql .= " AND xh.TrangThai = 0 AND EXISTS (
                SELECT 1 
                FROM TP_ChiTietXuatHang ct2 
                WHERE ct2.MaXuatHang = xh.MaXuatHang 
                AND ct2.TrangThai = 1
              )
              AND EXISTS (
                SELECT 1 
                FROM TP_ChiTietXuatHang ct3 
                WHERE ct3.MaXuatHang = xh.MaXuatHang 
                AND ct3.TrangThai = 0
              )";
} elseif ($trangThaiFilter === 'hoan_tat') {
    $sql .= " AND xh.TrangThai = 1";
}
$sql .= " GROUP BY xh.MaXuatHang, nv.TenNhanVien, kh.TenKhachHang, xh.NgayXuat, xh.TrangThai, xh.GhiChu, dvt.TenDVT
          ORDER BY xh.NgayXuat DESC
          OFFSET :offset ROWS 
          FETCH NEXT :limit ROWS ONLY";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':maXuatHang', $maXuatHang, PDO::PARAM_STR);
$stmt->bindValue(':tenKhachHang', $tenKhachHang, PDO::PARAM_STR);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();
$xuatHang = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xuất Kho MAJ5</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Chỉ giữ CSS cần thiết cho sticky header */
        .table-container {
            max-height: 400px; /* Chiều cao tối đa */
            overflow-y: auto; /* Cuộn dọc */
            overflow-x: auto; /* Cuộn ngang */
            position: relative; /* Ngữ cảnh cho sticky */
            width: 100%; /* Chiếm toàn bộ chiều rộng */
            display: block; /* Đảm bảo overflow áp dụng đúng */
        }
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px; /* Đảm bảo bảng có chiều rộng tối thiểu */
        }
        .table-container thead th {
            position: sticky; /* Giữ header cố định khi cuộn dọc */
            top: 0; /* Đặt ở đỉnh */
            background: #fef2f2; /* Màu nền của bg-red-50 */
            z-index: 1; /* Đảm bảo header nằm trên nội dung */
        }
        th {
            white-space: nowrap;
            min-width: 0;
        }
        td {
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <!-- Hero Section -->
    

    <!-- Main Content Section -->
    <section class="bg-gray-50">
        <div class="container mx-auto mt-3">
            <div class="bg-white shadow-xl border-l-4 border-red-600 p-1">
                <h2 class="text-[20px] font-bold text-gray-800 mb-3 flex items-center ">
                    <i class="fas fa-list-ul mr-3 text-red-600"></i>
                    DANH SÁCH ĐƠN XUẤT HÀNG
                </h2>

                <!-- Thanh tìm kiếm và bộ lọc trạng thái -->
                <div class="flex text-sm flex-col md:flex-row gap-4 mb-4">
                    <div class="relative w-full md:w-1/3 group">
                        <input type="text" id="searchMaXuatHang" placeholder="Tìm theo Mã Phiếu Xuất" class="p-1 border border-gray-300 rounded-lg w-full pl-12 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-300 shadow-sm">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 group-focus-within:text-red-500 transition-colors duration-300"></i>
                    </div>
                    <div class="relative w-full md:w-1/3 group">
                        <input type="text" id="searchTenKhachHang" placeholder="Tìm theo Tên Khách Hàng" class="p-1 border border-gray-300 rounded-lg w-full pl-12 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-300 shadow-sm">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 group-focus-within:text-red-500 transition-colors duration-300"></i>
                    </div>
                    <div class="w-full md:w-1/3">
                        <select id="filterTrangThai" class="p-1 border border-gray-300 rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-300 shadow-sm">
                            <option value="chua_xuat" <?php echo $trangThaiFilter === 'chua_xuat' ? 'selected' : ''; ?>>Đơn Hàng Chưa Xuất</option>
                            <option value="dang_xuat" <?php echo $trangThaiFilter === 'dang_xuat' ? 'selected' : ''; ?>>Đơn Hàng Đang Xuất</option>
                            <option value="hoan_tat" <?php echo $trangThaiFilter === 'hoan_tat' ? 'selected' : ''; ?>>Đơn Hàng Đã Hoàn Tất</option>
                        </select>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex gap-4 mb-4">
                    <a id="btnXemChiTiet" href="#" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-medium rounded-full shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300">
                        <i class="fas fa-eye mr-2"></i> Xem Chi Tiết
                    </a>
                    <a id="btnXuatHang" href="#" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-medium rounded-full shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-300">
                        <i class="fas fa-arrow-circle-up mr-2"></i> Xuất Hàng
                    </a>
                </div>

                <!-- Danh sách phiếu xuất -->
                <div class="table-container">
                    <table class="min-w-full divide-y divide-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-red-50">
                            <tr>
                                <th class="px-4 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Chọn</th>
                                <th class="px-4 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">STT</th>
                                <th class="px-4 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Mã Phiếu Xuất</th>
                                <th class="px-4 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Tên Khách Hàng</th>
                                <th class="px-4 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Tên Nhân Viên</th>
                                <th class="px-4 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Ngày Tạo</th>
                                <th class="px-4 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Trạng Thái</th>
                                <th class="px-4 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Tổng Số Lượng Xuất</th>
                                <th class="px-4 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody id="xuatHangTable" class="bg-white divide-y divide-gray-100">
                            <?php 
                            $stt = $offset + 1;
                            foreach ($xuatHang as $xh): 
                                $trangThaiHienThi = ($xh['DangXuat'] > 0) ? 'Đang xuất' : 'Chưa xuất';
                                $trangThaiClass = ($xh['DangXuat'] > 0) ? 'text-amber-600' : 'text-red-600';
                            ?>
                                <tr class="hover:bg-red-50 transition-colors duration-200">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <input type="checkbox" class="row-checkbox" style="width: 20px; height: 20px;" value="<?php echo htmlspecialchars($xh['MaXuatHang']); ?>">
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-700"><?php echo $stt++; ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-700"><?php echo htmlspecialchars($xh['MaXuatHang']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-700"><?php echo htmlspecialchars($xh['TenKhachHang']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-700"><?php echo htmlspecialchars($xh['TenNhanVien']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-700"><?php echo htmlspecialchars($xh['NgayXuat']); ?></td>
                                    <td class="px-4 py-4 text-xs <?php echo $trangThaiClass; ?> whitespace-nowrap"><?php echo htmlspecialchars($trangThaiHienThi); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-700"><?php echo number_format($xh['TongSoLuongXuat'], 0, ',', '.') . ' ' . htmlspecialchars($xh['TenDVT']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-700"><?php echo htmlspecialchars($xh['GhiChu']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <div id="pagination" class="mt-6 flex justify-center items-center space-x-2">
                    <?php if ($page > 1): ?>
                        <button onclick="loadPage(<?php echo $page - 1; ?>)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-all duration-300">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <button onclick="loadPage(<?php echo $i; ?>)" class="px-4 py-2 <?php echo $i === $page ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-full hover:bg-red-500 hover:text-white transition-all duration-300">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <button onclick="loadPage(<?php echo $page + 1; ?>)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-all duration-300">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Loading Indicator -->
    <div id="loading" class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-black bg-opacity-70 text-white p-5 rounded-lg hidden">
        <i class="fas fa-spinner fa-spin"></i> Đang tải...
    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <script>
       let currentPage = <?php echo $page; ?>;

// Hàm debounce để tránh gọi API quá nhanh
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}


// Ẩn/hiện nút Xuất Hàng dựa trên bộ lọc trạng thái và mã phân quyền
function toggleButtonsBasedOnFilter() {
    const trangThai = document.getElementById('filterTrangThai').value;
    const btnXuatHang = document.getElementById('btnXuatHang');
    const maPhanQuyen = <?php echo json_encode($maPhanQuyen); ?>;
    
    if (maPhanQuyen == 4 || trangThai === 'hoan_tat') {
        if (btnXuatHang) {
            btnXuatHang.style.display = 'none';
        }
    } else {
        if (btnXuatHang) {
            btnXuatHang.style.display = 'inline-flex';
        }
    }
}
// Hàm để bỏ chọn tất cả checkbox
function uncheckAllCheckboxes() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('btnXemChiTiet').href = '#';
    document.getElementById('btnXuatHang').href = '#';
}

// Xử lý khi trang được hiển thị (bao gồm cả khi quay lại)
window.addEventListener('pageshow', function(event) {
    uncheckAllCheckboxes();
    attachCheckboxEvents();
});

// Gọi API để tải dữ liệu
function loadPage(page) {
   const maXuatHang = document.getElementById('searchMaXuatHang').value;
    const tenKhachHang = document.getElementById('searchTenKhachHang').value;
    const trangThai = document.getElementById('filterTrangThai').value;
    const loading = document.getElementById('loading');

    loading.style.display = 'block';

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=getData&page=${page}&maXuatHang=${encodeURIComponent(maXuatHang)}&tenKhachHang=${encodeURIComponent(tenKhachHang)}&trangThai=${trangThai}`
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (!data.success) throw new Error(data.error || 'Unknown error');
        updateTable(data.data, data.offset);
        updatePagination(data.totalPages, data.currentPage);
        currentPage = data.currentPage;
        toggleButtonsBasedOnFilter();
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Lỗi!',
            text: 'Có lỗi xảy ra: ' + error.message,
        });
    })
    .finally(() => {
        loading.style.display = 'none';
    });
}

// Cập nhật bảng dữ liệu
function updateTable(data, offset) {
    const tbody = document.getElementById('xuatHangTable');
    tbody.innerHTML = '';
    let stt = offset + 1;
    data.forEach(xh => {
        const trangThaiHienThi = xh.TrangThai == 1 ? 'Hoàn tất' : (xh.DangXuat > 0 ? 'Đang xuất' : 'Chưa xuất');
        const trangThaiClass = xh.TrangThai == 1 ? 'text-green-600' : (xh.DangXuat > 0 ? 'text-amber-600' : 'text-red-600');
        const row = document.createElement('tr');
        row.className = 'hover:bg-red-50 transition-colors duration-200';
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                <input type="checkbox" class="row-checkbox" style="width: 20px; height: 20px;" value="${xh.MaXuatHang}">
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${stt++}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${xh.MaXuatHang}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${xh.TenKhachHang}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${xh.TenNhanVien}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${new Date(xh.NgayXuat).toLocaleDateString('vi-VN')}</td>
            <td class="px-6 py-4 text-sm ${trangThaiClass} whitespace-nowrap">${trangThaiHienThi}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${Number(xh.TongSoLuongXuat).toLocaleString('vi-VN')} ${xh.TenDVT}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${xh.GhiChu}</td>
        `;
        tbody.appendChild(row);
    });
    attachCheckboxEvents();
}

// Cập nhật phân trang
function updatePagination(totalPages, currentPage) {
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';

    if (currentPage > 1) {
        const prevButton = document.createElement('button');
        prevButton.className = 'px-4 py-2 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-all duration-300';
        prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevButton.onclick = () => loadPage(currentPage - 1);
        pagination.appendChild(prevButton);
    }

    for (let i = 1; i <= totalPages; i++) {
        const pageButton = document.createElement('button');
        pageButton.className = `px-4 py-2 ${i === currentPage ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700'} rounded-full hover:bg-red-500 hover:text-white transition-all duration-300`;
        pageButton.textContent = i;
        pageButton.onclick = () => loadPage(i);
        pagination.appendChild(pageButton);
    }

    if (currentPage < totalPages) {
        const nextButton = document.createElement('button');
        nextButton.className = 'px-4 py-2 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-all duration-300';
        nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextButton.onclick = () => loadPage(currentPage + 1);
        pagination.appendChild(nextButton);
    }
}

// Gắn sự kiện cho checkbox
function attachCheckboxEvents() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const btnXemChiTiet = document.getElementById('btnXemChiTiet');
    const btnXuatHang = document.getElementById('btnXuatHang');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                checkboxes.forEach(cb => {
                    if (cb !== this) cb.checked = false;
                });
                btnXemChiTiet.href = `TP_XuatKho/XemChiTietXuatHang.php?maXuatHang=${this.value}`;
                btnXuatHang.href = `TP_XuatKho/QuetQRXuatHang.php?maXuatHang=${this.value}`;
            } else {
                btnXemChiTiet.href = '#';
                btnXuatHang.href = '#';
            }
        });
    });
}

// Sự kiện cho nút Xem Chi Tiết
document.getElementById('btnXemChiTiet').addEventListener('click', function(e) {
    e.preventDefault();
    const selected = document.querySelectorAll('.row-checkbox:checked');
    if (selected.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Chưa chọn dòng nào',
            text: 'Vui lòng chọn 1 dòng để xem chi tiết!',
        });
        return;
    } else if (selected.length > 1) {
        Swal.fire({
            icon: 'error',
            title: 'Chỉ được chọn 1 dòng',
            text: 'Vui lòng chỉ chọn 1 dòng để xem chi tiết!',
        });
        return;
    }
    // Bỏ chọn tất cả checkbox trước khi chuyển hướng
    uncheckAllCheckboxes();
    // Chuyển hướng
    window.location.href = `TP_XuatKho/XemChiTietXuatHang.php?maXuatHang=${selected[0].value}`;
});

// Sự kiện cho nút Xuất Hàng
document.getElementById('btnXuatHang').addEventListener('click', function(e) {
    e.preventDefault();
    const selected = document.querySelectorAll('.row-checkbox:checked');
    if (selected.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Chưa chọn dòng nào',
            text: 'Vui lòng chọn 1 dòng để xuất hàng!',
        });
        return;
    } else if (selected.length > 1) {
        Swal.fire({
            icon: 'error',
            title: 'Chỉ được chọn 1 dòng',
            text: 'Vui lòng chỉ chọn 1 dòng để xuất hàng!',
        });
        return;
    }
    // Bỏ chọn tất cả checkbox trước khi chuyển hướng
    uncheckAllCheckboxes();
    // Chuyển hướng
    window.location.href = `TP_XuatKho/QuetQRXuatHang.php?maXuatHang=${selected[0].value}`;
});

// Gắn sự kiện tìm kiếm và lọc trạng thái
document.getElementById('searchMaXuatHang').addEventListener('input', debounce(() => loadPage(1), 300));
document.getElementById('searchTenKhachHang').addEventListener('input', debounce(() => loadPage(1), 300));
document.getElementById('filterTrangThai').addEventListener('change', () => {
    loadPage(1);
    toggleButtonsBasedOnFilter();
});

// Gắn sự kiện ban đầu cho checkbox và cập nhật trạng thái nút
attachCheckboxEvents();
toggleButtonsBasedOnFilter();
    </script>
</body>
</html>