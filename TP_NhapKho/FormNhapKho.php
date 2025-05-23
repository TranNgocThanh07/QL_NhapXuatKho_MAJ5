<?php
session_start();
ob_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
include('../db_config.php');

$maNhanVien = $_SESSION['maNhanVien'] ?? '';

function sendError($message, $exception = null) {
    ob_end_clean();
    $errorDetails = $message;
    if ($exception instanceof Exception) {
        $errorDetails .= "\nChi tiết lỗi: " . $exception->getMessage() .
                         "\nMã lỗi: " . $exception->getCode() .
                         "\nDòng: " . $exception->getLine() .
                         "\nTệp: " . $exception->getFile() .
                         "\nStack trace:\n" . $exception->getTraceAsString();
    }
    error_log($errorDetails);
    echo json_encode([
        'success' => false,
        'message' => '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> ' . nl2br(htmlspecialchars($errorDetails)) . '</div>'
    ]);
    exit;
}
//PDF
// Hàm tạo QR Code
function generateQRCode($content, $size) {
    try {
        $qrCode = new QrCode($content);
        $qrCode->setSize($size);
        $qrCode->setMargin(2);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        return $result->getString();
    } catch (Exception $e) {
        sendError("Lỗi khi tạo QR Code", $e);
    }
}

// Hàm tạo PDF Tem Hệ Thống (giữ nguyên từ code cũ)
function generateSystemLabel($pdf, $pdfData, $don, $tenMau, $tenDVT, $maSoMe) {
    $font = 'arial';
    $pdf->AddFont($font, '', 'arial.php');
    $pdf->SetFont($font, '', 6);
    $pdf->SetFont($font, 'B', 6);

    foreach ($pdfData as $item) {
        if (!isset($item['SoLot'], $item['SoLuong'], $item['TenThanhPhan'])) {
            continue;
        }

        $pdf->AddPage();
        $margin = 10;
        $tableTop = $margin;
        $tableWidth = 297.63 - 2 * $margin;
        $tableHeight = 419.53 - 2 * $margin;

        $rowHeights = array(40, 15, 15, 15, 15, 15, 15, 15, 15, 40);
        $totalRowHeight = array_sum($rowHeights);
        if ($totalRowHeight < $tableHeight) {
            $heightDifference = $tableHeight - $totalRowHeight;
            $heightIncreasePerRow = $heightDifference / count($rowHeights);
            for ($i = 0; $i < count($rowHeights); $i++) {
                $rowHeights[$i] += $heightIncreasePerRow;
            }
        }
        $columnWidthsPerRow = array_fill(0, 10, array($tableWidth * 0.25, $tableWidth * 0.75));
        $columnWidthsPerRow[8] = array($tableWidth * 0.25, $tableWidth * 0.50, $tableWidth * 0.25); // Hàng 8: 25% cho tiêu đề, 37.5% cho SoLuong, 37.5% cho SoKgCan
        $columnWidthsPerRow[9] = array($tableWidth * 0.75, $tableWidth * 0.25);

        $watermarkPath = __DIR__ . '/../assets/LogoMinhAnh.png';
        if (file_exists($watermarkPath)) {
            $centerX = 297.63 / 2;
            $centerY = 419.53 / 2;
            $watermarkWidth = 297.63 * 0.6;
            $imgInfo = getimagesize($watermarkPath);
            $watermarkHeight = $watermarkWidth * $imgInfo[1] / $imgInfo[0];
            $x = $centerX - $watermarkWidth / 2;
            $y = $centerY - $watermarkHeight / 2;
            $pdf->SetAlpha(0.6);
            $pdf->Image($watermarkPath, $x, $y, $watermarkWidth, $watermarkHeight);
            $pdf->SetAlpha(1);
        }

        $currentY = $tableTop;
        $padding = 4;
        $paddingCell = 5.5;
        $paddingInfo = 5;
        $QRpadding = 2;

        for ($row = 0; $row < 10; $row++) {
            $rowHeight = $rowHeights[$row];
            $y = $currentY;
            $currentX = $margin;
            $currentColumnWidths = $columnWidthsPerRow[$row];

            $pdf->Line($margin, $y, $margin + $tableWidth, $y);

            for ($col = 0; $col < count($currentColumnWidths); $col++) {
                $colWidth = $currentColumnWidths[$col];
                $pdf->Line($currentX, $y, $currentX, $y + $rowHeight);

                $cellX = $currentX;
                $cellY = $y;
                $cellWidth = $colWidth;
                $cellHeight = $rowHeight;

                switch ($row) {
                    case 0:
                        if ($col == 0) {
                            $qrContent = $item['MaQR'] ?? ($don['MaDonHang'] . "\nSố Lot: " . $item['SoLot'] . "\nSố lượng: " . number_format((float)$item['SoLuong'], 1) . " " . $tenDVT);
                            $qrCodeBinary = generateQRCode($qrContent, 100);
                            $qrPath = __DIR__ . '/temp_qr.png';
                            file_put_contents($qrPath, $qrCodeBinary);
                            $qrSize = min($cellWidth, $cellHeight) - 2 * $QRpadding;
                            $qrX = $cellX + ($cellWidth - $qrSize) / 2;
                            $qrY = $cellY + ($cellHeight - $qrSize) / 2;
                            $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize);
                            unlink($qrPath);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 10);
                            $pdf->Text($cellX, $cellY + $paddingInfo + 12, 'CÔNG TY TNHH DỆT KIM MINH ANH', false, false, true, 0, 0, 'C');
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->Text($cellX, $cellY + $paddingInfo + 28, 'MINH ANH KNITTING CO.,LTD', false, false, true, 0, 0, 'C');
                        }
                        break;
                     case 1:
                            if ($col == 0) {
                                $pdf->SetFont($font, 'B', 8);
                                $pdf->MultiCell($cellWidth, $cellHeight / 2, 'MẶT HÀNG', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                                $pdf->SetFont($font, 'B', 6);
                                $pdf->MultiCell($cellWidth, $cellHeight / 2, '(PRODUCT NAME)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                            } elseif ($col == 1) {
                                $pdf->SetFont($font, 'B', 8);
                                // Xử lý tên sản phẩm
                                $tenVai = $don['TenVai'];
                                // Trường hợp 1: Loại bỏ mã lặp trong tên nếu có
                                if (strpos($tenVai, $don['MaVai'] . ' (') === 0) {
                                    $tenVai = preg_replace('/^' . preg_quote($don['MaVai'], '/') . '\s*\(/', '(', $tenVai);
                                }
                                // Lấy phần trong ngoặc nếu có, hoặc giữ nguyên tên
                                $tenVai = preg_match('/\((.*?)\)/', $tenVai, $matches) ? $matches[1] : $tenVai;
                                // Nếu tên khác mã, thêm ngoặc; nếu giống mã, chỉ giữ mã
                                $output = ($tenVai !== $don['MaVai']) ? $don['MaVai'] . " (" . $tenVai . ")" : $don['MaVai'];
                                $pdf->MultiCell($cellWidth, $cellHeight, $output, 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                            }
                        break;
                    case 2:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'THÀNH PHẦN', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(INGREDIENTS)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['TenThanhPhan'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 3:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'MÀU', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(COLOR)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 8);
                            // Lấy dữ liệu trước (*)
                            $displayTenMau = strpos($tenMau, '*') !== false ? substr($tenMau, 0, strpos($tenMau, '*')) : $tenMau;
                            $pdf->MultiCell($cellWidth, $cellHeight, trim($displayTenMau), 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 4:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'KHỔ', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(SIZE)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['Kho'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 5:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'MÃ VẬT TƯ', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(SAP CODE)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['MaVatTu'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 6:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'MÃ ĐƠN HÀNG', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(ORDER CODE)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['MaDonHang'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 7:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'SỐ LOT', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(LOT NO.)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['SoLot'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);                        
                        }
                        break;
                        case 8:
                            if ($col == 0) {
                                $pdf->SetFont($font, 'B', 8);
                                $pdf->MultiCell($cellWidth, $cellHeight / 2, 'SỐ LƯỢNG', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                                $pdf->SetFont($font, 'B', 6);
                                $pdf->MultiCell($cellWidth, $cellHeight / 2, '(QUANTITY)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                            } elseif ($col == 1) {
                                $pdf->SetFont($font, 'B', 10);
                                $pdf->MultiCell($cellWidth, $cellHeight, number_format((float)$item['SoLuong'], 1) . " " . $tenDVT, 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                            } elseif ($col == 2) {
                                // Thêm cột SoKgCan vào đây, hiển thị với 2 chữ số thập phân hoặc N/A nếu NULL
                                $pdf->SetFont($font, 'B', 10);
                                $soKgCanDisplay = isset($item['SoKgCan']) && $item['SoKgCan'] !== null ? "≈" . " " . number_format((float)$item['SoKgCan'], 1) . " KG" : '';
                                $pdf->MultiCell($cellWidth, $cellHeight, $soKgCanDisplay, 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                            }
                        break;
                    case 9:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, 0, "Website: ", 0, 'L', false, 0, $cellX + $padding, $cellY + $padding);
                            $pdf->SetFont($font, '', 6);
                            $pdf->MultiCell($cellWidth - 20, 0, "www.detkimminhanh.vn", 0, 'L', false, 1, $cellX + $padding + 25, $cellY + $padding);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, 0, "Điện thoại: ", 0, 'L', false, 0, $cellX + $padding, $cellY + $padding + 10);
                            $pdf->SetFont($font, '', 6);
                            $pdf->MultiCell($cellWidth - 20, 0, "0283 7662 408 - 083 766 3329", 0, 'L', false, 1, $cellX + $padding + 30, $cellY + $padding + 10);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, 0, "Email: ", 0, 'L', false, 0, $cellX + $padding, $cellY + $padding + 20);
                            $pdf->SetFont($font, '', 6);
                            $pdf->MultiCell($cellWidth - 20, 0, "td@detkimminhanh.vn; detkimminhanh@yahoo.com", 0, 'L', false, 1, $cellX + $padding + 20, $cellY + $padding + 20);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, 0, "Địa chỉ: ", 0, 'L', false, 0, $cellX + $padding, $cellY + $padding + 30);
                            $pdf->SetFont($font, '', 6);
                            $pdf->MultiCell($cellWidth - 20, 0, "Lô J4-J5, đường số 3, KCN Lê Minh Xuân, Bình Chánh, TP.HCM", 0, 'L', false, 1, $cellX + $padding + 22, $cellY + $padding + 30);
                        } elseif ($col == 1) {
                            $qrContent = $item['MaQR'] ?? ($don['MaDonHang'] . "\nSố Lot: " . $item['SoLot'] . "\nSố lượng: " . number_format((float)$item['SoLuong'], 1) . " " . $tenDVT);
                            $qrCodeBinary = generateQRCode($qrContent, 100);
                            $qrPath = __DIR__ . '/temp_qr.png';
                            file_put_contents($qrPath, $qrCodeBinary);
                            $qrSize = min($cellWidth, $cellHeight) - 2 * $QRpadding;
                            $qrX = $cellX + ($cellWidth - $qrSize) / 2;
                            $qrY = $cellY + ($cellHeight - $qrSize) / 2;
                            $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize);
                            unlink($qrPath);
                        }
                        break;
                }

                $currentX += $colWidth;
            }

            $pdf->Line($currentX, $y, $currentX, $y + $rowHeight);
            $currentY += $rowHeight;
        }

        $pdf->Line($margin, $currentY, $margin + $tableWidth, $currentY);
    }
}

// Hàm tạo PDF Tem Khách Lẻ (dựa trên thiết kế C#)
function generateRetailLabel($pdf, $pdfData, $don, $tenMau, $tenDVT, $maSoMe) {
    $font = 'arial';
    //$pdf->AddFont($font, '', 'timesbd.php');
    $pdf->AddFont($font, '', 'arial.php');
    $pdf->SetFont($font, '', 6);
    $pdf->SetFont($font, 'B', 6);

    foreach ($pdfData as $item) {
        if (!isset($item['SoLot'], $item['SoLuong'], $item['TenThanhPhan'])) {
            continue;
        }

        $pdf->AddPage();
        $margin = 10;
        $tableTop = $margin;
        $tableWidth = 297.63 - 2 * $margin;
        $tableHeight = 419.53 - 2 * $margin;

        $rowHeights = array(40, 15, 15, 15, 15, 15, 15, 40); // 8 hàng
        $totalRowHeight = array_sum($rowHeights);
        if ($totalRowHeight < $tableHeight) {
            $heightDifference = $tableHeight - $totalRowHeight;
            $heightIncreasePerRow = $heightDifference / count($rowHeights);
            for ($i = 0; $i < count($rowHeights); $i++) {
                $rowHeights[$i] += $heightIncreasePerRow;
            }
        }

        // Cập nhật cấu trúc cột: Thêm 3 cột cho hàng 6 để chứa SoKgCan
        $columnWidthsPerRow = array(
            array($tableWidth * 0.25, $tableWidth * 0.5, $tableWidth * 0.25), // Hàng 0: 3 cột cho QR trái, giữa, QR phải
            array($tableWidth * 0.25, $tableWidth * 0.75), // Hàng 1
            array($tableWidth * 0.25, $tableWidth * 0.75), // Hàng 2
            array($tableWidth * 0.25, $tableWidth * 0.75), // Hàng 3
            array($tableWidth * 0.25, $tableWidth * 0.75), // Hàng 4
            array($tableWidth * 0.25, $tableWidth * 0.75), // Hàng 5
            array($tableWidth * 0.25, $tableWidth * 0.50, $tableWidth * 0.25), // Hàng 6: 3 cột (25% tiêu đề, 37.5% SoLuong, 37.5% SoKgCan)
            array($tableWidth * 0.75, $tableWidth * 0.25)  // Hàng 7
        );

        $currentY = $tableTop;
        $padding = 4;
        $paddingCell = 5.5;
        $paddingInfo = 5;
        $QRpadding = 2;

        for ($row = 0; $row < 8; $row++) {
            $rowHeight = $rowHeights[$row];
            $y = $currentY;
            $currentX = $margin;
            $currentColumnWidths = $columnWidthsPerRow[$row];

            $pdf->Line($margin, $y, $margin + $tableWidth, $y);

            for ($col = 0; $col < count($currentColumnWidths); $col++) {
                $colWidth = $currentColumnWidths[$col];
                $pdf->Line($currentX, $y, $currentX, $y + $rowHeight);

                $cellX = $currentX;
                $cellY = $y;
                $cellWidth = $colWidth;
                $cellHeight = $rowHeight;

                switch ($row) {
                    case 0:
                        if ($col == 0 || $col == 2) { // QR ở góc trên bên trái và trên bên phải
                            $qrContent = $item['MaQR'] ?? ($don['MaDonHang'] . "\nSố Lot: " . $item['SoLot'] . "\nSố lượng: " . number_format((float)$item['SoLuong'], 1) . " " . $tenDVT);
                            $qrCodeBinary = generateQRCode($qrContent, 100);
                            $qrPath = __DIR__ . '/temp_qr.png';
                            file_put_contents($qrPath, $qrCodeBinary);
                            $qrSize = min($cellWidth, $cellHeight) - 2 * $QRpadding;
                            $qrX = $cellX + ($cellWidth - $qrSize) / 2;
                            $qrY = $cellY + ($cellHeight - $qrSize) / 2;
                            $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize);
                            unlink($qrPath);
                        }
                        break;
                    case 1:
                            if ($col == 0) {
                                $pdf->SetFont($font, 'B', 8);
                                $pdf->MultiCell($cellWidth, $cellHeight / 2, 'MẶT HÀNG', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                                $pdf->SetFont($font, 'B', 6);
                                $pdf->MultiCell($cellWidth, $cellHeight / 2, '(PRODUCT NAME)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                            } elseif ($col == 1) {
                                $pdf->SetFont($font, 'B', 8);
                                // Xử lý tên sản phẩm
                                $tenVai = $don['TenVai'];
                                // Trường hợp 1: Loại bỏ mã lặp trong tên nếu có
                                if (strpos($tenVai, $don['MaVai'] . ' (') === 0) {
                                    $tenVai = preg_replace('/^' . preg_quote($don['MaVai'], '/') . '\s*\(/', '(', $tenVai);
                                }
                                // Lấy phần trong ngoặc nếu có, hoặc giữ nguyên tên
                                $tenVai = preg_match('/\((.*?)\)/', $tenVai, $matches) ? $matches[1] : $tenVai;
                                // Nếu tên khác mã, thêm ngoặc; nếu giống mã, chỉ giữ mã
                                $output = ($tenVai !== $don['MaVai']) ? $don['MaVai'] . " (" . $tenVai . ")" : $don['MaVai'];
                                $pdf->MultiCell($cellWidth, $cellHeight, $output, 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                            }
                        break;
                    case 2:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'THÀNH PHẦN', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(INGREDIENTS)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['TenThanhPhan'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 3:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'MÀU', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(COLOR)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 8);
                            // Lấy dữ liệu trước (*)
                            $displayTenMau = strpos($tenMau, '*') !== false ? substr($tenMau, 0, strpos($tenMau, '*')) : $tenMau;
                            $pdf->MultiCell($cellWidth, $cellHeight, trim($displayTenMau), 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 4:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'KHỔ', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(SIZE)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['Kho'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 5:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'SỐ LOT', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(LOT NO.)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['SoLot'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 6:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'SỐ LƯỢNG', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(QUANTITY)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            // Hiển thị giá trị SoLuong
                            $pdf->SetFont($font, 'B', 10);
                            $pdf->MultiCell($cellWidth, $cellHeight, number_format((float)$item['SoLuong'], 1) . " " . $tenDVT, 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        } elseif ($col == 2) {
                            // Thêm cột SoKgCan vào đây, hiển thị với 2 chữ số thập phân hoặc N/A nếu NULL
                            $pdf->SetFont($font, 'B', 10);
                            $soKgCanDisplay = isset($item['SoKgCan']) && $item['SoKgCan'] !== null ? "≈" . " " . number_format((float)$item['SoKgCan'], 1) . " KG" : '';
                            $pdf->MultiCell($cellWidth, $cellHeight, $soKgCanDisplay, 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 7:
                        if ($col == 1) {
                            $qrContent = $item['MaQR'] ?? ($don['MaDonHang'] . "\nSố Lot: " . $item['SoLot'] . "\nSố lượng: " . number_format((float)$item['SoLuong'], 1) . " " . $tenDVT);
                            $qrCodeBinary = generateQRCode($qrContent, 100);
                            $qrPath = __DIR__ . '/temp_qr.png';
                            file_put_contents($qrPath, $qrCodeBinary);
                            $qrSize = min($cellWidth, $cellHeight) - 2 * $QRpadding;
                            $qrX = $cellX + ($cellWidth - $qrSize) / 2;
                            $qrY = $cellY + ($cellHeight - $qrSize) / 2;
                            $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize);
                            unlink($qrPath);
                        }
                        break;
                }

                $currentX += $colWidth;
            }

            $pdf->Line($currentX, $y, $currentX, $y + $rowHeight);
            $currentY += $rowHeight;
        }

        $pdf->Line($margin, $currentY, $margin + $tableWidth, $currentY);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generatePDF') {
    $pdfData = json_decode($_POST['pdfData'], true);
    $maSoMe = $pdfData[0]['MaSoMe'] ?? '';
    $labelType = $_POST['labelType'] ?? 'system'; // Default to 'system' if not provided

    if (empty($pdfData) || empty($maSoMe)) {
        sendError("Dữ liệu đầu vào không đủ hoặc rỗng (thiếu pdfData hoặc MaSoMe).");
    }

    try {
        $sqlDon = "SELECT ds.*, dvt.TenDVT
                   FROM TP_DonSanXuat ds
                   LEFT JOIN TP_DonViTinh dvt ON ds.MaDVT = dvt.MaDVT
                   WHERE ds.MaSoMe = ?";
        $stmtDon = $pdo->prepare($sqlDon);
        $stmtDon->execute([$maSoMe]);
        $don = $stmtDon->fetch(PDO::FETCH_ASSOC);
        $tenDVT = $don['TenDVT'] ?? 'kg';
    } catch (Exception $e) {
        sendError("Lỗi khi truy vấn thông tin đơn hàng", $e);
    }

    if (!$don) {
        sendError("Không tìm thấy đơn hàng với MaSoMe: " . htmlspecialchars($maSoMe));
    }

    try {
        $sqlMau = "SELECT TenMau FROM TP_Mau WHERE MaMau = ?";
        $stmtMau = $pdo->prepare($sqlMau);
        $stmtMau->execute([$pdfData[0]['MaMau']]);
        $mau = $stmtMau->fetch(PDO::FETCH_ASSOC);
        $tenMau = $mau['TenMau'] ?? 'N/A';
    } catch (Exception $e) {
        sendError("Lỗi khi truy vấn tên màu", $e);
    }

    try {
        $pdf = new TCPDF('P', 'pt', array(297.63, 419.53), true, 'UTF-8', false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(false);
        $pdf->setFontSubsetting(true);

        if ($labelType === 'khachle') {
            generateRetailLabel($pdf, $pdfData, $don, $tenMau, $tenDVT, $maSoMe);
        } else {
            generateSystemLabel($pdf, $pdfData, $don, $tenMau, $tenDVT, $maSoMe);
        }

        $timestamp = date('YmdHis');
        $safeMaSoMe = preg_replace('/[^A-Za-z0-9_-]/', '_', $maSoMe);
        $pdfFileName = "Tem_NhapKho_" . ($labelType === 'khachle' ? 'KhachLe_' : 'HeThong_') . "{$safeMaSoMe}_{$timestamp}.pdf";

        ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $pdfFileName . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        $pdf->Output($pdfFileName, 'I');
        exit;
    } catch (Throwable $e) {
        sendError("Lỗi nghiêm trọng khi tạo hoặc xuất PDF", $e);
    }
}
// Xử lý lưu vào DB khi nhấn "Nhập kho"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'saveToDB') {
    header('Content-Type: application/json');
    
    $data = json_decode($_POST['data'], true);
    
    try {
        $pdo->beginTransaction();     

        $sqlDon = "SELECT SoKgQuyDoi, TenDVT,TongSoLuongGiao FROM TP_DonSanXuat ds
                   LEFT JOIN TP_DonViTinh dvt ON ds.MaDVT = dvt.MaDVT
                   WHERE ds.MaSoMe = ?";
        $stmtDon = $pdo->prepare($sqlDon);
        $stmtDon->execute([$data[0]['MaSoMe']]);
        $don = $stmtDon->fetch(PDO::FETCH_ASSOC);
        $soLuongGiao = floatval($don['TongSoLuongGiao']);
        $tenDVT = $don['TenDVT'] ?? 'kg';

        $sqlTongLuong = "SELECT SUM(SoLuong) as TongLuongDaNhap FROM TP_ChiTietDonSanXuat WHERE MaSoMe = ?";
        $stmtTongLuong = $pdo->prepare($sqlTongLuong);
        $stmtTongLuong->execute([$data[0]['MaSoMe']]);
        $tongSoLuongHienTai = floatval($stmtTongLuong->fetch(PDO::FETCH_ASSOC)['TongLuongDaNhap'] ?? 0);

        $tongSoLuongNhapMoi = array_reduce($data, function($sum, $item) {
            return $sum + floatval($item['SoLuong']);
        }, 0);
        $tongSoLuongMoi = $tongSoLuongHienTai + $tongSoLuongNhapMoi;

        $sqlInsert = "INSERT INTO TP_ChiTietDonSanXuat (
            MaSoMe, MaNguoiLienHe, MaCTNHTP, MaDonHang, MaVai, MaVatTu, TenVai, 
            MaMau, MaDVT, Kho, SoLuong, MaQR, TrangThai, SoLot, NgayTao, 
            MaKhachHang, MaNhanVien, TenThanhPhan, SoKgCan, OriginalTrangThai, MaKhuVuc, GhiChu
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($sqlInsert);

        foreach ($data as $item) {
            $soKgCan = isset($item['SoKgCan']) && is_numeric($item['SoKgCan']) && $item['SoKgCan'] > 0 ? floatval($item['SoKgCan']) : null;
            
            $stmtInsert->bindValue(1, $item['MaSoMe']);
            $stmtInsert->bindValue(2, $item['MaNguoiLienHe']);
            $stmtInsert->bindValue(3, $item['MaCTNHTP']);
            $stmtInsert->bindValue(4, $item['MaDonHang']);
            $stmtInsert->bindValue(5, $item['MaVai']);
            $stmtInsert->bindValue(6, $item['MaVatTu']);
            $stmtInsert->bindValue(7, $item['TenVai']);
            $stmtInsert->bindValue(8, $item['MaMau']);
            $stmtInsert->bindValue(9, $item['MaDVT']);
            $stmtInsert->bindValue(10, $item['Kho']);
            $stmtInsert->bindValue(11, floatval($item['SoLuong']));
            $stmtInsert->bindValue(12, $item['MaQR']);
            $stmtInsert->bindValue(13, $item['TrangThai']);
            $stmtInsert->bindValue(14, $item['SoLot']);
            $stmtInsert->bindValue(15, $item['NgayTao']);
            $stmtInsert->bindValue(16, $item['MaKhachHang']);
            $stmtInsert->bindValue(17, $item['MaNhanVien']);
            $stmtInsert->bindValue(18, $item['TenThanhPhan']);
            $stmtInsert->bindValue(19, $soKgCan);
            $stmtInsert->bindValue(20, $item['OriginalTrangThai']);
             $stmtInsert->bindValue(21, $item['MaKhuVuc']);
              $stmtInsert->bindValue(22, $item['GhiChu']);
            $stmtInsert->execute();
        }

        $pdo->commit();

        $soLuongConLai = $soLuongGiao - $tongSoLuongMoi;
        $message = "Nhập kho thành công!\n" .
           "Tổng số lượng: <span style=\"color: red;\">" . number_format($soLuongGiao) . "</span> $tenDVT\n" .
           "Tổng đã nhập: <span style=\"color: red;\">" . number_format($tongSoLuongMoi) . "</span> $tenDVT\n" .
           "Còn lại nhập: <span style=\"color: red;\">" . number_format($soLuongConLai) . "</span> $tenDVT";

        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => [
                'tongSoLuongNhapMoi' => $tongSoLuongNhapMoi,
                'soLuongGiao' => $soLuongGiao,
                'tongSoLuongNhap' => $tongSoLuongMoi,
                'soLuongConLai' => $soLuongConLai,
                'donViTinh' => $tenDVT
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        sendError('Lỗi cơ sở dữ liệu:', $e);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendError('Lỗi hệ thống:', $e);
    }
}

// Truy vấn thông tin đơn hàng và TenThanhPhan
$maSoMe = $_GET['maSoMe'] ?? '';
$soLuongGiao = 0;
$tongSoLuongNhap = 0;
$soLuongConLai = 0;
$tenDVT = 'kg';
$don = null;
$tenThanhPhan = '';
$thanhPhanInfo = null;

if ($maSoMe) {
    $sqlDon = "SELECT 
        ds.MaSoMe, ds.MaDonHang, ds.MaKhachHang, kh.TenKhachHang, 
        ds.MaVatTu, ds.MaVai, ds.TenVai, ds.MaMau, m.TenMau, 
        ds.MaDVT, dvt.TenDVT, ds.Kho, ds.MaNguoiLienHe, nlh.TenNguoiLienHe, 
        ds.TongSoLuongGiao 
    FROM TP_DonSanXuat ds
    LEFT JOIN TP_KhachHang kh ON ds.MaKhachHang = kh.MaKhachHang
    LEFT JOIN TP_Mau m ON ds.MaMau = m.MaMau
    LEFT JOIN TP_DonViTinh dvt ON ds.MaDVT = dvt.MaDVT
    LEFT JOIN TP_NguoiLienHe nlh ON ds.MaNguoiLienHe = nlh.MaNguoiLienHe
    WHERE ds.MaSoMe = ?";
    $stmtDon = $pdo->prepare($sqlDon);
    $stmtDon->execute([$maSoMe]);
    $don = $stmtDon->fetch(PDO::FETCH_ASSOC);

    if ($don) {
        $soLuongGiao = floatval($don['TongSoLuongGiao']);
        $tenDVT = $don['TenDVT'] ?? 'kg';
        $sqlTongLuong = "SELECT SUM(SoLuong) as TongLuongDaNhap FROM TP_ChiTietDonSanXuat WHERE MaSoMe = ?";
        $stmtTongLuong = $pdo->prepare($sqlTongLuong);
        $stmtTongLuong->execute([$maSoMe]);
        $tongSoLuongNhap = floatval($stmtTongLuong->fetch(PDO::FETCH_ASSOC)['TongLuongDaNhap'] ?? 0);
        $soLuongConLai = $soLuongGiao - $tongSoLuongNhap;
             
        // Truy vấn TenThanhPhan từ TP_Vai
        $maVai = $don['MaVai'] ?? '';
        $tenThanhPhan = ''; // Khởi tạo giá trị mặc định
        if ($maVai) {
            $sqlThanhPhanInfo = "SELECT TenThanhPhan 
                                FROM Vai 
                                WHERE MaVai = ?";
            $stmtThanhPhanInfo = $pdo->prepare($sqlThanhPhanInfo);
            $stmtThanhPhanInfo->execute([$maVai]);
            $thanhPhanInfo = $stmtThanhPhanInfo->fetch(PDO::FETCH_ASSOC);
            $tenThanhPhan = $thanhPhanInfo['TenThanhPhan'] ?? '';
            // Ghi log để debug
            if (!$thanhPhanInfo) {
                error_log("Không tìm thấy TenThanhPhan trong TP_Vai cho MaVai: $maVai");
            }
        }
    }
}

// Truy vấn dữ liệu từ TP_KhuVuc
$sqlKhuVuc = "SELECT MaKhuVuc FROM KhuVuc ORDER BY MaKhuVuc";
$stmtKhuVuc = $pdo->prepare($sqlKhuVuc);
$stmtKhuVuc->execute();
$MaKhuVucList = $stmtKhuVuc->fetchAll(PDO::FETCH_ASSOC);


// Xử lý cập nhật trạng thái đơn hàng khi nhập đủ số lượng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'updateDonStatus') {
    header('Content-Type: application/json');
    $maSoMeToUpdate = $_POST['maSoMe'] ?? '';

    if (empty($maSoMeToUpdate)) {
        echo json_encode(['success' => false, 'message' => 'Mã số mẻ không được cung cấp.']);
        exit;
    }

    try {
        $sqlCheckRemaining = "SELECT TongSoLuongGiao, (SELECT SUM(SoLuong) FROM TP_ChiTietDonSanXuat WHERE MaSoMe = ?) as TongDaNhap FROM TP_DonSanXuat WHERE MaSoMe = ?";
        $stmtCheckRemaining = $pdo->prepare($sqlCheckRemaining);
        $stmtCheckRemaining->execute([$maSoMeToUpdate, $maSoMeToUpdate]);
        $resultRemaining = $stmtCheckRemaining->fetch(PDO::FETCH_ASSOC);

        if ($resultRemaining) {
            $tongSoLuongGiao = floatval($resultRemaining['TongSoLuongGiao']);
            $tongDaNhap = floatval($resultRemaining['TongDaNhap'] ?? 0);
            if ($tongDaNhap >= $tongSoLuongGiao) {
                $sqlUpdateStatus = "UPDATE TP_DonSanXuat SET TrangThai = 2 WHERE MaSoMe = ?";
                $stmtUpdateStatus = $pdo->prepare($sqlUpdateStatus);
                $stmtUpdateStatus->execute([$maSoMeToUpdate]);

                if ($stmtUpdateStatus->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Trạng thái đơn hàng đã được cập nhật thành "Đã nhập đủ".']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật trạng thái đơn hàng hoặc trạng thái đã được cập nhật trước đó.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Chưa nhập đủ số lượng, không cập nhật trạng thái.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng với mã số mẻ: ' . htmlspecialchars($maSoMeToUpdate)]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
        error_log('Database error updating DonSanXuat status: ' . $e->getMessage());
    }
    exit;
}
// Xử lý yêu cầu AJAX để lấy tổng số lượng đã nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'getTongSoLuongNhap') {
    header('Content-Type: application/json');
    $maSoMe = $_POST['maSoMe'] ?? '';

    if (empty($maSoMe)) {
        echo json_encode(['success' => false, 'message' => 'Mã số mẻ không được cung cấp.']);
        exit;
    }

    try {
        $sqlTongLuong = "SELECT SUM(SoLuong) as TongLuongDaNhap FROM TP_ChiTietDonSanXuat WHERE MaSoMe = ?";
        $stmtTongLuong = $pdo->prepare($sqlTongLuong);
        $stmtTongLuong->execute([$maSoMe]);
        $tongSoLuongNhap = floatval($stmtTongLuong->fetch(PDO::FETCH_ASSOC)['TongLuongDaNhap'] ?? 0);

        echo json_encode([
            'success' => true,
            'tongSoLuongNhap' => $tongSoLuongNhap
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
    }
    exit;
}
//xem hàng mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'getChiTietNhapKho') {
    header('Content-Type: application/json');
    $maSoMe = $_POST['maSoMe'] ?? '';

    if (empty($maSoMe)) {
        echo json_encode(['success' => false, 'message' => 'Mã số mẻ không được cung cấp.']);
        exit;
    }

    try {
        $sqlChiTiet = "SELECT SoLuong, SoKgCan, SoLot, TenThanhPhan, MaKhuVuc, GhiChu
                       FROM TP_ChiTietDonSanXuat 
                       WHERE MaSoMe = ? AND TrangThai = 0
                       ORDER BY NgayTao DESC";
        $stmtChiTiet = $pdo->prepare($sqlChiTiet);
        $stmtChiTiet->execute([$maSoMe]);
        $chiTietList = $stmtChiTiet->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $chiTietList
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Nhập Hàng</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- nếu commet lại thì sẽ chạy trên trình duyệt web <script src="/TP_NhapKho/cordova.js"></script> -->
    <!-- <script src="/TP_NhapKho/cordova.js"></script> -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        .form-container {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .info-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .input-field {
            transition: all 0.2s ease;
            border: 2px solid #e5e7eb;
        }
        .input-field:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .btn {
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .number-align {
            font-family: 'Courier New', Courier, monospace;
            display: inline-block;
            width: 80px;
            text-align: right;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table th, .data-table td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            text-align: left;
            font-size: 14px;
        }
        .data-table th {
            background-color: #f3f4f6;
            font-weight: 600;
        }
        @media (max-width: 640px) {
            .data-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .data-table th, .data-table td {
                min-width: 100px;
            }
        }

       
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
<div class="container mx-auto ">
        <div class="bg-white rounded-2xl shadow-xl p-5">
            <!-- Header và Info Card giữ nguyên -->
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center">
                    <i class="fas fa-box-open text-2xl text-blue-600 mr-3"></i>
                    <h2 class="text-xl font-bold text-gray-800">Nhập Hàng</h2>
                </div>
                <a href="../nhapkho.php" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times text-xl"></i>
                </a>
            </div>

            <div class="info-card rounded-xl p-4 mb-6">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="flex items-center">
                        <i class="fas fa-box text-blue-500 mr-2"></i>
                        <span class="text-gray-700">Tổng số lượng:</span>
                    </div>
                    <span id="tongSoLuongGiaoDisplay" class="text-right font-semibold"><?php echo number_format($soLuongGiao, 2); ?> <?php echo $tenDVT; ?></span>
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        <span class="text-gray-700">Đã nhập:</span>
                    </div>
                    <span id="tongDaNhapDisplay" class="text-right font-semibold"><?php echo number_format($tongSoLuongNhap, 2); ?> <?php echo $tenDVT; ?></span>
                    <div class="flex items-center">
                        <i class="fas fa-hourglass-half text-orange-500 mr-2"></i>
                        <span class="text-gray-700">Còn lại:</span>
                    </div>
                    <span id="soLuongConLaiDisplay" class="text-right font-semibold"><?php echo number_format($soLuongConLai, 2); ?> <?php echo $tenDVT; ?></span>
                </div>
            </div>

            <form id="nhapHangForm" class="form-container">
                <div class="space-y-4">
                    <!-- Dòng 1: Mã Số Mẻ và Mã Đơn Hàng -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mã Số Mẻ</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['MaSoMe'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2.5 rounded-lg bg-gray-100 text-gray-600">
                            <input type="hidden" name="MaSoMe" value="<?php echo htmlspecialchars($don['MaSoMe'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mã Đơn Hàng</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['MaDonHang'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2.5 rounded-lg bg-gray-100 text-gray-600">
                            <input type="hidden" name="MaDonHang" value="<?php echo htmlspecialchars($don['MaDonHang'] ?? ''); ?>">
                        </div>
                    </div>
                 

                    <!-- Dòng 2: Mã Vải và Tên Vải -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mã Vải</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['MaVai'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2.5 rounded-lg bg-gray-100 text-gray-600">
                            <input type="hidden" name="MaVai" value="<?php echo htmlspecialchars($don['MaVai'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tên Vải</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['TenVai'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2.5 rounded-lg bg-gray-100 text-gray-600">
                            <input type="hidden" name="TenVai" value="<?php echo htmlspecialchars($don['TenVai'] ?? ''); ?>">
                        </div>
                    </div>                 

                    <!-- Dòng 3: Khổ và Đơn Vị Tính -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Khổ</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['Kho'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2.5 rounded-lg bg-gray-100 text-gray-600">
                            <input type="hidden" name="Kho" value="<?php echo htmlspecialchars($don['Kho'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Đơn Vị Tính</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['TenDVT'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2.5 rounded-lg bg-gray-100 text-gray-600">
                            <input type="hidden" name="MaDVT" value="<?php echo htmlspecialchars($don['MaDVT'] ?? ''); ?>">
                        </div>
                    </div>
                 
                     <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tên Khách Hàng</label>
                        <input type="text" value="<?php echo htmlspecialchars($don['TenKhachHang'] ?? ''); ?>" readonly 
                            class="input-field w-full p-2.5 rounded-lg bg-gray-100 text-gray-600">
                        <input type="hidden" name="MaKhachHang" value="<?php echo htmlspecialchars($don['MaKhachHang'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mã Vật Tư</label>
                        <input type="text" value="<?php echo htmlspecialchars($don['MaVatTu'] ?? ''); ?>" readonly 
                            class="input-field w-full p-2.5 rounded-lg bg-gray-100 text-gray-600">
                        <input type="hidden" name="MaVatTu" value="<?php echo htmlspecialchars($don['MaVatTu'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tên Màu</label>
                        <input type="text" value="<?php echo htmlspecialchars($don['TenMau'] ?? ''); ?>" readonly 
                            class="input-field w-full p-2.5 rounded-lg bg-gray-100 text-gray-600">
                        <input type="hidden" name="MaMau" value="<?php echo htmlspecialchars($don['MaMau'] ?? ''); ?>">
                    </div>
 
                     <!-- Thành Phần -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Thành Phần</label>
                        <input type="text" name="TenThanhPhan" id="TenThanhPhan" 
                            value="<?php echo htmlspecialchars($tenThanhPhan); ?>" 
                            class="input-field w-full p-2.5 rounded-lg">
                    </div>

                  <!-- Dòng 4: Số Lượng, Số KG Cân -->
                  <div class="grid <?php echo ($don['MaDVT'] !== '1' && $tenDVT !== 'KG') ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Số Lượng (<?php echo $tenDVT; ?>)</label>
                            <input type="number" step="0.01" name="SoLuong" id="soLuong" 
                                class="input-field w-full p-2.5 rounded-lg">
                        </div>
                        <?php if ($don['MaDVT'] !== '1' && $tenDVT !== 'KG'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Số KG Cân</label>
                            <input type="number" step="0.01" name="SoKgCan" id="soKGCan" 
                                class="input-field w-full p-2.5 rounded-lg">
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="SoKgCan" id="soKGCan">
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="SoCay" id="soCay" value="1">
                   
                    <!-- Khuvuc Và Ghi Chú -->
                     <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Khu Vực</label>
                            <input type="text" name="MaKhuVuc" id="MaKhuVuc" class="input-field w-full p-2.5 rounded-lg" list="MaKhuVucList">
                            <datalist id="MaKhuVucList">
                                <?php
                                foreach ($MaKhuVucList as $row) {
                                    $MaKhuVuc = htmlspecialchars($row['MaKhuVuc']);
                                    echo "<option value=\"$MaKhuVuc\">";
                                }
                                ?>
                            </datalist>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
                            <input type="text" name="GhiChu" id="GhiChu" class="input-field w-full p-2.5 rounded-lg" oninput="this.value = this.value.toUpperCase();">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Số Lot</label>
                        <input type="text" name="SoLot" id="soLot" class="input-field w-full p-2.5 rounded-lg" oninput="this.value = this.value.toUpperCase();">
                    </div>
                               
                    <!-- Hidden fields -->
                    <input type="hidden" name="MaNguoiLienHe" value="<?php echo htmlspecialchars($don['MaNguoiLienHe'] ?? ''); ?>">
                    <input type="hidden" name="MaNhanVien" value="<?php echo htmlspecialchars($maNhanVien); ?>">
                    <input type="hidden" name="NgayTao" value="<?php echo date('Y-m-d H:i:s'); ?>">
                    <input type="hidden" name="TrangThai" value="0">
                    <input type="hidden" name="OriginalTrangThai" value="0">
                </div>

                <!-- Buttons giữ nguyên -->
                <div class="mt-6 flex justify-end gap-3">
                    <button type="submit" 
                        class="btn px-6 py-2.5 bg-green-600 text-white rounded-lg shadow-md hover:bg-blue-700">
                        <i class="fas fa-check mr-2"></i>Xác Nhận
                    </button>
                    <a href="../nhapkho.php" 
                        class="btn px-6 py-2.5 bg-red-600 text-white rounded-lg shadow-md hover:bg-gray-700">
                        <i class="fas fa-times mr-2"></i>Hủy
                    </a>
                </div>
            </form>

            <!-- Table giữ nguyên -->
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-2">Danh sách hàng nhập kho</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Số Lượng</th>
                            <?php if ($don['MaDVT'] !== '1' && $tenDVT !== 'KG'): ?>
                                <th>Số KG Cân</th>
                            <?php endif; ?>
                            <th>Số Lot</th>
                            <th>Thành Phần</th>
                            <th>Khu Vực</th>
                            <th>Ghi Chú</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="dataTableBody"></tbody>
                </table>
                <div class="flex items-center gap-3 mt-4">
                    <select id="labelType" class="input-field p-2 rounded-lg">
                        <option value="system">Tem Hệ Thống</option>
                        <option value="khachle">Tem Khách Lẻ</option>
                    </select>
                    <button id="saveToDB" 
                        class="btn px-4 py-2.5 bg-blue-600 text-white rounded-lg shadow-md hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Nhập
                    </button>
                    <button id="XemChiTietNhap" 
                        class="btn px-4 py-2.5 bg-green-600 text-white rounded-lg shadow-md hover:bg-green-700 flex items-center">
                        <i class="fas fa-eye mr-2"></i>Xem
                    </button>
                </div>
            </div>
        </div>
    </div>
  
<script>
const { jsPDF } = window.jspdf;
let tempData = [];
let tempSTT = 0;

// Tạo mã chi tiết nhập hàng thành phẩm (MaCTNHTP) duy nhất
function generateMaCTNHTP(index) {
    const prefix = 'CTNHTP';
    const random = Math.floor(Math.random() * 900000) + 100000;
    const date = new Date().toISOString().replace(/[-:T.]/g, '').slice(0, 14);
    return prefix + date + random + '_' + String(index).padStart(3, '0');
}

// Lấy tổng số lượng đã nhập từ cơ sở dữ liệu qua AJAX
async function getTongSoLuongNhap(maSoMe) {
    const formData = new FormData();
    formData.append('action', 'getTongSoLuongNhap');
    formData.append('maSoMe', maSoMe);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            return data.tongSoLuongNhap;
        } else {
            throw new Error(data.message || 'Không thể lấy tổng số lượng đã nhập.');
        }
    } catch (error) {
        throw new Error('Lỗi kết nối hoặc server: ' + error.message);
    }
}

// Xử lý sự kiện submit form nhập kho, kiểm tra và thêm dữ liệu vào tempData
document.getElementById('nhapHangForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const soLuong = parseFloat(document.getElementById('soLuong').value) || 0;
    const soKGCanInput = document.getElementById('soKGCan').value;
    const soKGCan = soKGCanInput && !isNaN(soKGCanInput) && soKGCanInput.trim() !== '' ? parseFloat(soKGCanInput) : null;
    const soLot = document.getElementById('soLot').value.trim();
    const tenThanhPhan = document.getElementById('TenThanhPhan').value.trim();
    const maKhuVuc = document.getElementById('MaKhuVuc').value.trim();
    const ghiChu = document.getElementById('GhiChu').value.trim();

    let errorMessages = [];
    if (soLuong <= 0) errorMessages.push("Số lượng phải lớn hơn 0.");
    if (soKGCan !== null && soKGCan < 0) errorMessages.push("Số KG Cân không được âm.");
    if (!soLot) errorMessages.push("Số Lot không được để trống.");
    if (!tenThanhPhan) errorMessages.push("Thành phần không được để trống.");
    if (!maKhuVuc) errorMessages.push("Mã khu vực không được để trống.");

    if (errorMessages.length > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Dữ liệu không hợp lệ!',
            html: `<div class="text-left space-y-2">${errorMessages.map(msg => `<div><i class="fas fa-exclamation-circle mr-2 text-yellow-500"></i>${msg}</div>`).join('')}</div>`,
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false,
            width: '90%',
            padding: '1rem'
        });
        return;
    }

    const formData = new FormData(this);
    const tongSoLuongNhapMoi = soLuong;
    const soLuongGiao = <?php echo $soLuongGiao; ?>;
    const maSoMe = formData.get('MaSoMe');

    try {
        const tongSoLuongDaNhapDB = await getTongSoLuongNhap(maSoMe);
        const tongSoLuongDaNhapTrongTemp = tempData.reduce((sum, item) => sum + item.SoLuong, 0);
        const tongSoLuongHienTai = tongSoLuongDaNhapDB + tongSoLuongDaNhapTrongTemp;
        const soLuongConLai = soLuongGiao - tongSoLuongHienTai;

        if (tongSoLuongNhapMoi > soLuongConLai) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi!',
                html: `
                    <div class="text-left space-y-3">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2 text-red-500"></i>
                            <span>Bạn đã nhập quá số lượng yêu cầu: <span style="color: red;" class="font-semibold">${soLuongGiao.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?></span></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-arrow-up mr-2 text-red-500"></i>
                            <span>Đã nhập: <span style="color: red;" class="font-semibold">${tongSoLuongHienTai.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?></span></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-arrow-up mr-2 text-red-500"></i>
                            <span>Bạn đang nhập: <span style="color: red;" class="font-semibold">${tongSoLuongNhapMoi.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?></span></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2 text-yellow-500"></i>
                            <span>Bạn chỉ được phép nhập tối đa: <span style="color: red;" class="font-semibold">${soLuongConLai.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?></span></span>
                        </div>
                    </div>
                `,
                confirmButtonText: 'OK',
                customClass: {
                    popup: 'rounded-xl',
                    title: 'text-lg font-semibold',
                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                },
                buttonsStyling: false,
                width: '90%',
                padding: '1rem'
            });
            return;
        }

        const maQRBase = `${formData.get('MaKhachHang')}_${formData.get('MaVai')}_${formData.get('MaMau')}_${formData.get('MaDVT')}_${formData.get('Kho')}_${soLuong}_${soLot}_${tenThanhPhan}`;

        tempSTT++;
        const maCTNHTP = generateMaCTNHTP(tempSTT);
        tempData.push({
            STT: tempSTT,
            MaSoMe: formData.get('MaSoMe'),
            MaNguoiLienHe: formData.get('MaNguoiLienHe'),
            MaCTNHTP: maCTNHTP,
            MaDonHang: formData.get('MaDonHang'),
            MaVai: formData.get('MaVai'),
            MaVatTu: formData.get('MaVatTu'),
            TenVai: formData.get('TenVai'),
            MaMau: formData.get('MaMau'),
            MaDVT: formData.get('MaDVT'),
            Kho: formData.get('Kho'),
            SoLuong: soLuong,
            MaQR: maQRBase,
            TrangThai: 0,
            SoLot: soLot,
            NgayTao: formData.get('NgayTao'),
            MaKhachHang: formData.get('MaKhachHang'),
            MaNhanVien: formData.get('MaNhanVien'),
            TenThanhPhan: tenThanhPhan,
            SoKgCan: soKGCan,
            OriginalTrangThai: 0,
            MaKhuVuc: maKhuVuc,
            GhiChu: ghiChu || null
        });

        updateTable();
        document.getElementById('soLuong').value = '';
        document.getElementById('soKGCan').value = '';
        document.getElementById('soLot').value = '';
        document.getElementById('MaKhuVuc').value = '';
        document.getElementById('GhiChu').value = '';      
        document.getElementById('TenThanhPhan').value = existingTenThanhPhan;

        Swal.fire({
            icon: 'success',
            title: 'Thành công!',
            text: `Đã thêm 1 cây vào danh sách nhập kho`,
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi!',
            text: `Lỗi khi kiểm tra số lượng: ${error.message}`,
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false
        });
    }
});

// Cập nhật bảng hiển thị dữ liệu nhập kho tạm thời
function updateTable() {
    const tbody = document.getElementById('dataTableBody');
    tbody.innerHTML = '';
    const isKgUnit = '<?php echo $don['MaDVT'] === '1' || $tenDVT === 'KG' ? 'true' : 'false'; ?>';
    tempData.forEach((item, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.STT}</td>
            <td>${item.SoLuong} <?php echo $tenDVT; ?></td>
            ${isKgUnit === 'false' ? `<td>${item.SoKgCan ? item.SoKgCan + ' kg' : ''}</td>` : ''}
            <td>${item.SoLot}</td>
            <td>${item.TenThanhPhan}</td>
            <td>${item.MaKhuVuc }</td>
            <td>${item.GhiChu || ''}</td>
            <td>
                <button onclick="deleteRow(${index})" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Xóa một hàng dữ liệu khỏi tempData
function deleteRow(index) {
    tempData.splice(index, 1);
    updateTable();
}

// Tạo và xử lý file PDF chứa thông tin tem nhập kho
async function generatePDF(data, labelType) {
    if (!data || data.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Dữ liệu không hợp lệ',
            text: 'Không có dữ liệu để tạo PDF.',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false
        });
        return;
    }

    Swal.fire({
        title: 'Đang tạo PDF...',
        text: 'Vui lòng chờ trong giây lát.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    if (typeof cordova !== 'undefined') {
        await new Promise((resolve) => {
            if (typeof cordova.plugins !== 'undefined') {
                resolve();
            } else {
                document.addEventListener('deviceready', resolve, { once: true });
            }
        });
    }

    const formData = new FormData();
    formData.append('action', 'generatePDF');
    formData.append('pdfData', JSON.stringify(data));
    formData.append('labelType', labelType);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => response.text());
            throw new Error(errorData.error || `Lỗi Server: ${response.status}`);
        }

        const pdfBlob = await response.blob();
        const fileName = response.headers.get('Content-Disposition')?.match(/filename="(.+)"/)?.[1] || 
                         `Tem_NhapKho_${labelType}_${Date.now()}.pdf`;

        const isCordova = typeof cordova !== 'undefined' && 
                         typeof cordova.plugins !== 'undefined' && 
                         typeof cordova.file !== 'undefined' && 
                         typeof cordova.plugins.fileOpener2 !== 'undefined';

        if (isCordova) {
            await saveAndOpenPDF(pdfBlob, fileName);
        } else {
            const pdfUrl = URL.createObjectURL(pdfBlob);
            const newWindow = window.open(pdfUrl, '_blank');
            if (!newWindow) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Popup bị chặn',
                    text: 'Trình duyệt đã chặn mở tab mới. Nhấn OK để tải file.',
                    showCancelButton: true,
                    confirmButtonText: 'Tải file',
                    cancelButtonText: 'Hủy',
                    customClass: {
                        popup: 'rounded-xl',
                        title: 'text-lg font-semibold',
                        confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        const link = document.createElement('a');
                        link.href = pdfUrl;
                        link.download = fileName;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }
                });
            }
            setTimeout(() => URL.revokeObjectURL(pdfUrl), 10000);
        }

        Swal.fire({
            icon: 'success',
            title: 'Thành công!',
            text: 'File PDF đã được xử lý.',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi!',
            text: `Lỗi khi tạo PDF: ${error.message}`,
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false
        });
    } finally {
        Swal.close();
    }
}

// Lưu và mở file PDF trên thiết bị Cordova
async function saveAndOpenPDF(pdfBlob, fileName) {
    if (!cordova.file || !cordova.plugins.fileOpener2) {
        throw new Error('Plugin Cordova không sẵn sàng (file hoặc fileOpener2).');
    }

    if (cordova.plugins.permissions) {
        const permissions = cordova.plugins.permissions;
        const perms = [permissions.WRITE_EXTERNAL_STORAGE, permissions.READ_EXTERNAL_STORAGE];
        await new Promise((resolve, reject) => {
            permissions.checkPermission(perms[0], (status) => {
                if (status.hasPermission) resolve();
                else {
                    permissions.requestPermissions(perms, (status) => {
                        if (status.hasPermission) resolve();
                        else reject(new Error('Quyền truy cập bộ nhớ bị từ chối.'));
                    }, reject);
                }
            }, reject);
        });
    }

    return new Promise((resolve, reject) => {
        const directory = cordova.file.externalDataDirectory || cordova.file.documentsDirectory || cordova.file.dataDirectory;
        window.resolveLocalFileSystemURL(directory, (dirEntry) => {
            dirEntry.getFile(fileName, { create: true, exclusive: false }, (fileEntry) => {
                fileEntry.createWriter((fileWriter) => {
                    fileWriter.onwriteend = () => {
                        cordova.plugins.fileOpener2.open(
                            fileEntry.nativeURL,
                            'application/pdf',
                            {
                                error: (e) => reject(new Error('Không thể mở file: ' + e.message)),
                                success: () => resolve()
                            }
                        );
                    };
                    fileWriter.onerror = (e) => reject(new Error('Không thể ghi file: ' + e.toString()));
                    fileWriter.write(pdfBlob);
                }, reject);
            }, reject);
        }, reject);
    });
}

// Khởi tạo sự kiện Cordova khi thiết bị sẵn sàng
document.addEventListener('deviceready', () => {
    console.log('Cordova đã sẵn sàng.');
}, false);

// Xử lý sự kiện lưu dữ liệu từ tempData vào cơ sở dữ liệu và tạo PDF
document.getElementById('saveToDB').addEventListener('click', async function() {
    if (tempData.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Chưa có dữ liệu',
            text: 'Vui lòng thêm dữ liệu trước khi nhập kho!',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false
        });
        return;
    }

    const labelType = document.getElementById('labelType').value;
    const maSoMe = document.querySelector('input[name="MaSoMe"]').value;
    const soLuongGiao = <?php echo $soLuongGiao; ?>;

    try {
        const tongSoLuongDaNhapDB = await getTongSoLuongNhap(maSoMe);
        const tongSoLuongNhapMoi = tempData.reduce((sum, item) => sum + item.SoLuong, 0);
        const tongSoLuongHienTai = tongSoLuongDaNhapDB + tongSoLuongNhapMoi;

        if (tongSoLuongHienTai > soLuongGiao) {
            const soLuongConLai = soLuongGiao - tongSoLuongDaNhapDB;
            Swal.fire({
                icon: 'error',
                title: 'Lỗi!',
                html: `
                    <div class="text-left space-y-3">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2 text-red-500"></i>
                            <span>Bạn đã nhập quá số lượng yêu cầu: <span style="color: red;" class="font-semibold">${soLuongGiao.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?></span></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-arrow-up mr-2 text-red-500"></i>
                            <span>Đã nhập: <span style="color: red;" class="font-semibold">${tongSoLuongDaNhapDB.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?></span></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-arrow-up mr-2 text-red-500"></i>
                            <span>Bạn đang nhập: <span style="color: red;" class="font-semibold">${tongSoLuongNhapMoi.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?></span></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2 text-yellow-500"></i>
                            <span>Bạn chỉ được phép nhập tối đa: <span style="color: red;" class="font-semibold">${soLuongConLai.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?></span></span>
                        </div>
                    </div>
                `,
                confirmButtonText: 'OK',
                customClass: {
                    popup: 'rounded-xl',
                    title: 'text-lg font-semibold',
                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                },
                buttonsStyling: false,
                width: '90%',
                padding: '1rem'
            });
            return;
        }

        const formData = new FormData();
        formData.append('action', 'saveToDB');
        formData.append('data', JSON.stringify(tempData));

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            const lines = data.message.split('\n');
            const htmlContent = `
                <div class="text-left space-y-3">
                    <div class="flex items-center title-row">
                        <i class="fas fa-check-circle mr-2 text-green-500"></i>
                        <span>${lines[0]}</span>
                    </div>
                    <div class="details">
                        ${lines.slice(1).map(line => `
                            <div class="flex items-center">
                                <div class="icon-col">
                                    ${line.includes('Tổng số lượng') ? '<i class="fas fa-box-open text-blue-500"></i>' :
                                    line.includes('Tổng đã nhập') ? '<i class="fas fa-arrow-down text-green-500"></i>' :
                                    '<i class="fas fa-hourglass-half text-orange-500"></i>'}
                                </div>
                                <div class="label-col">${line.split(':')[0]}:</div>
                                <div class="value-col">${line.split(':').slice(1).join(':').trim()}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                <style>
                    .title-row { font-weight: bold; margin-bottom: 10px; }
                    .icon-col { width: 30px; text-align: center; }
                    .label-col { width: 150px; text-align: right; padding-right: 10px; }
                    .value-col { flex: 1; text-align: left; }
                    .details { space-y-3; }
                </style>
            `;

            Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                html: htmlContent,
                confirmButtonText: 'OK',
                customClass: {
                    popup: 'rounded-xl',
                    title: 'text-lg font-semibold',
                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                },
                buttonsStyling: false,
                width: '90%',
                padding: '1rem'
            }).then(() => {
                Swal.fire({
                    title: 'Đang tạo tem PDF...',
                    text: 'Xin vui lòng chờ chuyển đến in tem PDF.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                generatePDF(tempData, labelType).finally(() => {
                    Swal.close();
                });

                document.getElementById('tongSoLuongGiaoDisplay').textContent = parseFloat(data.data.soLuongGiao).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + data.data.donViTinh;
                document.getElementById('tongDaNhapDisplay').textContent = parseFloat(data.data.tongSoLuongNhap).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + data.data.donViTinh;
                document.getElementById('soLuongConLaiDisplay').textContent = parseFloat(data.data.soLuongConLai).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + data.data.donViTinh;

                if (parseFloat(data.data.soLuongConLai) === 0) {
                    const updateStatusFormData = new FormData();
                    updateStatusFormData.append('action', 'updateDonStatus');
                    updateStatusFormData.append('maSoMe', maSoMe);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: updateStatusFormData
                    })
                    .then(updateResponse => updateResponse.json())
                    .then(updateData => {
                        if (updateData.success) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Đã nhập đủ',
                                text: 'Đơn hàng này đã nhập đủ số lượng và trạng thái nhập đủ hàng đã được cập nhật.',
                                confirmButtonText: 'OK',
                                customClass: {
                                    popup: 'rounded-xl',
                                    title: 'text-lg font-semibold',
                                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                                },
                                buttonsStyling: false
                            }).then(() => {
                                window.location.href = '../nhapkho.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi cập nhật trạng thái',
                                text: 'Có lỗi xảy ra khi cập nhật trạng thái đơn hàng: ' + updateData.message,
                                confirmButtonText: 'OK',
                                customClass: {
                                    popup: 'rounded-xl',
                                    title: 'text-lg font-semibold',
                                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                                },
                                buttonsStyling: false
                            });
                        }
                    })
                    .catch(updateError => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi mạng',
                            text: 'Lỗi kết nối khi cập nhật trạng thái đơn hàng: ' + updateError.message,
                            confirmButtonText: 'OK',
                            customClass: {
                                popup: 'rounded-xl',
                                title: 'text-lg font-semibold',
                                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                            },
                            buttonsStyling: false
                        });
                    });
                }

                tempData = [];
                tempSTT = 0;
                updateTable();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi!',
                text: data.message,
                confirmButtonText: 'OK',
                customClass: {
                    popup: 'rounded-xl',
                    title: 'text-lg font-semibold',
                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                },
                buttonsStyling: false
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi Hệ Thống',
            text: 'Đã xảy ra lỗi: ' + error.message,
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false
        });
    }
});
//xem hàng mới
document.getElementById('XemChiTietNhap').addEventListener('click', async function() {
    const maSoMe = '<?php echo htmlspecialchars($maSoMe); ?>';
    const isKgUnit = '<?php echo $don['MaDVT'] === '1' || $tenDVT === 'KG' ? 'true' : 'false'; ?>';
    if (!maSoMe) {
        Swal.fire({
            icon: 'warning',
            title: 'Lỗi!',
            text: 'Mã số mẻ không hợp lệ.',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false
        });
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'getChiTietNhapKho');
        formData.append('maSoMe', maSoMe);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success && result.data.length > 0) {
            const tableRows = result.data.map((item, index) => `
                <tr>
                    <td class="border px-4 py-2">${index + 1}</td>
                    <td class="border px-4 py-2">${parseFloat(item.SoLuong).toFixed(2)} <?php echo $tenDVT; ?></td>
                    ${isKgUnit === 'false' ? `<td class="border px-4 py-2">${item.SoKgCan ? parseFloat(item.SoKgCan).toFixed(2) + ' kg' : ''}</td>` : ''}
                    <td class="border px-4 py-2">${item.SoLot}</td>
                    <td class="border px-4 py-2">${item.TenThanhPhan}</td>
                    <td class="border px-4 py-2">${item.MaKhuVuc ||''}</td>
                    <td class="border px-4 py-2">${item.GhiChu || ''}</td>
                </tr>
            `).join('');

            const htmlContent = `
                <div class="text-left">                 
                    <div class="overflow-x-auto">
                        <table class="min-w-[800px] text-sm text-left text-gray-700 border-collapse shadow-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border px-6 py-3 font-semibold min-w-[60px]">🔢 STT</th>
                                    <th class="border px-6 py-3 font-semibold min-w-[120px]">📦 Số Lượng</th>
                                    ${isKgUnit === 'false' ? '<th class="border px-6 py-3 font-semibold min-w-[120px]">⚖️ Số KG Cân</th>' : ''}
                                    <th class="border px-6 py-3 font-semibold min-w-[150px] whitespace-nowrap">🏷️ Số Lot</th>
                                    <th class="border px-6 py-3 font-semibold min-w-[200px] whitespace-nowrap">🧵 Thành Phần</th>
                                    <th class="border px-6 py-3 font-semibold min-w-[120px] whitespace-nowrap">📍 Khu Vực</th>
                                    <th class="border px-6 py-3 font-semibold min-w-[150px] whitespace-nowrap">📝 Ghi Chú</th>
                                </tr>
                            </thead>
                            <tbody>${tableRows}</tbody>
                        </table>
                    </div>
                </div>
                <style>
                    table { border: 1px solid #e5e7eb; }
                    th, td { border: 1px solid #e5e7eb; white-space: nowrap; }
                    tbody tr:hover { background-color: #f9fafb; }
                    .overflow-x-auto::-webkit-scrollbar { height: 8px; }
                    .overflow-x-auto::-webkit-scrollbar-thumb { background-color: #d1d5db; border-radius: 4px; }
                    .overflow-x-auto::-webkit-scrollbar-track { background-color: #f3f4f6; }
                </style>
            `;

            Swal.fire({
                title: 'Danh sách hàng nhập kho',
                html: htmlContent,
                width: '99%',
                confirmButtonText: 'Đóng',
                showCloseButton: true,
                customClass: {
                    popup: 'rounded-xl',
                    title: 'text-lg font-semibold sticky-title',
                    closeButton: 'custom-close-button',
                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-1 py-1 rounded-lg'
                },
                buttonsStyling: false
            });
        } else {
            Swal.fire({
                icon: 'info',
                title: 'Không có dữ liệu',
                text: 'Chưa có dữ liệu nhập kho cho mã số mẻ này.',
                confirmButtonText: 'OK',
                customClass: {
                    popup: 'rounded-xl',
                    title: 'text-lg font-semibold',
                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                },
                buttonsStyling: false
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi!',
            text: 'Đã xảy ra lỗi khi lấy dữ liệu: ' + error.message,
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false
        });
    }
});
</script>
</body>
</html>