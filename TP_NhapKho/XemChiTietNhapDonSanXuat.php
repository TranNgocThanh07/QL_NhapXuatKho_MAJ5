<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

include('../db_config.php');

function sendError($message, $exception = null) {
    $errorMsg = $message;
    if ($exception) {
        $errorMsg .= ": " . $exception->getMessage();
    }
    http_response_code(500);
    echo json_encode(['error' => $errorMsg]);
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

// Hiển thị trang chi tiết
$maSoMe = $_GET['maSoMe'];
$sql = "SELECT ct.*, m.TenMau, dvt.TenDVT 
        FROM TP_ChiTietDonSanXuat ct
        LEFT JOIN TP_Mau m ON ct.MaMau = m.MaMau
        LEFT JOIN TP_DonViTinh dvt ON ct.MaDVT = dvt.MaDVT
        WHERE ct.MaSoMe = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$maSoMe]);
$chiTietList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Đơn Sản Xuất</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- nếu commet lại thì sẽ chạy trên trình duyệt web <script src="/TP_NhapKho/cordova.js"></script> -->
    <!-- <script src="/TP_NhapKho/cordova.js"></script> -->
    <style>
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
        th {
            white-space: nowrap;
            min-width: 0;
        }
        td {
            white-space: nowrap;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50 font-sans antialiased">
    <div class="w-full h-screen bg-white shadow-lg overflow-hidden flex flex-col">
        <!-- Header - Sticky -->
        <header class="sticky top-0 z-20 bg-gradient-to-r from-red-700 to-red-600 text-white w-full flex justify-between items-center">
            <a href="../nhapkho.php" class="text-white text-2xl hover:scale-105 transition-transform p-4">
                <i class="ri-arrow-left-line"></i>
            </a>
            <h2 class="text-lg md:text-xl font-bold flex items-center gap-2 absolute left-1/2 transform -translate-x-1/2">
                <i class="ri-settings-3-line"></i> Chi Tiết Nhập Kho
            </h2>          
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto w-full">
            <?php if ($chiTietList): ?>
                <div class="overflow-x-auto custom-scrollbar relative w-full" style="max-height: calc(100vh - 112px);">
                    <table class="w-full min-w-[1000px] border-collapse">
                        <thead class="bg-red-50 text-red-800 sticky top-0 z-10">
                            <tr>
                                <th class="text-left sticky left-0 bg-red-50 z-20 p-3 font-semibold">STT</th>
                                <th class="text-left p-3 font-semibold">Mã Số Mẻ</th>
                                <!-- <th class="text-left p-3 font-semibold">Người Liên Hệ</th> -->
                                <!-- <th class="text-left p-3 font-semibold">Mã Đơn Hàng</th> -->
                                <!-- <th class="text-left p-3 font-semibold">Mã Vật Tư</th> -->
                                <!-- <th class="text-left p-3 font-semibold">Tên Vải</th> -->
                                <!-- <th class="text-left p-3 font-semibold">Màu</th>
                                <th class="text-left p-3 font-semibold">Đơn Vị Tính</th>
                                <th class="text-left p-3 font-semibold">Kho</th> -->
                                <th class="text-left p-3 font-semibold">Số Lượng</th>
                                <th class="text-left p-3 font-semibold">Số Lot</th>
                                <th class="text-left p-3 font-semibold">Thành Phần</th>
                                <th class="text-left p-3 font-semibold">Trạng Thái</th>
                                <th class="text-left p-3 font-semibold">Ngày Tạo</th>
                                <th class="text-left p-3 font-semibold">In Tem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chiTietList as $index => $chiTiet): ?>
                                <tr class="border-b border-gray-200 hover:bg-red-100 transition-colors">
                                    <td class="sticky left-0 bg-white p-3"><?php echo $index + 1; ?></td>
                                    <td class="font-semibold text-red-600 p-3"><?php echo htmlspecialchars($chiTiet['MaSoMe'] ?? ''); ?></td>
                                    <!-- <td class="p-3"><?php echo htmlspecialchars($chiTiet['MaNguoiLienHe'] ?? 'N/A'); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($chiTiet['MaDonHang'] ?? 'N/A'); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($chiTiet['MaVatTu'] ?? 'N/A'); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($chiTiet['TenVai'] ?? 'N/A'); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($chiTiet['TenMau'] ?? 'N/A'); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($chiTiet['MaDVT'] ?? 'N/A'); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($chiTiet['Kho'] ?? 'N/A'); ?></td> -->
                                    <td class="font-bold p-3 whitespace-normal <?php echo intval($chiTiet['SoLuong']) > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo htmlspecialchars($chiTiet['SoLuong'] ?? '0') . ' ' . htmlspecialchars($chiTiet['TenDVT'] ?? ''); ?>
                                    </td>                                 
                                    <td class="p-3"><?php echo htmlspecialchars($chiTiet['SoLot'] ?? 'N/A'); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($chiTiet['TenThanhPhan'] ?? 'N/A'); ?></td>
                                    <td class="p-3">
                                        <?php 
                                            $trangThai = $chiTiet['TrangThai'] ?? 'N/A'; 
                                            $classes = '';
                                            $text = '';

                                            switch ($trangThai) {
                                                case '0':
                                                    $classes = 'bg-blue-100 text-blue-800'; // Màu xanh dương cho "Đã nhập"
                                                    $text = 'Hàng Mới';
                                                    break;
                                                case '1':
                                                    $classes = 'bg-yellow-100 text-yellow-800'; // Màu vàng cho "Chờ xuất"
                                                    $text = 'Hàng Xuất';
                                                    break;
                                                case '2':
                                                    $classes = 'bg-green-100 text-green-800'; // Màu xanh lá cho "Đã xuất"
                                                    $text = 'Hàng Tồn';
                                                    break;
                                                default:
                                                    $classes = 'bg-gray-100 text-gray-800'; // Màu xám cho trạng thái không xác định
                                                    $text = 'N/A';
                                                    break;
                                            }
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs <?php echo $classes; ?>">
                                            <?php echo htmlspecialchars($text); ?>
                                        </span>
                                    </td>

                                    <td class="text-gray-600 p-3"><?php echo htmlspecialchars($chiTiet['NgayTao'] ?? 'N/A'); ?></td>
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
                <div class="text-center bg-red-50 rounded-lg h-full flex items-center justify-center flex-col">
                    <i class="ri-error-warning-line text-4xl text-red-500 mb-3"></i>
                    <p class="text-red-600 text-base font-semibold">Không tìm thấy chi tiết cho đơn này.</p>
                </div>
            <?php endif; ?>
        </div>      
    </div>  
    <!-- <div id="logContainer" class="fixed bottom-0 left-0 w-full bg-black text-white p-2 max-h-40 overflow-y-auto z-50" style="display: none;">
        <button onclick="toggleLog()" class="absolute top-2 right-2 text-red-500">Ẩn</button>
    <div id="logContent"></div> -->
