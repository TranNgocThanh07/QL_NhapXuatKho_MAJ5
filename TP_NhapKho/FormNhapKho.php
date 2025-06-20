<?php
session_start();
ob_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once __DIR__ . '/../vendor/autoload.php';

include '../convert.php';
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

// Hàm tạo PDF Tem Hệ Thống
function generateSystemLabel($pdf, $pdfData, $don, $tenMau, $tenDVT, $maSoMe) {
    $font = 'freesans';

    foreach ($pdfData as $item) {
        if (!isset($item['SoLot'], $item['SoLuong'], $item['TenThanhPhan'])) {
            continue;
        }

        $pdf->AddPage();
        $pdf->SetMargins(0, 0, 0);

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
        $columnWidthsPerRow = array_fill(0, 8, array($tableWidth * 0.25, $tableWidth * 0.75));
        $columnWidthsPerRow = array_fill(0, 7, array($tableWidth * 0.25, $tableWidth * 0.75));
        $columnWidthsPerRow[7] = array($tableWidth * 0.25, $tableWidth * 0.50, $tableWidth * 0.125, $tableWidth * 0.125);
        $columnWidthsPerRow[8] = array($tableWidth * 0.25, $tableWidth * 0.50, $tableWidth * 0.25);
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
            $pdf->SetAlpha(0.05);
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

            $pdf->SetLineWidth(1);
            $pdf->Line($margin, $y, $margin + $tableWidth, $y);

            for ($col = 0; $col < count($currentColumnWidths); $col++) {
                $colWidth = $currentColumnWidths[$col];
                $cellX = $currentX;
                $cellY = $y;
                $cellWidth = $colWidth;
                $cellHeight = $rowHeight;

                $pdf->SetLineWidth(1);
                $pdf->Rect($cellX, $cellY, $cellWidth, $cellHeight, 'D');

                switch ($row) {
                    case 0:
                        if ($col == 0) {
                            $qrContent = $item['MaQR'] ?? ($don['MaDonHang'] . "\nSố Lot: " . $item['SoLot'] . "\nSố lượng: " . number_format((float)$item['SoLuong'], 1) . " " . $tenDVT);
                            $qrCodeBinary = generateQRCode($qrContent, 100);
                            $qrPath = __DIR__ . '/temp_qr_' . uniqid() . '.png';
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
                            $pdf->SetFont($font, 'B', 9);
                            $tenVai = $don['TenVai'];
                            if (strpos($tenVai, $don['MaVai'] . ' (') === 0) {
                                $tenVai = preg_replace('/^' . preg_quote($don['MaVai'], '/') . '\s*\(/', '(', $tenVai);
                            }
                            $tenVai = preg_match('/\((.*?)\)/', $tenVai, $matches) ? $matches[1] : $tenVai;
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
                            $pdf->SetFont($font, 'B', 9);
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
                            $pdf->SetFont($font, 'B', 9);
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
                            $pdf->SetFont($font, 'B', 9);
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
                            $pdf->SetFont($font, 'B', 9);
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
                            $pdf->SetFont($font, 'B', 9);
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
                            $pdf->SetFont($font, 'B', 9);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['SoLot'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        } elseif ($col == 2) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'STT', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(NO.)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 3) {
                            $pdf->SetFont($font, 'B', 9);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['STT'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 8:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'SỐ LƯỢNG', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(QUANTITY)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 9);
                            $pdf->MultiCell($cellWidth, $cellHeight, number_format((float)$item['SoLuong'], 1) . " " . $tenDVT, 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        } elseif ($col == 2) {
                            $pdf->SetFont($font, 'B', 9);
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
                            $qrPath = __DIR__ . '/temp_qr_' . uniqid() . '.png';
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

            $pdf->SetLineWidth(1);
            $pdf->Line($margin, $currentY + $rowHeight, $margin + $tableWidth, $currentY + $rowHeight);
            $currentY += $rowHeight;
        }
    }
}

// Hàm tạo PDF Tem Khách Lẻ
function generateRetailLabel($pdf, $pdfData, $don, $tenMau, $tenDVT, $maSoMe) {
    $font = 'freesans';

    foreach ($pdfData as $item) {
        if (!isset($item['SoLot'], $item['SoLuong'], $item['TenThanhPhan'])) {
            continue;
        }

        $pdf->AddPage();
        $margin = 10;
        $tableTop = $margin;
        $tableWidth = 297.63 - 2 * $margin;
        $tableHeight = 419.53 - 2 * $margin;

        $rowHeights = array(40, 15, 15, 15, 15, 15, 15, 40);
        $totalRowHeight = array_sum($rowHeights);
        if ($totalRowHeight < $tableHeight) {
            $heightDifference = $tableHeight - $totalRowHeight;
            $heightIncreasePerRow = $heightDifference / count($rowHeights);
            for ($i = 0; $i < count($rowHeights); $i++) {
                $rowHeights[$i] += $heightIncreasePerRow;
            }
        }

        $columnWidthsPerRow = array(
            array($tableWidth * 0.25, $tableWidth * 0.5, $tableWidth * 0.25),
            array($tableWidth * 0.25, $tableWidth * 0.75),
            array($tableWidth * 0.25, $tableWidth * 0.75),
            array($tableWidth * 0.25, $tableWidth * 0.75),
            array($tableWidth * 0.25, $tableWidth * 0.75),
            array($tableWidth * 0.25, $tableWidth * 0.50,$tableWidth * 0.125,$tableWidth * 0.125),
            array($tableWidth * 0.25, $tableWidth * 0.50, $tableWidth * 0.25),
            array($tableWidth * 0.75, $tableWidth * 0.25)
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

            $pdf->SetLineWidth(1);
            $pdf->Line($margin, $y, $margin + $tableWidth, $y);

            for ($col = 0; $col < count($currentColumnWidths); $col++) {
                $colWidth = $currentColumnWidths[$col];
                $cellX = $currentX;
                $cellY = $y;
                $cellWidth = $colWidth;
                $cellHeight = $rowHeight;

                $pdf->SetLineWidth(1);
                $pdf->Rect($cellX, $cellY, $cellWidth, $cellHeight, 'D');

                switch ($row) {
                    case 0:
                        if ($col == 0 || $col == 2) {
                            $qrContent = $item['MaQR'] ?? ($don['MaDonHang'] . "\nSố Lot: " . $item['SoLot'] . "\nSố lượng: " . number_format((float)$item['SoLuong'], 1) . " " . $tenDVT);
                            $qrCodeBinary = generateQRCode($qrContent, 100);
                            $qrPath = __DIR__ . '/temp_qr_' . uniqid() . '.png';
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
                            $pdf->SetFont($font, 'B', 9);
                            $tenVai = $don['TenVai'];
                            if (strpos($tenVai, $don['MaVai'] . ' (') === 0) {
                                $tenVai = preg_replace('/^' . preg_quote($don['MaVai'], '/') . '\s*\(/', '(', $tenVai);
                            }
                            $tenVai = preg_match('/\((.*?)\)/', $tenVai, $matches) ? $matches[1] : $tenVai;
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
                            $pdf->SetFont($font, 'B', 9);
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
                            $pdf->SetFont($font, 'B', 9);
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
                            $pdf->SetFont($font, 'B', 9);
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
                            $pdf->SetFont($font, 'B', 9);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['SoLot'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        } elseif ($col == 2) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'STT', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(NO.)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 3) {
                            $pdf->SetFont($font, 'B', 9);
                            $pdf->MultiCell($cellWidth, $cellHeight, $item['STT'], 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 6:
                        if ($col == 0) {
                            $pdf->SetFont($font, 'B', 8);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, 'SỐ LƯỢNG', 0, 'C', false, 1, $cellX, $cellY + $paddingCell);
                            $pdf->SetFont($font, 'B', 6);
                            $pdf->MultiCell($cellWidth, $cellHeight / 2, '(QUANTITY)', 0, 'C', false, 1, $cellX, $cellY + $paddingCell + 15);
                        } elseif ($col == 1) {
                            $pdf->SetFont($font, 'B', 9);
                            $pdf->MultiCell($cellWidth, $cellHeight, number_format((float)$item['SoLuong'], 1) . " " . $tenDVT, 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        } elseif ($col == 2) {
                            $pdf->SetFont($font, 'B', 9);
                            $soKgCanDisplay = isset($item['SoKgCan']) && $item['SoKgCan'] !== null ? "≈" . " " . number_format((float)$item['SoKgCan'], 1) . " KG" : '';
                            $pdf->MultiCell($cellWidth, $cellHeight, $soKgCanDisplay, 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        }
                        break;
                    case 7:
                        if ($col == 1) {
                            $qrContent = $item['MaQR'] ?? ($don['MaDonHang'] . "\nSố Lot: " . $item['SoLot'] . "\nSố lượng: " . number_format((float)$item['SoLuong'], 1) . " " . $tenDVT);
                            $qrCodeBinary = generateQRCode($qrContent, 100);
                            $qrPath = __DIR__ . '/temp_qr_' . uniqid() . '.png';
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

            $pdf->SetLineWidth(1);
            $pdf->Line($margin, $currentY + $rowHeight, $margin + $tableWidth, $currentY + $rowHeight);
            $currentY += $rowHeight;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generatePDF') {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    $pdfData = isset($_POST['pdfData']) ? json_decode($_POST['pdfData'], true) : null;
    $maSoMe = $pdfData[0]['MaSoMe'] ?? '';
    $labelType = $_POST['labelType'] ?? 'system';

    if (empty($pdfData) || !is_array($pdfData) || empty($maSoMe)) {
        sendError("Dữ liệu đầu vào không đủ hoặc không hợp lệ (thiếu pdfData hoặc MaSoMe).");
    }

    error_log("[" . date('Y-m-d H:i:s') . "] Bắt đầu generatePDF: maSoMe=$maSoMe, labelType=$labelType, pdfDataItems=" . count($pdfData));

    try {
        $sqlDon = "SELECT ds.*, dvt.TenDVT
                   FROM TP_DonSanXuat ds
                   LEFT JOIN TP_DonViTinh dvt ON ds.MaDVT = dvt.MaDVT
                   WHERE ds.MaSoMe = ?";
        $stmtDon = $pdo->prepare($sqlDon);
        if (!$stmtDon) {
            sendError("Lỗi chuẩn bị truy vấn SQL đơn hàng: " . print_r($pdo->errorInfo(), true));
        }
        $stmtDon->execute([$maSoMe]);
        $don = $stmtDon->fetch(PDO::FETCH_ASSOC);

        if (!$don) {
            sendError("Không tìm thấy đơn hàng với MaSoMe: " . htmlspecialchars($maSoMe));
        }
        $tenDVT = $don['TenDVT'] ?? 'kg';

        error_log("[" . date('Y-m-d H:i:s') . "] Truy vấn đơn hàng thành công: tenDVT=$tenDVT");

        $sqlMau = "SELECT TenMau FROM TP_Mau WHERE MaMau = ?";
        $stmtMau = $pdo->prepare($sqlMau);
        if (!$stmtMau) {
            sendError("Lỗi chuẩn bị truy vấn SQL màu: " . print_r($pdo->errorInfo(), true));
        }
        $stmtMau->execute([$pdfData[0]['MaMau']]);
        $mau = $stmtMau->fetch(PDO::FETCH_ASSOC);
        $tenMau = $mau['TenMau'] ?? 'N/A';

        error_log("[" . date('Y-m-d H:i:s') . "] Truy vấn màu thành công: tenMau=$tenMau");

        $pdf = new TCPDF('P', 'pt', [297.63, 419.53], true, 'UTF-8', false);
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
        $pdfFileName = "Tem_NhapKhoTon_" . ($labelType === 'khachle' ? 'KhachLe_' : 'HeThong_') . "{$safeMaSoMe}_{$timestamp}.pdf";

        $pdfDataOutput = $pdf->Output('', 'S');
        if (strlen($pdfDataOutput) < 100 || strpos($pdfDataOutput, '%PDF') !== 0) {
            error_log("[" . date('Y-m-d H:i:s') . "] Dữ liệu PDF không hợp lệ: length=" . strlen($pdfDataOutput));
            sendError("Dữ liệu PDF đầu ra không hợp lệ");
        }

        $bmpDataArray = convertPdfToBmpAllPagesInMemory($pdfDataOutput);
        if (!$bmpDataArray || empty($bmpDataArray)) {
            error_log("[" . date('Y-m-d H:i:s') . "] Chuyển đổi PDF sang BMP thất bại");
            sendError("Không thể chuyển đổi PDF sang BMP");
        }

        error_log("[" . date('Y-m-d H:i:s') . "] Chuyển đổi BMP thành công: pages=" . count($bmpDataArray));

        // Tạo danh sách BMP base64
        $bmpBase64Array = array_map(function($bmpData) {
            $base64 = base64_encode($bmpData);
            error_log("[" . date('Y-m-d H:i:s') . "] BMP base64: size=" . strlen($base64));
            return $base64;
        }, $bmpDataArray);

        // Trả về phản hồi JSON với dữ liệu BMP base64
        $response = [
            'status' => 'success',
            'bmpData' => $bmpBase64Array, // Trả về base64 thay vì tên file
            'labelType' => $labelType,
            'redirect' => 'printer_interface.php'
        ];

        error_log("[" . date('Y-m-d H:i:s') . "] Trả về JSON thành công: bmpCount=" . count($bmpBase64Array));
        echo json_encode($response);
        exit;
    } catch (Throwable $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Lỗi nghiêm trọng: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        sendError("Lỗi nghiêm trọng khi tạo hoặc xuất BMP", $e);
    }
}

// Xử lý lưu vào DB khi nhấn "Nhập kho"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'saveToDB') {
    header('Content-Type: application/json');
    
    $data = json_decode($_POST['data'], true);
    
    try {
        $pdo->beginTransaction();     

        $sqlDon = "SELECT SoKgQuyDoi, TenDVT, TongSoLuongGiao FROM TP_DonSanXuat ds
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
            MaKhachHang, MaNhanVien, TenThanhPhan, SoKgCan, OriginalTrangThai, MaKhuVuc, GhiChu, STT
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($sqlInsert);

        $sqlMaxSTT = "SELECT MAX(STT) as MaxSTT FROM TP_ChiTietDonSanXuat WHERE MaSoMe = ? AND SoLot = ?";
        $stmtMaxSTT = $pdo->prepare($sqlMaxSTT);

        foreach ($data as $item) {
            $soKgCan = isset($item['SoKgCan']) && is_numeric($item['SoKgCan']) && $item['SoKgCan'] > 0 ? floatval($item['SoKgCan']) : null;
            
            // Lưu hoặc cập nhật ghi chú số lot
            if (!empty($item['GhiChuLot'])) {
                $sqlCheckLot = "SELECT COUNT(*) as count FROM TP_SoLotMe WHERE MaSoMe = ? AND SoLot = ?";
                $stmtCheckLot = $pdo->prepare($sqlCheckLot);
                $stmtCheckLot->execute([$item['MaSoMe'], $item['SoLot']]);
                $exists = $stmtCheckLot->fetch(PDO::FETCH_ASSOC)['count'] > 0;

                if ($exists) {
                    $sqlUpdateLot = "UPDATE TP_SoLotMe SET GhiChu = ? WHERE MaSoMe = ? AND SoLot = ?";
                    $stmtUpdateLot = $pdo->prepare($sqlUpdateLot);
                    $stmtUpdateLot->execute([$item['GhiChuLot'], $item['MaSoMe'], $item['SoLot']]);
                } else {
                    $sqlInsertLot = "INSERT INTO TP_SoLotMe (MaSoMe, SoLot, GhiChu) VALUES (?, ?, ?)";
                    $stmtInsertLot = $pdo->prepare($sqlInsertLot);
                    $stmtInsertLot->execute([$item['MaSoMe'], $item['SoLot'], $item['GhiChuLot']]);
                }
            }

            // Xác định STT dựa trên SoLot
            $stmtMaxSTT->execute([$item['MaSoMe'], $item['SoLot']]);
            $maxSTTResult = $stmtMaxSTT->fetch(PDO::FETCH_ASSOC);
            $stt = ($maxSTTResult['MaxSTT'] !== null) ? intval($maxSTTResult['MaxSTT']) + 1 : 1;

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
            $stmtInsert->bindValue(23, $stt);
            $stmtInsert->execute();
        }

        $pdo->commit();

        $soLuongConLai = $soLuongGiao - $tongSoLuongMoi;
        $message = "<div style=\"font-size: 14px;\">" .
                    "Nhập kho thành công!<br>" .
                    "Tổng số lượng: <span style=\"color: red;\">" . number_format($soLuongGiao) . "</span> $tenDVT<br>" .
                    "Tổng đã nhập: <span style=\"color: red;\">" . number_format($tongSoLuongMoi) . "</span> $tenDVT<br>" .
                    "Còn lại nhập: <span style=\"color: red;\">" . number_format($soLuongConLai) . "</span> $tenDVT" .
                    "</div>";

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'saveOrUpdateGhiChuLot') {
    header('Content-Type: application/json');
    $maSoMe = $_POST['maSoMe'] ?? '';
    $soLot = $_POST['soLot'] ?? '';
    $ghiChuLot = $_POST['ghiChuLot'] ?? '';

    if (empty($maSoMe) || empty($soLot)) {
        echo json_encode(['success' => false, 'message' => 'Thiếu mã số mẻ hoặc số lot.']);
        exit;
    }

    try {
        // Kiểm tra xem số lot đã tồn tại chưa
        $sqlCheck = "SELECT COUNT(*) as count FROM TP_SoLotMe WHERE MaSoMe = ? AND SoLot = ?";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$maSoMe, $soLot]);
        $exists = $stmtCheck->fetch(PDO::FETCH_ASSOC)['count'] > 0;

        if ($exists) {
            // Cập nhật ghi chú
            $sqlUpdate = "UPDATE TP_SoLotMe SET GhiChu = ? WHERE MaSoMe = ? AND SoLot = ?";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([$ghiChuLot, $maSoMe, $soLot]);
        } else {
            // Thêm mới số lot
            $sqlInsert = "INSERT INTO TP_SoLotMe (MaSoMe, SoLot, GhiChu) VALUES (?, ?, ?)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([$maSoMe, $soLot, $ghiChuLot]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
    }
    exit;
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
$sqlKhuVuc = "SELECT MaKhuVuc FROM TP_KhuVuc ORDER BY MaKhuVuc";
$stmtKhuVuc = $pdo->prepare($sqlKhuVuc);
$stmtKhuVuc->execute();
$MaKhuVucList = $stmtKhuVuc->fetchAll(PDO::FETCH_ASSOC);


// Xử lý cập nhật trạng thái đơn hàng khi nhập đủ số lượng và cập nhật trạng thái đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'updateDonStatus') {
    header('Content-Type: application/json');
    $maSoMeToUpdate = $_POST['maSoMe'] ?? '';
    $forceComplete = isset($_POST['forceComplete']) && $_POST['forceComplete'] === 'true';

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

            // Kiểm tra nhập đủ số lượng nếu không phải trường hợp đặc biệt
            if (!$forceComplete && $tongDaNhap < $tongSoLuongGiao) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Chưa nhập đủ số lượng, không thể cập nhật trạng thái. Đã nhập: ' . 
                                 number_format($tongDaNhap, 2) . ' / ' . 
                                 number_format($tongSoLuongGiao, 2)
                ]);
                exit;
            }

            // Cập nhật trạng thái đơn hàng
            $sqlUpdateStatus = "UPDATE TP_DonSanXuat SET TrangThai = 3 WHERE MaSoMe = ?";
            $stmtUpdateStatus = $pdo->prepare($sqlUpdateStatus);
            $stmtUpdateStatus->execute([$maSoMeToUpdate]);

            if ($stmtUpdateStatus->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Trạng thái đơn hàng đã được cập nhật thành "Đã nhập đủ".'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Không thể cập nhật trạng thái đơn hàng hoặc trạng thái đã được cập nhật trước đó.'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng với mã số mẻ: ' . htmlspecialchars($maSoMeToUpdate)
            ]);
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
        $sqlChiTiet = "SELECT ctdsx.SoLuong, ctdsx.SoKgCan, ctdsx.SoLot, ctdsx.TenThanhPhan, ctdsx.MaKhuVuc, ctdsx.GhiChu, ctdsx.STT, slm.GhiChu as GhiChuLot
                       FROM TP_ChiTietDonSanXuat ctdsx
                       LEFT JOIN TP_SoLotMe slm ON ctdsx.MaSoMe = slm.MaSoMe AND ctdsx.SoLot = slm.SoLot
                       WHERE ctdsx.MaSoMe = ? AND ctdsx.TrangThai = 0
                       ORDER BY ctdsx.SoLot, ctdsx.STT";
        $stmtChiTiet = $pdo->prepare($sqlChiTiet);
        $stmtChiTiet->execute([$maSoMe]);
        $chiTietList = $stmtChiTiet->fetchAll(PDO::FETCH_ASSOC);

        // Ghi log để kiểm tra dữ liệu trả về
        error_log("[" . date('Y-m-d H:i:s') . "] Dữ liệu chi tiết nhập kho: " . json_encode($chiTietList));

        echo json_encode([
            'success' => true,
            'data' => $chiTietList
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkSoLot') {
    $soLot = $_POST['soLot'] ?? '';
    $maSoMe = $_POST['maSoMe'] ?? '';

    if (empty($soLot) || empty($maSoMe)) {
        echo json_encode(['success' => false, 'message' => 'Thiếu số lot hoặc mã số mẻ.']);
        exit;
    }

    try {
        $sql = "SELECT GhiChu FROM TP_SoLotMe WHERE MaSoMe = ? AND SoLot = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$maSoMe, $soLot]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $result ? ['GhiChu' => $result['GhiChu'] ?? ''] : null
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
    }
    exit;
}

$maSoMe = $_GET['maSoMe'] ?? '';
$soLotList = [];
if ($maSoMe) {
    $sqlSoLot = "SELECT SoLot, GhiChu FROM TP_SoLotMe WHERE MaSoMe = ? ORDER BY SoLot";
    $stmtSoLot = $pdo->prepare($sqlSoLot);
    $stmtSoLot->execute([$maSoMe]);
    $soLotList = $stmtSoLot->fetchAll(PDO::FETCH_ASSOC);
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
            padding: 5px;
            text-align: left;
            font-size: 12px;
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
                min-width: 80px;
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
                    <span id="tongDaNhapDisplay" class="text-right text-green-700 font-semibold"><?php echo number_format($tongSoLuongNhap, 2); ?> <?php echo $tenDVT; ?></span>
                    <div class="flex items-center">
                        <i class="fas fa-hourglass-half text-orange-500 mr-2"></i>
                        <span class="text-gray-700">Còn lại:</span>
                    </div>
                    <span id="soLuongConLaiDisplay" class="text-right text-red-800 font-semibold"><?php echo number_format($soLuongConLai, 2); ?> <?php echo $tenDVT; ?></span>
                </div>
            </div>

            <form id="nhapHangForm" class="form-container">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Tên Khách Hàng</label>
                        <input type="text" value="<?php echo htmlspecialchars($don['TenKhachHang'] ?? ''); ?>" readonly 
                            class="input-field w-full p-2 text-xs rounded-lg bg-gray-100 text-gray-600">
                        <input type="hidden" name="MaKhachHang" value="<?php echo htmlspecialchars($don['MaKhachHang'] ?? ''); ?>">
                    </div>
                    <!-- Dòng 1: Mã Số Mẻ và Mã Đơn Hàng -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Mã Số Mẻ</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['MaSoMe'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2 rounded-lg bg-gray-100 text-xs text-gray-600">
                            <input type="hidden" name="MaSoMe" value="<?php echo htmlspecialchars($don['MaSoMe'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Mã Đơn Hàng</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['MaDonHang'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2 rounded-lg bg-gray-100 text-xs text-gray-600">
                            <input type="hidden" name="MaDonHang" value="<?php echo htmlspecialchars($don['MaDonHang'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Mã Vật Tư</label>
                        <input type="text" value="<?php echo htmlspecialchars($don['MaVatTu'] ?? ''); ?>" readonly 
                            class="input-field w-full p-2 text-xs rounded-lg bg-gray-100 text-gray-600">
                        <input type="hidden" name="MaVatTu" value="<?php echo htmlspecialchars($don['MaVatTu'] ?? ''); ?>">
                    </div>
                    

                    <!-- Dòng 2: Mã Vải và Tên Vải -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Mã Vải</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['MaVai'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2 rounded-lg bg-gray-100 text-xs text-gray-600">
                            <input type="hidden" name="MaVai" value="<?php echo htmlspecialchars($don['MaVai'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Tên Vải</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['TenVai'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2 rounded-lg bg-gray-100 text-xs text-gray-600">
                            <input type="hidden" name="TenVai" value="<?php echo htmlspecialchars($don['TenVai'] ?? ''); ?>">
                        </div>
                    </div>                 

                    <!-- Dòng 3: Khổ và Đơn Vị Tính -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Khổ</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['Kho'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2 rounded-lg bg-gray-100 text-xs text-gray-600">
                            <input type="hidden" name="Kho" value="<?php echo htmlspecialchars($don['Kho'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Đơn Vị Tính</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['TenDVT'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2 rounded-lg bg-gray-100 text-xs text-gray-600">
                            <input type="hidden" name="MaDVT" value="<?php echo htmlspecialchars($don['MaDVT'] ?? ''); ?>">
                        </div>
                    </div>
                 
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Tên Màu</label>
                            <input type="text" value="<?php echo htmlspecialchars($don['TenMau'] ?? ''); ?>" readonly 
                                class="input-field w-full p-2 text-xs rounded-lg bg-gray-100 text-gray-600">
                            <input type="hidden" name="MaMau" value="<?php echo htmlspecialchars($don['MaMau'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Thành Phần</label>
                            <input type="text" name="TenThanhPhan" id="TenThanhPhan" 
                                value="<?php echo htmlspecialchars($tenThanhPhan); ?>" 
                                class="input-field w-full p-2 text-xs rounded-lg text-gray-600">
                        </div>

                    </div>
                  <!-- Dòng 4: Số Lượng, Số KG Cân -->
                  <div class="grid <?php echo ($don['MaDVT'] !== '1' && $tenDVT !== 'KG') ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Số Lượng (<?php echo $tenDVT; ?>)</label>
                            <input type="number" step="0.01" name="SoLuong" id="soLuong" 
                                class="input-field w-full p-2 text-xs rounded-lg">
                        </div>
                        <?php if ($don['MaDVT'] !== '1' && $tenDVT !== 'KG'): ?>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Số Kg Cân</label>
                            <input type="number" step="0.01" name="SoKgCan" id="soKGCan" 
                                class="input-field w-full p-2 text-xs rounded-lg">
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="SoKgCan" id="soKGCan">
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="SoCay" id="soCay" value="1">
                   
                    <!-- Khuvuc Và Ghi Chú -->
                     <div class="grid grid-cols-2 gap-4">
                     
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Số Lot</label>
                            <input type="text" id="soLot" name="SoLot" value=""
                                list="soLotList"
                                oninput="restrictLotNumber(this)"
                                class="input-field w-full p-2 rounded-lg text-xs border-gray-300 focus:border-blue-500"
                                placeholder="Nhập số lot (tối đa 8 số)" required>
                            <datalist id="soLotList">
                                <?php foreach ($soLotList as $lot): ?>
                                    <option value="<?php echo htmlspecialchars($lot['SoLot']); ?>" data-ghichu="<?php echo htmlspecialchars($lot['GhiChu'] ?? ''); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Ghi Chú Lot</label>
                            <input type="text" id="ghiChuLot" name="GhiChuLot"
                                class="input-field w-full p-2 rounded-lg text-xs border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Nhập ghi chú cho số lot (nếu có)">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Khu Vực</label>
                            <select name="MaKhuVuc" id="MaKhuVuc" class="input-field w-full p-2 text-xs rounded-lg">
                                <option value="">-- Chọn khu vực --</option>
                                <?php
                                foreach ($MaKhuVucList as $row) {
                                    $MaKhuVuc = htmlspecialchars($row['MaKhuVuc']);
                                    echo "<option value=\"$MaKhuVuc\">$MaKhuVuc</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Ghi chú</label>
                            <input type="text" name="GhiChu" id="GhiChu" class="input-field w-full p-2 text-xs rounded-lg" oninput="this.value = this.value.toUpperCase();">
                    </div>
                    
                    
                               
                    <!-- Hidden fields -->
                    <input type="hidden" name="MaNguoiLienHe" value="<?php echo htmlspecialchars($don['MaNguoiLienHe'] ?? ''); ?>">
                    <input type="hidden" name="MaNhanVien" value="<?php echo htmlspecialchars($maNhanVien); ?>">
                    <input type="hidden" name="NgayTao" value="<?php echo date('Y-m-d H:i:s'); ?>">
                    <input type="hidden" name="TrangThai" value="0">
                    <input type="hidden" name="OriginalTrangThai" value="0">
                </div>

                <!-- Buttons giữ nguyên -->
                <div class="mt-6 flex text-xs justify-end gap-3">
                    <button type="submit" 
                        class="btn px-4 py-2 bg-green-600 text-white rounded-lg shadow-md hover:bg-blue-700">
                        <i class="fas fa-check mr-2"></i>Xác Nhận
                    </button>
                    <a href="../nhapkho.php" 
                        class="btn px-4 py-2 bg-red-600 text-white rounded-lg shadow-md hover:bg-gray-700">
                        <i class="fas fa-times mr-2"></i>Hủy
                    </a>
                </div>
            </form>
             

            <!-- Table giữ nguyên -->
            <div class="mt-6 mx-auto mb-20">
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
                   
                    <button id="saveToDB" 
                        class="btn px-4 py-2 text-xs bg-blue-600 text-white rounded-lg shadow-md hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Nhập
                    </button>
                   <button id="XemChiTietNhap" 
                        class="btn px-4 py-2 text-xs bg-orange-600 text-white rounded-lg shadow-md hover:bg-orange-700 flex items-center">
                        <i class="fas fa-eye mr-2"></i>Xem
                    </button>
                    <button id="XacNhanHoanThanh" 
                        class="btn px-4 py-2 text-xs bg-green-600 text-white rounded-lg shadow-md hover:bg-green-700 flex items-center">
                        <i class="fas fa-eye mr-2"></i>Xác Nhận Đơn
                    </button>
                </div>
                <div class="flex items-center space-x-2 mt-4">
                    <label for="labelType" class="text-sm font-medium text-gray-700 whitespace-nowrap">Chọn Tem để in:</label>
                    <select id="labelType" class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="system">Tem Hệ Thống</option>
                        <option value="khachle">Tem Khách Lẻ</option>
                    </select>
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

//sử lý thông báo xác nhận đơn 
async function updateXacNhanHoanThanhButton() {
    const maSoMe = '<?php echo htmlspecialchars($maSoMe); ?>';
    const soLuongGiao = <?php echo $soLuongGiao; ?>;
    const xacNhanButton = document.getElementById('XacNhanHoanThanh');

    if (!maSoMe || !xacNhanButton) return;

    try {
        const tongSoLuongDaNhapDB = await getTongSoLuongNhap(maSoMe);
        const tongSoLuongNhapTam = tempData.reduce((sum, item) => sum + item.SoLuong, 0);
        const tongSoLuongDaNhap = tongSoLuongDaNhapDB + tongSoLuongNhapTam;
        const tyLeNhap = (tongSoLuongDaNhap / soLuongGiao) * 100;

        // Luôn cho phép nhấn nút bằng cách không đặt disabled
        xacNhanButton.disabled = false;

        // Cập nhật giao diện nút dựa trên tỷ lệ
        if (tyLeNhap >= 70) {
            xacNhanButton.classList.remove('opacity-50', 'cursor-not-allowed');
            xacNhanButton.classList.add('hover:bg-green-700');
        } else {
            xacNhanButton.classList.add('opacity-50', 'cursor-not-allowed');
            xacNhanButton.classList.remove('hover:bg-green-700');
        }
    } catch (error) {
        console.error('Lỗi khi kiểm tra tỷ lệ nhập kho:', error);
        xacNhanButton.classList.add('opacity-50', 'cursor-not-allowed');
        xacNhanButton.classList.remove('hover:bg-green-700');
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    await updateXacNhanHoanThanhButton();
});
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

function restrictLotNumber(input) {
    // Loại bỏ mọi ký tự không phải số
    input.value = input.value.replace(/[^0-9]/g, '');

    // Giới hạn tối đa 8 chữ số
    if (input.value.length > 8) {
        input.value = input.value.slice(0, 8);
        Swal.fire({
            icon: 'warning',
            title: 'Cảnh báo!',
            text: 'Số Lot chỉ được phép nhập tối đa 8 chữ số.',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false,
            width: '320px',
        });
    }
}

// sự kiện để tự động điền ghi chú lot khi chọn số lot
document.getElementById('soLot').addEventListener('input', async function(e) {
    const soLot = e.target.value.trim();
    const maSoMe = '<?php echo htmlspecialchars($maSoMe); ?>';
    const ghiChuLotInput = document.getElementById('ghiChuLot');

    if (soLot && maSoMe) {
        try {
            const formData = new FormData();
            formData.append('action', 'checkSoLot');
            formData.append('soLot', soLot);
            formData.append('maSoMe', maSoMe);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success && result.data && result.data.GhiChu) {
                ghiChuLotInput.value = result.data.GhiChu;
            } else {
                ghiChuLotInput.value = ''; // Xóa ghi chú nếu không tìm thấy
            }
        } catch (error) {
            console.error('Lỗi khi lấy ghi chú lot:', error);
            ghiChuLotInput.value = ''; // Xóa ghi chú nếu có lỗi
        }
    } else {
        ghiChuLotInput.value = ''; // Xóa ghi chú nếu số lot trống
    }
});

// Xử lý sự kiện submit form nhập kho, kiểm tra và thêm dữ liệu vào tempData
document.getElementById('nhapHangForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    // Lấy giá trị từ form
    const soLuongElement = document.getElementById('soLuong');
    const soKGCanElement = document.getElementById('soKGCan');
    const soLotElement = document.getElementById('soLot');
    const tenThanhPhanElement = document.getElementById('TenThanhPhan');
    const maKhuVucElement = document.getElementById('MaKhuVuc');
    const ghiChuElement = document.getElementById('GhiChu');
    const ghiChuLotElement = document.getElementById('ghiChuLot');

    // Kiểm tra sự tồn tại của các phần tử
    let errorMessages = [];
    if (!soLuongElement) errorMessages.push("Không tìm thấy trường số lượng.");
    if (!soKGCanElement) errorMessages.push("Không tìm thấy trường số KG cân.");
    if (!soLotElement) errorMessages.push("Không tìm thấy trường số Lot.");
    if (!tenThanhPhanElement) errorMessages.push("Không tìm thấy trường thành phần.");
    if (!maKhuVucElement) errorMessages.push("Không tìm thấy trường mã khu vực.");
    if (!ghiChuElement) errorMessages.push("Không tìm thấy trường ghi chú.");
    if (!ghiChuLotElement) errorMessages.push("Không tìm thấy trường ghi chú số lot.");

    if (errorMessages.length > 0) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi cấu hình form!',
            html: `<div class="text-left space-y-2">${errorMessages.map(msg => `<div><i class="fas fa-exclamation-circle mr-2 text-red-600"></i>${msg}</div>`).join('')}</div>`,
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white rounded-lg'
            },
            buttonsStyling: false,
            width: '320px',
        });
        return;
    }

    const soLuong = parseFloat(soLuongElement.value) || 0;
    const soKGCanInput = soKGCanElement.value;
    const soKGCan = soKGCanInput && !isNaN(soKGCanInput) && soKGCanInput.trim() !== '' ? parseFloat(soKGCanInput) : null;
    const soLot = soLotElement.value.trim();
    const tenThanhPhan = tenThanhPhanElement.value.trim();
    const maKhuVuc = maKhuVucElement.value.trim();
    const ghiChu = ghiChuElement.value.trim();
    let ghiChuLot = ghiChuLotElement.value.trim(); // Lấy giá trị từ input

    // Kiểm tra dữ liệu đầu vào
    errorMessages = [];
    if (soLuong <= 0) errorMessages.push("Số lượng phải lớn hơn 0.");
    if (soKGCan !== null && soKGCan < 0) errorMessages.push("Số KG Cân không được âm.");
    if (!soLot) errorMessages.push("Số Lot không được để trống.");
    else if (!/^\d{8}$/.test(soLot)) {
        errorMessages.push(`Số Lot phải đúng 8 chữ số, hiện tại bạn nhập ${soLot.length} chữ số.`);
    }
    if (!tenThanhPhan) errorMessages.push("Thành phần không được để trống.");
    if (!maKhuVuc) errorMessages.push("Mã khu vực không được để trống.");

    // Kiểm tra số cây tối đa
    const currentTotalTrees = tempData.length;
    const newTotalTrees = currentTotalTrees + 1;
    if (newTotalTrees > 20) {
        errorMessages.push(`
            <div style="background-color: #fff8e1; border: 1px solid #ffe0a3; padding: 14px 18px; border-radius: 10px; color: #7c5700; font-size: 14px; line-height: 1.6; margin: 12px 0; box-shadow: 0 2px 6px rgba(0,0,0,0.05); font-family: 'Segoe UI', Tahoma, sans-serif;">
                <div style="margin-bottom: 6px;">🧵 Hiện tại bạn đã nhập: <strong>${currentTotalTrees}</strong> cây .</div>
                <div style="margin-bottom: 6px;">➕ Bạn đang nhập thêm 1 cây, tổng cộng ${newTotalTrees}</strong> cây.</div>
                <div style="color: #b10000; font-weight: bold;">❌ Bạn chỉ được phép nhập tối đa 20 cây trong bảng .</div>
            </div>
        `);
    }

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
            width: '320px',
        });
        return;
    }

    const validMaKhuVuc = <?php echo json_encode(array_column($MaKhuVucList, 'MaKhuVuc')); ?>;
    if (!validMaKhuVuc.includes(maKhuVuc)) {
        Swal.fire({
            icon: 'warning',
            title: 'Mã khu vực không hợp lệ!',
            text: 'Vui lòng chọn một giá trị từ danh sách gợi ý.',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false,
            width: '320px',
        });
        return;
    }

    const formData = new FormData(this);
    const tongSoLuongNhapMoi = soLuong;
    const soLuongGiao = <?php echo $soLuongGiao; ?>;
    const maSoMe = formData.get('MaSoMe');

    try {
        // Kiểm tra số lượng nhập
        const tongSoLuongDaNhapDB = await getTongSoLuongNhap(maSoMe);
        const tongSoLuongDaNhapTrongTemp = tempData.reduce((sum, item) => sum + item.SoLuong, 0);
        const tongSoLuongHienTai = tongSoLuongDaNhapDB + tongSoLuongDaNhapTrongTemp;
        const soLuongConLai = soLuongGiao - tongSoLuongHienTai;

        if (tongSoLuongNhapMoi > soLuongConLai) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi!',
                html: `
                    <div class="text-left space-y-3 text-[14px]">
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
                width: '320px',
            });
            return;
        }

        // Kiểm tra số lot và lấy ghi chú từ server nếu input rỗng
        const serverGhiChuLot = await checkSoLot(soLot, maSoMe);
        const finalGhiChuLot = ghiChuLot ;

        // Tính STT
        const existingItems = tempData.filter(item => item.SoLot === soLot);
        const existingInDB = await getSTTForSoLot(maSoMe, soLot);
        const maxSTT = Math.max(
            ...existingItems.map(item => item.STT || 0),
            existingInDB || 0
        );
        const newSTT = maxSTT + 1;

        const maQRBase = `${formData.get('MaKhachHang')}_${formData.get('MaVai')}_${formData.get('MaMau')}_${formData.get('MaDVT')}_${formData.get(' تقریباً Kho')}_${soLuong}_${soLot}`;

        // Thêm vào tempData
        const maCTNHTP = generateMaCTNHTP(newSTT);
        tempData.push({
            STT: newSTT,
            MaSoMe: maSoMe,
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
            GhiChu: ghiChu || null,
            GhiChuLot: finalGhiChuLot || null // Sử dụng finalGhiChuLot
        });

        console.log('tempData sau khi thêm:', tempData); // Debug

        // Lưu hoặc cập nhật ghi chú số lot
        if (finalGhiChuLot) {
            await saveOrUpdateGhiChuLot(maSoMe, soLot, finalGhiChuLot);
        }

        updateTable();
        soLuongElement.value = '';
        soKGCanElement.value = '';
        maKhuVucElement.value = '';
        ghiChuElement.value = '';
        //ghiChuLotElement.value = '';

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
            buttonsStyling: false,
            width: '320px',
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
            buttonsStyling: false,
            width: '320px',
        });
    }
});

// Kiểm tra số lot và load ghi chú nếu có
async function checkSoLot(soLot, maSoMe) {
    const formData = new FormData();
    formData.append('action', 'checkSoLot');
    formData.append('soLot', soLot);
    formData.append('maSoMe', maSoMe);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        console.log('Kết quả checkSoLot:', result); // Debug
        if (result.success && result.data) {
            return result.data.GhiChu || '';
        }
        return '';
    } catch (error) {
        console.error('Lỗi khi kiểm tra SoLot:', error);
        return '';
    }
}

// Lấy STT lớn nhất cho số lot
async function getSTTForSoLot(maSoMe, soLot) {
    try {
        const formData = new FormData();
        formData.append('action', 'getMaxSTTForSoLot');
        formData.append('maSoMe', maSoMe);
        formData.append('soLot', soLot);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            return result.maxSTT || 0;
        }
        return 0;
    } catch (error) {
        console.error('Lỗi khi lấy STT lớn nhất:', error);
        return 0;
    }
}

// Lưu hoặc cập nhật ghi chú số lot
async function saveOrUpdateGhiChuLot(maSoMe, soLot, ghiChuLot) {
    try {
        const formData = new FormData();
        formData.append('action', 'saveOrUpdateGhiChuLot');
        formData.append('maSoMe', maSoMe);
        formData.append('soLot', soLot);
        formData.append('ghiChuLot', ghiChuLot);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (!result.success) {
            console.error('Lỗi khi lưu ghi chú số lot:', result.message);
            throw new Error(result.message || 'Không thể lưu ghi chú số lot');
        }
    } catch (error) {
        console.error('Lỗi khi lưu ghi chú số lot:', error);
        throw error;
    }
}
// Cập nhật bảng hiển thị dữ liệu nhập kho tạm thời
function updateTable() {
    const tbody = document.getElementById('dataTableBody');
    tbody.innerHTML = '';
    const isKgUnit = '<?php echo $don['MaDVT'] === '1' || $tenDVT === 'KG' ? 'true' : 'false'; ?>';

    const groupedBySoLot = tempData.reduce((acc, item) => {
        if (!acc[item.SoLot]) {
            acc[item.SoLot] = { items: [], GhiChuLot: '' };
        }
        acc[item.SoLot].items.push(item);
        // Cập nhật GhiChuLot với giá trị từ bản ghi cuối cùng
        acc[item.SoLot].GhiChuLot = item.GhiChuLot || '';
        return acc;
    }, {});

    Object.entries(groupedBySoLot).forEach(([soLot, data], groupIndex) => {
        const lotRow = document.createElement('tr');
        lotRow.className = 'bg-gray-200 font-semibold';
        const ghiChuDisplay = data.GhiChuLot ? data.GhiChuLot : ''; // Sử dụng GhiChuLot từ bản ghi cuối
        lotRow.innerHTML = `
            <td colspan="${isKgUnit === 'false' ? 7 : 6}">Số Lot: ${soLot} ${ghiChuDisplay ? `Ghi chú: ${ghiChuDisplay}` : ''}</td>
        `;
        tbody.appendChild(lotRow);

        data.items.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.STT}</td>
                <td>${item.SoLuong} <?php echo $tenDVT; ?></td>
                ${isKgUnit === 'false' ? `<td>${item.SoKgCan ? item.SoKgCan + ' kg' : ''}</td>` : ''}
                <td>${item.SoLot}</td>
                <td>${item.TenThanhPhan}</td>
                <td>${item.MaKhuVuc}</td>
                <td>${item.GhiChu || ''}</td>
                <td>
                    <button onclick="deleteRow(${item.STT - 1})" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    });
}

// Xóa một hàng dữ liệu khỏi tempData
function deleteRow(index) {
    tempData.splice(index, 1);
    updateTable();
}

// Tạo và xử lý file PDF chứa thông tin tem nhập kho
async function generatePDF(data, labelType) {
    const startTime = performance.now();
    console.log(`[${new Date().toISOString()}] Bắt đầu hàm generatePDF`, {
        labelType,
        dataLength: data ? data.length : 0,
        startTime: startTime.toFixed(2) + 'ms'
    });

    // Kiểm tra dữ liệu đầu vào
    if (!data || !Array.isArray(data) || data.length === 0) {
        console.error(`[${new Date().toISOString()}] Dữ liệu đầu vào không hợp lệ`, {
            data: JSON.stringify(data, null, 2),
            isArray: Array.isArray(data),
            length: data ? data.length : 'N/A'
        });
        Swal.fire({
            icon: 'warning',
            title: 'Dữ liệu không hợp lệ',
            text: 'Không có dữ liệu để tạo BMP.',
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

    console.log(`[${new Date().toISOString()}] Dữ liệu đầu vào hợp lệ`, {
        dataSample: JSON.stringify(data.slice(0, 2), null, 2),
        totalItems: data.length
    });

    // Đợi Cordova sẵn sàng nếu chạy trong môi trường Cordova
    const isCordova = typeof cordova !== 'undefined' && cordova.platformId;
    if (isCordova) {
        await new Promise(resolve => {
            if (document.readyState === 'complete' && typeof cordova.plugins !== 'undefined') {
                console.log(`[${new Date().toISOString()}] Môi trường Cordova đã sẵn sàng ngay lập tức`);
                resolve();
            } else {
                console.log(`[${new Date().toISOString()}] Đang chờ sự kiện deviceready`);
                document.addEventListener('deviceready', () => {
                    console.log(`[${new Date().toISOString()}] Sự kiện deviceready được kích hoạt`);
                    resolve();
                }, { once: true });
            }
        });
    } else {
        console.log(`[${new Date().toISOString()}] Chạy trong môi trường trình duyệt`);
    }

    console.log(`[${new Date().toISOString()}] Hiển thị Swal loading`);
    Swal.fire({
        title: 'Đang tạo BMP...',
        text: 'Vui lòng chờ trong giây lát.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        console.log(`[${new Date().toISOString()}] Môi trường thực thi`, {
            isCordova,
            platform: isCordova ? cordova.platformId : 'Trình duyệt'
        });

        const formData = new FormData();
        formData.append('action', 'generatePDF');
        formData.append('pdfData', JSON.stringify(data));
        formData.append('labelType', labelType);

        console.log(`[${new Date().toISOString()}] Chuẩn bị gửi yêu cầu POST`, {
            action: 'generatePDF',
            pdfDataLength: JSON.stringify(data).length,
            labelType,
            formDataKeys: [...formData.keys()]
        });

        sessionStorage.setItem('previousPage', window.location.href);
        console.log(`[${new Date().toISOString()}] Đã lưu previousPage vào sessionStorage`, {
            url: window.location.href
        });

        console.log(`[${new Date().toISOString()}] Gửi yêu cầu POST tới: ${window.location.href}`);
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        console.log(`[${new Date().toISOString()}] Nhận phản hồi từ server`, {
            status: response.status,
            ok: response.ok,
            headers: Object.fromEntries(response.headers.entries()),
            responseTime: (performance.now() - startTime).toFixed(2) + 'ms'
        });

        if (!response.ok) {
            let errorData;
            try {
                errorData = await response.json();
                console.error(`[${new Date().toISOString()}] Lỗi từ server`, {
                    status: response.status,
                    errorData: JSON.stringify(errorData, null, 2)
                });
            } catch (jsonError) {
                console.error(`[${new Date().toISOString()}] Không thể phân tích JSON từ phản hồi lỗi`, {
                    status: response.status,
                    responseText: await response.text(),
                    jsonError: jsonError.message
                });
                errorData = { error: `Lỗi Server: ${response.status}` };
            }
            throw new Error(errorData.error || `Lỗi Server: ${response.status}`);
        }

        const responseText = await response.text();
        console.log(`[${new Date().toISOString()}] Nội dung phản hồi thô`, {
            responseLength: responseText.length,
            responsePreview: responseText.slice(0, 500)
        });

        let result;
        try {
            result = JSON.parse(responseText);
            console.log(`[${new Date().toISOString()}] Kết quả JSON từ server`, {
                result: JSON.stringify(result, null, 2),
                parseTime: (performance.now() - startTime).toFixed(2) + 'ms'
            });
        } catch (jsonError) {
            console.error(`[${new Date().toISOString()}] Lỗi phân tích JSON`, {
                error: jsonError.message,
                responseText,
                stack: jsonError.stack
            });
            throw new Error(`Phản hồi từ server không phải JSON hợp lệ: ${jsonError.message}`);
        }

        if (result.status !== 'success') {
            console.error(`[${new Date().toISOString()}] Tạo BMP thất bại`, {
                error: result.error || 'Không có thông tin lỗi',
                result: JSON.stringify(result, null, 2)
            });
            throw new Error(result.error || 'Không thể tạo BMP');
        }

        console.log(`[${new Date().toISOString()}] Tạo BMP thành công`, {
            bmpDataCount: result.bmpData ? result.bmpData.length : 0,
            labelType
        });

        if (isCordova) {
            console.log(`[${new Date().toISOString()}] Xử lý trong môi trường Cordova`);
            const bmpDataArray = result.bmpData || [];
            console.log(`[${new Date().toISOString()}] Danh sách BMP base64`, {
                count: bmpDataArray.length
            });

            if (!bmpDataArray.length) {
                console.error(`[${new Date().toISOString()}] Không có dữ liệu BMP để xử lý trong Cordova`);
                throw new Error('Không có dữ liệu BMP để xử lý');
            }

            // Kiểm tra quyền truy cập bộ nhớ (nếu có plugin diagnostic)
            if (cordova.plugins && cordova.plugins.diagnostic) {
                await new Promise((resolve, reject) => {
                    cordova.plugins.diagnostic.requestExternalStorageAuthorization(status => {
                        if (status === cordova.plugins.diagnostic.permissionStatus.GRANTED) {
                            console.log(`[${new Date().toISOString()}] Quyền truy cập bộ nhớ đã được cấp`);
                            resolve();
                        } else {
                            console.error(`[${new Date().toISOString()}] Không có quyền truy cập bộ nhớ`);
                            reject(new Error('Không có quyền truy cập bộ nhớ'));
                        }
                    }, reject);
                });
            }

            const maSoMe = data[0]?.MaSoMe || 'Unknown';
            const timestamp = new Date().toISOString().replace(/[-:.]/g, '');
            const filePaths = await Promise.all(bmpDataArray.map(async (bmpBase64, index) => {
                // Kiểm tra dữ liệu base64
                if (!bmpBase64 || typeof bmpBase64 !== 'string') {
                    console.error(`[${new Date().toISOString()}] Dữ liệu BMP ${index + 1} không hợp lệ`);
                    throw new Error(`Dữ liệu BMP ${index + 1} không hợp lệ`);
                }

                console.log(`[${new Date().toISOString()}] Lưu BMP base64`, {
                    index: index + 1,
                    total: bmpDataArray.length,
                    base64Length: bmpBase64.length
                });

                const bmpBlob = await (await fetch(`data:image/bmp;base64,${bmpBase64}`)).blob();
                console.log(`[${new Date().toISOString()}] Đã tạo BMP blob`, {
                    size: bmpBlob.size,
                    type: bmpBlob.type
                });

                // Kiểm tra kích thước file
                const maxSizeMB = 10; // Giới hạn 10MB
                if (bmpBlob.size > maxSizeMB * 1024 * 1024) {
                    console.error(`[${new Date().toISOString()}] File BMP quá lớn`, {
                        size: (bmpBlob.size / 1024 / 1024).toFixed(2) + 'MB'
                    });
                    throw new Error('Kích thước file BMP vượt quá giới hạn');
                }

                const fileName = `Tem_NhapKhoTon_${labelType}_${maSoMe}_${timestamp}_page${index + 1}.bmp`;
                return new Promise((resolve, reject) => {
                    window.resolveLocalFileSystemURL(cordova.file.dataDirectory, (dirEntry) => {
                        console.log(`[${new Date().toISOString()}] Truy cập thư mục dataDirectory`, {
                            directory: cordova.file.dataDirectory
                        });

                        dirEntry.getFile(fileName, { create: true }, (fileEntry) => {
                            console.log(`[${new Date().toISOString()}] Tạo file entry`, { fileName });

                            fileEntry.createWriter((fileWriter) => {
                                fileWriter.onwriteend = () => {
                                    console.log(`[${new Date().toISOString()}] Đã lưu file BMP`, {
                                        fileName,
                                        url: fileEntry.toURL()
                                    });
                                    resolve(fileEntry.toURL());
                                };
                                fileWriter.onerror = (e) => {
                                    console.error(`[${new Date().toISOString()}] Lỗi khi lưu file BMP`, {
                                        fileName,
                                        error: e.toString()
                                    });
                                    reject(new Error(`Lỗi lưu file ${fileName}: ${e.toString()}`));
                                };
                                fileWriter.write(bmpBlob);
                            }, (e) => {
                                console.error(`[${new Date().toISOString()}] Lỗi tạo file writer`, {
                                    fileName,
                                    error: e.toString()
                                });
                                reject(new Error(`Lỗi tạo file writer: ${e.toString()}`));
                            });
                        }, (e) => {
                            console.error(`[${new Date().toISOString()}] Lỗi truy cập file`, {
                                fileName,
                                error: e.toString()
                            });
                            reject(new Error(`Lỗi truy cập file: ${e.toString()}`));
                        });
                    }, (e) => {
                        console.error(`[${new Date().toISOString()}] Lỗi truy cập dataDirectory`, {
                            error: e.toString()
                        });
                        reject(new Error(`Lỗi truy cập thư mục: ${e.toString()}`));
                    });
                });
            }));

            console.log(`[${new Date().toISOString()}] Đã lưu tất cả file BMP vào thiết bị`, {
                filePaths,
                count: filePaths.length
            });

            const filePathParam = encodeURIComponent(filePaths.join(','));
            console.log(`[${new Date().toISOString()}] Chuẩn bị chuyển hướng trong Cordova`, {
                filePathParam,
                labelType
            });

            const redirectUrl = `printer_interface.php?filePath=${filePathParam}&labelType=${encodeURIComponent(labelType)}`;
            console.log(`[${new Date().toISOString()}] Chuyển hướng tới: ${redirectUrl}`);
            Swal.close();
            setTimeout(() => {
                window.location.href = redirectUrl;
                setTimeout(() => {
                    if (!window.location.href.includes('printer_interface.php')) {
                        console.error(`[${new Date().toISOString()}] Chuyển hướng thất bại`);
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi',
                            text: 'Không thể chuyển hướng đến trang in.'
                        });
                    }
                }, 1000);
            }, 100);
        } else {
            console.log(`[${new Date().toISOString()}] Xử lý trong môi trường trình duyệt`);
            const bmpDataArray = result.bmpData || [];
            console.log(`[${new Date().toISOString()}] Danh sách BMP base64`, {
                count: bmpDataArray.length
            });

            if (!bmpDataArray.length) {
                console.error(`[${new Date().toISOString()}] Không có dữ liệu BMP để xử lý trong trình duyệt`);
                throw new Error('Không có dữ liệu BMP để xử lý');
            }

            const bmpDataUrls = bmpDataArray.map((bmpBase64) => `data:image/bmp;base64,${bmpBase64}`);
            console.log(`[${new Date().toISOString()}] Đã xử lý tất cả BMP thành data URL`, {
                bmpDataUrlsCount: bmpDataUrls.length,
                sampleUrlLength: bmpDataUrls[0] ? bmpDataUrls[0].length : 0
            });

            sessionStorage.setItem('bmpFiles', JSON.stringify(bmpDataUrls));
            console.log(`[${new Date().toISOString()}] Lưu bmpFiles vào sessionStorage`, {
                itemCount: bmpDataUrls.length,
                storageSize: JSON.stringify(bmpDataUrls).length
            });

            sessionStorage.setItem('labelType', labelType);
            console.log(`[${new Date().toISOString()}] Lưu labelType vào sessionStorage`, { labelType });

            console.log(`[${new Date().toISOString()}] Chuyển hướng đến printer_interface.php trong trình duyệt`);
            setTimeout(() => {
                window.location.href = 'printer_interface.php';
                setTimeout(() => {
                    if (!window.location.href.includes('printer_interface.php')) {
                        console.error(`[${new Date().toISOString()}] Chuyển hướng thất bại`);
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi',
                            text: 'Không thể chuyển hướng đến trang in.'
                        });
                    }
                }, 1000);
            }, 100);
        }

        console.log(`[${new Date().toISOString()}] Đóng Swal loading`, {
            totalTime: (performance.now() - startTime).toFixed(2) + 'ms'
        });
    } catch (error) {
        console.error(`[${new Date().toISOString()}] Lỗi trong generatePDF`, {
            message: error.message,
            stack: error.stack,
            context: {
                labelType,
                dataLength: data ? data.length : 0,
                totalTime: (performance.now() - startTime).toFixed(2) + 'ms'
            }
        });

        Swal.fire({
            icon: 'error',
            title: 'Lỗi!',
            text: `Lỗi khi tạo BMP: ${error.message}`,
            confirmButtonText: 'OK',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false
        });
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
                    <div class="text-left space-y-3 text-sm">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2 text-red-500"></i>
                            <span>
                                Bạn đã nhập quá số lượng yêu cầu:
                                <span style="color: red;" class="font-semibold">
                                    ${soLuongGiao.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?>
                                </span>
                            </span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-arrow-up mr-2 text-red-500"></i>
                            <span>
                                Đã nhập:
                                <span style="color: red;" class="font-semibold">
                                    ${tongSoLuongDaNhapDB.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?>
                                </span>
                            </span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-arrow-up mr-2 text-red-500"></i>
                            <span>
                                Bạn đang nhập:
                                <span style="color: red;" class="font-semibold">
                                    ${tongSoLuongNhapMoi.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?>
                                </span>
                            </span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2 text-yellow-500"></i>
                            <span>
                                Bạn chỉ được phép nhập tối đa:
                                <span style="color: red;" class="font-semibold">
                                    ${soLuongConLai.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} <?php echo $tenDVT; ?>
                                </span>
                            </span>
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
            }).then(async () => {
                // Hiển thị thông báo đang tạo tem
                Swal.fire({
                    title: 'Đang tạo tem PDF...',
                    text: 'Xin vui lòng chờ chuyển đến in tem PDF.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Lưu trạng thái "đã nhập đủ" nếu soLuongConLai === 0
                if (parseFloat(data.data.soLuongConLai) === 0) {
                    sessionStorage.setItem('showCompletedMessage', 'true');
                    sessionStorage.setItem('maSoMe', maSoMe);
                }

                // Tạo và in tem
                await generatePDF(tempData, labelType);

                // Cập nhật giao diện
                document.getElementById('tongSoLuongGiaoDisplay').textContent = parseFloat(data.data.soLuongGiao).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + data.data.donViTinh;
                document.getElementById('tongDaNhapDisplay').textContent = parseFloat(data.data.tongSoLuongNhap).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + data.data.donViTinh;
                document.getElementById('soLuongConLaiDisplay').textContent = parseFloat(data.data.soLuongConLai).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + data.data.donViTinh;

                // Xóa dữ liệu tạm và cập nhật bảng
                tempData = [];
                tempSTT = 0;
                updateTable();
                await updateXacNhanHoanThanhButton();
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

//xử lý sự kiện nhấn xác nhận đơn 
document.getElementById('XacNhanHoanThanh').addEventListener('click', async function() {
    const maSoMe = '<?php echo htmlspecialchars($maSoMe); ?>';
    const soLuongGiao = <?php echo $soLuongGiao; ?>;
    const tenDVT = '<?php echo $tenDVT; ?>';

    try {
        const tongSoLuongDaNhapDB = await getTongSoLuongNhap(maSoMe);
        const tongSoLuongNhapTam = tempData.reduce((sum, item) => sum + item.SoLuong, 0);
        const tongSoLuongDaNhap = tongSoLuongDaNhapDB + tongSoLuongNhapTam;
        const tyLeNhap = (tongSoLuongDaNhap / soLuongGiao) * 100;

        // Kiểm tra tỷ lệ nhập kho
        if (tyLeNhap < 70) {
            Swal.fire({
                icon: 'warning',
                title: 'Chưa đủ điều kiện!',
                html: `
                    <div class="text-left space-y-3 text-sm">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i>
                            <span><strong>Số lượng nhập hàng trên 70% tổng số lượng nhập mới được xác nhận đơn!!!</strong></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-box-open mr-2 text-blue-500"></i>
                            <span>Tổng số lượng: <span class="font-semibold">${soLuongGiao.toLocaleString(undefined, { minimumFractionDigits: 2 })} ${tenDVT}</span></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-arrow-down mr-2 text-green-500"></i>
                            <span>Đã nhập: <span class="font-semibold">${tongSoLuongDaNhap.toLocaleString(undefined, { minimumFractionDigits: 2 })} ${tenDVT} (${tyLeNhap.toFixed(2)}%)</span></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2 text-red-500"></i>
                            <span>Vui lòng nhập thêm ít nhất: <span class="font-semibold">${((soLuongGiao * 0.7) - tongSoLuongDaNhap).toLocaleString(undefined, { minimumFractionDigits: 2 })} ${tenDVT}</span></span>
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
                width: '90%'
            });
            return;
        }

        // Nếu tỷ lệ >= 70%, hiển thị thông báo xác nhận
        Swal.fire({
            icon: 'question',
            title: 'Xác nhận hoàn thành đơn hàng?',
            html: `
                <div class="text-left space-y-3 text-sm">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2 text-red-500"></i>
                        <span><strong>Cảnh báo:</strong> Hành động này sẽ đánh dấu đơn hàng <strong>${maSoMe}</strong> là hoàn thành và <strong>không thể hoàn tác</strong>.</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-box-open mr-2 text-blue-500"></i>
                        <span>Tổng số lượng yêu cầu: <span class="font-semibold">${soLuongGiao.toLocaleString(undefined, { minimumFractionDigits: 2 })} ${tenDVT}</span></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-arrow-down mr-2 text-green-500"></i>
                        <span>Đã nhập: <span class="font-semibold">${tongSoLuongDaNhap.toLocaleString(undefined, { minimumFractionDigits: 2 })} ${tenDVT}</span></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-question-circle mr-2 text-yellow-500"></i>
                        <span>Bạn có chắc chắn muốn xác nhận hoàn thành đơn hàng này không?</span>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Xác nhận',
            cancelButtonText: 'Hủy',
            customClass: {
                popup: 'rounded-xl',
                title: 'text-lg font-semibold',
                confirmButton: 'bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg mr-2',
                cancelButton: 'bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg'
            },
            buttonsStyling: false,
            width: '90%'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'updateDonStatus');
                    formData.append('maSoMe', maSoMe);
                    formData.append('forceComplete', 'true');

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công!',
                            text: 'Đơn hàng đã được xác nhận hoàn thành.',
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
                        title: 'Lỗi!',
                        text: 'Lỗi khi xác nhận đơn hàng: ' + error.message,
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'rounded-xl',
                            title: 'text-lg font-semibold',
                            confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                        },
                        buttonsStyling: false
                    });
                }
            }
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi!',
            text: 'Không thể kiểm tra số lượng đã nhập: ' + error.message,
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

// Kiểm tra trạng thái "đã nhập đủ" khi trang tải
document.addEventListener('DOMContentLoaded', async function() {
    const showCompletedMessage = sessionStorage.getItem('showCompletedMessage');
    const maSoMe = sessionStorage.getItem('maSoMe');

    if (showCompletedMessage === 'true' && maSoMe) {
        try {
            // Kiểm tra trạng thái đơn hàng
            const updateStatusFormData = new FormData();
            updateStatusFormData.append('action', 'updateDonStatus');
            updateStatusFormData.append('maSoMe', maSoMe);

            const updateResponse = await fetch(window.location.href, {
                method: 'POST',
                body: updateStatusFormData
            });
            const updateData = await updateResponse.json();

            // Hiển thị thông báo "Đã nhập đủ"
            Swal.fire({
                icon: 'info',
                title: 'Đã nhập đủ',
                text: updateData.success 
                    ? 'Đơn hàng này đã nhập đủ số lượng và trạng thái nhập đủ hàng đã được cập nhật.'
                    : 'Có lỗi xảy ra khi cập nhật trạng thái đơn hàng: ' + updateData.message,
                confirmButtonText: 'OK',
                customClass: {
                    popup: 'rounded-xl',
                    title: 'text-lg font-semibold',
                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                },
                buttonsStyling: false
            }).then(() => {
                // Xóa trạng thái trong sessionStorage
                sessionStorage.removeItem('showCompletedMessage');
                sessionStorage.removeItem('maSoMe');

                // Chuyển hướng về nhapkho.php
                window.location.href = '../nhapkho.php';
            });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi mạng',
                text: 'Lỗi kết nối khi cập nhật trạng thái đơn hàng: ' + error.message,
                confirmButtonText: 'OK',
                customClass: {
                    popup: 'rounded-xl',
                    title: 'text-lg font-semibold',
                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg'
                },
                buttonsStyling: false
            });
        }
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
            // Nhóm dữ liệu theo số lot
            const groupedBySoLot = result.data.reduce((acc, item) => {
                if (!acc[item.SoLot]) {
                    acc[item.SoLot] = { items: [], ghiChuLot: item.GhiChuLot || '' };
                }
                acc[item.SoLot].items.push(item);
                return acc;
            }, {});

            // Hàm tạo HTML cho bảng
            function generateTableRows(groupedData) {
                let html = '';
                Object.entries(groupedBySoLot).forEach(([soLot, data], groupIndex) => {
                    html += `
                        <tr class="bg-gray-200 font-semibold">
                            <td colspan="${isKgUnit === 'false' ? 7 : 6}" class="border px-4 py-2">
                                Số Lot: ${soLot} ${data.ghiChuLot ? `(Ghi chú: ${data.ghiChuLot})` : ''}
                            </td>
                        </tr>
                    `;
                    data.items.forEach((item, index) => {
                        html += `
                            <tr data-note="${item.GhiChu || ''}">
                                <td class="border px-4 py-2">${item.STT}</td>
                                <td class="border px-4 py-2">${parseFloat(item.SoLuong).toFixed(2)} <?php echo $tenDVT; ?></td>
                                ${isKgUnit === 'false' ? `<td class="border px-4 py-2">${item.SoKgCan ? parseFloat(item.SoKgCan).toFixed(2) + ' kg' : ''}</td>` : ''}
                                <td class="border px-4 py-2">${item.SoLot}</td>
                                <td class="border px-4 py-2">${item.TenThanhPhan}</td>
                                <td class="border px-4 py-2">${item.MaKhuVuc || ''}</td>
                                <td class="border px-4 py-2">${item.GhiChu || ''}</td>
                            </tr>
                        `;
                    });
                });
                return html;
            }

            const htmlContent = `
                <div class="text-left">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Danh sách hàng</h3>
                        <select id="filterChiTiet" onchange="filterTable()" class="border border-gray-300 rounded-lg p-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="all">Tất cả chi tiết</option>
                            <option value="hasNote">Chi tiết có ghi chú</option>
                        </select>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="chiTietTable" class="min-w-[800px] text-sm text-left text-gray-700 border-collapse shadow-sm">
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
                            <tbody>${generateTableRows(groupedBySoLot)}</tbody>
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
                buttonsStyling: false,
                didRender: () => {
                    window.filterTable = function() {
                        const filterValue = document.getElementById('filterChiTiet').value;
                        const table = document.getElementById('chiTietTable');
                        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

                        for (let row of rows) {
                            const note = row.getAttribute('data-note');
                            if (row.classList.contains('bg-gray-200')) {
                                row.style.display = ''; // Luôn hiển thị hàng tiêu đề số lot
                            } else {
                                row.style.display = (filterValue === 'hasNote' && (!note || note.trim() === '')) ? 'none' : '';
                            }
                        }
                    };
                }
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