<?php
require_once __DIR__ . '/../vendor/autoload.php';
include '../db_config.php';

// Kiểm tra kết nối PDO
if (!isset($pdo)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Lỗi kết nối cơ sở dữ liệu']);
    exit;
}

// Cấu hình các trường tìm kiếm
$searchFieldsConfig = [
    'MaSoMe' => ['table' => 'ct', 'type' => 'string', 'label' => 'Mã Số Mẻ', 'search_type' => 'prefix'],
    'MaDonHang' => ['table' => 'ct', 'type' => 'string', 'label' => 'Mã Đơn Hàng', 'search_type' => 'prefix'],
    'MaVatTu' => ['table' => 'ct', 'type' => 'string', 'label' => 'Mã Vật Tư', 'search_type' => 'prefix'],
    'TenVai' => ['table' => 'ct', 'type' => 'string', 'label' => 'Tên Vải', 'search_type' => 'full'],
    'TenMau' => ['table' => 'm', 'type' => 'string', 'label' => 'Tên Màu', 'search_type' => 'full'],
    'TenDVT' => ['table' => 'dvt', 'type' => 'string', 'label' => 'Đơn Vị Tính', 'search_type' => 'full'],
    'Kho' => ['table' => 'ct', 'type' => 'string', 'label' => 'Khổ', 'search_type' => 'full'],
    'SoLuong' => ['table' => 'ct', 'type' => 'float', 'label' => 'Số Lượng', 'search_type' => 'exact'],
    'SoLot' => ['table' => 'ct', 'type' => 'string', 'label' => 'Số Lot', 'search_type' => 'prefix'],
    'TenKhachHang' => ['table' => 'kh', 'type' => 'string', 'label' => 'Khách Hàng', 'search_type' => 'full'],
    'TenNhanVien' => ['table' => 'nv', 'type' => 'string', 'label' => 'Nhân Viên', 'search_type' => 'full'],
    'TenThanhPhan' => ['table' => 'ct', 'type' => 'string', 'label' => 'Thành Phần', 'search_type' => 'full'],
    'SoKgCan' => ['table' => 'ct', 'type' => 'float', 'label' => 'Số Kg Cân', 'search_type' => 'exact'],
    'GhiChu' => ['table' => 'ct', 'type' => 'string', 'label' => 'Ghi Chú', 'search_type' => 'full'],
    'MaKhuVuc' => ['table' => 'ct', 'type' => 'string', 'label' => 'Khu Vực', 'search_type' => 'prefix'],
];

// Hàm xây dựng điều kiện tìm kiếm
function buildSearchConditions($searchFieldsConfig, $postData) {
    $conditions = [];
    $params = [];
    foreach ($searchFieldsConfig as $field => $config) {
        if (!empty($postData[$field])) {
            $value = trim($postData[$field]);
            if ($config['type'] === 'float') {
                if (is_numeric($value) && $value >= 0) {
                    $conditions[] = "{$config['table']}.$field = ?";
                    $params[] = (float)$value;
                }
            } elseif ($config['type'] === 'string') {
                $likePattern = $config['search_type'] === 'prefix' ? "$value%" : "%$value%";
                $conditions[] = "{$config['table']}.$field LIKE ?";
                $params[] = $likePattern;
            }
        }
    }
    return [$conditions, $params];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
        header('Content-Type: application/json');

        $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        // Truy vấn tổng số bản ghi
        $sqlCount = "SELECT COUNT(1) as total 
                     FROM TP_ChiTietDonSanXuat ct
                     LEFT JOIN TP_NguoiLienHe nlh ON ct.MaNguoiLienHe = nlh.MaNguoiLienHe
                     LEFT JOIN TP_KhachHang kh ON ct.MaKhachHang = kh.MaKhachHang
                     LEFT JOIN NhanVien nv ON ct.MaNhanVien = nv.MaNhanVien
                     LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
                     LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
                     WHERE 1=1";

        [$conditions, $params] = buildSearchConditions($searchFieldsConfig, $_POST);
        if (!empty($conditions)) {
            $sqlCount .= " AND " . implode(" AND ", $conditions);
        }

        $stmtCount = $pdo->prepare($sqlCount);
        foreach ($params as $index => $param) {
            $stmtCount->bindValue($index + 1, $param, is_float($param) ? PDO::PARAM_STR : PDO::PARAM_STR);
        }
        $stmtCount->execute();
        $totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalRecords / $limit);

        // Truy vấn chi tiết
        $sql = "SELECT ct.MaSoMe, ct.MaDonHang, ct.MaVatTu, ct.TenVai, ct.SoLuong, 
                       m.TenMau, dvt.TenDVT, nlh.TenNguoiLienHe, kh.TenKhachHang, 
                       nv.TenNhanVien, ct.Kho, ct.SoLot, ct.TenThanhPhan, ct.SoKgCan, 
                       ct.GhiChu, ct.MaKhuVuc, ct.TrangThai, ct.NgayTao
                FROM TP_ChiTietDonSanXuat ct
                LEFT JOIN TP_NguoiLienHe nlh ON ct.MaNguoiLienHe = nlh.MaNguoiLienHe
                LEFT JOIN TP_KhachHang kh ON ct.MaKhachHang = kh.MaKhachHang
                LEFT JOIN NhanVien nv ON ct.MaNhanVien = nv.MaNhanVien
                LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
                LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
                WHERE 1=1";

        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY ct.NgayTao DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param, is_float($param) ? PDO::PARAM_STR : PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, (int)$offset, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $chiTietList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $chiTietList,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'offset' => $offset
        ]);
        exit;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Lỗi server: ' . $e->getMessage()]);
    exit;
}

