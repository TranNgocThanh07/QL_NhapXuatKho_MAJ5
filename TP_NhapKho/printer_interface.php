<?php
// Xprinter D465B TCP/IP Print Interface - Chỉ sử dụng TSPL BITMAP
// Cấu hình máy in
$printer_ip = '192.168.110.198';
$printer_port = 9100;
$timeout = 10;
/**
 * Thử kết nối lại với máy in qua TCP
 * @param string $ip Địa chỉ IP máy in
 * @param int $port Cổng máy in
 * @param int $timeout Timeout cho mỗi lần thử (giây)
 * @param int $maxRetries Số lần thử lại tối đa
 * @param int $initialDelay Thời gian chờ ban đầu (mili giây)
 * @return resource|bool Trả về socket hoặc false nếu thất bại
 */
function retrySocketConnection($ip, $port, $timeout, $maxRetries = 3, $initialDelay = 1000) {
    $socket = false;
    $errno = 0;
    $errstr = '';

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $startTime = microtime(true);
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        
        if ($socket) {
            stream_set_timeout($socket, $timeout);
            writeDebugLog("Kết nối thành công ở lần thử $attempt", [
                'ip' => $ip, 
                'port' => $port, 
                'response_time' => $responseTime . 'ms'
            ]);
            return $socket;
        }

        writeDebugLog("Thử kết nối lần $attempt thất bại", [
            'ip' => $ip,
            'port' => $port,
            'error' => $errstr,
            'errno' => $errno,
            'response_time' => $responseTime . 'ms'
        ]);

        // Nếu lỗi là "Connection refused" (errno 111) thì máy chắc chắn đã tắt
        // Không cần thử lại nhiều lần
        if ($errno === 111 || $errno === 10061) { // Linux: 111, Windows: 10061
            writeDebugLog("Máy in đã tắt hoặc từ chối kết nối", ['errno' => $errno]);
            break;
        }

        if ($attempt < $maxRetries) {
            $delay = $initialDelay * pow(2, $attempt - 1);
            usleep($delay * 1000);
        }
    }

    return false;
}

// Xử lý file BMP từ query string (Cordova)
if (isset($_GET['filePath']) && !empty($_GET['filePath'])) {
    $filePath = urldecode($_GET['filePath']);
    session_start();
    $_SESSION['bmpFilePath'] = $filePath;
    if (isset($_GET['labelType'])) {
        $_SESSION['labelType'] = urldecode($_GET['labelType']);
    }
}

// Xử lý các yêu cầu POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xử lý test kết nối - sửa tên thuộc tính
    if (isset($_POST['test_connection'])) {
        echo testActualPrinterConnection($printer_ip, $printer_port, $timeout);
        exit;
    }
    
    // Xử lý test kết nối cũ (giữ lại để tương thích)
    if (isset($_POST['test_printer_connection'])) {
        echo testPrinterConnection($printer_ip, $printer_port, $timeout);
        exit;
    }
    
    // Xử lý hiệu chuẩn máy in
    if (isset($_POST['calibrate_printer'])) {
        echo calibratePrinter($printer_ip, $printer_port, $timeout);
        exit;
    }
    
    // Xử lý test in text
    if (isset($_POST['print_text_test'])) {
        echo printTextTest($printer_ip, $printer_port, $timeout);
        exit;
    }
    
    // Xử lý xóa bộ nhớ
    if (isset($_POST['clear_memory'])) {
        echo clearPrinterMemory($printer_ip, $printer_port, $timeout);
        exit;
    }
    
    // Xử lý cập nhật IP
    if (isset($_POST['action']) && $_POST['action'] === 'update_ip') {
        $printer_ip = filter_var($_POST['printer_ip'], FILTER_VALIDATE_IP);
        if ($printer_ip === false) {
            echo '<div class="alert alert-danger">❌ Địa chỉ IP không hợp lệ</div>';
            exit;
        }
        writeDebugLog("Cập nhật IP máy in", ['new_ip' => $printer_ip]);
        exit;
    }
    
    // Xử lý test ghi log
    if (isset($_POST['write_test_log'])) {
        writeDebugLog("Kiểm tra ghi log", ['test' => 'OK']);
        echo '<div class="alert alert-success">✅ Đã ghi log kiểm tra</div>';
        exit;
    }
    
    // Xử lý in với label
    if (isset($_POST['action']) && $_POST['action'] === 'print_with_label') {
        $labelType = $_POST['label_type'] ?? 'default';
        session_start();
        $filePath = isset($_SESSION['bmpFilePath']) ? $_SESSION['bmpFilePath'] : null;
        $bmpData = isset($_POST['bmp_data']) ? $_POST['bmp_data'] : null;
        $fileName = isset($_POST['file_name']) ? $_POST['file_name'] : null;

        if ($filePath && file_exists($filePath) && is_readable($filePath)) {
            $socket = retrySocketConnection($printer_ip, $printer_port, $timeout);
            if ($socket) {
                $result = printWithBitmap($socket, $filePath, $labelType);
                fclose($socket);
                unset($_SESSION['bmpFilePath']);
                cleanupSession();
                echo $result;
            } else {
                cleanupSession();
                echo handleError("Không thể kết nối đến máy in", ['ip' => $printer_ip, 'port' => $printer_port]);
            }
        } elseif ($bmpData && $fileName) {
            $fileName = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $fileName);
            $tempFile = sys_get_temp_dir() . '/' . uniqid('bmp_') . '_' . $fileName;

            $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $bmpData), true);
            if ($data === false) {
                echo handleError("Dữ liệu base64 không hợp lệ", ['file' => $fileName]);
                exit;
            }

            if (!is_writable(sys_get_temp_dir())) {
                echo handleError("Không thể ghi vào thư mục tạm", ['dir' => sys_get_temp_dir()]);
                exit;
            }

            if (file_put_contents($tempFile, $data) !== false) {
                $socket = retrySocketConnection($printer_ip, $printer_port, $timeout);
                if ($socket) {
                    $result = printWithBitmap($socket, $tempFile, $labelType);
                    fclose($socket);
                    unlink($tempFile);
                    echo $result;
                } else {
                    unlink($tempFile);
                    echo handleError("Không thể kết nối đến máy in", ['ip' => $printer_ip, 'port' => $printer_port]);
                }
            } else {
                echo handleError("Không thể lưu file BMP từ Data URL", ['file' => $tempFile]);
            }
        } else {
            echo handleError("Không tìm thấy dữ liệu BMP để in", []);
        }
        exit;
    }
}

/**
 * Ghi thông tin debug vào file debug.log
 */
