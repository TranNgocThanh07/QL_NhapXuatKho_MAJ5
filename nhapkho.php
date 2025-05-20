<?php
//Nhập Kho
include 'db_config.php'; // Chỉ chứa kết nối DB, không echo gì

// Xử lý yêu cầu AJAX trước mọi thứ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'getData') {
    try {
        $recordsPerPage = 10;
        $page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
        $offset = ($page - 1) * $recordsPerPage;

        $maSoMe = isset($_POST['maSoMe']) ? "%" . $_POST['maSoMe'] . "%" : "%";
        $tenKhachHang = isset($_POST['tenKhachHang']) ? "%" . $_POST['tenKhachHang'] . "%" : "%";
        $trangThai = isset($_POST['trangThai']) && in_array($_POST['trangThai'], ['0', '2']) ? (int)$_POST['trangThai'] : 0;

        // Điều kiện lọc
        $whereClause = $trangThai == 0 ? "TP_DonSanXuat.TrangThai = :trangThai AND TP_DonSanXuat.LoaiDon IN (0, 2)" : 
                                       "(TP_DonSanXuat.TrangThai = :trangThai OR TP_DonSanXuat.LoaiDon = 3)";

        // Đếm tổng số bản ghi
        $sqlCount = "SELECT COUNT(*) as total 
                     FROM TP_DonSanXuat 
                     LEFT JOIN TP_KhachHang ON TP_DonSanXuat.MaKhachHang = TP_KhachHang.MaKhachHang 
                     WHERE $whereClause 
                     AND TP_DonSanXuat.MaSoMe LIKE :maSoMe 
                     AND TP_KhachHang.TenKhachHang LIKE :tenKhachHang";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->bindValue(':trangThai', $trangThai, PDO::PARAM_INT);
        $stmtCount->bindValue(':maSoMe', $maSoMe, PDO::PARAM_STR);
        $stmtCount->bindValue(':tenKhachHang', $tenKhachHang, PDO::PARAM_STR);
        $stmtCount->execute();
        $totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalRecords / $recordsPerPage);

        // Lấy dữ liệu cho trang hiện tại
        $sql = "SELECT TP_DonSanXuat.MaSoMe, TP_DonSanXuat.MaKhachHang, TP_DonSanXuat.MaDonHang, 
                       TP_DonSanXuat.TenVai, TP_DonSanXuat.TongSoLuongGiao, TP_DonSanXuat.MaDVT, 
                       TP_DonSanXuat.TrangThai, TP_DonSanXuat.NgayNhan, TP_DonSanXuat.LoaiDon,
                       TP_KhachHang.TenKhachHang, TP_DonViTinh.TenDVT 
                FROM TP_DonSanXuat 
                LEFT JOIN TP_KhachHang ON TP_DonSanXuat.MaKhachHang = TP_KhachHang.MaKhachHang 
                LEFT JOIN TP_DonViTinh ON TP_DonSanXuat.MaDVT = TP_DonViTinh.MaDVT 
                WHERE $whereClause 
                AND TP_DonSanXuat.MaSoMe LIKE :maSoMe 
                AND TP_KhachHang.TenKhachHang LIKE :tenKhachHang 
                ORDER BY TP_DonSanXuat.NgayNhan DESC 
                OFFSET :offset ROWS 
                FETCH NEXT :limit ROWS ONLY";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':trangThai', $trangThai, PDO::PARAM_INT);
        $stmt->bindValue(':maSoMe', $maSoMe, PDO::PARAM_STR);
        $stmt->bindValue(':tenKhachHang', $tenKhachHang, PDO::PARAM_STR);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $donSanXuat = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Kiểm tra LoaiDon = 3
        $hasLoaiDon3 = false;
        foreach ($donSanXuat as $don) {
            if ($don['LoaiDon'] == 3) {
                $hasLoaiDon3 = true;
                break;
            }
        }

        // Trả về JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $donSanXuat,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'offset' => $offset,
            'hasLoaiDon3' => $hasLoaiDon3
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

include 'header.php';

