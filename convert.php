<?php
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Chuyển đổi file PDF sang định dạng BMP với tối ưu hóa tốc độ.
 *
 * @param string $pdfPath Đường dẫn đến file PDF đầu vào.
 * @param array $options Tùy chọn: ['dpi' => 150, 'use_cache' => true, 'max_width' => 832, 'max_height' => 1180]
 * @param callable $progressCallback Callback để theo dõi tiến trình
 * @return string|bool Đường dẫn đến file BMP đầu ra hoặc false nếu thất bại.
 */
function convertPdfToBmp($pdfPath, $options = [], $progressCallback = null)
{
    // Thiết lập mặc định
    $defaults = [
        'dpi' => 150,
        'use_cache' => true,
        'max_width' => 832,
        'max_height' => 1180,
        'quality' => 85,
        'grayscale' => true
    ];
    $options = array_merge($defaults, $options);
    
    if ($progressCallback) $progressCallback(5, "Bắt đầu chuyển đổi...");
    
    // Kiểm tra file PDF có tồn tại và hợp lệ
    if (!file_exists($pdfPath) || filesize($pdfPath) < 100) {
        writeDebugLog("File PDF không hợp lệ hoặc không tồn tại", ['file' => $pdfPath]);
        return false;
    }
    
    if ($progressCallback) $progressCallback(10, "Kiểm tra file PDF...");
    
    // Kiểm tra cache trước
    if ($options['use_cache']) {
        $cachedFile = getCachedBmpFile($pdfPath, $options);
        if ($cachedFile) {
            if ($progressCallback) $progressCallback(100, "Sử dụng cache!");
            writeDebugLog("Sử dụng file cache", ['cache_file' => $cachedFile]);
            return $cachedFile;
        }
    }
    
    if ($progressCallback) $progressCallback(20, "Bắt đầu chuyển đổi...");
    
    try {
        // Kiểm tra xem Imagick có sẵn không
        if (!extension_loaded('imagick')) {
            writeDebugLog("Imagick không được cài đặt, chuyển sang Ghostscript", ['file' => $pdfPath]);
            return convertPdfToBmpWithGs($pdfPath, $options, $progressCallback);
        }

        return convertPdfToBmpWithImagick($pdfPath, $options, $progressCallback);
        
    } catch (Exception $e) {
        writeDebugLog("Lỗi chung khi chuyển đổi PDF", ['error' => $e->getMessage(), 'file' => $pdfPath]);
        return false;
    }
}

/**
 * Chuyển đổi PDF sang BMP sử dụng Imagick (tối ưu hóa).
 */