function testActualPrinterConnection($ip, $port, $timeout = 3) {
    $startTime = microtime(true);
    
    // Sử dụng timeout ngắn hơn cho việc kiểm tra trạng thái
    $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    
    $endTime = microtime(true);
    $responseTime = round(($endTime - $startTime) * 1000, 2); // ms
    
    if ($socket) {
        // Thử gửi một lệnh đơn giản để đảm bảo máy in thực sự hoạt động
        $testCommand = "~!T\n"; // Lệnh reset đơn giản
        $writeResult = @fwrite($socket, $testCommand);
        fclose($socket);
        
        if ($writeResult !== false) {
            writeDebugLog("Kiểm tra kết nối máy in thành công", [
                'ip' => $ip, 
                'port' => $port, 
                'response_time' => $responseTime . 'ms',
                'bytes_written' => $writeResult
            ]);
            return '<div class="alert alert-success">✅ Kết nối thành công đến máy in tại ' . htmlspecialchars($ip) . ':' . $port . ' (' . $responseTime . 'ms)</div>';
        } else {
            writeDebugLog("Máy in không phản hồi lệnh", [
                'ip' => $ip, 
                'port' => $port, 
                'response_time' => $responseTime . 'ms'
            ]);
            return '<div class="alert alert-warning">⚠️ Kết nối được thiết lập nhưng máy in không phản hồi</div>';
        }
    } else {
        writeDebugLog("Không thể kết nối đến máy in", [
            'ip' => $ip, 
            'port' => $port, 
            'error' => $errstr, 
            'errno' => $errno,
            'response_time' => $responseTime . 'ms'
        ]);
        return '<div class="alert alert-danger">❌ Không thể kết nối đến máy in: ' . htmlspecialchars($errstr) . ' (Code: ' . $errno . ')</div>';
    }
}
function cleanupSession() {
    session_start();
    unset($_SESSION['bmpFilePath']);
    unset($_SESSION['labelType']);
    session_write_close();
}
function writeDebugLog($message, $additionalInfo = [])
{
    $logFile = 'debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    foreach ($additionalInfo as $key => $value) {
        $logMessage .= " | $key: $value";
    }
    $logMessage .= "\n";
    if (file_put_contents($logFile, $logMessage, FILE_APPEND) === false) {
        error_log("Không thể ghi vào file debug.log");
    }
}

/**
 * Test kết nối đến máy in
 */
function testPrinterConnection($ip, $port, $timeout) {
    $socket = retrySocketConnection($ip, $port, $timeout);
    if ($socket) {
        fclose($socket);
        return '<div class="alert alert-success">✅ Kết nối thành công đến máy in tại ' . $ip . ':' . $port . '</div>';
    } else {
        return '<div class="alert alert-danger">❌ Không thể kết nối đến máy in sau ' . $timeout . ' giây</div>';
    }
}
/**
 * Xử lý và trả về lỗi có cấu trúc
 * @param string $message Thông điệp lỗi
 * @param array $context Ngữ cảnh bổ sung
 * @return string HTML alert
 */
function handleError($message, $context = []) {
    $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
    $context['file'] = isset($caller['file']) ? basename($caller['file']) : 'unknown';
    $context['line'] = isset($caller['line']) ? $caller['line'] : 'unknown';
    
    writeDebugLog("Lỗi: $message", $context);
    return '<div class="alert alert-danger">❌ ' . htmlspecialchars($message) . '</div>';
}

/**
 * Hiệu chuẩn máy in (quan trọng cho việc in tem)
 */
function calibratePrinter($ip, $port, $timeout)
{
    $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        writeDebugLog("Lỗi kết nối khi hiệu chuẩn", ['ip' => $ip, 'port' => $port, 'error' => $errstr]);
        return '<div class="alert alert-danger">❌ Không thể gửi kết nối máy in. Vui lòng tắt và mở lại máy in!</div>';
    }

    $calibration_commands = "CLS\n"
        . "DIRECTION 1\n"
        . "SET GAP 2,0\n"
        . "SET CUTTER OFF\n"
        . "SET PARTIAL_CUTTER OFF\n"
        . "SET TEAR ON\n"
        . "SETUP 105,148,4,8,0\n"
        . "CALIBRATE\n"
        . "PRINT 1,1\n";

    $bytes_sent = fwrite($socket, $calibration_commands);
    fclose($socket);

    if ($bytes_sent > 0) {
        writeDebugLog("Hiệu chuẩn thành công", ['bytes_sent' => $bytes_sent]);
                return '<div class="alert alert-danger">✅ Hiệu chuẩn thành công!</div>';
    } else {
        writeDebugLog("Lỗi hiệu chuẩn", ['bytes_sent' => $bytes_sent]);
        return '<div class="alert alert-danger">❌ Hiệu chuẩn thất bại!</div>';
    }
}

/**
 * Xóa bộ nhớ máy in
 */
function clearPrinterMemory($ip, $port, $timeout)
{
    $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        writeDebugLog("Lỗi kết nối khi xóa bộ nhớ", ['ip' => $ip, 'port' => $port, 'error' => $errstr]);
        return '<div class="alert alert-danger">❌ Không thể gửi kết nối máy in. Vui lòng tắt và mở lại máy in!</div>';
    }

    $clear_commands = "~!T\n"
        . "CLS\n"
        . "KILL \"*.*\"\n";

    $bytes_sent = fwrite($socket, $clear_commands);
    fclose($socket);

    if ($bytes_sent > 0) {
        writeDebugLog("Xóa bộ nhớ máy in", ['bytes_sent' => $bytes_sent]);
                    return '<div class="alert alert-success">✅ Đã xóa bộ nhớ tạm thành công!</div>';
    } else {
        writeDebugLog("Lỗi xóa bộ nhớ", ['bytes_sent' => $bytes_sent]);
     
        return '<div class="alert alert-danger">❌ Xóa bộ nhớ tạm thất bại!</div>';
    }
}

/**
 * Test in text đơn giản
 */