// Lấy dữ liệu ban đầu cho lần load đầu tiên
$recordsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$trangThai = isset($_GET['trangThai']) && in_array($_GET['trangThai'], ['0', '2']) ? (int)$_GET['trangThai'] : 0;
$offset = ($page - 1) * $recordsPerPage;

// Điều kiện lọc cho Nhập Hàng và Nhập Hàng Tồn
$whereClause = $trangThai == 0 ? "TP_DonSanXuat.TrangThai = :trangThai AND TP_DonSanXuat.LoaiDon IN (0, 2)" : 
                                 "(TP_DonSanXuat.TrangThai = :trangThai OR TP_DonSanXuat.LoaiDon = 3)";

// Đếm tổng số bản ghi
$sqlCount = "SELECT COUNT(*) as total 
             FROM TP_DonSanXuat 
             WHERE $whereClause";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->bindValue(':trangThai', $trangThai, PDO::PARAM_INT);
$stmtCount->execute();
$totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Lấy dữ liệu cho trang hiện tại
$sql = "SELECT TP_DonSanXuat.MaSoMe, TP_DonSanXuat.MaKhachHang, TP_DonSanXuat.MaDonHang, 
               TP_DonSanXuat.TenVai, TP_DonSanXuat.TongSoLuongGiao, TP_DonSanXuat.MaDVT, 
               TP_DonSanXuat.TrangThai, TP_DonSanXuat.NgayNhan, TP_DonSanXuat.LoaiDon,
               TP_KhachHang.TenKhachHang, TP_DonViTinh.TenDVT 
        FROM TP_DonSanXuat 
        LEFT JOIN TP_KhachHang ON TP_DonSanXuat.MaKhachHang = TP_KhachHang.MaKhachHang 
        LEFT JOIN TP_DonViTinh ON TP_DonSanXuat.MaDVT = TP_DonViTinh.MaDVT 
        WHERE $whereClause 
        ORDER BY TP_DonSanXuat.NgayNhan DESC 
        OFFSET :offset ROWS 
        FETCH NEXT :limit ROWS ONLY";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':trangThai', $trangThai, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();