function convertPdfToBmpWithImagick($pdfPath, $options, $progressCallback = null)
{
    try {
        if ($progressCallback) $progressCallback(25, "Khởi tạo Imagick...");
        
        // Tạo đối tượng Imagick
        $imagick = new Imagick();
        
        // Tối ưu memory và performance
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024); // 256MB
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);    // 512MB
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_DISK, 1024 * 1024 * 1024);  // 1GB

        // Cấu hình để render PDF với độ phân giải tối ưu
        $imagick->setResolution($options['dpi'], $options['dpi']);
        
        // Tối ưu compression
        $imagick->setCompression(Imagick::COMPRESSION_JPEG);
        $imagick->setCompressionQuality($options['quality']);
        
        if ($progressCallback) $progressCallback(40, "Đọc file PDF...");
        
        // Đọc trang đầu tiên của PDF
        $imagick->readImage($pdfPath . '[0]'); // [0] = trang đầu tiên
        
        if ($progressCallback) $progressCallback(60, "Xử lý hình ảnh...");
        
        // Chuyển sang grayscale trước (nhanh hơn)
        if ($options['grayscale']) {
            $imagick->setImageColorspace(Imagick::COLORSPACE_GRAY);
            $imagick->setImageType(Imagick::IMGTYPE_GRAYSCALE);
        }
        
        // Điều chỉnh kích thước nếu cần
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if ($width > $options['max_width'] || $height > $options['max_height']) {
            $scale = min($options['max_width'] / $width, $options['max_height'] / $height);
            $newWidth = (int)($width * $scale);
            $newHeight = (int)($height * $scale);
            
            // Sử dụng filter nhanh hơn
            $imagick->resizeImage($newWidth, $newHeight, Imagick::FILTER_TRIANGLE, 0.8);
            writeDebugLog("Điều chỉnh kích thước hình ảnh", [
                'original_width' => $width, 
                'original_height' => $height, 
                'new_width' => $newWidth, 
                'new_height' => $newHeight
            ]);
        }
        
        if ($progressCallback) $progressCallback(80, "Lưu file BMP...");

        // Lưu thành BMP
        $bmpFile = sys_get_temp_dir() . '/' . uniqid('pdf_to_bmp_') . '.bmp';
        $imagick->setImageFormat('BMP');
        $imagick->writeImage($bmpFile);

        // Cleanup
        $imagick->clear();
        $imagick->destroy();

        // Kiểm tra file BMP được tạo
        if (file_exists($bmpFile) && filesize($bmpFile) > 100) {
            if ($progressCallback) $progressCallback(95, "Lưu cache...");
            
            // Lưu vào cache nếu được bật
            if ($options['use_cache']) {
                saveToCacheFile($pdfPath, $bmpFile, $options);
            }
            
            if ($progressCallback) $progressCallback(100, "Hoàn thành!");
            
            writeDebugLog("Chuyển đổi PDF sang BMP thành công với Imagick", [
                'bmp_file' => $bmpFile, 
                'size' => filesize($bmpFile),
                'dpi' => $options['dpi']
            ]);
            return $bmpFile;
        }

        writeDebugLog("File BMP không hợp lệ sau khi chuyển đổi với Imagick", ['bmp_file' => $bmpFile]);
        return false;
        
    } catch (Exception $e) {
        writeDebugLog("Lỗi khi chuyển đổi PDF với Imagick", ['error' => $e->getMessage(), 'file' => $pdfPath]);
        // Thử phương pháp dự phòng với Ghostscript
        return convertPdfToBmpWithGs($pdfPath, $options, $progressCallback);
    }
}

/**
 * Chuyển đổi PDF sang BMP sử dụng Ghostscript (tối ưu hóa).
 */
