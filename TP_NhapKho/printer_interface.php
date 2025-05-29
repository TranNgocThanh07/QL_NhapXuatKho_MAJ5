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
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if ($socket) {
            stream_set_timeout($socket, $timeout); // Thiết lập timeout cho I/O
            writeDebugLog("Kết nối thành công ở lần thử $attempt", ['ip' => $ip, 'port' => $port]);
            return $socket;
        }

        writeDebugLog("Thử kết nối lần $attempt thất bại", [
            'ip' => $ip,
            'port' => $port,
            'error' => $errstr,
            'errno' => $errno
        ]);

        if ($attempt < $maxRetries) {
            $delay = $initialDelay * pow(2, $attempt - 1);
            usleep($delay * 1000);
        }
    }

    writeDebugLog("Kết nối thất bại sau $maxRetries lần thử", ['ip' => $ip, 'port' => $port]);
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
    if (isset($_POST['test_connection'])) {
        echo testPrinterConnection($printer_ip, $printer_port, $timeout);
    } elseif (isset($_POST['calibrate_printer'])) {
        echo calibratePrinter($printer_ip, $printer_port, $timeout);
    } elseif (isset($_POST['print_text_test'])) {
        echo printTextTest($printer_ip, $printer_port, $timeout);
    } elseif ($_POST['action'] === 'update_ip') {
        $printer_ip = filter_var($_POST['printer_ip'], FILTER_VALIDATE_IP);
        if ($printer_ip === false) {
            echo '<div class="alert alert-danger">❌ Địa chỉ IP không hợp lệ</div>';
            exit;
        }
        writeDebugLog("Cập nhật IP máy in", ['new_ip' => $printer_ip]);
    } elseif (isset($_POST['clear_memory'])) {
        echo clearPrinterMemory($printer_ip, $printer_port, $timeout);
    } elseif (isset($_POST['write_test_log'])) {
        writeDebugLog("Kiểm tra ghi log", ['test' => 'OK']);
        echo '<div class="alert alert-success">✅ Đã ghi log kiểm tra</div>';
    } 
    if ($_POST['action'] === 'print_with_label') {
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
                cleanupSession(); // Dọn dẹp session
                echo $result;
            } else {
                cleanupSession(); // Dọn dẹp session
                echo handleError("Không thể kết nối đến máy in", ['ip' => $printer_ip, 'port' => $printer_port]);
            }
        } elseif ($bmpData && $fileName) {
            // Kiểm tra và vệ sinh tên file
            $fileName = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $fileName);
            $tempFile = sys_get_temp_dir() . '/' . uniqid('bmp_') . '_' . $fileName;

            // Kiểm tra dữ liệu base64
            $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $bmpData), true);
            if ($data === false) {
                echo handleError("Dữ liệu base64 không hợp lệ", ['file' => $fileName]);
                return;
            }

            // Kiểm tra quyền ghi vào thư mục tạm
            if (!is_writable(sys_get_temp_dir())) {
                echo handleError("Không thể ghi vào thư mục tạm", ['dir' => sys_get_temp_dir()]);
                return;
            }

            if (file_put_contents($tempFile, $data) !== false) {
                $socket = retrySocketConnection($printer_ip, $printer_port, $timeout);
                if ($socket) {
                    $result = printWithBitmap($socket, $tempFile, $labelType);
                    fclose($socket);
                    unlink($tempFile); // Dọn dẹp file tạm
                    echo $result;
                } else {
                    unlink($tempFile); // Dọn dẹp file tạm ngay cả khi lỗi
                    echo handleError("Không thể kết nối đến máy in", ['ip' => $printer_ip, 'port' => $printer_port]);
                }
            } else {
                echo handleError("Không thể lưu file BMP từ Data URL", ['file' => $tempFile]);
            }
        } else {
            echo handleError("Không tìm thấy dữ liệu BMP để in", []);
        }
    }
}