$donSanXuat = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kiểm tra nếu có đơn với LoaiDon = 3 để điều chỉnh hiển thị nút Nhập Hàng Tồn
$hasLoaiDon3 = false;
foreach ($donSanXuat as $don) {
    if ($don['LoaiDon'] == 3) {
        $hasLoaiDon3 = true;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhập Kho MAJ5</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .hero-gradient {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            color: white;
        }
        .loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 20px;
            border-radius: 8px;
            display: none;
        }
        th, td {
            white-space: nowrap;
        }
        .table-container {
            max-height: 400px;
            overflow-y: auto;
            overflow-x: auto;
            position: relative;
            width: 100%;
            display: block;
        }
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        .table-container thead th {
            position: sticky;
            top: 0;
            background: #fef2f2;
            z-index: 5;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <!-- Hero Section -->
    <section class="hero-gradient text-white py-12 md:py-20">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="md:w-1/2 mb-8 md:mb-0">
                    <h1 class="text-4xl md:text-5xl font-bold mb-4">Nhập Kho MAJ5</h1>
                    <p class="text-lg opacity-90 mb-6">Chào mừng <strong><?php echo htmlspecialchars($tenNhanVien); ?></strong> đến với hệ thống nhập kho của MAJ5.</p>
                </div>
                <!-- <div class="md:w-1/2 flex justify-center">
                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRU2OJYIIRPZ0gr7vWKtLO-mx6p62rg1VkzpQ&s" alt="Warehouse Illustration" class="max-w-full h-auto md:max-w-md">
                </div> -->
            </div>
        </div>
    </section>

    <!-- Main Content Section -->
    <section class="bg-gray-50">
        <div class="container mx-auto">
            <div class="bg-white shadow-xl border-l-4 border-red-600 p-1">
                <h2 class="text-[22px] font-bold text-gray-800 mb-8 flex items-center ">
                    <i class="fas fa-list-ul mr-3 text-red-600"></i>
                    DANH SÁCH ĐƠN SẢN XUẤT
                </h2>

                <!-- Thanh tìm kiếm và bộ lọc trạng thái -->
                <div class="flex flex-col md:flex-row gap-4 mb-4">
                    <div class="relative w-full md:w-1/3 group">
                        <input type="text" id="searchMaSoMe" placeholder="Tìm theo Mã Số Mẻ" class="p-3 border border-gray-300 rounded-lg w-full pl-12 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-300 shadow-sm">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 group-focus-within:text-red-500 transition-colors duration-300"></i>
                    </div>
                    <div class="relative w-full md:w-1/3 group">
                        <input type="text" id="searchTenKhachHang" placeholder="Tìm theo Tên Khách Hàng" class="p-3 border border-gray-300 rounded-lg w-full pl-12 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-300 shadow-sm">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 group-focus-within:text-red-500 transition-colors duration-300"></i>
                    </div>
                    <div class="w-full md:w-1/3">
                        <select id="filterTrangThai" class="p-3 border border-gray-300 rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-300 shadow-sm">
                            <option value="0" <?php echo $trangThai == 0 ? 'selected' : ''; ?>>Đơn Sản Xuất Chưa Nhập Đủ Hàng</option>
                            <option value="2" <?php echo $trangThai == 2 ? 'selected' : ''; ?>>Đơn Sản Xuất Đã Nhập Đủ Hàng</option>
                        </select>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex gap-4 mb-4">
                    <a id="btnXemChiTiet" href="#" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300">
                        <i class="fas fa-eye mr-2"></i> Xem Chi Tiết
                    </a>
                    <a id="btnNhapHang" href="#" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-300" style="display: <?php echo $trangThai == 0 ? 'inline-flex' : 'none'; ?>;">
                        <i class="fas fa-arrow-circle-down mr-2"></i> Nhập Hàng
                    </a>
                    <a id="btnNhapHangTon" href="#" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-all duration-300" style="display: <?php echo ($trangThai == 2 || $hasLoaiDon3) ? 'inline-flex' : 'none'; ?>;">
                        <i class="fas fa-box-open mr-2"></i> Nhập Hàng Tồn
                    </a>
                </div>

                <!-- Danh sách đơn sản xuất -->
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200 rounded-lg overflow-hidden">
                            <thead class="bg-red-50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Chọn</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">STT</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Mã Số Mẻ</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Mã Đơn Hàng</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Tên Khách Hàng</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Tên Vải</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Số Lượng Giao</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Ngày Nhận</th>
                                </tr>
                            </thead>
                            <tbody id="donSanXuatTable" class="bg-white divide-y divide-gray-100">
                                <?php 
                                $stt = $offset + 1;
                                foreach ($donSanXuat as $don): 
                                ?>
                                    <tr class="hover:bg-red-50 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <input type="checkbox" class="row-checkbox" style="width: 20px; height: 20px; "value="<?php echo htmlspecialchars($don['MaSoMe']); ?>">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo $stt++; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($don['MaSoMe']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($don['MaDonHang']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($don['TenKhachHang']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($don['TenVai']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($don['TongSoLuongGiao']); ?> <?php echo htmlspecialchars($don['TenDVT']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo date('d/m/Y', strtotime($don['NgayNhan'])); ?></td>
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
    <div id="loading" class="loading">
        <i class="fas fa-spinner fa-spin"></i> Đang tải...
    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <script>
       let currentPage = <?php echo $page; ?>;

// Debounce để tránh gọi API quá nhiều khi nhập nhanh
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Gọi API để load dữ liệu
function loadPage(page) {
    const maSoMe = document.getElementById('searchMaSoMe').value;
    const tenKhachHang = document.getElementById('searchTenKhachHang').value;
    const trangThai = document.getElementById('filterTrangThai').value;
    const loading = document.getElementById('loading');

    loading.style.display = 'block';

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=getData&page=${page}&maSoMe=${encodeURIComponent(maSoMe)}&tenKhachHang=${encodeURIComponent(tenKhachHang)}&trangThai=${trangThai}`
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (!data.success) throw new Error(data.error || 'Unknown error');
        updateTable(data.data, data.offset);
        updatePagination(data.totalPages, data.currentPage);
        updateButtonVisibility(trangThai);
        currentPage = data.currentPage;
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

// Cập nhật bảng với offset từ server
function updateTable(data, offset) {
    const tbody = document.getElementById('donSanXuatTable');
    tbody.innerHTML = '';
    let stt = offset + 1;
    data.forEach(don => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-red-50 transition-colors duration-200';
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                <input type="checkbox" class="row-checkbox" style="width: 20px; height: 20px;" value="${don.MaSoMe}">
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${stt++}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${don.MaSoMe || ''}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${don.MaDonHang || ''}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${don.TenKhachHang || ''}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${don.TenVai || ''}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${don.TongSoLuongGiao || ''} ${don.TenDVT || ''}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${don.NgayNhan ? new Date(don.NgayNhan).toLocaleDateString('vi-VN') : ''}</td>
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

// Cập nhật hiển thị các nút dựa trên trạng thái
function updateButtonVisibility(trangThai) {
    const btnNhapHang = document.getElementById('btnNhapHang');
    const btnNhapHangTon = document.getElementById('btnNhapHangTon');
    if (trangThai == 0) {
        btnNhapHang.style.display = 'inline-flex';
        btnNhapHangTon.style.display = 'none';
    } else {
        btnNhapHang.style.display = 'none';
        btnNhapHangTon.style.display = 'inline-flex';
    }
}

// Gắn sự kiện cho checkbox
function attachCheckboxEvents() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const btnXemChiTiet = document.getElementById('btnXemChiTiet');
    const btnNhapHang = document.getElementById('btnNhapHang');
    const btnNhapHangTon = document.getElementById('btnNhapHangTon');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                checkboxes.forEach(cb => {
                    if (cb !== this) cb.checked = false;
                });
                btnXemChiTiet.href = `TP_NhapKho/XemChiTietDonSanXuat.php?maSoMe=${this.value}`;
                btnNhapHang.href = `TP_NhapKho/FormNhapKho.php?maSoMe=${this.value}`;
                btnNhapHangTon.href = `TP_NhapKho/FormNhapTonKho.php?maSoMe=${this.value}`;
            } else {
                btnXemChiTiet.href = '#';
                btnNhapHang.href = '#';
                btnNhapHangTon.href = '#';
            }
        });
    });
}

// Gắn sự kiện tìm kiếm, lọc trạng thái và nút
document.getElementById('searchMaSoMe').addEventListener('input', debounce(() => loadPage(1), 300));
document.getElementById('searchTenKhachHang').addEventListener('input', debounce(() => loadPage(1), 300));
document.getElementById('filterTrangThai').addEventListener('change', () => loadPage(1));
attachCheckboxEvents();

// Sự kiện cho nút Xem Chi Tiết
document.getElementById('btnXemChiTiet').addEventListener('click', function(e) {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    if (selected.length === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Chưa chọn dòng nào',
            text: 'Vui lòng chọn 1 dòng để xem chi tiết!',
        });
    } else if (selected.length > 1) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Chỉ được chọn 1 dòng',
            text: 'Vui lòng chỉ chọn 1 dòng để xem chi tiết!',
        });
    }
});

// Sự kiện cho nút Nhập Hàng
document.getElementById('btnNhapHang').addEventListener('click', function(e) {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    if (selected.length === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Chưa chọn dòng nào',
            text: 'Vui lòng chọn 1 dòng để nhập hàng!',
        });
    } else if (selected.length > 1) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Chỉ được chọn 1 dòng',
            text: 'Vui lòng chỉ chọn 1 dòng để nhập hàng!',
        });
    }
});

// Sự kiện cho nút Nhập Hàng Tồn
document.getElementById('btnNhapHangTon').addEventListener('click', function(e) {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    if (selected.length === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Chưa chọn dòng nào',
            text: 'Vui lòng chọn 1 dòng để nhập hàng tồn!',
        });
    } else if (selected.length > 1) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Chỉ được chọn 1 dòng',
            text: 'Vui lòng chỉ chọn 1 dòng để nhập hàng tồn!',
        });
    }
});
    </script>
</body>
</html>