function convertPdfToBmpWithGs($pdfFile, $options, $progressCallback = null)
{
    // Cấu hình đường dẫn Ghostscript
    $gsCommands = [
        '"C:\Program Files\gs\gs10.05.1\bin\gswin64c.exe"',  // Windows 64-bit
        '"C:\Program Files (x86)\gs\gs10.05.1\bin\gswin32c.exe"', // Windows 32-bit
        'gs', // Linux/Unix
        '/usr/bin/gs', // Alternative Linux path
        '/usr/local/bin/gs' // macOS/Homebrew
    ];
    
    $gs_command = null;
    foreach ($gsCommands as $cmd) {
        $testCmd = str_replace('"', '', $cmd);
        if (is_executable($testCmd) || (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')) {
            $gs_command = $cmd;
            break;
        }
    }
    
    if (!$gs_command) {
        writeDebugLog("Không tìm thấy Ghostscript", ['paths_tried' => $gsCommands]);
        return false;
    }
    
    try {
        if ($progressCallback) $progressCallback(30, "Khởi tạo Ghostscript...");
        
        $bmpFile = sys_get_temp_dir() . '/' . uniqid('pdf_to_bmp_gs_') . '.bmp';
        
        // Tối ưu lệnh Ghostscript
        $device = $options['grayscale'] ? 'bmpgray' : 'bmp16m';
        $gsCommand = sprintf(
            '%s -dSAFER -dBATCH -dNOPAUSE -dQUIET ' .
            '-dFirstPage=1 -dLastPage=1 ' .
            '-sDEVICE=%s -r%d ' .
            '-dTextAlphaBits=4 -dGraphicsAlphaBits=4 ' .
            '-dColorConversionStrategy=/LeaveColorUnchanged ' .
            '-dDownScaleFactor=1 ' .
            '-sOutputFile=%s %s 2>&1',
            $gs_command,
            $device,
            $options['dpi'],
            escapeshellarg($bmpFile),
            escapeshellarg($pdfFile)
        );

        if ($progressCallback) $progressCallback(50, "Đang chuyển đổi với Ghostscript...");
        
        $startTime = microtime(true);
        exec($gsCommand, $output, $returnCode);
        $executionTime = microtime(true) - $startTime;
        
        writeDebugLog("Chạy lệnh Ghostscript", [
            'command' => $gsCommand, 
            'return_code' => $returnCode,
            'execution_time' => $executionTime,
            'output' => implode("\n", $output)
        ]);

        if ($returnCode === 0 && file_exists($bmpFile) && filesize($bmpFile) > 100) {
            if ($progressCallback) $progressCallback(90, "Lưu cache...");
            
            // Lưu vào cache nếu được bật
            if ($options['use_cache']) {
                saveToCacheFile($pdfFile, $bmpFile, $options);
            }
            
            if ($progressCallback) $progressCallback(100, "Hoàn thành!");
            
            writeDebugLog("Chuyển đổi PDF sang BMP thành công với Ghostscript", [
                'bmp_file' => $bmpFile, 
                'size' => filesize($bmpFile),
                'execution_time' => $executionTime,
                'dpi' => $options['dpi']
            ]);
            return $bmpFile;
        }

        writeDebugLog("Lỗi khi chuyển đổi với Ghostscript", [
            'output' => implode("\n", $output),
            'return_code' => $returnCode
        ]);
        return false;
        
    } catch (Exception $e) {
        writeDebugLog("Lỗi khi chạy Ghostscript", ['error' => $e->getMessage(), 'file' => $pdfFile]);
        return false;
    }
}

/**
 * Lấy file BMP từ cache nếu có.
 */
function getCachedBmpFile($pdfPath, $options)
{
    $cacheKey = generateCacheKey($pdfPath, $options);
    $cacheFile = getCacheDir() . '/' . $cacheKey . '.bmp';
    
    if (file_exists($cacheFile) && filesize($cacheFile) > 100) {
        // Kiểm tra cache có còn hợp lệ không (file PDF có thay đổi không)
        $pdfModTime = filemtime($pdfPath);
        $cacheModTime = filemtime($cacheFile);
        
        if ($cacheModTime >= $pdfModTime) {
            return $cacheFile;
        }
    }
    
    return false;
}

/**
 * Lưu file BMP vào cache.
 */
function saveToCacheFile($pdfPath, $bmpFile, $options)
{
    try {
        $cacheDir = getCacheDir();
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheKey = generateCacheKey($pdfPath, $options);
        $cacheFile = $cacheDir . '/' . $cacheKey . '.bmp';
        
        if (copy($bmpFile, $cacheFile)) {
            writeDebugLog("Lưu cache thành công", ['cache_file' => $cacheFile]);
        }
    } catch (Exception $e) {
        writeDebugLog("Lỗi khi lưu cache", ['error' => $e->getMessage()]);
    }
}

/**
 * Tạo cache key duy nhất.
 */
function generateCacheKey($pdfPath, $options)
{
    $fileInfo = [
        'path' => $pdfPath,
        'size' => filesize($pdfPath),
        'mtime' => filemtime($pdfPath),
        'options' => $options
    ];
    
    return 'pdf_bmp_' . md5(serialize($fileInfo));
}

/**
 * Lấy thư mục cache.
 */
function getCacheDir()
{
    $cacheDir = __DIR__ . '/cache/pdf_to_bmp';
    return $cacheDir;
}

/**
 * Dọn dẹp cache cũ (gọi định kỳ).
 */
function cleanupCache($maxAgeHours = 24)
{
    try {
        $cacheDir = getCacheDir();
        if (!is_dir($cacheDir)) {
            return;
        }
        
        $files = glob($cacheDir . '/*.bmp');
        $cutoffTime = time() - ($maxAgeHours * 3600);
        $deletedCount = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }
        
        writeDebugLog("Dọn dẹp cache", ['deleted_files' => $deletedCount, 'max_age_hours' => $maxAgeHours]);
        
    } catch (Exception $e) {
        writeDebugLog("Lỗi khi dọn dẹp cache", ['error' => $e->getMessage()]);
    }
}

/**
 * Xử lý hàng đợi chuyển đổi (background processing).
 */
function queuePdfConversion($pdfPath, $options = [], $callback = null)
{
    $queueDir = __DIR__ . '/queue';
    if (!is_dir($queueDir)) {
        mkdir($queueDir, 0755, true);
    }
    
    $jobData = [
        'id' => uniqid('job_'),
        'pdf_path' => $pdfPath,
        'options' => $options,
        'callback' => $callback,
        'created_at' => time(),
        'status' => 'pending'
    ];
    
    $jobFile = $queueDir . '/' . $jobData['id'] . '.json';
    file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT));
    
    writeDebugLog("Thêm job vào queue", ['job_id' => $jobData['id'], 'pdf_path' => $pdfPath]);
    
    return $jobData['id'];
}

