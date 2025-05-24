<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

include '../db_config.php';

// Hàm gửi lỗi
function sendError($message, $exception = null) {
    $errorMsg = $message;
    if ($exception) {
        $errorMsg .= ": " . $exception->getMessage();
    }
    http_response_code(500);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

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
    $font = 'timesbd';
    $pdf->AddFont($font, '', 'timesbd.php');
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

// Hàm tạo PDF Tem Khách Lẻ
function generateRetailLabel($pdf, $pdfData, $don, $tenMau, $tenDVT, $maSoMe) {
    $font = 'timesbd';
    $pdf->AddFont($font, '', 'timesbd.php');
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
            array($tableWidth * 0.25, $tableWidth * 0.75),
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
                        if ($col == 0 || $col == 2) {
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
                            $pdf->SetFont($font, 'B', 10);
                            $pdf->MultiCell($cellWidth, $cellHeight, number_format((float)$item['SoLuong'], 1) . " " . $tenDVT, 0, 'C', false, 1, $cellX + $padding, $cellY + $padding + 7);
                        } elseif ($col == 2) {
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

// Truy vấn thông tin đơn sản xuất
$maSoMe = $_GET['maSoMe'] ?? '';
$sql = "SELECT 
            dsx.MaSoMe, dsx.MaDonHang, dsx.MaVai, dsx.TenVai, dsx.Kho, 
            dsx.SoLuongDatHang, dsx.NgayNhan, dsx.NgayGiao, dsx.SoKgQuyDoi, 
            dsx.Loss, dsx.TongSoLuongGiao, dsx.DinhMuc, dsx.DoBenMau, 
            dsx.DoLechMau, dsx.DinhLuong, dsx.GhiChu, dsx.TrangThai, 
            dsx.YeuCauKhac, dsx.LoaiDon, kh.TenKhachHang, 
            nlh.TenNguoiLienHe, nv.TenNhanVien, mau.TenMau, dvt.TenDVT, 
            ho.TenSuDungHo, tc.TenTieuChuan
        FROM TP_DonSanXuat dsx
        LEFT JOIN TP_KhachHang kh ON dsx.MaKhachHang = kh.MaKhachHang
        LEFT JOIN TP_NguoiLienHe nlh ON dsx.MaNguoiLienHe = nlh.MaNguoiLienHe
        LEFT JOIN NhanVien nv ON dsx.MaNhanVien = nv.MaNhanVien
        LEFT JOIN TP_Mau mau ON dsx.MaMau = mau.MaMau
        LEFT JOIN TP_DonViTinh dvt ON dsx.MaDVT = dvt.MaDVT
        LEFT JOIN TP_Ho ho ON dsx.MaSuDungHo = ho.MaSuDungHo
        LEFT JOIN TP_TieuChuan tc ON dsx.MaTieuChuan = tc.MaTieuChuan
        WHERE dsx.MaSoMe = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$maSoMe]);
$don = $stmt->fetch(PDO::FETCH_ASSOC);

// Tính tổng đã nhập từ chi tiết
if ($don) {
    $sqlDaNhap = "SELECT SUM(SoLuong) as DaNhap 
                  FROM TP_ChiTietDonSanXuat 
                  WHERE MaSoMe = ? ";
    $stmtDaNhap = $pdo->prepare($sqlDaNhap);
    $stmtDaNhap->execute([$maSoMe]);
    $daNhap = $stmtDaNhap->fetch(PDO::FETCH_ASSOC)['DaNhap'] ?? 0;

    $tongNhap = $don['SoLuongDatHang'] ?? 0;
    $don['DaNhap'] = $daNhap;
    $don['ConLai'] = number_format($tongNhap - $daNhap, 2, '.', '');
}

// Truy vấn chi tiết nhập kho
$sqlChiTiet = "SELECT ct.*, m.TenMau, dvt.TenDVT 
               FROM TP_ChiTietDonSanXuat ct
               LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
               LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
               WHERE ct.MaSoMe = ?";
$stmtChiTiet = $pdo->prepare($sqlChiTiet);
$stmtChiTiet->execute([$maSoMe]);
$chiTietList = $stmtChiTiet->fetchAll(PDO::FETCH_ASSOC);

// Xử lý yêu cầu in PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generatePDF') {
    $pdfData = json_decode($_POST['pdfData'], true);
    $maSoMe = $pdfData[0]['MaSoMe'] ?? '';
    $labelType = $_POST['labelType'] ?? 'system';

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

// Hàm hỗ trợ hiển thị HTML an toàn
function safeHtml($value) {
    return $value !== null && $value !== '' ? htmlspecialchars($value) : '';
}

// Hàm lấy class và text trạng thái
function getStatusClass($status) {
    return $status === '0' ? 'bg-yellow-100 text-yellow-800 border border-yellow-300' : 'bg-green-100 text-green-800 border border-green-300';
}

function getStatusText($status) {
    return $status === '0' ? 'Chưa nhập đủ hàng' : ($status === '2' ? 'Đã nhập đủ hàng' : $status);
}

function getStatusClassChiTiet($status) {
    switch ($status) {
        case '0':
            return 'bg-blue-100 text-blue-800';
        case '1':
            return 'bg-yellow-100 text-yellow-800';
        case '2':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getStatusTextChiTiet($status) {
    switch ($status) {
        case '0':
            return 'Hàng Mới';
        case '1':
            return 'Hàng Xuất';
        case '2':
            return 'Hàng Tồn';
        default:
            return 'N/A';
    }
}

// Tính phần trăm hoàn thành
$percentCompleted = $don && $don['SoLuongDatHang'] > 0 ? min(100, round(($don['DaNhap'] / $don['SoLuongDatHang']) * 100)) : 0;

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Đơn Sản Xuất & Nhập Kho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f4f8;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .icon-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
        }

        .fixed-back-btn {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 50;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .progress-animate {
            animation: fillBar 1.5s ease-out;
        }

        @keyframes fillBar {
            from { width: 0%; }
            to { width: attr(data-percentage); }
        }

        .section-header {
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }

        .section-header::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -1px;
            height: 3px;
            width: 60px;
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #b91c1c;
            border-radius: 10px;
        }

        th, td {
            white-space: nowrap;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 font-sans">
    <div class="relative min-h-screen">
        <!-- Header -->
        <header class="sticky top-0 z-20 bg-gradient-to-r from-blue-800 to-indigo-600 p-3 shadow-lg">
            <div class="flex items-center justify-between max-w-7xl mx-auto">
                <div class="flex items-center">
                    <a href="../nhapkho.php" class="text-white text-xl hover:scale-110 transition-transform p-2">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h2 class="text-white font-bold text-2xl flex items-center ml-4">
                        <i class="fas fa-file-lines mr-2"></i> Chi Tiết Đơn Sản Xuất & Nhập Kho
                    </h2>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto p-4 space-y-5 pb-20">
            <?php if ($don): ?>
                <!-- Thông tin đơn sản xuất -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow p-4 border-l-4 border-blue-500 card-hover">
                    <h3 class="section-header text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-blue-100 text-blue-600">
                            <i class="fas fa-hashtag"></i>
                        </div>
                        <span>Thông Tin Đơn Sản Xuất</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="flex items-start mb-2"><i class="fas fa-tag text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Mã Số Mẻ:</span> <span class="text-red-600"><?php echo safeHtml($don['MaSoMe']); ?></span></p>
                            <p class="flex items-start mb-2"><i class="fas fa-file-alt text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Mã Đơn Hàng:</span> <?php echo safeHtml($don['MaDonHang']); ?></p>
                            <p class="flex items-start mb-2"><i class="fas fa-user text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Khách Hàng:</span> <?php echo safeHtml($don['TenKhachHang']); ?></p>
                            <p class="flex items-start mb-2"><i class="fas fa-tshirt text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Vải:</span> <?php echo safeHtml($don['TenVai']); ?> (<?php echo safeHtml($don['MaVai']); ?>)</p>
                            <p class="flex items-start mb-2"><i class="fas fa-ruler text-blue-500 mr-2 mt-1"></i><span><span class="font-semibold text-gray-700">Khổ:</span> <?php echo safeHtml($don['Kho']); ?></p>
                            <div class="flex items-center mb-2">
                                <i class="fas fa-palette text-blue-500 mr-2 mt-1"></i>
                                <span class="font-semibold text-gray-700">Màu:</span>
                                <div class="w-6 h-6 rounded-full mr-2 shadow-sm" style="background: linear-gradient(to right, red, orange, yellow, green, blue, indigo, violet);"></div>
                                <span><?php echo safeHtml($don['TenMau']); ?></span>
                            </div>
                            <span class="status-badge <?php echo getStatusClass($don['TrangThai']); ?>">
                                <i class="fas <?php echo $don['TrangThai'] === '0' ? 'fa-clock mr-1' : 'fa-check-circle mr-1'; ?>"></i>
                                <?php echo getStatusText($don['TrangThai']); ?>
                            </span>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                                <h4 class="text-base font-bold text-gray-800 flex items-center gap-2 mb-4">
                                    <div class="icon-circle bg-indigo-100 text-indigo-600">
                                        <i class="fas fa-chart-pie"></i>
                                    </div>
                                    <span>Tiến Độ Nhập Hàng</span>
                                </h4>
                                <div class="text-sm">
                                    <div class="flex justify-between mb-2">
                                        <span class="font-semibold text-gray-700 flex items-center">
                                            <i class="fas fa-spinner text-indigo-500 mr-1"></i>
                                            Hoàn thành: <span class="ml-1 text-indigo-600 font-bold"><?php echo $percentCompleted; ?>%</span>
                                        </span>
                                        <span class="font-semibold text-gray-700"><?php echo number_format($don['DaNhap'], 2, '.', ''); ?> / <?php echo number_format($don['SoLuongDatHang'], 2, '.', ''); ?> <?php echo safeHtml($don['TenDVT']); ?></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-4 mb-4 overflow-hidden">
                                        <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 h-4 rounded-full progress-animate relative" style="width: <?php echo $percentCompleted; ?>%">
                                            <?php if ($percentCompleted > 25): ?>
                                                <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-white"><?php echo $percentCompleted; ?>%</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-3 rounded-lg shadow-sm border border-blue-200 text-center transform transition-all hover:scale-105">
                                            <div class="icon-circle bg-blue-500 text-white mx-auto mb-2">
                                                <i class="fas fa-box-open"></i>
                                            </div>
                                            <p class="text-xs text-blue-700 font-medium mb-1">Tổng Nhập</p>
                                            <p class="font-bold text-blue-800 text-lg"><?php echo number_format($don['SoLuongDatHang'], 2, '.', ''); ?></p>
                                        </div>
                                        <div class="bg-gradient-to-br from-green-50 to-green-100 p-3 rounded-lg shadow-sm border border-green-200 text-center transform transition-all hover:scale-105">
                                            <div class="icon-circle bg-green-500 text-white mx-auto mb-2">
                                                <i class="fas fa-check"></i>
                                            </div>
                                            <p class="text-xs text-green-700 font-medium mb-1">Đã Nhập</p>
                                            <p class="font-bold text-green-800 text-lg"><?php echo number_format($don['DaNhap'], 2, '.', ''); ?></p>
                                        </div>
                                        <div class="bg-gradient-to-br from-amber-50 to-amber-100 p-3 rounded-lg shadow-sm border border-amber-200 text-center transform transition-all hover:scale-105">
                                            <div class="icon-circle bg-amber-500 text-white mx-auto mb-2">
                                                <i class="fas fa-hourglass-half"></i>
                                            </div>
                                            <p class="text-xs text-amber-700 font-medium mb-1">Còn Lại</p>
                                            <p class="font-bold text-amber-800 text-lg"><?php echo number_format($don['ConLai'], 2, '.', ''); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thông tin chi tiết -->
                <div class="bg-white rounded-xl shadow-sm p-4 card-hover border border-gray-100">
                    <h3 class="section-header text-base font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-indigo-100 text-indigo-600">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <span>Thông Tin Chi Tiết</span>
                    </h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-box"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Số Lượng Đặt</p>
                                <p class="font-semibold"><?php echo number_format($don['SoLuongDatHang'], 2, '.', '') . ' ' . safeHtml($don['TenDVT']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-weight-hanging"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Kg Quy Đổi</p>
                                <p class="font-semibold"><?php echo number_format((float)$don['SoKgQuyDoi'], 2, '.', '') . ' Kg'; ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Ngày Nhận</p>
                                <p class="font-semibold"><?php echo safeHtml($don['NgayNhan']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Ngày Giao</p>
                                <p class="font-semibold"><?php echo safeHtml($don['NgayGiao']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Loss</p>
                                <p class="font-semibold"><?php echo number_format((float)$don['Loss'], 2, '.', '') . ' Kg'; ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Loại Đơn</p>
                                <p class="font-semibold"><?php echo safeHtml($don['LoaiDon']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Người Liên Hệ</p>
                                <p class="font-semibold"><?php echo safeHtml($don['TenNguoiLienHe']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg items-center gap-2">
                            <div class="icon-circle bg-red-100 text-red-500">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Nhân Viên</p>
                                <p class="font-semibold"><?php echo safeHtml($don['TenNhanVien']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 bg-blue-50 p-3 rounded-lg border border-blue-100">
                        <p class="flex items-start">
                            <i class="fas fa-comment-dots text-blue-500 mr-2 mt-1"></i>
                            <span>
                                <span class="font-semibold text-blue-700">Yêu Cầu Khác:</span><br>
                                <span class="text-gray-700"><?php echo safeHtml($don['YeuCauKhac']) ?: 'Không có yêu cầu khác.'; ?></span>
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Thông tin kỹ thuật -->
                <div class="bg-white rounded-xl shadow-sm p-4 card-hover border border-gray-100">
                    <h3 class="section-header text-base font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-indigo-100 text-indigo-600">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <span>Thông Tin Kỹ Thuật</span>
                    </h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-ruler"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Định Mức</p>
                                <p class="font-semibold"><?php echo safeHtml($don['DinhMuc']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-weight"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Định Lượng</p>
                                <p class="font-semibold"><?php echo safeHtml($don['DinhLuong']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-tint"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Độ Bền Màu</p>
                                <p class="font-semibold"><?php echo safeHtml($don['DoBenMau']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-tint-slash"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Độ Lệch Màu</p>
                                <p class="font-semibold"><?php echo safeHtml($don['DoLechMau']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Sử Dụng Hộ</p>
                                <p class="font-semibold"><?php echo safeHtml($don['TenSuDungHo']); ?></p>
                            </div>
                        </div>
                        <div class="flex bg-gray-50 p-2 rounded-lg">
                            <div class="icon-circle bg-red-100 text-red-500 mr-2">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Tiêu Chuẩn</p>
                                <p class="font-semibold"><?php echo safeHtml($don['TenTieuChuan']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ghi chú -->
                <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl shadow-sm p-4 card-hover border border-amber-200">
                    <h3 class="text-base font-bold text-amber-800 flex items-center gap-2 mb-3 pb-2 border-b border-amber-200">
                        <div class="icon-circle bg-amber-200 text-amber-700">
                            <i class="fas fa-sticky-note"></i>
                        </div>
                        <span>Ghi Chú</span>
                    </h3>
                    <div class="bg-white p-3 rounded-lg shadow-sm">
                        <p class="text-sm text-gray-700 flex items-start">
                            <i class="fas fa-quote-left text-amber-500 mr-2 mt-1"></i>
                            <span><?php echo safeHtml($don['GhiChu']) ?: 'Không có ghi chú.'; ?></span>
                        </p>
                    </div>
                </div>

                <!-- Chi tiết nhập kho -->
                <div class="bg-white rounded-xl shadow-sm p-4 card-hover border border-gray-100">
                    <h3 class="section-header text-base font-bold text-gray-800 flex items-center gap-2 mb-4">
                        <div class="icon-circle bg-indigo-100 text-indigo-600">
                            <i class="fas fa-list"></i>
                        </div>
                        <span>Chi Tiết Nhập Kho</span>
                    </h3>
                    <?php if ($chiTietList): ?>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full min-w-[1000px] border-collapse">
                                <thead class="bg-red-50 text-red-800 sticky top-0 z-10">
                                    <tr>
                                        <th class="text-left sticky left-0 bg-red-50 z-20 p-3 font-semibold">STT</th>                                     
                                        <th class="text-left p-3 font-semibold">Số Lượng</th>
                                        <th class="text-left p-3 font-semibold">Số Lot</th>
                                        <th class="text-left p-3 font-semibold">Thành Phần</th>
                                        <th class="text-left p-3 font-semibold">Trạng Thái</th>
                                        <th class="text-left p-3 font-semibold">Ngày Tạo</th>
                                        <th class="text-left p-3 font-semibold">Khu Vực</th>
                                        <th class="text-left p-3 font-semibold">Ghi Chú</th>
                                        <th class="text-left p-3 font-semibold">In Tem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($chiTietList as $index => $chiTiet): ?>
                                        <tr class="border-b border-gray-200 hover:bg-red-100 transition-colors">
                                            <td class="sticky left-0 bg-white p-3"><?php echo $index + 1; ?></td>                                        
                                            <td class="font-bold p-3 whitespace-normal <?php echo intval($chiTiet['SoLuong']) > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo safeHtml($chiTiet['SoLuong']) . ' ' . safeHtml($chiTiet['TenDVT']); ?>
                                            </td>
                                            <td class="p-3"><?php echo safeHtml($chiTiet['SoLot']); ?></td>
                                            <td class="p-3"><?php echo safeHtml($chiTiet['TenThanhPhan']); ?></td>
                                            <td class="p-3">
                                                <span class="px-2 py-1 rounded-full text-xs <?php echo getStatusClassChiTiet($chiTiet['TrangThai']); ?>">
                                                    <?php echo getStatusTextChiTiet($chiTiet['TrangThai']); ?>
                                                </span>
                                            </td>
                                            <td class="text-gray-600 p-3"><?php echo safeHtml($chiTiet['NgayTao']); ?></td>
                                            <td class="text-gray-600 p-3"><?php echo safeHtml($chiTiet['MaKhuVuc']); ?></td>
                                            <td class="text-gray-600 p-3"><?php echo safeHtml($chiTiet['GhiChu']); ?></td>
                                            <td class="p-3">
                                                <button onclick='generatePDF(<?php echo json_encode([$chiTiet]); ?>)' class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700">
                                                    <i class="ri-printer-line"></i> In Tem
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center bg-red-50 rounded-lg p-6">
                            <i class="ri-error-warning-line text-4xl text-red-500 mb-3"></i>
                            <p class="text-red-600 text-base font-semibold">Không tìm thấy chi tiết nhập kho cho đơn này.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-center h-[calc(100vh-64px)]">
                    <div class="text-center p-6 bg-white rounded-lg shadow-lg border-t-4 border-red-500">
                        <div class="w-20 h-20 mx-auto mb-4 flex items-center justify-center bg-red-100 rounded-full">
                            <i class="fas fa-exclamation-triangle text-4xl text-red-500"></i>
                        </div>
                        <p class="text-lg font-semibold text-gray-800 mb-1">Không tìm thấy đơn sản xuất</p>
                        <p class="text-sm text-gray-600 mb-4">Mã số mẻ không tồn tại hoặc đã bị xóa.</p>
                        <a href="../nhapkho.php" class="inline-block bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Quay lại Trang Chính
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Hàm ghi log
        function logToScreen(message, type = 'info') {
            console[type === 'error' ? 'error' : 'log'](message);
        }

        // Kiểm tra môi trường Cordova
        function checkCordovaEnvironment() {
            logToScreen('[checkCordova] Kiểm tra môi trường Cordova...');
            return typeof cordova !== 'undefined' && typeof cordova.plugins !== 'undefined' && typeof cordova.file !== 'undefined' && typeof cordova.plugins.fileOpener2 !== 'undefined';
        }

        // Xử lý khi Cordova sẵn sàng
        document.addEventListener('deviceready', onDeviceReady, false);
        function onDeviceReady() {
            logToScreen('[deviceready] Cordova đã sẵn sàng.');
            checkCordovaEnvironment();
        }

        // Nếu không phải Cordova, chạy logic trình duyệt
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof cordova === 'undefined') {
                logToScreen('[DOMContentLoaded] Không phát hiện Cordova, chạy như trình duyệt.');
                checkCordovaEnvironment();
            }
        });

        // Hàm generatePDF
        window.generatePDF = async function(data) {
            logToScreen('[generatePDF] Bắt đầu hàm generatePDF với dữ liệu: ' + JSON.stringify(data));
            if (!data || data.length === 0) {
                logToScreen('[generatePDF] Dữ liệu rỗng.', 'error');
                Swal.fire({ icon: 'warning', title: 'Dữ liệu không hợp lệ', text: 'Không có dữ liệu để tạo PDF.' });
                return;
            }

            if (typeof cordova !== 'undefined') {
                await new Promise((resolve) => {
                    if (typeof cordova.plugins !== 'undefined') {
                        resolve();
                    } else {
                        document.addEventListener('deviceready', resolve, { once: true });
                    }
                });
                logToScreen('[generatePDF] deviceready đã sẵn sàng.');
            } else {
                logToScreen('[generatePDF] Chạy trong môi trường trình duyệt.');
            }

            const { value: labelType } = await Swal.fire({
                title: 'Chọn loại tem',
                text: 'Vui lòng chọn loại tem bạn muốn in:',
                icon: 'question',
                input: 'select',
                inputOptions: { 'system': 'Tem Hệ Thống', 'khachle': 'Tem Khách Lẻ' },
                inputPlaceholder: 'Chọn loại tem',
                showCancelButton: true,
                confirmButtonText: 'In Tem',
                cancelButtonText: 'Hủy',
                inputValidator: (value) => !value && 'Bạn phải chọn một loại tem!'
            });

            if (!labelType) {
                logToScreen('[generatePDF] Người dùng hủy chọn.');
                return;
            }

            Swal.fire({ title: 'Đang tạo PDF...', text: 'Vui lòng chờ.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            const formData = new FormData();
            formData.append('action', 'generatePDF');
            formData.append('pdfData', JSON.stringify(data));
            formData.append('labelType', labelType);

            try {
                logToScreen('[generatePDF] Gửi request POST tới: ' + window.location.href);
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                logToScreen('[generatePDF] Phản hồi từ server, trạng thái: ' + response.status);

                if (!response.ok) {
                    const errorData = await response.json().catch(() => response.text());
                    throw new Error(errorData.error || `Lỗi Server: ${response.status}`);
                }

                const pdfBlob = await response.blob();
                const fileName = response.headers.get('Content-Disposition')?.match(/filename="(.+)"/)?.[1] || `Tem_NhapKho_${labelType}_${Date.now()}.pdf`;
                logToScreen('[generatePDF] Đã nhận Blob PDF, tên file: ' + fileName);

                const isCordova = checkCordovaEnvironment();
                logToScreen('[generatePDF] Xác định môi trường: ' + (isCordova ? 'Cordova' : 'Trình duyệt'));

                if (isCordova) {
                    logToScreen('[generatePDF] Chạy logic Cordova...');
                    await saveAndOpenPDF(pdfBlob, fileName);
                } else {
                    logToScreen('[generatePDF] Chạy logic trình duyệt...');
                    const pdfUrl = URL.createObjectURL(pdfBlob);
                    logToScreen('[generatePDF] Đã tạo URL cho PDF: ' + pdfUrl);
                    const newWindow = window.open(pdfUrl, '_blank');
                    if (!newWindow) {
                        logToScreen('[generatePDF] Popup bị chặn, hiển thị tùy chọn tải.');
                        Swal.fire({
                            icon: 'warning',
                            title: 'Popup bị chặn',
                            text: 'Trình duyệt đã chặn mở tab mới. Nhấn OK để tải file.',
                            showCancelButton: true,
                            confirmButtonText: 'Tải file',
                            cancelButtonText: 'Hủy'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const link = document.createElement('a');
                                link.href = pdfUrl;
                                link.download = fileName;
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                logToScreen('[generatePDF] Đã tải file xuống: ' + fileName);
                            }
                        });
                    } else {
                        logToScreen('[generatePDF] Đã mở PDF trong tab mới.');
                    }
                    setTimeout(() => {
                        URL.revokeObjectURL(pdfUrl);
                        logToScreen('[generatePDF] Đã giải phóng URL: ' + pdfUrl);
                    }, 10000);
                }

                Swal.fire({ icon: 'success', title: 'Thành công!', text: 'File PDF đã được xử lý.' });
            } catch (error) {
                logToScreen('[generatePDF] Lỗi: ' + error.message, 'error');
                Swal.fire({ icon: 'error', title: 'Lỗi!', text: `Lỗi khi tạo PDF: ${error.message}` });
            } finally {
                if (Swal.isLoading()) Swal.close();
            }
        };

       // Hàm lưu và mở PDF trên Android (Cordova)
async function saveAndOpenPDF(pdfBlob, fileName) {
    logToScreen('[saveAndOpenPDF] Bắt đầu lưu và mở PDF...');
    if (!cordova.file || !cordova.plugins.fileOpener2) {
        throw new Error('Plugin Cordova không sẵn sàng (file hoặc fileOpener2).');
    }

    await requestPermissions();

    return new Promise((resolve, reject) => {
        const directory = cordova.file.externalDataDirectory || cordova.file.documentsDirectory || cordova.file.dataDirectory;
        logToScreen('[saveAndOpenPDF] Thư mục lưu trữ: ' + directory);

        window.resolveLocalFileSystemURL(directory, function(dirEntry) {
            dirEntry.getFile(fileName, { create: true, exclusive: false }, function(fileEntry) {
                fileEntry.createWriter(function(fileWriter) {
                    fileWriter.onwriteend = function() {
                        logToScreen('[saveAndOpenPDF] Đã lưu file tại: ' + fileEntry.nativeURL);
                        cordova.plugins.fileOpener2.open(
                            fileEntry.nativeURL,
                            'application/pdf',
                            {
                                error: function(e) {
                                    logToScreen('[saveAndOpenPDF] Lỗi khi mở file: ' + JSON.stringify(e), 'error');
                                    reject(new Error('Không thể mở file: ' + e.message));
                                },
                                success: function() {
                                    logToScreen('[saveAndOpenPDF] Đã mở file thành công.');
                                    resolve();
                                }
                            }
                        );
                    };
                    fileWriter.onerror = function(e) {
                        logToScreen('[saveAndOpenPDF] Lỗi khi ghi file: ' + e.toString(), 'error');
                        reject(new Error('Không thể ghi file: ' + e.toString()));
                    };
                    fileWriter.write(pdfBlob);
                }, reject);
            }, reject);
        }, reject);
    });
}

// Hàm yêu cầu quyền (Cordova)
async function requestPermissions() {
    if (!cordova.plugins.permissions) {
        logToScreen('[requestPermissions] Plugin permissions không sẵn sàng.');
        return;
    }

    const permissions = cordova.plugins.permissions;
    const perms = [permissions.WRITE_EXTERNAL_STORAGE, permissions.READ_EXTERNAL_STORAGE];

    return new Promise((resolve, reject) => {
        permissions.checkPermission(perms[0], function(status) {
            if (status.hasPermission) {
                logToScreen('[requestPermissions] Quyền đã được cấp.');
                resolve();
            } else {
                permissions.requestPermissions(perms, function(status) {
                    if (status.hasPermission) {
                        logToScreen('[requestPermissions] Quyền được cấp sau khi yêu cầu.');
                        resolve();
                    } else {
                        logToScreen('[requestPermissions] Quyền bị từ chối.', 'error');
                        reject(new Error('Quyền truy cập bộ nhớ bị từ chối.'));
                    }
                }, reject);
            }
        }, reject);
    });
}
</script>  
</body>
</html>