</div>
<script>
// Hàm ghi log (giữ nguyên)
function logToScreen(message, type = 'info') {
    console[type === 'error' ? 'error' : 'log'](message);
    const logContent = document.getElementById('logContent');
    if (logContent) {
        const logEntry = document.createElement('p');
        logEntry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
        logEntry.style.color = type === 'error' ? '#ff5555' : '#ffffff';
        logContent.appendChild(logEntry);
        logContent.scrollTop = logContent.scrollHeight;
        document.getElementById('logContainer').style.display = 'block';
    }
}

function toggleLog() {
    const logContainer = document.getElementById('logContainer');
    logContainer.style.display = logContainer.style.display === 'none' ? 'block' : 'none';
}

// Kiểm tra môi trường Cordova
function checkCordovaEnvironment() {
    logToScreen('[checkCordova] Kiểm tra môi trường Cordova...');
    logToScreen('[checkCordova] cordova defined: ' + (typeof cordova !== 'undefined'));
    logToScreen('[checkCordova] cordova.plugins defined: ' + (typeof cordova !== 'undefined' && typeof cordova.plugins !== 'undefined'));
    logToScreen('[checkCordova] cordova.file defined: ' + (typeof cordova !== 'undefined' && typeof cordova.file !== 'undefined'));
    logToScreen('[checkCordova] cordova.plugins.permissions defined: ' + (typeof cordova !== 'undefined' && typeof cordova.plugins !== 'undefined' && typeof cordova.plugins.permissions !== 'undefined'));
    logToScreen('[checkCordova] cordova.plugins.fileOpener2 defined: ' + (typeof cordova !== 'undefined' && typeof cordova.plugins !== 'undefined' && typeof cordova.plugins.fileOpener2 !== 'undefined'));
    return typeof cordova !== 'undefined' && typeof cordova.plugins !== 'undefined' && typeof cordova.file !== 'undefined' && typeof cordova.plugins.fileOpener2 !== 'undefined';
}

// Xử lý khi Cordova sẵn sàng
document.addEventListener('deviceready', onDeviceReady, false);
function onDeviceReady() {
    logToScreen('[deviceready] Cordova đã sẵn sàng.');
    checkCordovaEnvironment();
}

// Nếu không phải Cordova, chạy logic trình duyệt ngay khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', function() {
    if (typeof cordova === 'undefined') {
        logToScreen('[DOMContentLoaded] Không phát hiện Cordova, chạy như trình duyệt.');
        checkCordovaEnvironment();
    }
});

// Hàm generatePDF hỗ trợ cả Cordova và trình duyệt
window.generatePDF = async function(data) {
    logToScreen('[generatePDF] Bắt đầu hàm generatePDF với dữ liệu: ' + JSON.stringify(data));
    if (!data || data.length === 0) {
        logToScreen('[generatePDF] Dữ liệu rỗng.', 'error');
        Swal.fire({ icon: 'warning', title: 'Dữ liệu không hợp lệ', text: 'Không có dữ liệu để tạo PDF.' });
        return;
    }

    // Chờ deviceready nếu là Cordova, bỏ qua nếu là trình duyệt
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
        logToScreen('[generatePDF] Chạy trong môi trường trình duyệt, không cần deviceready.');
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
            // Logic cho Cordova (Android)
            logToScreen('[generatePDF] Chạy logic Cordova...');
            await saveAndOpenPDF(pdfBlob, fileName);
        } else {
            // Logic cho trình duyệt
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