/**
 * Ghi thông tin debug vào file debug.log
 */

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
        return '<div class="alert alert-danger">❌ Không thể kết nối đến máy in</div>';
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
        return '<div class="alert alert-success">✅ Đã hiệu chuẩn máy in thành công! (' . $bytes_sent . ' bytes)</div>';
    } else {
        writeDebugLog("Lỗi hiệu chuẩn", ['bytes_sent' => $bytes_sent]);
        return '<div class="alert alert-danger">❌ Không thể hiệu chuẩn máy in</div>';
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
        return '<div class="alert alert-danger">❌ Không thể kết nối đến máy in</div>';
    }

    $clear_commands = "~!T\n"
        . "CLS\n"
        . "KILL \"*.*\"\n";

    $bytes_sent = fwrite($socket, $clear_commands);
    fclose($socket);

    writeDebugLog("Xóa bộ nhớ máy in", ['bytes_sent' => $bytes_sent]);
    return '<div class="alert alert-success">✅ Đã xóa bộ nhớ máy in</div>';
}

/**
 * Test in text đơn giản
 */
function printTextTest($ip, $port, $timeout)
{
    $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        writeDebugLog("Lỗi kết nối khi in text", ['ip' => $ip, 'port' => $port, 'error' => $errstr]);
        return '<div class="alert alert-danger">❌ Không thể kết nối đến máy in</div>';
    }

    $test_commands = "SIZE 105 mm,148 mm\n"
        . "GAP 2 mm,0 mm\n"
        . "DIRECTION 1\n"
        . "REFERENCE 0,0\n"
        . "OFFSET 0 mm\n"
        . "SET CUTTER OFF\n"
        . "SET PARTIAL_CUTTER OFF\n"
        . "SET TEAR ON\n"
        . "CLS\n"
        . "CODEPAGE 1252\n"
        . "TEXT 50,50,\"3\",0,1,1,\"TEST PRINT\"\n"
        . "TEXT 50,100,\"2\",0,1,1,\"Xprinter D465B\"\n"
        . "TEXT 50,150,\"1\",0,1,1,\"" . date('Y-m-d H:i:s') . "\"\n"
        . "PRINT 1,1\n";

    $bytes_sent = fwrite($socket, $test_commands);
    fclose($socket);

    if ($bytes_sent > 0) {
        writeDebugLog("In text thành công", ['bytes_sent' => $bytes_sent]);
        return '<div class="alert alert-success">✅ Đã gửi lệnh test text (' . $bytes_sent . ' bytes)</div>';
    } else {
        writeDebugLog("Lỗi in text", ['bytes_sent' => $bytes_sent]);
        return '<div class="alert alert-danger">❌ Không thể gửi lệnh test</div>';
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
    //return '<div class="alert alert-success">✅ Đã in bitmap thành công! Kích thước: ' . $width . 'x' . $height . ' pixels, Loại tem: ' . $labelType . '</div>';
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>MA PRINTER - TSPL BITMAP</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --primary-color: #2563eb;
        --primary-hover: #1d4ed8;
        --success-color: #059669;
        --success-hover: #047857;
        --warning-color: #d97706;
        --warning-hover: #b45309;
        --danger-color: #dc2626;
        --danger-hover: #b91c1c;
        --bg-primary: #f8fafc;
        --bg-card: #ffffff;
        --text-primary: #1e293b;
        --text-secondary: #64748b;
        --border-color: #e2e8f0;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        --radius: 12px;
        --radius-sm: 8px;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        color: var(--text-primary);
        line-height: 1.6;
    }

    .header {
        background: var(--bg-card);
        padding: 1rem;
        box-shadow: var(--shadow);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .header-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .header h1 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary-color);
    }

    .printer-icon {
        width: 2.5rem;
        height: 2.5rem;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.25rem;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        /* Linh hoạt hơn */
    }

    @media (min-width: 768px) {
        .container {
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        }

        .card.full-width {
            grid-column: 1 / -1;
        }

        .card.span-2 {
            grid-column: span 2;
        }
    }

    .card {
        background: var(--bg-card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: all 0.2s ease;
    }

    .card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }

    .card-header {
        background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-primary);
    }

    .card-body {
        padding: 1.5rem;
    }

    .info-banner {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        border: 1px solid #93c5fd;
        border-radius: var(--radius-sm);
        padding: 1rem;
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--primary-color);
    }

    .info-banner strong {
        color: var(--primary-color);
        display: block;
        margin-bottom: 0.5rem;
    }

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .form-input,
    .form-select {
        width: 100%;
        padding: 0.875rem;
        border: 2px solid var(--border-color);
        border-radius: var(--radius-sm);
        font-size: 1rem;
        transition: all 0.2s ease;
        background: var(--bg-card);
    }

    .form-input:focus,
    .form-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.875rem 1.25rem;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        min-height: 44px;
        /* Touch target */
        white-space: nowrap;
    }

    .btn:active {
        transform: translateY(1px);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, var(--primary-hover), #1e40af);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success-color), var(--success-hover));
        color: white;
    }

    .btn-success:hover {
        background: linear-gradient(135deg, var(--success-hover), #065f46);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning-color), var(--warning-hover));
        color: white;
    }

    .btn-warning:hover {
        background: linear-gradient(135deg, var(--warning-hover), #92400e);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger-color), var(--danger-hover));
        color: white;
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, var(--danger-hover), #991b1b);
    }

    .btn-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        /* Luôn 2 cột */
        gap: 1rem;
    }

    .error-message {
        margin-top: 0.5rem;
        display: block;
    }

    .preview-container:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }

    .btn:hover {
        transform: translateY(-2px);
    }

    .status-item:hover {
        background: #e2e8f0;
    }

    .status-card {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        padding: 1rem;
        margin: 1rem 0;
        display: grid;
        gap: 0.5rem;
    }


    .status-item {
        display: flex;
        font-weight:500;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        /* background: var(--bg-card); */
        border-radius: var(--radius-sm);
        transition: all 0.2s ease;
    }

    .status-item:hover {
        background: #e2e8f0;
    }

    .status-item:last-child {
        border-bottom: none;
    }

    .status-label {
        color: var(--text-secondary);
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .alert.alert-success.print-message {
    font-size: 0.875rem;
    padding: 0.75rem;
    margin: 0.5rem 0;
    }

    #alert-container .alert {
        margin: 0.5rem 0;
        width: 100%;
        box-sizing: border-box;
    }
    .status-value {
        font-weight: 600;
        color: var(--text-primary);
    }

    .alert {
        padding: 1rem;
        border-radius: var(--radius-sm);
        margin: 1rem 0;
        border-left: 4px solid;
    }

    .alert-success {
        background: #dcfce7;
        border-color: var(--success-color);
        color: #166534;
    }

    .alert-danger {
        background: #fef2f2;
        border-color: var(--danger-color);
        color: #991b1b;
    }

    .troubleshoot-steps {
        list-style: none;
        counter-reset: step-counter;
    }

    .troubleshoot-steps li {
        counter-increment: step-counter;
        margin-bottom: 0.75rem;
        padding-left: 2.5rem;
        position: relative;
    }

    .troubleshoot-steps li:before {
        content: counter(step-counter);
        position: absolute;
        left: 0;
        top: 0;
        background: var(--primary-color);
        color: white;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
    }

    .preview-container {
        background: var(--bg-card);
        padding: 1rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        margin-bottom: 1.5rem;
    }

    .loading {
        display: inline-block;
        width: 1.2rem;
        height: 1.2rem;
        border: 3px solid #ffffff40;
        border-radius: 50%;
        border-top-color: #ffffff;
        animation: spin 1s ease-in-out infinite;
        margin-right: 0.5rem;
        vertical-align: middle;
    }

    .preview-container h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    #bmp-preview {
        max-height: 400px;
        object-fit: contain;
    }

    .requirements-list {
        display: grid;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .requirement-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        background: #f8fafc;
        border-radius: var(--radius-sm);
    }

    .check-icon {
        width: 1.25rem;
        height: 1.25rem;
        background: var(--success-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.75rem;
    }

    .hidden {
        display: none;
    }

    /* Mobile optimizations */
    @media (max-width: 768px) {
        .container {
            padding: 0.5rem;
            gap: 0.75rem;
        }

        .card-body {
            padding: 1rem;
        }

        .btn-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .header h1 {
            font-size: 1.125rem;
        }

        .form-input,
        .form-select {
            font-size: 16px;
            /* Prevent zoom on iOS */
        }
    }

    @media (min-width: 768px) {
        .container {
            grid-template-columns: 1fr 1fr;
            align-items: start;
        }

        .card.full-width {
            grid-column: 1 / -1;
        }

        .btn-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (min-width: 1024px) {
        .container {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .card.span-2 {
            grid-column: span 2;
        }
    }

    /* Loading animation */
    .loading {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid #ffffff40;
        border-radius: 50%;
        border-top-color: #ffffff;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Touch improvements */
    @media (hover: none) and (pointer: coarse) {
        .btn {
            padding: 1rem 1.25rem;
        }

        .form-input,
        .form-select {
            padding: 1rem;
        }
    }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-content">
            <div class="printer-icon">🖨️</div>
            <h1>MA PRINTER - TSPL BITMAP</h1>
        </div>
    </header>
    <div id="alert-container" style="position: fixed; top: 80px; left: 50%; transform: translateX(-50%); width: 90%; max-width: 600px; z-index: 1000;"></div>
    <div class="container">
        <!-- Print Section -->
        <div class="card full-width" id="print-section">
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

        <!-- Configuration -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">⚙️ Cấu hình</h2>
            </div>
            <div class="card-body">
                <label class="form-label" for="printer_ip">IP Address máy in:</label>
                <form method="post" id="config-form" style="text-align: center;">
                    <div class="form-group">
                        
                        <input type="text" id="printer_ip" name="printer_ip" class="form-input"
                            value="<?php echo htmlspecialchars($printer_ip); ?>" placeholder="192.168.1.100"
                            pattern="^(\d{1,3}\.){3}\d{1,3}$" required>
                        <span class="error-message"
                            style="display: none; color: var(--danger-color); font-size: 0.875rem;">
                            Vui lòng nhập địa chỉ IP hợp lệ (ví dụ: 192.168.1.100)
                        </span>
                        
                    </div>
                    <button type="submit" name="action" value="update_ip" class="btn btn-primary">
                        💾 Cập nhật IP
                    </button>
                </form>

                <!-- Thay thế phần status-card trong HTML -->
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
                    <!-- Thêm sẵn phần tử connection status -->
                    <div class="status-item" id="connection-status">
                        <span class="status-label">
                            <svg class="icon status-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6 9 17l-5-5"/>
                            </svg> Trạng thái kết nối:
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
                <div class="btn-grid">
                    <form method="post" style="display: contents;">
                        <button type="submit" name="test_connection" class="btn btn-success">
                            <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path d="M20 6 9 17l-5-5" />
                            </svg> Kết nối máy
                        </button>
                    </form>
                    <form method="post" style="display: contents;">
                        <button type="submit" name="print_text_test" class="btn btn-success">
                            <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
                            </svg>Test in text
                        </button>
                    </form>
                    <form method="post" style="display: contents;">
                        <button type="submit" name="calibrate_printer" class="btn btn-warning">
                            <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path d="M6 10H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2" />
                                <path d="M6 14H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2" />
                                <path d="M6 6h.01" />
                                <path d="M6 18h.01" />
                            </svg>Hiệu chuẩn
                        </button>
                    </form>
                    <form method="post" style="display: contents;">
                        <button type="submit" name="clear_memory" class="btn btn-danger">
                            <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path d="M3 6h18" />
                                <path
                                    d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                            </svg>Xóa bộ nhớ
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <!-- Troubleshooting -->
        <div class="card span-2">
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

        <!-- BMP Requirements -->


        <!-- Debug Tools -->
        <!-- <div class="card">
            <div class="card-header">
                <h2 class="card-title">🔍 Debug Tools</h2>
            </div>
            <div class="card-body">
                <div class="btn-grid">
                    <form method="post" style="display: contents;">
                        <button type="submit" name="write_test_log" class="btn btn-success">
                            📝 Ghi log test
                        </button>
                    </form>
                    <form method="post" style="display: contents;">
                        <button type="submit" name="view_log" class="btn btn-primary">
                            📜 Xem debug log
                        </button>
                    </form>
                </div>
            </div>
        </div> -->

        <!-- PHP Debug Section -->
        <?php
        if (isset($_POST['write_test_log'])) {
            writeDebugLog("Kiểm tra ghi log", ['test' => 'OK']);
            echo '<div class="alert alert-success">✅ Đã ghi log kiểm tra</div>';
        }
        if (isset($_POST['view_log']) && file_exists('debug.log')) {
            echo '<div class="card full-width">
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
            maxRetries: 3,
            retryDelay: 2000, // 2 giây
            timeoutDuration: 10000 // 10 giây
        };

        // Xử lý form cấu hình IP
        const configForm = document.getElementById('config-form');
        if (configForm) {
            const ipInput = configForm.querySelector('#printer_ip');
            const errorMessage = configForm.querySelector('.error-message');

            if (ipInput && errorMessage) {
                ipInput.addEventListener('input', function() {
                    if (ipInput.validity.valid) {
                        ipInput.style.borderColor = 'var(--primary-color)';
                        errorMessage.style.display = 'none';
                    } else {
                        ipInput.style.borderColor = 'var(--danger-color)';
                        errorMessage.style.display = 'block';
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
                    
                    // Exponential backoff: 2s, 4s, 8s
                    const delay = RETRY_CONFIG.retryDelay * Math.pow(2, attempt - 1);
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            }
        }

        // Enhanced form submission với retry mechanism
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn) return;

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
                        alertContainer.appendChild(clonedAlert); // Chèn vào alert-container
                        setTimeout(() => {
                            fadeOutAlert(clonedAlert);
                        }, 4000);
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

                    // Điền thông tin vào form
                    if (bmpData && fileName) {
                        if (bmpDataInput) bmpDataInput.value = bmpData;
                        if (fileNameInput) fileNameInput.value = fileName;
                        if (previewImg) previewImg.src = bmpData;
                    } else if (filePath && typeof cordova !== 'undefined') {
                        // Xử lý xem trước trong Cordova
                        handleCordovaPreview(filePath, previewImg, printSection);
                    }
                    
                    if (labelTypeInput) labelTypeInput.value = labelType;

                    // Xử lý submit form in với retry
                    if (form && !form.hasAttribute('data-listener-added')) {
                        form.setAttribute('data-listener-added', 'true');
                        form.addEventListener('submit', async function(event) {
                            event.preventDefault();
                            
                            try {
                                const formData = new FormData(form);
                                
                                const result = await retryRequest(async (signal) => {
                                    const response = await fetch(window.location.href, {
                                        method: 'POST',
                                        body: formData,
                                        signal: signal
                                    });
                                    
                                    if (!response.ok) {
                                        throw new Error(`HTTP ${response.status}`);
                                    }
                                    
                                    return await response.text();
                                });

                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = result;
                                const alertElements = tempDiv.querySelectorAll('.alert');
                                
                                alertElements.forEach(alert => {
                                    document.body.appendChild(alert.cloneNode(true));
                                });

                                if (result.includes('alert-success')) {
                                    // Xóa sessionStorage sau khi in thành công
                                    sessionStorage.removeItem('bmpFile');
                                    sessionStorage.removeItem('bmpFileName');
                                    sessionStorage.removeItem('labelType');
                                    printSection.classList.add('hidden');
                                    showMessage('✅ In thành công!', 'success');
                                }
                            } catch (error) {
                                console.error('Lỗi khi in:', error);
                                showMessage(`❌ Lỗi khi in: ${error.message}`, 'danger');
                            }
                        });
                    }
                }
            } else {
                // Không có dữ liệu BMP
                if (printSection) {
                    printSection.classList.add('hidden');
                }
                showMessage('❌ Không tìm thấy dữ liệu tem để hiển thị', 'danger');
            }

            // Xử lý Cordova preview
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

            // Touch effects cho buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.95)';
                });

                btn.addEventListener('touchend', function() {
                    this.style.transform = '';
                });
            });

            // Auto-hide alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => {
                setTimeout(() => {
                    fadeOutAlert(alert);
                }, 5000);
            });
        });

    // Connection status checker với retry
    async function checkConnection() {
    const PING_TIMEOUT = 5000;
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 2000;

    for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), PING_TIMEOUT);

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: new FormData().append('test_connection', '1'),
                signal: controller.signal,
                cache: 'no-cache'
            });
            clearTimeout(timeoutId);

            if (response.ok) {
                const text = await response.text();
                return text.includes('alert-success');
            }
            throw new Error(`HTTP ${response.status}`);
        } catch (error) {
            clearTimeout(timeoutId);
            console.warn(`Thử kết nối lần ${attempt}/${MAX_RETRIES}: ${error.message}`);
            if (attempt < MAX_RETRIES) {
                await new Promise(resolve => setTimeout(resolve, RETRY_DELAY * Math.pow(2, attempt - 1)));
            }
        }
    }
    return false;
}

    async function updateConnectionStatus() 
    {
        const connectionStatus = document.getElementById('connection-status');
        if (!connectionStatus) return;

        const statusIcon = connectionStatus.querySelector('.status-icon');
        const statusText = connectionStatus.querySelector('.status-text');
        
        if (!statusIcon || !statusText) return;

        try {
            const isConnected = await checkConnection();
            
            statusIcon.innerHTML = isConnected 
                ? '<path d="M20 6 9 17l-5-5"/>'
                : '<path d="M6 18L18 6M6 6l12 12"/>';
                
            statusText.textContent = isConnected ? 'Kết nối' : 'Mất kết nối';
            statusText.style.color = isConnected ? 'var(--success-color)' : 'var(--danger-color)';
            
            connectionStatus.classList.toggle('connected', isConnected);
            connectionStatus.classList.toggle('disconnected', !isConnected);
            
            } catch (error) {
                console.error('Lỗi kiểm tra kết nối:', error);
                statusText.textContent = 'Lỗi kết nối';
                statusText.style.color = 'var(--warning-color)';
                connectionStatus.classList.remove('connected');
                connectionStatus.classList.add('disconnected');
            }
        }

        // Khởi tạo connection checker
        updateConnectionStatus();
        setInterval(updateConnectionStatus, 30000);

        // Show success/error messages với animation
        function showMessage(message, type = 'success') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = message;
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        alert.style.transition = 'all 0.3s ease';
        
        const alertContainer = document.getElementById('alert-container');
        alertContainer.appendChild(alert);

        // Animate in
        setTimeout(() => {
            alert.style.opacity = '1';
            alert.style.transform = 'translateY(0)';
        }, 10);

        // Auto remove
        setTimeout(() => {
            fadeOutAlert(alert);
        }, 4000);
    }

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

                    // Thay thế alert gốc bằng thông báo "Đã in"
                    alert.parentNode.replaceChild(printMessage, alert);

                    // Animate in thông báo "Đã in"
                    setTimeout(() => {
                        printMessage.style.opacity = '1';
                        printMessage.style.transform = 'translateY(0)';
                    }, 10);

                    // Tự động ẩn thông báo "Đã in" sau 3 giây
                    setTimeout(() => {
                        fadeOutAlert(printMessage);
                    }, 3000);
                } else {
                    // Xóa alert gốc nếu không phải alert-success
                    alert.remove();
                }
            }
        }, 300);
    }
}

    // Global error handler
    window.addEventListener('error', function(event) {
        console.error('Global error:', event.error);
        showMessage('❌ Đã xảy ra lỗi không mong muốn', 'danger');
    });

    // Handle unhandled promise rejections
    window.addEventListener('unhandledrejection', function(event) {
        console.error('Unhandled promise rejection:', event.reason);
        showMessage('❌ Lỗi xử lý bất đồng bộ', 'danger');
        event.preventDefault();
    });
</script>
<noscript>
    <div class="alert alert-warning">
        ⚠️ JavaScript bị tắt. Một số tính năng như xem trước tem sẽ không hoạt động. Vui lòng bật JavaScript để có trải nghiệm tốt nhất.
    </div>
</noscript>
</body>

</html>