function printTextTest($ip, $port, $timeout)
{
    $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        writeDebugLog("Lỗi kết nối khi in text", ['ip' => $ip, 'port' => $port, 'error' => $errstr]);
        return '<div class="alert alert-danger">❌ Không thể gửi kết nối máy in. Vui lòng tắt và mở lại máy in!</div>';
    }

    $test_commands = "SIZE 105 mm,148 mm\n"
        . "GAP 2 mm,0 mm\n"
        . "DIRECTION 1\n"
        . "SPEED 8\n"
        . "DENSITY 12\n"
        . "REFERENCE 0,0\n"
        . "OFFSET 0 mm\n"
        . "SET CUTTER OFF\n"
        . "SET PARTIAL_CUTTER OFF\n"
        . "SET TEAR ON\n"
        . "CLS\n"
        . "CODEPAGE 1252\n"
        . "TEXT 50,50,\"3\",0,1,1,\"TEST PRINT MINH ANH\"\n"
        . "TEXT 50,100,\"2\",0,1,1,\"Xprinter D465B\"\n"
        . "TEXT 50,150,\"1\",0,1,1,\"" . date('Y-m-d H:i:s') . "\"\n"
        . "PRINT 1,1\n";

    $bytes_sent = fwrite($socket, $test_commands);
    fclose($socket);

    if ($bytes_sent > 0) {
        writeDebugLog("In text thành công", ['bytes_sent' => $bytes_sent]);
            return '<div class="alert alert-success">✅ Đã gửi lệnh test in thành công!</div>';
    } else {
        writeDebugLog("Lỗi in text", ['bytes_sent' => $bytes_sent]);
        return '<div class="alert alert-danger">❌ Không thể gửi lệnh test!</div>';
    }
}

/**
 * Chuyển đổi BMP thành dữ liệu bitmap cho TSPL
 */
function convertBmpToBitmap($bmpFile, $maxWidth = 832, $maxHeight = 1180) {
    // Kiểm tra file tồn tại và có thể đọc
    if (!file_exists($bmpFile) || !is_readable($bmpFile)) {
        return handleError("File BMP không tồn tại hoặc không thể đọc", ['file' => $bmpFile]);
    }

    // Kiểm tra kích thước file
    $fileSize = filesize($bmpFile);
    if ($fileSize === false || $fileSize > 10 * 1024 * 1024) { // Giới hạn 10MB
        return handleError("File BMP quá lớn hoặc lỗi khi lấy kích thước", ['file' => $bmpFile, 'size' => $fileSize]);
    }

    $file = fopen($bmpFile, 'rb');
    if (!$file) {
        return handleError("Không thể mở file BMP", ['file' => $bmpFile]);
    }

    $header = fread($file, 54);
    if (strlen($header) < 54) {
        fclose($file);
        return handleError("Header BMP không hợp lệ", ['file' => $bmpFile]);
    }

    $width = unpack('V', substr($header, 18, 4))[1];
    $height = abs(unpack('V', substr($header, 22, 4))[1]);
    $bitsPerPixel = unpack('v', substr($header, 28, 2))[1];

    if ($width > $maxWidth || $height > $maxHeight) {
        fclose($file);
        return handleError("Kích thước BMP vượt quá giới hạn", ['width' => $width, 'height' => $height]);
    }

    if ($bitsPerPixel != 1 && $bitsPerPixel != 24) {
        fclose($file);
        return handleError("Định dạng BMP không được hỗ trợ", ['bits_per_pixel' => $bitsPerPixel]);
    }

    $rowSize = floor(($bitsPerPixel * $width + 31) / 32) * 4;
    $dataOffset = unpack('V', substr($header, 10, 4))[1];
    if (fseek($file, $dataOffset) !== 0) {
        fclose($file);
        return handleError("Không thể di chuyển con trỏ file đến dữ liệu BMP", ['file' => $bmpFile]);
    }

    $bitmapData = '';
    $bytesPerRow = ceil($width / 8);

    for ($y = 0; $y < $height; $y++) {
        $row = fread($file, $rowSize);
        if ($row === false) {
            fclose($file);
            return handleError("Lỗi khi đọc dữ liệu BMP", ['file' => $bmpFile, 'row' => $y]);
        }

        $binaryRow = '';
        if ($bitsPerPixel == 1) {
            for ($i = 0; $i < $bytesPerRow; $i++) {
                $byte = isset($row[$i]) ? ord($row[$i]) : 0;
                $binaryRow .= chr($byte);
            }
        } else {
            for ($x = 0; $x < $width; $x += 8) {
                $byte = 0;
                for ($bit = 0; $bit < 8 && ($x + $bit) < $width; $bit++) {
                    $pixelOffset = ($x + $bit) * 3;
                    if ($pixelOffset + 2 < strlen($row)) {
                        $b = ord($row[$pixelOffset]);
                        $g = ord($row[$pixelOffset + 1]);
                        $r = ord($row[$pixelOffset + 2]);
                        $gray = ($r + $g + $b) / 3;
                        if ($gray < 128) {
                            $byte |= (1 << (7 - $bit));
                        }
                    }
                }
                $binaryRow .= chr($byte);
            }
        }
        $bitmapData = $binaryRow . $bitmapData;
    }

    fclose($file);
    writeDebugLog("Chuyển đổi BMP thành bitmap thành công", ['file' => $bmpFile, 'width' => $width, 'height' => $height]);
    return ['width' => $width, 'height' => $height, 'data' => $bitmapData];
}
/**
 * In bitmap bằng phương pháp TSPL BITMAP với dữ liệu nhúng
 */