/**
 * Xử lý queue jobs (chạy từng job).
 */
function processQueue($maxJobs = 5)
{
    $queueDir = __DIR__ . '/queue';
    if (!is_dir($queueDir)) {
        return;
    }
    
    $jobFiles = glob($queueDir . '/*.json');
    $processed = 0;
    
    foreach ($jobFiles as $jobFile) {
        if ($processed >= $maxJobs) {
            break;
        }
        
        try {
            $jobData = json_decode(file_get_contents($jobFile), true);
            
            if ($jobData['status'] !== 'pending') {
                continue;
            }
            
            writeDebugLog("Xử lý job", ['job_id' => $jobData['id']]);
            
            // Cập nhật status
            $jobData['status'] = 'processing';
            $jobData['started_at'] = time();
            file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT));
            
            // Thực hiện chuyển đổi
            $result = convertPdfToBmp($jobData['pdf_path'], $jobData['options']);
            
            // Cập nhật kết quả
            $jobData['status'] = $result ? 'completed' : 'failed';
            $jobData['result'] = $result;
            $jobData['completed_at'] = time();
            file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT));
            
            // Gọi callback nếu có
            if ($jobData['callback'] && is_callable($jobData['callback'])) {
                call_user_func($jobData['callback'], $result, $jobData);
            }
            
            $processed++;
            
        } catch (Exception $e) {
            writeDebugLog("Lỗi khi xử lý job", ['error' => $e->getMessage(), 'job_file' => $jobFile]);
        }
    }
    
    writeDebugLog("Xử lý queue hoàn thành", ['processed_jobs' => $processed]);
}

/**
 * Benchmark function để test hiệu suất.
 */
function benchmarkConversion($pdfPath, $iterations = 3)
{
    $results = [];
    
    // Test với các cấu hình khác nhau
    $configs = [
        ['name' => 'DPI_100', 'dpi' => 100, 'use_cache' => false],
        ['name' => 'DPI_150', 'dpi' => 150, 'use_cache' => false],
        ['name' => 'DPI_200', 'dpi' => 200, 'use_cache' => false],
        ['name' => 'DPI_150_CACHED', 'dpi' => 150, 'use_cache' => true],
    ];
    
    foreach ($configs as $config) {
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            $result = convertPdfToBmp($pdfPath, $config);
            $endTime = microtime(true);
            
            if ($result) {
                $times[] = $endTime - $startTime;
                // Cleanup temp file
                if (file_exists($result)) {
                    unlink($result);
                }
            }
        }
        
        if (!empty($times)) {
            $results[$config['name']] = [
                'avg_time' => array_sum($times) / count($times),
                'min_time' => min($times),
                'max_time' => max($times),
                'iterations' => count($times)
            ];
        }
    }
    
    return $results;
}

/**
 * Ghi log gỡ lỗi vào file debug.log.
 *
 * @param string $message Thông điệp log.
 * @param array $context Dữ liệu ngữ cảnh bổ sung.
 */
function writeDebugLog($message, $context = [])
{
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
    $logMessage = "[$timestamp] $message$contextStr" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Ví dụ sử dụng:
/*
// Sử dụng cơ bản
$result = convertPdfToBmp('path/to/file.pdf');

// Sử dụng với options
$result = convertPdfToBmp('path/to/file.pdf', [
    'dpi' => 150,
    'use_cache' => true,
    'max_width' => 800,
    'max_height' => 1200
]);

// Sử dụng với progress callback
$result = convertPdfToBmp('path/to/file.pdf', [], function($percent, $message) {
    echo "[$percent%] $message\n";
});

// Sử dụng queue
$jobId = queuePdfConversion('path/to/file.pdf');
processQueue(); // Xử lý các job trong queue

// Benchmark
$benchmarkResults = benchmarkConversion('path/to/file.pdf');
print_r($benchmarkResults);

// Dọn dẹp cache
cleanupCache(24); // Xóa cache cũ hơn 24 giờ
*/
?>