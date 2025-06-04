<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

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
            $pdf->SetAlpha(0.4);
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

?>