function printWithBitmap($socket, $file, $labelType)
{
    $bitmap = convertBmpToBitmap($file);
    if (!$bitmap) {
        writeDebugLog("Lỗi chuyển đổi BMP", ['file' => $file]);
        return '<div class="alert alert-danger">❌ Không thể chuyển đổi BMP</div>';
    }

    $width = $bitmap['width'];
    $height = $bitmap['height'];
    $data = $bitmap['data'];
    $bytesPerRow = ceil($width / 8);

    $invertedData = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $invertedData .= chr(~ord($data[$i]));
    }

    $commands = "SIZE 105 mm,148 mm\n"
        . "GAP 2 mm,0 mm\n"
        . "DIRECTION 1\n"
        . "SPEED 8\n"
        . "DENSITY 12\n"
        . "CLS\n"
        . "BITMAP 0,0,$bytesPerRow,$height,0,";
    fwrite($socket, $commands);
    fwrite($socket, $invertedData);
    fwrite($socket, "\nPRINT 1,1\n");

    writeDebugLog("In bitmap thành công", ['width' => $width, 'height' => $height, 'file' => $file, 'labelType' => $labelType]);
    return '<div class="alert alert-success">✅ Đã in thành công!</div>';
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>MA PRINTER</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        max-width: 100%;
    }
        html, body {
        overflow-x: hidden;
        max-width: 100vw;
    }
    body, input, button, select {
        touch-action: manipulation;
    }

    :root {
        /* Color Palette - Soft & Professional */
        --primary-color: #3b82f6;
        --primary-hover: #2563eb;
        --primary-light: #dbeafe;
        --success-color: #10b981;
        --success-hover: #059669;
        --success-light: #d1fae5;
        --warning-color: #f59e0b;
        --warning-hover: #d97706;
        --warning-light: #fef3c7;
        --danger-color: #ef4444;
        --danger-hover: #dc2626;
        --danger-light: #fee2e2;
        
        /* Neutral Colors */
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --gray-900: #111827;
        
        /* Background & Text */
        --bg-primary: var(--gray-50);
        --bg-card: #ffffff;
        --text-primary: var(--gray-900);
        --text-secondary: var(--gray-600);
        --text-muted: var(--gray-500);
        
        /* Border & Shadow */
        --border-color: var(--gray-200);
        --border-hover: var(--gray-300);
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        
        /* Border Radius */
        --radius-sm: 6px;
        --radius: 8px;
        --radius-lg: 12px;
        --radius-xl: 16px;
        
        /* Spacing */
        --space-1: 0.25rem;
        --space-2: 0.5rem;
        --space-3: 0.75rem;
        --space-4: 1rem;
        --space-5: 1.25rem;
        --space-6: 1.5rem;
        --space-8: 2rem;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        background-color: var(--bg-primary);
        color: var(--text-primary);
        line-height: 1.6;
        min-height: 100vh;
    }

    /* Header Styles */
    .header {
        background: var(--bg-card);
        border-bottom: 1px solid var(--border-color);
        padding: var(--space-4) 0;
        position: sticky;
        top: 0;
        z-index: 100;
        backdrop-filter: blur(10px);
        background-color: rgba(255, 255, 255, 0.95);
    }

    .header-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 var(--space-4);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-4);
    }

    .header-brand {
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    .printer-icon {
        width: 40px;
        height: 40px;
        background-color: var(--primary-color);
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
    }

    .header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    @media (max-width: 768px) {
        .container {
            padding: 0.75rem;
            gap: 1rem;
        }
    }
    
    @media (max-width: 480px) {
        .container {
            padding: 0.5rem;
        }
    }
    @media (max-width: 768px) {
        .header h1 {
            font-size: 1.1rem;
        }
        
        .printer-icon {
            width: 36px;
            height: 36px;
            font-size: 1.1rem;
        }
    }

    /* Container & Layout */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: var(--space-6) var(--space-4);
        display: grid;
        gap: var(--space-6);
        grid-template-columns: 1fr 1fr;
    }

    /* Container responsive */
    @media (max-width: 968px) {
        .container {
            grid-template-columns: 1fr;
            gap: 1rem;
            padding: 1rem;
        }
}

    /* Alert Container */
    #alert-container {
        position: fixed;
        top: 100px;
        left: 50%;
        transform: translateX(-50%);
        width: 90%;
        max-width: 600px;
        z-index: 1000;
        pointer-events: none;
    }

    #alert-container .alert {
        pointer-events: auto;
        margin-bottom: var(--space-3);
    }

    /* Card Styles */
    .card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        transition: all 0.2s ease;
        margin-bottom: var(--space-6);
    }

    .card:hover {
        box-shadow: var(--shadow);
        border-color: var(--border-hover);
    }

    .card-header {
        background-color: var(--gray-50);
        padding: var(--space-5) var(--space-6);
        border-bottom: 1px solid var(--border-color);
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: var(--space-2);
        margin: 0;
    }

.card-body {
    padding: var(--space-6); /* Padding 1.5rem */
    display: flex;
    flex-direction: column;
    align-items: center; /* Căn giữa nội dung */
}
    @media (max-width: 768px) {
        .card-header {
            padding: var(--space-4) var(--space-5);
        }
        
        .card-body {
            padding: var(--space-5);
        }
    }
    @media (max-width: 768px) {
        .form-input,
        .form-select {
            font-size: 16px; /* Prevents zoom on iOS */
            padding: 1rem;
        }
    }

    /* Info Banner */
    .info-banner {
        background-color: var(--primary-light);
        border: 1px solid var(--primary-color);
        border-radius: var(--radius);
        padding: var(--space-4);
        margin-bottom: var(--space-6);
        border-left: 4px solid var(--primary-color);
    }

    .info-banner strong {
        color: var(--primary-color);
        display: block;
        margin-bottom: var(--space-2);
    }

    /* Form Styles */
    .form-group {
        margin-bottom: var(--space-5);
    }

    .form-label {
        display: block;
        margin-bottom: var(--space-2);
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .form-input,
    .form-select {
        width: 100%;
        padding: var(--space-3) var(--space-4);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        font-size: 1rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.2s ease;
    }

    .form-input:focus,
    .form-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-light);
    }

    .form-input:hover,
    .form-select:hover {
        border-color: var(--border-hover);
    }

    .error-message {
        display: block;
        color: var(--danger-color);
        font-size: 0.875rem;
        margin-top: var(--space-2);
    }

    /* Button Styles */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-3);
        border: 2px solid transparent;
        border-radius: var(--radius);
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        min-height: 44px;
        min-width: 100px; /* Đảm bảo nút đủ rộng */
        max-width: 100%; /* Không vượt quá container */
        white-space: normal; /* Cho phép xuống dòng nếu văn bản dài */
        text-align: center;
    }

    .btn:active {
        transform: translateY(1px);
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    /* Button Variants */
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .btn-primary:hover:not(:disabled) {
        background-color: var(--primary-hover);
        border-color: var(--primary-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow);
    }

    .btn-success {
        background-color: var(--success-color);
        color: white;
        border-color: var(--success-color);
    }

    .btn-success:hover:not(:disabled) {
        background-color: var(--success-hover);
        border-color: var(--success-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow);
    }

    .btn-warning {
        background-color: var(--warning-color);
        color: white;
        border-color: var(--warning-color);
    }

    .btn-warning:hover:not(:disabled) {
        background-color: var(--warning-hover);
        border-color: var(--warning-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow);
    }

    .btn-danger {
        background-color: var(--danger-color);
        color: white;
        border-color: var(--danger-color);
    }

    .btn-danger:hover:not(:disabled) {
        background-color: var(--danger-hover);
        border-color: var(--danger-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow);
    }

    .btn-outline {
        background-color: transparent;
        color: var(--text-primary);
        border-color: var(--border-color);
    }

    .btn-outline:hover:not(:disabled) {
        background-color: var(--gray-50);
        border-color: var(--border-hover);
    }

    /* Button Grid */.btn-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr); /* 2 cột */
        grid-template-rows: repeat(2, auto); /* 2 dòng */
        gap: var(--space-4); /* Khoảng cách 1rem */
        width: 100%; /* Chiếm toàn bộ chiều rộng */
    }

