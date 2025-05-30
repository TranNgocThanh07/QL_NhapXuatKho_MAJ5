<?php
require_once __DIR__ . '/vendor/autoload.php';
/**
 * Chuyển đổi file PDF sang định dạng BMP.
 *
 * @param string $pdfPath Đường dẫn đến file PDF đầu vào.
 * @param string $outputDir Thư mục đầu ra cho file BMP (mặc định là thư mục tạm của hệ thống).
 * @param int $dpi Độ phân giải của ảnh BMP (mặc định 300).
 * @return string|bool Đường dẫn đến file BMP đầu ra hoặc false nếu thất bại.
 */
function convertPdfToBmp($pdfPath)
{
    // Kiểm tra file PDF có tồn tại và hợp lệ
    if (!file_exists($pdfPath) || filesize($pdfPath) < 100) {
        writeDebugLog("File PDF không hợp lệ hoặc không tồn tại", ['file' => $pdfPath]);
        return false;
    }

    try {
        // Kiểm tra xem Imagick có sẵn không
        if (!extension_loaded('imagick')) {
            writeDebugLog("Imagick không được cài đặt, chuyển sang Ghostscript", ['file' => $pdfPath]);
            return convertPdfToBmpWithGs($pdfPath);
        }

        // Tạo đối tượng Imagick
        $imagick = new Imagick();

        // Cấu hình để render PDF với độ phân giải cao
        $imagick->setResolution(150, 150);

        // Đọc trang đầu tiên của PDF
        $imagick->readImage($pdfPath . '[0]'); // [0] = trang đầu tiên

        // Chuyển sang định dạng đen trắng
        $imagick->setImageType(Imagick::IMGTYPE_BILEVEL);
        $imagick->setImageColorspace(Imagick::COLORSPACE_GRAY);

        // Điều chỉnh kích thước nếu cần (max 832x1180)
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if ($width > 832 || $height > 1180) {
            $scale = min(832 / $width, 1180 / $height);
            $newWidth = (int)($width * $scale);
            $newHeight = (int)($height * $scale);
            $imagick->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
            writeDebugLog("Điều chỉnh kích thước hình ảnh", ['original_width' => $width, 'original_height' => $height, 'new_width' => $newWidth, 'new_height' => $newHeight]);
        }

        // Lưu thành BMP
        $bmpFile = sys_get_temp_dir() . '/' . uniqid('pdf_to_bmp_') . '.bmp';
        $imagick->setImageFormat('BMP');
        $imagick->writeImage($bmpFile);

        $imagick->clear();
        $imagick->destroy();

        // Kiểm tra file BMP được tạo
        if (file_exists($bmpFile) && filesize($bmpFile) > 100) {
            writeDebugLog("Chuyển đổi PDF sang BMP thành công với Imagick", ['bmp_file' => $bmpFile, 'size' => filesize($bmpFile)]);
            return $bmpFile;
        }

        writeDebugLog("File BMP không hợp lệ sau khi chuyển đổi với Imagick", ['bmp_file' => $bmpFile]);
        return false;
    } catch (Exception $e) {
        writeDebugLog("Lỗi khi chuyển đổi PDF với Imagick", ['error' => $e->getMessage(), 'file' => $pdfPath]);
        // Thử phương pháp dự phòng với Ghostscript
        return convertPdfToBmpWithGs($pdfPath);
    }
}

/**
 * Chuyển đổi PDF sang BMP sử dụng Imagick.
 *
 * @param string $pdfPath Đường dẫn đến file PDF.
 * @param string $bmpPath Đường dẫn đến file BMP đầu ra.
 * @param int $dpi Độ phân giải.
 * @return string|bool Đường dẫn đến file BMP hoặc false nếu thất bại.
 */
/**
 * Chuyển đổi PDF sang BMP sử dụng Ghostscript.
 *
 * @param string $pdfPath Đường dẫn đến file PDF.
 * @param string $bmpPath Đường dẫn đến file BMP đầu ra.
 * @param int $dpi Độ phân giải.
 * @return string|bool Đường dẫn đến file BMP hoặc false nếu thất bại.
 */
function convertPdfToBmpWithGs($pdfFile)
{
    // Sử dụng gswin64c.exe cho Windows 64-bit
    $gs_command = '"C:\Program Files\gs\gs10.05.1\bin\gswin64c.exe"'; // Cập nhật phiên bản nếu cần
    try {
        $bmpFile = sys_get_temp_dir() . '/' . uniqid('pdf_to_bmp_gs_') . '.bmp';

        // Cấu hình lệnh Ghostscript
        $gsCommand = sprintf(
            '%s -dSAFER -dBATCH -dNOPAUSE -dFirstPage=1 -dLastPage=1 -sDEVICE=bmp16m -r200 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=%s %s 2>&1',
            $gs_command,
            escapeshellarg($bmpFile),
            escapeshellarg($pdfFile)
        );

        exec($gsCommand, $output, $returnCode);
        writeDebugLog("Chạy lệnh Ghostscript", ['command' => $gsCommand, 'return_code' => $returnCode]);

        if ($returnCode === 0 && file_exists($bmpFile) && filesize($bmpFile) > 100) {
            writeDebugLog("Chuyển đổi PDF sang BMP thành công với Ghostscript", ['bmp_file' => $bmpFile, 'size' => filesize($bmpFile)]);
            return $bmpFile;
        }

        writeDebugLog("Lỗi khi chuyển đổi với Ghostscript", ['output' => implode("\n", $output)]);
        return false;
    } catch (Exception $e) {
        writeDebugLog("Lỗi khi chạy Ghostscript", ['error' => $e->getMessage(), 'file' => $pdfFile]);
        return false;
    }
}

/**
 * Ghi log gỡ lỗi vào file debug.log.
 *
 * @param string $message Thông điệp log.
 */
function writeDebugLog($message)
{
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}