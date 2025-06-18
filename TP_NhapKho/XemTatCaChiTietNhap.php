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
        if (isset($postData[$field]) && trim($postData[$field]) !== '') {
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

        // Bắt đầu xây dựng điều kiện và tham số
        [$conditions, $params] = buildSearchConditions($searchFieldsConfig, $_POST);
        
        // *** SỬA LỖI: Xử lý bộ lọc đặc biệt cho Ghi Chú ***
        if (isset($_POST['filterChiTiet']) && $_POST['filterChiTiet'] === 'hasNote') {
            // Thêm điều kiện: Ghi Chú không được rỗng (NULL hoặc chuỗi trống)
            $conditions[] = "(ct.GhiChu IS NOT NULL AND ct.GhiChu <> '')";
        }

        // Truy vấn tổng số bản ghi
        $sqlCount = "SELECT COUNT(1) as total 
                     FROM TP_ChiTietDonSanXuat ct
                     LEFT JOIN TP_NguoiLienHe nlh ON ct.MaNguoiLienHe = nlh.MaNguoiLienHe
                     LEFT JOIN TP_KhachHang kh ON ct.MaKhachHang = kh.MaKhachHang
                     LEFT JOIN NhanVien nv ON ct.MaNhanVien = nv.MaNhanVien
                     LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
                     LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
                     WHERE 1=1";

        if (!empty($conditions)) {
            $sqlCount .= " AND " . implode(" AND ", $conditions);
        }

        $stmtCount = $pdo->prepare($sqlCount);
        // Bind params cho câu lệnh count
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
        
        // 1. Bind các tham số điều kiện (WHERE)
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param, is_float($param) ? PDO::PARAM_STR : PDO::PARAM_STR);
        }
        // 2. Bind các tham số phân trang (OFFSET, FETCH) với kiểu INT
        $stmt->bindValue(count($params) + 1, (int)$offset, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, (int)$limit, PDO::PARAM_INT);
        
        $stmt->execute();
        $chiTietList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $chiTietList,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'totalRecords' => $totalRecords,
            'offset' => $offset,
            'activeFilters' => array_filter($_POST, function($value, $key) {
                return !in_array($key, ['action', 'page']) && !empty(trim($value));
            }, ARRAY_FILTER_USE_BOTH)
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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#fef2f2', 100: '#fee2e2', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c', 800: '#991b1b', 900: '#7f1d1d' }
                    },
                    animation: { 'fade-in': 'fadeIn 0.5s ease-in-out', 'slide-in': 'slideIn 0.3s ease-out' }
                }
            }
        }
    </script>
   <style>
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes slideIn { from { transform: translateX(-100%); } to { transform: translateX(0); } }
    
    .loading {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    
    .table-container {
        max-height: 70vh;
        overflow: auto;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    table {
        table-layout: auto;
        width: 100%;
        border-collapse: collapse;
    }
    
    .table-header-cell, td {
        white-space: nowrap;
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .table-header-cell {
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        font-size: 0.75rem;
    }
    
    td {
        color: #374151;
        font-size: 0.875rem;
    }
    
    .table-container td, .table-header-cell {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .table-container::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    .table-container::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }
    
    .table-container::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    
    .table-container::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    .search-input {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .search-input:focus {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px -5px rgba(239, 68, 68, 0.25);
    }
    
    .filter-chip {
        animation: slideIn 0.3s ease-out;
    }
</style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <header class="sticky top-0 z-30 bg-gradient-to-r from-primary-800 via-primary-700 to-primary-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between py-4">
                <div class="flex items-center space-x-4">
                    <a href="../nhapkho.php" class="group flex items-center justify-center w-10 h-10 bg-white/10 hover:bg-white/20 rounded-lg transition-all duration-300 hover:scale-105">
                        <i class="fas fa-arrow-left text-white group-hover:text-primary-100"></i>
                    </a>
                    <div class="flex flex-col">
                        <h1 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-warehouse mr-3 text-primary-200"></i>
                            Chi Tiết Nhập Kho
                        </h1>
                        <p class="text-primary-100 text-sm hidden sm:block">Quản lý và tìm kiếm chi tiết nhập kho</p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center space-x-3">
                    <div id="recordCount" class="bg-white/10 px-3 py-1 rounded-full text-primary-100 text-sm font-medium">
                        <i class="fas fa-list-ol mr-1"></i>
                        <span>0 bản ghi</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6 space-y-6">
        <!-- Search and Filter Section -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-primary-50 to-primary-100 px-6 py-4 border-b border-primary-200">
                <h2 class="text-lg font-bold text-primary-800 flex items-center"> <i class="fas fa-search mr-3 text-primary-600"></i> Bộ Lọc & Tìm Kiếm </h2>
                <p class="text-sm text-primary-600 mt-1">Sử dụng các bộ lọc để tìm kiếm chi tiết nhập kho</p>
            </div>
            
            <div class="p-6">
                <div id="activeFilters" class="mb-6 hidden">
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <span class="text-sm font-medium text-gray-700">Bộ lọc đang áp dụng:</span>
                        <div id="filterChips" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 mb-6">
                    <div class="flex-1">
                        <select id="filterChiTiet" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all duration-300 bg-white shadow-sm">
                            <option value="all">Tất cả chi tiết</option>
                            <option value="hasNote">Chi tiết có ghi chú</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button id="resetFilters" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white rounded-xl font-medium transition-all duration-300 hover:scale-105 hover:shadow-lg flex items-center">
                            <i class="fas fa-sync-alt mr-2"></i> <span class="hidden sm:inline">Làm mới</span>
                        </button>
                        <button id="clearAllFilters" class="px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white rounded-xl font-medium transition-all duration-300 hover:scale-105 hover:shadow-lg flex items-center">
                            <i class="fas fa-times mr-2"></i> <span class="hidden sm:inline">Xóa tất cả</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php foreach ($searchFieldsConfig as $field => $config): ?>
                        <div class="relative group">
                            <label class="block text-sm font-medium text-gray-700 mb-2"> <?php echo safeHtml($config['label']); ?> </label>
                            <div class="relative">
                                <input type="text" id="<?php echo safeHtml($field); ?>" placeholder="Tìm <?php echo safeHtml($config['label']); ?>..." class="search-input w-full px-4 py-3 pl-12 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all duration-300 bg-white shadow-sm hover:shadow-md" data-label="<?php echo safeHtml($config['label']); ?>">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"> <i class="fas fa-search text-gray-400 group-focus-within:text-primary-500 transition-colors duration-300"></i> </div>
                                <button type="button" class="clear-input absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-primary-500 transition-colors duration-300 hidden" data-field="<?php echo safeHtml($field); ?>"> <i class="fas fa-times"></i> </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="bg-white rounded-2xl shadow-xl border  overflow-hidden">
            <div class="bg-gradient-to-r from-primary-50 to-primary-100 px-6 py-4 border-b border-primary-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-primary-800 flex items-center"> <i class="fas fa-table mr-3 text-primary-600"></i> Danh Sách Chi Tiết </h2>
                    <div id="resultsInfo" class="text-sm text-primary-600"></div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="w-full">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">STT</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Mã Số Mẻ</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Đơn Hàng</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Vật Tư</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Tên Vải</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Màu</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">ĐVT</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Khổ</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Số Lượng</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Số Lot</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Thành Phần</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Kg Cân</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Khách Hàng</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Nhân Viên</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Người LH</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Khu Vực</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Trạng Thái</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Ngày Tạo</th>
                            <th class="table-header-cell px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">Ghi Chú</th>
                        </tr>
                    </thead>
                    <tbody id="chiTietTableBody" class="bg-white divide-y divide-gray-100"></tbody>
                </table>
            </div>
            
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <div id="pagination" class="flex items-center justify-center space-x-2"></div>
            </div>
        </div>
    </main>

    <div id="loading" class="loading">
        <div class="bg-white rounded-2xl p-8 shadow-2xl text-center">
            <div class="animate-spin w-12 h-12 border-4 border-primary-200 border-t-primary-600 rounded-full mx-auto mb-4"></div>
            <p class="text-gray-700 font-medium">Đang tải dữ liệu...</p>
        </div>
    </div>

    <script>
        let currentPage = 1;
        const searchFields = <?php echo json_encode(array_keys($searchFieldsConfig)); ?>;
        const fieldLabels = <?php echo json_encode(array_column($searchFieldsConfig, 'label', null)); ?>;
        let debounceTimers = new Map();

        function debounceSearch(field, func, delay = 800) {
            if (debounceTimers.has(field)) clearTimeout(debounceTimers.get(field));
            const timer = setTimeout(() => { func(); debounceTimers.delete(field); }, delay);
            debounceTimers.set(field, timer);
        }

        function updateActiveFilters() {
            const activeFilters = new Map();
            searchFields.forEach(field => {
                const value = $(`#${field}`).val().trim();
                if (value) activeFilters.set(field, { value: value, label: fieldLabels[field] || field });
            });
            displayFilterChips(activeFilters);
        }

        function displayFilterChips(activeFilters) {
            const $activeFiltersContainer = $('#activeFilters');
            const $filterChips = $('#filterChips');
            $activeFiltersContainer.toggleClass('hidden', activeFilters.size === 0);
            $filterChips.empty();
            activeFilters.forEach((filter, field) => {
                const chip = $(`<div class="filter-chip flex items-center bg-primary-100 text-primary-800 px-3 py-1 rounded-full text-sm font-medium"> <span class="mr-2">${filter.label}: ${filter.value}</span> <button type="button" class="remove-filter hover:text-primary-600 transition-colors" data-field="${field}"> <i class="fas fa-times"></i> </button> </div>`);
                $filterChips.append(chip);
            });
        }

        function removeSpecificFilter(field) {
            $(`#${field}`).val('').trigger('input');
        }

        function clearAllFilters() {
            searchFields.forEach(field => $(`#${field}`).val(''));
            $('#filterChiTiet').val('all');
            $('.clear-input').addClass('hidden');
            loadPage(1);
        }

        $(document).ready(function() {
            searchFields.forEach(field => {
                $(`#${field}`).on('input', function() {
                    $(this).siblings('.clear-input').toggleClass('hidden', $(this).val().trim() === '');
                    debounceSearch(field, () => loadPage(1));
                });
            });
            $(document).on('click', '.clear-input, .remove-filter', function() { removeSpecificFilter($(this).data('field')); });
            $('#resetFilters').on('click', () => loadPage(currentPage));
            $('#clearAllFilters').on('click', clearAllFilters);
            $('#filterChiTiet').on('change', () => loadPage(1));
            $(document).on('click', '.page-link', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page && page != currentPage) loadPage(page);
            });
            loadPage(1);
        });

        function loadPage(page) {
            $('#loading').css('display', 'flex');
            updateActiveFilters();
            const formData = { action: 'search', page: page };

            // Lấy dữ liệu từ các ô input tìm kiếm
            searchFields.forEach(field => {
                const value = $(`#${field}`).val().trim();
                if (value) formData[field] = value;
            });
            
            // *** SỬA LỖI: Gửi giá trị của bộ lọc Ghi Chú ***
            const filterChiTietValue = $('#filterChiTiet').val();
            if (filterChiTietValue !== 'all') {
                formData.filterChiTiet = filterChiTietValue;
            }

            $.ajax({
                url: '', type: 'POST', data: formData, dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        currentPage = response.currentPage;
                        renderTable(response.data, response.offset);
                        renderPagination(response.totalPages, response.currentPage);
                        $('#recordCount span').text(`${response.totalRecords} bản ghi`);
                        $('#resultsInfo').text(` Tổng số ${response.totalRecords}`);
                    } else {
                        Swal.fire('Lỗi!', response.error || 'Có lỗi xảy ra khi tải dữ liệu.', 'error');
                    }
                },
                error: (jqXHR, textStatus) => Swal.fire('Lỗi!', 'Không thể kết nối đến máy chủ: ' + textStatus, 'error'),
                complete: () => $('#loading').css('display', 'none')
            });
        }
        
        function renderTable(data, offset) {
            const $tbody = $('#chiTietTableBody');
            $tbody.empty();
            if (data.length === 0) {
                $tbody.html('<tr><td colspan="19" class="text-center text-gray-500 py-10">Không tìm thấy dữ liệu phù hợp.</td></tr>');
                return;
            }
            const renderCell = (value) => value !== null && value !== '' ? $('<div>').text(value).html() : '';
            const rows = data.map((item, index) => {
                const rowNum = offset + index + 1;
                const formattedDate = item.NgayTao ? new Date(item.NgayTao.replace(' ', 'T')).toLocaleDateString('vi-VN') : null;
                return `
                    <tr class="hover:bg-primary-50 transition-colors duration-200 animate-fade-in" style="animation-delay: ${index * 0.02}s">
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${rowNum}</td>
                        <td class="px-4 py-3 text-sm font-medium text-primary-700 border-b border-gray-200">${renderCell(item.MaSoMe)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.MaDonHang)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.MaVatTu)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.TenVai)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.TenMau)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.TenDVT)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.Kho)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.SoLuong)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.SoLot)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.TenThanhPhan)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.SoKgCan)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.TenKhachHang)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.TenNhanVien)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.TenNguoiLienHe)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.MaKhuVuc)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(item.TrangThai)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200">${renderCell(formattedDate)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-200"><div class="max-w-xs truncate" title="${renderCell(item.GhiChu)}">${renderCell(item.GhiChu)}</div></td>
                    </tr>
                `;
            });
            $tbody.append(rows.join(''));
        }

        function renderPagination(totalPages, currentPage) {
            const $pagination = $('#pagination');
            $pagination.empty();
            if (totalPages <= 1) return;
            const createLink = (page, text, isActive = false, isDisabled = false) => {
                const activeClass = isActive ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 hover:bg-gray-100';
                const disabledClass = isDisabled ? 'opacity-50 cursor-not-allowed' : '';
                return `<a href="#" class="page-link px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium transition-colors ${activeClass} ${disabledClass}" data-page="${page}">${text}</a>`;
            };
            let html = createLink(currentPage - 1, '<i class="fas fa-chevron-left"></i>', false, currentPage === 1);
            const pageWindow = 2;
            let startPage = Math.max(1, currentPage - pageWindow);
            let endPage = Math.min(totalPages, currentPage + pageWindow);
            if (startPage > 1) {
                html += createLink(1, '1');
                if (startPage > 2) html += `<span class="px-4 py-2 text-gray-500">...</span>`;
            }
            for (let i = startPage; i <= endPage; i++) {
                html += createLink(i, i, i === currentPage);
            }
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += `<span class="px-4 py-2 text-gray-500">...</span>`;
                html += createLink(totalPages, totalPages);
            }
            html += createLink(currentPage + 1, '<i class="fas fa-chevron-right"></i>', false, currentPage === totalPages);
            $pagination.html(html);
        }
    </script>
</body>
</html>