@media (max-width: 768px) {
    .btn-grid {
        grid-template-columns: repeat(2, 1fr); /* Vẫn giữ 2 cột */
        gap: var(--space-3); /* Giảm khoảng cách xuống 0.75rem cho màn hình nhỏ */
    }
    .btn {
        padding: var(--space-3) var(--space-4); /* Giảm padding cho nút */
        min-height: 44px; /* Giảm chiều cao tối thiểu */
        font-size: 0.8rem; /* Giảm kích thước chữ */
    }
}

    /* Status Card */
    .status-card {
        background-color: var(--gray-50);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: var(--space-4);
        margin: var(--space-4) 0;
    }

    .status-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-3);
        border-radius: var(--radius-sm);
        transition: all 0.2s ease;
        margin-bottom: var(--space-1);
    }

    .status-item:hover {
        background-color: var(--gray-100);
    }

    .status-item:last-child {
        margin-bottom: 0;
    }

    .status-label {
        color: var(--text-secondary);
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: var(--space-2);
        font-weight: 500;
    }

    .status-value {
        font-weight: 600;
        color: var(--text-primary);
    }

    /* Connection Status */
    #connection-status.connected .status-value {
        color: var(--success-color);
    }

    #connection-status.disconnected .status-value {
        color: var(--danger-color);
    }

    /* Alert Styles */
    .alert {
        padding: var(--space-4);
        border-radius: var(--radius);
        margin: var(--space-4) 0;
        border-left: 4px solid;
        transition: all 0.3s ease;
    }

    .alert-success {
        background-color: var(--success-light);
        border-color: var(--success-color);
        color: var(--success-hover);
    }

    .alert-danger {
        background-color: var(--danger-light);
        border-color: var(--danger-color);
        color: var(--danger-hover);
    }

    .alert-warning {
        background-color: var(--warning-light);
        border-color: var(--warning-color);
        color: var(--warning-hover);
    }

    /* Preview Container */
    .preview-container {
        background: var(--bg-card);
        padding: var(--space-6);
        border-radius: var(--radius);
        border: 2px dashed var(--border-color);
        margin-bottom: var(--space-6);
        text-align: center;
        transition: all 0.2s ease;
        width: 100%; /* Chiếm toàn bộ chiều rộng của parent */
        min-height: 300px; /* Đảm bảo container đủ lớn */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    @media (min-width: 1024px) {
        #bmp-preview {
            max-width: 600px; /* Tăng kích thước tối đa trên màn hình lớn */
            min-width: 500px; /* Đảm bảo hình ảnh không quá nhỏ */
        }
        .preview-container {
            min-height: 400px; /* Tăng chiều cao container */
        }
    }
    .preview-container:hover {
        border-color: var(--primary-color);
        background-color: var(--gray-50);
    }

    .preview-container h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: var(--space-4);
    }

    #bmp-preview {
        width: 100%; /* Chiếm toàn bộ chiều rộng của container */
        max-height: 400px; /* Giới hạn chiều cao */
        height: auto; /* Giữ tỷ lệ */
        object-fit: contain;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        background-color: var(--bg-card);
    }

    /* Troubleshooting Steps */
    .troubleshoot-steps {
        list-style: none;
        counter-reset: step-counter;
        margin-top: var(--space-4);
    }

    .troubleshoot-steps li {
        counter-increment: step-counter;
        margin-bottom: var(--space-4);
        padding-left: 3rem;
        position: relative;
        line-height: 1.6;
    }

    .troubleshoot-steps li:before {
        content: counter(step-counter);
        position: absolute;
        left: 0;
        top: 0;
        background-color: var(--primary-color);
        color: white;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        font-weight: 700;
    }

    /* Loading Spinner */
    .loading {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: currentColor;
        animation: spin 1s linear infinite;
        margin-right: var(--space-2);
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Icon Styles */
    .icon {
        width: 1rem;
        height: 1rem;
        stroke-width: 2;
    }

    .card-title .icon {
        width: 1.25rem;
        height: 1.25rem;
    }

    /* Utility Classes */
    .hidden {
        display: none !important;
    }

    .text-center {
        text-align: center;
    }

    /* Touch Optimizations */
    @media (hover: none) and (pointer: coarse) {
        .btn {
            padding: var(--space-4) var(--space-6);
            min-height: 48px;
        }

        .form-input,
        .form-select {
            padding: var(--space-4);
            font-size: 16px; /* Prevents zoom on iOS */
        }
        
        .status-item {
            padding: var(--space-4);
        }
    }

    /* High contrast mode support */
    @media (prefers-contrast: high) {
        :root {
            --border-color: var(--gray-400);
            --border-hover: var(--gray-500);
        }
        
        .card {
            border-width: 2px;
        }
    }

    /* Reduced motion support */
    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* Dark mode preparation (commented out for now) */
    /*
    @media (prefers-color-scheme: dark) {
        :root {
            --bg-primary: var(--gray-900);
            --bg-card: var(--gray-800);
            --text-primary: var(--gray-100);
            --text-secondary: var(--gray-300);
            --border-color: var(--gray-700);
        }
    }
    */
    </style>
</head>

<body>
    <header class="header">
        <div class="header-content">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="printer-icon">🖨️</div>
                <h1>MA PRINTER</h1>
            </div>
            <button class="btn btn-primary" onclick="window.history.back()">
                <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 18l-6-6 6-6" />
                </svg> Quay lại
            </button>
        </div>
    </header>
    <div id="alert-container" style="position: fixed; top: 80px; left: 50%; transform: translateX(-50%); width: 90%; max-width: 600px; z-index: 1000;"></div>
    <div class="container">
        <!-- Left Column: Preview and Print -->
        <div class="left-column">
            <div class="card" id="print-section">
                <div class="card-header">
                    <h2 class="card-title">🖼️ Xem trước và In Tem</h2>
                </div>
                <div class="card-body">
                    <div class="preview-container" style="text-align: center; margin-bottom: 1.5rem;">
                        <h3>Xem trước tem</h3>
                        <img id="bmp-preview" src="" alt="Xem trước tem"
                            style="max-width: 100%; border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                    </div>
                    <form id="print-form" method="post" style="text-align: center;">
                        <input type="hidden" name="action" value="print_with_label">
                        <input type="hidden" name="bmp_data" id="bmp_data">
                        <input type="hidden" name="file_name" id="file_name">
                        <input type="hidden" name="label_type" id="label_type">
                        <button type="submit" class="btn btn-primary">
                            🖨️ In Tem
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Configuration, Testing & Maintenance, Troubleshooting -->
        <div class="right-column">
            <!-- Configuration -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">⚙️ Cấu hình</h2>
                </div>
                <div class="card-body">
                    <form method="post" id="config-form" style="text-align: center;">
                        <div class="form-group">
                            <label class="form-label" for="printer_select">Máy in:</label>
                            <select id="printer_select" name="printer_select" class="form-select" onchange="toggleCustomIP()">
                                <option value="printer1" <?php echo ($printer_ip == '192.168.1.100') ? 'selected' : ''; ?>>Máy in 1 (192.168.1.100)</option>
                                <option value="printer2" <?php echo ($printer_ip == '192.168.1.101') ? 'selected' : ''; ?>>Máy in 2 (192.168.1.101)</option>
                                <option value="printer3" <?php echo ($printer_ip == '192.168.1.102') ? 'selected' : ''; ?>>Máy in 3 (192.168.1.102)</option>
                                <option value="custom">Nhập IP thủ công</option>
                            </select>
                        </div>
                        <div class="form-group" id="custom_ip_group" style="display: none;">
                            <label class="form-label" for="printer_ip">IP Address máy in:</label>
                            <input type="text" id="printer_ip" name="printer_ip" class="form-input"
                                value="<?php echo htmlspecialchars($printer_ip); ?>" placeholder="192.168.1.100"
                                pattern="^(\d{1,3}\.){3}\d{1,3}$">
                            <span class="error-message"
                                style="display: none; color: var(--danger-color); font-size: 0.875rem;">
                                Vui lòng nhập địa chỉ IP hợp lệ (ví dụ: 192.168.1.100)
                            </span>
                        </div>
                        <div class="form-group">
                            <!-- <label class="form-label" for="printer_port">Cổng máy in:</label>
                            <input type="number" id="printer_port" name="printer_port" class="form-input"
                                value="<?php echo htmlspecialchars($printer_port); ?>" placeholder="9100"
                                min="1" max="65535" required> -->
                            <span class="error-message"
                                style="display: none; color: var(--danger-color); font-size: 0.875rem;">
                                Vui lòng nhập cổng hợp lệ (1-65535)
                            </span>
                        </div>
                        <button type="submit" name="action" value="update_config" class="btn btn-primary">
                            💾 Cập nhật cấu hình
                        </button>
                    </form>

                    <div class="status-card">
                        <div class="status-item">
                            <span class="status-label">
                                <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M7 7h10v10H7z" />
                                </svg> IP Address:
                            </span>
                            <span class="status-value"><?php echo htmlspecialchars($printer_ip); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">
                                <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14" />
                                </svg> Port:
                            </span>
                            <span class="status-value"><?php echo htmlspecialchars($printer_port); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">
                                <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.364-7.364l-1.414 1.414m-10.95 10.95l1.414 1.414m12.728-1.414l1.414-1.414M5.636 5.636l1.414-1.414" />
                                </svg> Timeout:
                            </span>
                            <span class="status-value"><?php echo htmlspecialchars($timeout); ?>s</span>
                        </div>
                        <div class="status-item" id="connection-status">
                            <span class="status-label">
                                <svg class="icon status-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 6 9 17l-5-5"/>
                                </svg> Trạng thái:
                            </span>
                            <span class="status-value status-text" style="color: var(--success-color)">Đang kiểm tra...</span>
                        </div>
                    </div>
                </div>
            </div>

<!-- Testing & Maintenance -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M11 21H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v7" />
                            <path d="M22 15v4a2 2 0 0 1-2 2h-7" />
                            <path d="M2 9h20" />
                            <path d="M6 14h2m2 0h5" />
                        </svg> Kiểm tra & Bảo trì</h2>
                </div>
                <div class="card-body">
                    <form method="post" id="maintenance-form">
                        <div class="btn-grid">
                            <button type="submit" name="action" value="test_connection" class="btn btn-success">
                                <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg> Kết nối
                            </button>
                        </form>
                        
                        <!-- Nút test in text -->
                        <form method="post" style="display: contents;">
                            <button type="submit" name="print_text_test" class="btn btn-success">
                                <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                </svg>Test in
                            </button>
                        </form>
                        
                        <!-- Nút hiệu chuẩn -->
                        <form method="post" style="display: contents;">
                            <button type="submit" name="calibrate_printer" class="btn btn-warning">
                                <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M6 10H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2" />
                                    <path d="M6 14H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0-2-2h-2" />
                                    <path d="M6 6h.01" />
                                    <path d="M6 18h.01" />
                                </svg>Hiệu chuẩn
                            </button>
                        </form>
                        
                        <!-- Nút xóa bộ nhớ -->
                        <form method="post" style="display: contents;">
                            <button type="submit" name="clear_memory" class="btn btn-danger">
                                <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18" />
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                </svg>Xóa bộ nhớ
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🚨 Hướng dẫn khắc phục</h2>
                </div>
                <div class="card-body">
                    <div class="info-banner">
                        <strong>Nếu không in được, thực hiện theo thứ tự:</strong>
                        <ol class="troubleshoot-steps">
                            <li><strong>Test kết nối</strong> - Đảm bảo kết nối ổn định</li>
                            <li><strong>Hiệu chuẩn máy in</strong> - Rất quan trọng cho tem</li>
                            <li><strong>Test in text</strong> - Kiểm tra máy in hoạt động</li>
                            <li><strong>Xóa bộ nhớ</strong> - Khi máy in bị treo</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- PHP Debug Section -->
            <?php
            if (isset($_POST['write_test_log'])) {
                writeDebugLog("Kiểm tra ghi log", ['test' => 'OK']);
                echo '<div class="card"><div class="alert alert-success">✅ Đã ghi log kiểm tra</div></div>';
            }
            if (isset($_POST['view_log']) && file_exists('debug.log')) {
                echo '<div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📜 Debug Log</h2>
                        </div>
                        <div class="card-body">
                            <pre style="background: #f8f9fa; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 0.875rem; line-height: 1.4;">'
                    . htmlspecialchars(file_get_contents('debug.log')) . '</pre>
                        </div>
                      </div>';
            }
            ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Lấy dữ liệu từ sessionStorage hoặc PHP
        const bmpData = sessionStorage.getItem('bmpFile');
        const fileName = sessionStorage.getItem('bmpFileName');
        const filePath = '<?php echo isset($_SESSION['bmpFilePath']) ? htmlspecialchars($_SESSION['bmpFilePath']) : ''; ?>';
        const printSection = document.getElementById('print-section');
        const previewImg = document.getElementById('bmp-preview');
        const form = document.getElementById('print-form');
        const bmpDataInput = document.getElementById('bmp_data');
        const fileNameInput = document.getElementById('file_name');
        const labelTypeInput = document.getElementById('label_type');

        // Cấu hình retry
        const RETRY_CONFIG = {
            maxRetries: 2,
            retryDelay: 2000,
            timeoutDuration: 10000
        };

        // Hàm hiển thị thông báo
        function showMessage(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = message;
            alertContainer.appendChild(alert);
            setTimeout(() => fadeOutAlert(alert), 4000);
        }

        // Hàm xóa session data
        function clearSessionData() {
            sessionStorage.removeItem('bmpFile');
            sessionStorage.removeItem('bmpFileName');
            sessionStorage.removeItem('labelType');
        }

        // Xử lý nút quay lại
        document.querySelector('.btn-primary').addEventListener('click', function(e) {
            e.preventDefault();
            clearSessionData();
            if (window.history.length > 1 && document.referrer && document.referrer !== window.location.href) {
                window.history.back();
            } else {
                window.location.href = '/';
            }
        });

        // Ngăn zoom trên thiết bị di động
        document.addEventListener('touchmove', function(e) {
            if (e.touches.length > 1) {
                e.preventDefault();
            }
        }, { passive: false });

        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(e) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

        // Xử lý form cấu hình
        const configForm = document.getElementById('config-form');
        if (configForm) {
            const printerSelect = configForm.querySelector('#printer_select');
            const ipInput = configForm.querySelector('#printer_ip');
            const portInput = configForm.querySelector('#printer_port');
            const ipErrorMessage = configForm.querySelector('#custom_ip_group .error-message');
            const portErrorMessage = configForm.querySelector('#printer_port + .error-message');

            window.toggleCustomIP = function() {
                const customIpGroup = document.getElementById('custom_ip_group');
                if (printerSelect.value === 'custom') {
                    customIpGroup.style.display = 'block';
                    ipInput.setAttribute('required', 'required');
                } else {
                    customIpGroup.style.display = 'none';
                    ipInput.removeAttribute('required');
                }
            };

            toggleCustomIP();

            if (ipInput && ipErrorMessage) {
                ipInput.addEventListener('input', function() {
                    if (ipInput.validity.valid || ipInput.value === '') {
                        ipInput.style.borderColor = 'var(--primary-color)';
                        ipErrorMessage.style.display = 'none';
                    } else {
                        ipInput.style.borderColor = 'var(--danger-color)';
                        ipErrorMessage.style.display = 'block';
                    }
                });
            }

            if (portInput && portErrorMessage) {
                portInput.addEventListener('input', function() {
                    const value = parseInt(portInput.value);
                    if (value >= 1 && value <= 65535) {
                        portInput.style.borderColor = 'var(--primary-color)';
                        portErrorMessage.style.display = 'none';
                    } else {
                        portInput.style.borderColor = 'var(--danger-color)';
                        portErrorMessage.style.display = 'block';
                    }
                });
            }
        }

        // Hàm retry với exponential backoff
        async function retryRequest(requestFn, retries = RETRY_CONFIG.maxRetries) {
            for (let attempt = 1; attempt <= retries; attempt++) {
                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), RETRY_CONFIG.timeoutDuration);
                    const result = await requestFn(controller.signal);
                    clearTimeout(timeoutId);
                    return result;
                } catch (error) {
                    console.warn(`Thử lại lần ${attempt}/${retries}:`, error.message);
                    if (attempt === retries) {
                        throw new Error(`Thất bại sau ${retries} lần thử: ${error.message}`);
                    }
                    const delay = RETRY_CONFIG.retryDelay * Math.pow(2, attempt - 1);
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            }
        }

        // Xử lý submit form
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', async function(e) {
                const submitBtn = e.submitter;
                if (!submitBtn) return;

                // CHỈ xử lý AJAX cho các form cần thiết, không chặn tất cả
                const needsAjax = form.id === 'print-form' || 
                                form.id === 'config-form' || 
                                submitBtn.name === 'test_printer_connection';
                
                if (!needsAjax) {
                    // Cho phép form submit bình thường cho các nút test, calibrate, clear memory
                    console.log('Allowing normal form submit for:', submitBtn.name);
                    return; // Không preventDefault, để form submit bình thường
                }

                // Chỉ preventDefault cho các form cần AJAX
                e.preventDefault();
                
                const originalText = submitBtn.innerHTML;
                let currentRetry = 0;

                const updateButtonState = (isLoading, retryCount = 0) => {
                    if (isLoading) {
                        const retryText = retryCount > 0 ? ` (Thử lại ${retryCount}/${RETRY_CONFIG.maxRetries})` : '';
                        submitBtn.innerHTML = `<span class="loading"></span> Đang xử lý...${retryText}`;
                        submitBtn.disabled = true;
                    } else {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                };

                try {
                    updateButtonState(true);
                    const result = await retryRequest(async (signal) => {
                        currentRetry++;
                        if (currentRetry > 1) {
                            updateButtonState(true, currentRetry - 1);
                        }
                        const formData = new FormData(form);
                        if (submitBtn.name && submitBtn.value) {
                            formData.set(submitBtn.name, submitBtn.value);
                        }
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData,
                            signal: signal
                        });
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return await response.text();
                    });

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = result;
                    const alertElements = tempDiv.querySelectorAll('.alert');
                    const alertContainer = document.getElementById('alert-container');

                    alertElements.forEach(alert => {
                        const clonedAlert = alert.cloneNode(true);
                        alertContainer.appendChild(clonedAlert);
                        setTimeout(() => fadeOutAlert(clonedAlert), 4000);
                    });

                    const isSuccess = result.includes('alert-success');
                    if (form.id === 'print-form' && isSuccess) {
                        setTimeout(() => {
                            if (document.referrer && document.referrer !== window.location.href) {
                                window.location.href = document.referrer;
                            } else {
                                window.history.back();
                            }
                        }, 3000);
                    }
                } catch (error) {
                    console.error('Lỗi cuối cùng:', error);
                    showMessage(`❌ ${error.message}`, 'danger');
                } finally {
                    updateButtonState(false);
                }
            });
        });

        // Lấy loại tem từ sessionStorage
        const labelType = sessionStorage.getItem('labelType') || 'system';

        // Hiển thị print section nếu có dữ liệu
        if (filePath || (bmpData && fileName)) {
            if (printSection) {
                printSection.classList.remove('hidden');
                if (bmpData && fileName) {
                    if (bmpDataInput) bmpDataInput.value = bmpData;
                    if (fileNameInput) fileNameInput.value = fileName;
                    if (previewImg) previewImg.src = bmpData;
                } else if (filePath && typeof cordova !== 'undefined') {
                    handleCordovaPreview(filePath, previewImg, printSection);
                }
                if (labelTypeInput) labelTypeInput.value = labelType;
            }
        } else {
            if (printSection) printSection.classList.add('hidden');
            showMessage('❌ Không tìm thấy dữ liệu tem để hiển thị', 'danger');
        }

        function handleCordovaPreview(filePath, previewImg, printSection) {
            if (typeof window.resolveLocalFileSystemURL === 'function') {
                window.resolveLocalFileSystemURL(filePath, function(fileEntry) {
                    fileEntry.file(function(file) {
                        const reader = new FileReader();
                        reader.onloadend = function() {
                            if (previewImg) previewImg.src = this.result;
                            if (printSection) printSection.classList.remove('hidden');
                        };
                        reader.onerror = function(error) {
                            console.error('Lỗi đọc file trong Cordova:', error);
                            showMessage('❌ Lỗi khi đọc file tem trong Cordova', 'danger');
                            if (printSection) printSection.classList.add('hidden');
                        };
                        reader.readAsDataURL(file);
                    }, function(error) {
                        console.error('Lỗi đọc file trong Cordova:', error);
                        showMessage('❌ Lỗi khi đọc file tem trong Cordova', 'danger');
                        if (printSection) printSection.classList.add('hidden');
                    });
                }, function(error) {
                    console.error('Lỗi truy cập filePath trong Cordova:', error);
                    showMessage('❌ Lỗi khi truy cập file tem trong Cordova', 'danger');
                    if (printSection) printSection.classList.add('hidden');
                });
            }
        }

        // Xử lý hiệu ứng touch cho nút
       

        // Xóa alert hiện có sau 5 giây
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => {
            setTimeout(() => fadeOutAlert(alert), 5000);
        });

        async function checkConnection() {
            const PING_TIMEOUT = 10000;
            const MAX_RETRIES = 2;
            const RETRY_DELAY = 1000;

            for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), PING_TIMEOUT);
                try {
                    const formData = new FormData();
                    formData.append('action', 'test_printer_connection');
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        signal: controller.signal,
                        cache: 'no-cache'
                    });
                    clearTimeout(timeoutId);
                    if (response.ok) {
                        const text = await response.text();
                        return text.includes('alert-success') && text.includes('Kết nối thành công');
                    }
                    throw new Error(`HTTP ${response.status}`);
                } catch (error) {
                    clearTimeout(timeoutId);
                    console.warn(`Thử kết nối máy in lần ${attempt}/${MAX_RETRIES}: ${error.message}`);
                    if (attempt < MAX_RETRIES) {
                        await new Promise(resolve => setTimeout(resolve, RETRY_DELAY));
                    }
                }
            }
            return false;
        }

        let connectionCheckPaused = false;
        async function updateConnectionStatus() {
            if (connectionCheckPaused) return;
            const connectionStatus = document.getElementById('connection-status');
            if (!connectionStatus) return;

            const statusIcon = connectionStatus.querySelector('.status-icon');
            const statusText = connectionStatus.querySelector('.status-text');
            if (!statusIcon || !statusText) return;

            statusText.textContent = 'Đang kiểm tra...';
            statusText.style.color = 'var(--warning-color)';
            statusIcon.innerHTML = '<path d="M12 2v10l4-4"/><circle cx="12" cy="12" r="10"/>';

            try {
                const isConnected = await checkConnection();
                if (isConnected) {
                    statusIcon.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
                    statusText.textContent = 'Đã kết nối';
                    statusText.style.color = 'var(--success-color)';
                    connectionStatus.classList.add('connected');
                    connectionStatus.classList.remove('disconnected');
                } else {
                    statusIcon.innerHTML = '<path d="M6 18L18 6M6 6l12 12"/>';
                    statusText.textContent = 'Mất kết nối';
                    statusText.style.color = 'var(--danger-color)';
                    connectionStatus.classList.remove('connected');
                    connectionStatus.classList.add('disconnected');
                    connectionCheckPaused = true;
                    setTimeout(() => { connectionCheckPaused = false; }, 10000);
                }
            } catch (error) {
                console.error('Lỗi kiểm tra kết nối máy in:', error);
                statusIcon.innerHTML = '<path d="M12 9v4m0 4h.01M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/>';
                statusText.textContent = 'Lỗi kiểm tra kết nối';
                statusText.style.color = 'var(--warning-color)';
                connectionStatus.classList.remove('connected');
                connectionStatus.classList.add('disconnected');
            }
        }

        updateConnectionStatus();
        setInterval(updateConnectionStatus, 10000);

        function fadeOutAlert(alert) {
            if (alert && alert.parentNode) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                const isSuccess = alert.classList.contains('alert-success');
                setTimeout(() => {
                    if (alert.parentNode) {
                        if (isSuccess) {
                            const printMessage = document.createElement('div');
                            printMessage.className = 'alert alert-success print-message';
                            printMessage.innerHTML = '✅ Đã in';
                            printMessage.style.opacity = '0';
                            printMessage.style.transform = 'translateY(-20px)';
                            printMessage.style.transition = 'all 0.3s ease';
                            alert.parentNode.replaceChild(printMessage, alert);
                            setTimeout(() => {
                                printMessage.style.opacity = '1';
                                printMessage.style.transform = 'translateY(0)';
                            }, 10);
                            setTimeout(() => fadeOutAlert(printMessage), 3000);
                        } else {
                            alert.remove();
                        }
                    }
                }, 300);
            }
        }

        window.addEventListener('error', function(event) {
            console.error('Global error:', event.error);
            showMessage('❌ Đã xảy ra lỗi không mong muốn', 'danger');
        });

        window.addEventListener('unhandledrejection', function(event) {
            console.error('Unhandled promise rejection:', event.reason);
            showMessage('❌ Lỗi xử lý bất đồng bộ', 'danger');
            event.preventDefault();
        });
    });
    </script>
    <noscript>
        <div class="alert alert-warning">
            ⚠️ JavaScript bị tắt. Một số tính năng như xem trước tem sẽ không hoạt động. Vui lòng bật JavaScript để có trải nghiệm tốt nhất.
        </div>
    </noscript>
</body>

</html>