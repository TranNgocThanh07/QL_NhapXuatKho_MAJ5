<?php
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Chuyển đổi toàn bộ trang PDF sang BMP trong bộ nhớ sử dụng Ghostscript.
 *
 * @param string $pdfData Dữ liệu PDF dưới dạng chuỗi.
 * @return array|bool Mảng dữ liệu BMP nhị phân hoặc false nếu thất bại.
 */
function convertPdfToBmpAllPagesInMemory($pdfData)
{
    writeDebugLog("Bắt đầu chuyển đổi PDF sang BMP trong bộ nhớ với Ghostscript", ['data_length' => strlen($pdfData)]);

    // Kiểm tra dữ liệu PDF hợp lệ
    if (strlen($pdfData) < 100) {
        writeDebugLog("Dữ liệu PDF không hợp lệ", ['data_length' => strlen($pdfData)]);
        return false;
    }

    try {
        // Tạo file PDF tạm
        $tempDir = sys_get_temp_dir() . '/' . uniqid('pdf_to_bmp_gs_');
        if (!mkdir($tempDir, 0755, true)) {
            writeDebugLog("Không thể tạo thư mục tạm", ['temp_dir' => $tempDir]);
            return false;
        }

        $tempPdfFile = $tempDir . '/temp_pdf.pdf';
        file_put_contents($tempPdfFile, $pdfData);
        writeDebugLog("Tạo file PDF tạm thành công", ['temp_pdf' => $tempPdfFile]);

        // Đếm số trang PDF
        $pageCount = getPdfPageCount($tempPdfFile);
        if ($pageCount === false) {
            writeDebugLog("Không thể đếm số trang PDF", ['temp_pdf' => $tempPdfFile]);
            @unlink($tempPdfFile);
            @rmdir($tempDir);
            return false;
        }

        $bmpDataArray = [];
        $gs_command = '"C:\Program Files\gs\gs10.05.1\bin\gswin64c.exe"';

        for ($page = 1; $page <= $pageCount; $page++) {
            writeDebugLog("Xử lý trang với Ghostscript", ['page' => $page, 'total' => $pageCount]);

            $bmpFile = $tempDir . '/page_' . str_pad($page, 3, '0', STR_PAD_LEFT) . '.bmp';

            // Cấu hình lệnh Ghostscript cho từng trang
           $gsCommand = sprintf(
    '%s -dSAFER -dBATCH -dNOPAUSE -dFirstPage=%d -dLastPage=%d -sDEVICE=bmpmono -r203 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -dPDFFitPage -sPAPERSIZE=a6 -dFIXEDMEDIA -sOutputFile=%s %s 2>&1',
    $gs_command,
    $page,
    $page,
    escapeshellarg($bmpFile),
    escapeshellarg($tempPdfFile)
);
            exec($gsCommand, $output, $returnCode);
            writeDebugLog("Chạy lệnh Ghostscript cho trang " . $page, [
                'command' => $gsCommand,
                'return_code' => $returnCode,
                'output_lines' => count($output)
            ]);

            if ($returnCode === 0 && file_exists($bmpFile) && filesize($bmpFile) > 100) {
                // Đọc dữ liệu BMP và thêm vào mảng
                $bmpData = file_get_contents($bmpFile);
                if (strlen($bmpData) > 100) {
                    $bmpDataArray[] = $bmpData;
                    writeDebugLog("Chuyển đổi trang " . $page . " thành công với Ghostscript", [
                        'page' => $page,
                        'bmp_file' => $bmpFile,
                        'size' => filesize($bmpFile)
                    ]);
                } else {
                    writeDebugLog("Dữ liệu BMP không hợp lệ cho trang " . $page, [
                        'page' => $page,
                        'bmp_file' => $bmpFile,
                        'size' => filesize($bmpFile)
                    ]);
                }
                // Xóa file BMP tạm
                @unlink($bmpFile);
            } else {
                writeDebugLog("Lỗi khi chuyển đổi trang " . $page . " với Ghostscript", [
                    'page' => $page,
                    'return_code' => $returnCode,
                    'file_exists' => file_exists($bmpFile),
                    'file_size' => file_exists($bmpFile) ? filesize($bmpFile) : 0,
                    'output' => implode("\n", array_slice($output, -5))
                ]);
            }

            $output = [];
        }

        // Xóa file PDF tạm và thư mục
        @unlink($tempPdfFile);
        @rmdir($tempDir);
        writeDebugLog("Dọn dẹp file tạm", ['temp_dir' => $tempDir]);

        if (empty($bmpDataArray)) {
            writeDebugLog("Không có trang nào được chuyển đổi thành công", ['total_pages' => $pageCount]);
            return false;
        }

        writeDebugLog("Chuyển đổi hoàn tất", ['total_files' => count($bmpDataArray)]);
        return $bmpDataArray;

    } catch (Exception $e) {
        writeDebugLog("Lỗi khi chuyển đổi PDF sang BMP với Ghostscript", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        // Dọn dẹp file tạm nếu có lỗi
        if (isset($tempPdfFile) && file_exists($tempPdfFile)) {
            @unlink($tempPdfFile);
        }
        if (isset($tempDir) && file_exists($tempDir)) {
            @rmdir($tempDir);
        }
        return false;
    }
}

/**
 * Đếm số trang trong file PDF.
 *
 * @param string $pdfPath Đường dẫn đến file PDF.
 * @return int|bool Số trang hoặc false nếu thất bại.
 */
function getPdfPageCount($pdfPath)
{
    writeDebugLog("Bắt đầu đếm số trang PDF", ['file' => $pdfPath]);
    
    try {
        // Thử với Imagick trước
        if (extension_loaded('imagick')) {
            $imagick = new Imagick();
            $imagick->readImage($pdfPath);
            $pageCount = $imagick->getNumberImages();
            $imagick->clear();
            $imagick->destroy();
            
            writeDebugLog("Đếm trang với Imagick thành công", ['page_count' => $pageCount]);
            return $pageCount;
        }
    } catch (Exception $e) {
        writeDebugLog("Lỗi khi đếm trang với Imagick", ['error' => $e->getMessage()]);
    }

    // Thử với Ghostscript
    $gs_command = '"C:\Program Files\gs\gs10.05.1\bin\gswin64c.exe"';
    $gsCommand = sprintf(
        '%s -dSAFER -dBATCH -dNOPAUSE -q -sDEVICE=nullpage %s 2>&1',
        $gs_command,
        escapeshellarg($pdfPath)
    );

    exec($gsCommand, $output, $returnCode);
    writeDebugLog("Lệnh đếm trang Ghostscript", ['command' => $gsCommand, 'return_code' => $returnCode, 'output' => $output]);

    // Tìm dòng chứa thông tin số trang
    foreach ($output as $line) {
        if (preg_match('/Processing pages \d+ through (\d+)/', $line, $matches)) {
            $pageCount = (int)$matches[1];
            writeDebugLog("Đếm trang với Ghostscript thành công", ['page_count' => $pageCount]);
            return $pageCount;
        }
    }

    // Nếu không tìm được, thử phương pháp khác
    try {
        $content = file_get_contents($pdfPath);
        preg_match_all('/\/Count\s+(\d+)/', $content, $matches);
        if (!empty($matches[1])) {
            $pageCount = max($matches[1]);
            writeDebugLog("Đếm trang bằng regex thành công", ['page_count' => $pageCount]);
            return $pageCount;
        }
    } catch (Exception $e) {
        writeDebugLog("Lỗi khi đếm trang bằng regex", ['error' => $e->getMessage()]);
    }

    writeDebugLog("Không thể đếm số trang PDF", ['file' => $pdfPath]);
    return false;
}

/**
 * Chuyển đổi toàn bộ trang PDF sang BMP sử dụng Ghostscript.
 *
 * @param string $pdfPath Đường dẫn đến file PDF.
 * @param int $pageCount Số trang trong PDF.
 * @return array|bool Mảng đường dẫn đến các file BMP hoặc false nếu thất bại.
 */
function convertPdfToBmpWithGsAllPages($pdfFile, $pageCount)
{
    writeDebugLog("Bắt đầu chuyển đổi toàn bộ trang với Ghostscript", ['file' => $pdfFile, 'page_count' => $pageCount]);
    
    $gs_command = '"C:\Program Files\gs\gs10.05.1\bin\gswin64c.exe"';
    $bmpFiles = [];

    try {
        // Tạo thư mục tạm cho các file BMP
        $tempDir = sys_get_temp_dir() . '/' . uniqid('pdf_to_bmp_gs_');
        if (!mkdir($tempDir, 0755, true)) {
            writeDebugLog("Không thể tạo thư mục tạm", ['temp_dir' => $tempDir]);
            return false;
        }

        writeDebugLog("Tạo thư mục tạm thành công", ['temp_dir' => $tempDir]);

        for ($page = 1; $page <= $pageCount; $page++) {
            writeDebugLog("Xử lý trang với Ghostscript", ['page' => $page, 'total' => $pageCount]);
            
            $bmpFile = $tempDir . '/page_' . str_pad($page, 3, '0', STR_PAD_LEFT) . '.bmp';

            // Cấu hình lệnh Ghostscript cho từng trang
            $gsCommand = sprintf(
                '%s -dSAFER -dBATCH -dNOPAUSE -dFirstPage=%d -dLastPage=%d -sDEVICE=bmp16m -r200 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=%s %s 2>&1',
                $gs_command,
                $page,
                $page,
                escapeshellarg($bmpFile),
                escapeshellarg($pdfFile)
            );

            exec($gsCommand, $output, $returnCode);
            writeDebugLog("Chạy lệnh Ghostscript cho trang " . $page, [
                'command' => $gsCommand, 
                'return_code' => $returnCode,
                'output_lines' => count($output)
            ]);

            if ($returnCode === 0 && file_exists($bmpFile) && filesize($bmpFile) > 100) {
                $bmpFiles[] = $bmpFile;
                writeDebugLog("Chuyển đổi trang " . $page . " thành công với Ghostscript", [
                    'page' => $page,
                    'bmp_file' => $bmpFile, 
                    'size' => filesize($bmpFile)
                ]);
            } else {
                writeDebugLog("Lỗi khi chuyển đổi trang " . $page . " với Ghostscript", [
                    'page' => $page,
                    'return_code' => $returnCode,
                    'file_exists' => file_exists($bmpFile),
                    'file_size' => file_exists($bmpFile) ? filesize($bmpFile) : 0,
                    'output' => implode("\n", array_slice($output, -5)) // Chỉ lấy 5 dòng cuối của output
                ]);
            }

            // Reset output array cho trang tiếp theo
            $output = [];
        }

        if (empty($bmpFiles)) {
            writeDebugLog("Không có trang nào được chuyển đổi thành công với Ghostscript", ['total_pages' => $pageCount]);
            return false;
        }

        writeDebugLog("Chuyển đổi hoàn tất với Ghostscript", ['total_files' => count($bmpFiles), 'files' => $bmpFiles]);
        return $bmpFiles;

    } catch (Exception $e) {
        writeDebugLog("Lỗi khi chạy Ghostscript cho toàn bộ trang", [
            'error' => $e->getMessage(), 
            'file' => $pdfFile,
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}

/**
 * Tạo file ZIP chứa tất cả các file BMP.
 *
 * @param array $bmpFiles Mảng đường dẫn các file BMP.
 * @param string $originalName Tên file PDF gốc.

 */
/**
 * Ghi log gỡ lỗi vào file debug.log.
 *
 * @param string $message Thông điệp log.
 * @param array $context Thông tin bổ sung.
 */
function writeDebugLog($message, $context = [])
{
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logMessage = "[$timestamp] $message$contextStr" . PHP_EOL;
    
    // Đảm bảo file log không quá lớn (giới hạn 10MB)
    if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
        // Xóa nửa đầu file log
        $content = file_get_contents($logFile);
        $lines = explode("\n", $content);
        $halfLines = array_slice($lines, count($lines) / 2);
        file_put_contents($logFile, implode("\n", $halfLines));
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}