// Hàm hiển thị HTML an toàn
function safeHtml($value) {
    return $value !== null && $value !== '' ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : '';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tất Cả Chi Tiết Nhập Kho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
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
            z-index: 1000;
        }
        .table-container {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: auto;
            position: relative;
            width: 100%;
            display: block;
        }
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
            table-layout: auto;
        }
        .table-container thead th {
            position: sticky;
            top: 0;
            background: #fef2f2;
            z-index: 5;
            font-size: 0.75rem;
            padding: 8px;
            white-space: nowrap;
            text-align: left;
            vertical-align: middle;
            min-width: 80px;
        }
        .table-container tbody td {
            padding: 8px;
            white-space: nowrap;
            text-align: left;
            vertical-align: middle;
            font-size: 0.875rem;
        }
        .table-container tbody tr {
            transition: background-color 0.2s;
        }
        .table-container tbody tr:hover {
            background-color: #fef2f2;
        }
        .search-input {
            transition: all 0.3s;
        }
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <div class="relative min-h-screen">
        <!-- Header -->
        <header class="sticky top-0 z-20 bg-gradient-to-r from-blue-800 to-indigo-600 p-3 shadow-lg">
            <div class="flex items-center justify-between max-w-7xl mx-auto">
                <div class="flex items-center">
                    <a href="../nhapkho.php" class="text-white text-xl hover:scale-110 transition-transform p-2" aria-label="Quay lại trang nhập kho">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h2 class="text-white font-bold text-lg sm:text-xl flex items-center ml-2 sm:ml-4">
                        <i class="fas fa-list mr-2"></i> Tất Cả Chi Tiết Nhập Kho
                    </h2>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto p-2 sm:p-4 space-y-5 pb-20">
            <!-- Bộ lọc và tìm kiếm -->
            <div class="bg-white shadow-xl border-l-4 border-red-600 p-3 sm:p-4">
                <h3 class="text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                    <i class="fas fa-filter mr-3 text-red-600"></i> Bộ Lọc & Tìm Kiếm
                </h3>
                <div class="flex flex-col md:flex-row gap-4 mb-4">
                    <div class="relative w-full md:w-1/3 group">
                        <select id="filterChiTiet" class="p-2 border border-gray-300 rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-300 shadow-sm" aria-label="Lọc chi tiết nhập kho">
                            <option value="all">Tất cả chi tiết</option>
                            <option value="hasNote">Chi tiết có ghi chú</option>
                        </select>
                    </div>
                    <div class="relative w-full md:w-1/3">
                        <button id="resetFilters" type="button" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-300" aria-label="Làm mới bộ lọc">
                            <i class="fas fa-sync-alt mr-2"></i> Làm mới
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <?php foreach ($searchFieldsConfig as $field => $config): ?>
                        <div class="relative group">
                            <input type="text" id="<?php echo safeHtml($field); ?>" 
                                   placeholder="Tìm theo <?php echo safeHtml($config['label']); ?>" 
                                   class="search-input p-2 border border-gray-300 rounded-lg w-full pl-12 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-300 shadow-sm"
                                   data-type="<?php echo $config['type']; ?>"
                                   aria-label="Tìm kiếm theo <?php echo safeHtml($config['label']); ?>">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 group-focus-within:text-red-500 transition-colors duration-300"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Danh sách chi tiết nhập kho -->
            <div class="bg-white shadow-xl border-l-4 border-red-600 p-3 sm:p-4">
                <h3 class="text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                    <i class="fas fa-list-ul mr-3 text-red-600"></i> Danh Sách Chi Tiết Nhập Kho
                </h3>
                <div class="table-container">
                    <table class="min-w-full divide-y divide-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-red-50">
                            <tr>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">STT</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Mã Số Mẻ</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Đơn Hàng</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Vật Tư</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Tên Vải</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Màu</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">ĐVT</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Khổ</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Số Lượng</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Số Lot</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Thành Phần</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Số Kg Cân</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Khách Hàng</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Nhân Viên</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Người Liên Hệ</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Khu Vực</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Trạng Thái</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Ngày Tạo</th>
                                <th class="px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Ghi Chú</th>
                            </tr>
                        </thead>
                        <tbody id="chiTietTableBody" class="bg-white divide-y divide-gray-100"></tbody>
                    </table>
                </div>
                <div id="pagination" class="mt-6 flex justify-center items-center space-x-2"></div>
            </div>
        </main>

        <!-- Loading Indicator -->
        <div id="loading" class="loading">
            <i class="fas fa-spinner fa-spin"></i> Đang tải...
        </div>
    </div>

 <script>
    let currentPage = 1;
    let totalPages = 1;
    const searchFields = <?php echo json_encode(array_keys($searchFieldsConfig)); ?>;
    let lastChangedField = null; // Lưu trường cuối cùng được thay đổi

    // Hàm debounce
    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Hàm reset bộ lọc cụ thể
    function resetSpecificFilter(fieldToReset) {
        if (fieldToReset) {
            $(`#${fieldToReset}`).val(''); // Xóa giá trị của trường cụ thể
            lastChangedField = null; // Đặt lại để tránh lặp
        }
        loadPage(1); // Tải lại trang với các giá trị input hiện tại
    }

    // Hàm reset toàn bộ bộ lọc
    function resetAllFilters() {
        searchFields.forEach(field => $(`#${field}`).val(''));
        $('#filterChiTiet').val('all');
        lastChangedField = null;
        loadPage(1);
    }

    // Khởi tạo sự kiện
    $(document).ready(function() {
        searchFields.forEach(field => {
            const $input = $(`#${field}`);
            $input.on('input', debounce(() => {
                if ($input.attr('data-type') === 'float' && $input.val() && (!/^\d*\.?\d*$/.test($input.val()) || $input.val() < 0)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Dữ liệu không hợp lệ',
                        text: `Vui lòng nhập số không âm hợp lệ cho ${$input.attr('placeholder')}`
                    });
                    $input.val('');
                    return;
                }
                lastChangedField = field; // Lưu trường vừa thay đổi
                console.log(`Field changed: ${field}, Value: ${$input.val()}`); // Debug
                loadPage(1);
            }, 500));
        });

        $('#filterChiTiet').on('change', () => {
            lastChangedField = 'filterChiTiet'; // Lưu trường bộ lọc đặc biệt
            console.log(`Filter changed: ${$('#filterChiTiet').val()}`); // Debug
            loadPage(1);
        });

        $('#resetFilters').on('click', resetAllFilters);
        loadPage(1);
    });

    // Gọi API để load dữ liệu
    async function loadPage(page) {
        if (page < 1 || page > totalPages) {
            $('#loading').hide();
            return;
        }

        const filterValue = $('#filterChiTiet').val();
        const formData = new FormData();
        formData.append('action', 'search');
        formData.append('page', page);
        searchFields.forEach(field => {
            const value = $(`#${field}`).val();
            if (value) formData.append(field, value);
        });

        // Debug: Log các giá trị FormData
        const formDataDebug = {};
        for (let [key, value] of formData.entries()) {
            formDataDebug[key] = value;
        }
        console.log('FormData sent:', formDataDebug);

        $('#loading').show();

        try {
            const response = await $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 10000
            });

            if (response.success) {
                updateTable(response.data, response.offset, filterValue);
                totalPages = response.totalPages;
                currentPage = response.currentPage;
                updatePagination(response.totalPages, response.currentPage);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi!',
                    text: response.error || 'Không thể tải dữ liệu.'
                });
                updateTable([], response.offset, filterValue); // Hiển thị bảng rỗng nếu có lỗi
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi kết nối!',
                text: 'Không thể kết nối đến server: ' + error.message
            });
            updateTable([], 0, filterValue); // Hiển thị bảng rỗng nếu lỗi kết nối
        } finally {
            $('#loading').hide();
        }
    }

    // Cập nhật bảng
    function updateTable(data, offset, filterValue) {
        const tbody = $('#chiTietTableBody');
        tbody.empty();
        if (!data || data.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="19" class="text-center p-6 bg-red-50 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-3"></i>
                        <p class="text-red-600 text-base font-semibold">Không tìm thấy chi tiết nhập kho.</p>
                    </td>
                </tr>
            `);

            if (lastChangedField) {
                const fieldLabel = lastChangedField === 'filterChiTiet' 
                    ? 'Bộ lọc chi tiết' 
                    : <?php echo json_encode(array_column($searchFieldsConfig, 'label', null)); ?>[lastChangedField] || lastChangedField;
                Swal.fire({
                    icon: 'info',
                    title: 'Không tìm thấy',
                    text: `Không có chi tiết nhập kho phù hợp với bộ lọc "${fieldLabel}".`,
                    showCancelButton: true,
                    confirmButtonText: `Xóa bộ lọc "${fieldLabel}"`,
                    cancelButtonText: 'Đóng',
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280'
                }).then((result) => {
                    if (result.isConfirmed) {
                        resetSpecificFilter(lastChangedField); // Xóa bộ lọc gần nhất
                    }
                });
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Không tìm thấy',
                    text: 'Không có chi tiết nhập kho phù hợp với các bộ lọc. Vui lòng kiểm tra lại.',
                    showCancelButton: true,
                    confirmButtonText: 'Xóa tất cả bộ lọc',
                    cancelButtonText: 'Đóng',
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280'
                }).then((result) => {
                    if (result.isConfirmed) {
                        resetAllFilters(); // Xóa tất cả bộ lọc
                    }
                });
            }
            return;
        }

        let stt = offset + 1;
        data.forEach(item => {
            if (filterValue === 'hasNote' && (!item.GhiChu || item.GhiChu.trim() === '')) return;
            const row = `
                <tr class="hover:bg-red-50 transition-colors duration-200" data-note="${item.GhiChu || ''}">
                    <td class="px-4 py-2 text-sm text-gray-700">${stt++}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.MaSoMe || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.MaDonHang || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.MaVatTu || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.TenVai || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.TenMau || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.TenDVT || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.Kho || ''}</td>
                    <td class="px-4 py-2 text-sm font-bold ${parseFloat(item.SoLuong) > 0 ? 'text-green-700' : 'text-red-700'}">
                        ${item.SoLuong || '0'} ${item.TenDVT || ''}
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.SoLot || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.TenThanhPhan || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.SoKgCan || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.TenKhachHang || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.TenNhanVien || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.TenNguoiLienHe || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.MaKhuVuc || ''}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">
                        <span class="px-2 py-1 rounded-full text-xs ${getStatusClass(item.TrangThai)}">
                            ${getStatusText(item.TrangThai)}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-700">
                        ${item.NgayTao && new Date(item.NgayTao).toLocaleDateString('vi-VN') || ''}
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-700">${item.GhiChu || ''}</td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    // Cập nhật phân trang
    function updatePagination(totalPages, currentPage) {
        const pagination = $('#pagination');
        pagination.empty();

        const maxPagesToShow = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage + 1 < maxPagesToShow) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        if (currentPage > 1) {
            pagination.append(`
                <button onclick="loadPage(${currentPage - 1})" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-all duration-300" aria-label="Trang trước">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `);
        }

        if (startPage > 1) {
            pagination.append(`
                <button onclick="loadPage(1)" class="px-4 py-2 ${1 === currentPage ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700'} rounded-full hover:bg-red-500 hover:text-white transition-all duration-300" aria-label="Trang 1">
                    1
                </button>
            `);
            if (startPage > 2) {
                pagination.append('<span class="px-4 py-2">...</span>');
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            pagination.append(`
                <button onclick="loadPage(${i})" class="px-4 py-2 ${i === currentPage ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700'} rounded-full hover:bg-red-500 hover:text-white transition-all duration-300" aria-label="Trang ${i}">
                    ${i}
                </button>
            `);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pagination.append('<span class="px-4 py-2">...</span>');
            }
            pagination.append(`
                <button onclick="loadPage(${totalPages})" class="px-4 py-2 ${totalPages === currentPage ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700'} rounded-full hover:bg-red-500 hover:text-white transition-all duration-300" aria-label="Trang ${totalPages}">
                    ${totalPages}
                </button>
            `);
        }

        if (currentPage < totalPages) {
            pagination.append(`
                <button onclick="loadPage(${currentPage + 1})" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-all duration-300" aria-label="Trang sau">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `);
        }
    }

    // Hàm lấy class và text trạng thái
    function getStatusClass(status) {
        switch (status) {
            case '0': return 'bg-blue-100 text-blue-800';
            case '1': return 'bg-yellow-100 text-yellow-800';
            case '2': return 'bg-green-100 text-green-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    }

    function getStatusText(status) {
        switch (status) {
            case '0': return 'Hàng Mới';
            case '1': return 'Hàng Xuất';
            case '2': return 'Hàng Tồn';
            default: return 'N/A';
        }
    }
</script>
</body>
</html>