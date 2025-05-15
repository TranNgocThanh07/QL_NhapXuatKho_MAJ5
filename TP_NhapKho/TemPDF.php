<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Mpdf\Mpdf;

function sendError($message, $exception = null) {
    $errorMsg = $message;
    if ($exception) {
        $errorMsg .= ": " . $exception->getMessage();
    }
    http_response_code(500);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generatePDF') {
    include('../db_config.php'); // Kết nối database

    $pdfData = json_decode($_POST['pdfData'], true);
    $maSoMe = $pdfData[0]['MaSoMe'] ?? '';

    if (empty($pdfData) || empty($maSoMe)) {
        sendError("Dữ liệu đầu vào không đủ hoặc rỗng (thiếu pdfData hoặc MaSoMe).");
    }

    // Get order information
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

    // Get color name from TP_Mau
    try {
        $sqlMau = "SELECT TenMau FROM TP_Mau WHERE MaMau = ?";
        $stmtMau = $pdo->prepare($sqlMau);
        $stmtMau->execute([$pdfData[0]['MaMau']]);
        $mau = $stmtMau->fetch(PDO::FETCH_ASSOC);
        $tenMau = $mau['TenMau'] ?? 'N/A';
    } catch (Exception $e) {
        sendError("Lỗi khi truy vấn tên màu", $e);
    }

    // QR Code generation function
    function generateQRCode($content, $size) {
        try {
            $qrCode = new QrCode($content);
            $qrCode->setSize($size);
            $qrCode->setMargin(5);
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            return $result->getString();
        } catch (Exception $e) {
            sendError("Lỗi khi tạo QR Code", $e);
        }
    }

    try {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [105, 148], // A6 size in mm
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
            'default_font' => 'arial'
        ]);

        // CSS matching C# layout
        $css = '
            body { font-family: Arial, sans-serif; font-size: 6pt; margin: 0; padding: 0; }
            .main-table { width: 100%; border-collapse: collapse; }
            .main-table td { border: none; padding: 0; }
            .sub-table { width: 100%; border-collapse: collapse; }
            .sub-table td { border: 1px solid black; vertical-align: middle; padding: 2mm; }
            .qr-cell { text-align: center; }
            .info-cell { text-align: center; }
            .label-cell { text-align: center; }
            .data-cell { text-align: center; font-weight: bold; }
            .footer-info { text-align: left; font-size: 6pt; }
            .footer-qr { text-align: center; }
            .header-text { font-size: 11pt; font-weight: bold; margin-bottom: 2mm; }
            .subheader-text { font-size: 8pt; font-weight: bold; }
            .label-text { font-size: 8pt; font-weight: bold; }
            .sublabel-text { font-size: 6pt; }
            .qr-img { width: 20mm; height: 20mm; margin: auto; }
            .row-height-1 { height: 40mm; }
            .row-height-2 { height: 15mm; }
            .row-height-3 { height: 40mm; }
            .footer-info div { margin-bottom: 1mm; }
            .footer-info strong { font-weight: bold; }
            .footer-info span { font-weight: normal; }
        ';

        $html = '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>';

        foreach ($pdfData as $index => $item) {
            if (!isset($item['SoLot'], $item['SoLuong'], $item['ThanhPhan'])) {
                continue;
            }

            if ($index > 0) {
                $html .= '<pagebreak />';
            }

            $qrContent = "Mã đơn hàng: " . htmlspecialchars($don['MaDonHang'] ?? 'N/A') . "\n" .
                        "Số Lot: " . htmlspecialchars($item['SoLot']) . "\n" .
                        "Số lượng: " . number_format((float)$item['SoLuong'], 1) . " " . htmlspecialchars($tenDVT);
            $qrCodeBinary = generateQRCode($qrContent, 100);
            $qrCodeUrl = 'data:image/png;base64,' . base64_encode($qrCodeBinary);

            $html .= '
                <table class="main-table">
                    <tr class="row-height-1">
                        <td>
                            <table class="sub-table">
                                <tr>
                                    <td style="width: 25%;">
                                        <div class="qr-cell">
                                            <img src="' . $qrCodeUrl . '" class="qr-img" alt="QR Code">
                                        </div>
                                    </td>
                                    <td style="width: 75%;">
                                        <div class="info-cell">
                                            <div class="header-text">CÔNG TY TNHH DỆT KIM MINH ANH</div>
                                            <div class="subheader-text">MINH ANH KNITTING CO.,LTD</div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="row-height-2">
                        <td>
                            <table class="sub-table">
                                <tr>
                                    <td style="width: 25%;">
                                        <div class="label-cell"><span class="label-text">MẶT HÀNG</span><br><span class="sublabel-text">(PRODUCT NAME)</span></div>
                                    </td>
                                    <td style="width: 75%;">
                                        <div class="data-cell">' . htmlspecialchars($don['MaVai'] ?? 'N/A') . ' (' . htmlspecialchars($don['TenVai'] ?? 'N/A') . ')</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="row-height-2">
                        <td>
                            <table class="sub-table">
                                <tr>
                                    <td style="width: 25%;">
                                        <div class="label-cell"><span class="label-text">THÀNH PHẦN</span><br><span class="sublabel-text">(INGREDIENTS)</span></div>
                                    </td>
                                    <td style="width: 75%;">
                                        <div class="data-cell">' . htmlspecialchars($item['ThanhPhan']) . '</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="row-height-2">
                        <td>
                            <table class="sub-table">
                                <tr>
                                    <td style="width: 25%;">
                                        <div class="label-cell"><span class="label-text">MÀU</span><br><span class="sublabel-text">(COLOR)</span></div>
                                    </td>
                                    <td style="width: 75%;">
                                        <div class="data-cell">' . htmlspecialchars($tenMau) . '</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="row-height-2">
                        <td>
                            <table class="sub-table">
                                <tr>
                                    <td style="width: 25%;">
                                        <div class="label-cell"><span class="label-text">KHỔ</span><br><span class="sublabel-text">(SIZE)</span></div>
                                    </td>
                                    <td style="width: 75%;">
                                        <div class="data-cell">' . htmlspecialchars($don['Kho'] ?? 'N/A') . '</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="row-height-2">
                        <td>
                            <table class="sub-table">
                                <tr>
                                    <td style="width: 25%;">
                                        <div class="label-cell"><span class="label-text">MÃ VẬT TƯ</span><br><span class="sublabel-text">(SAP CODE)</span></div>
                                    </td>
                                    <td style="width: 75%;">
                                        <div class="data-cell">' . htmlspecialchars($don['MaVatTu'] ?? 'N/A') . '</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="row-height-2">
                        <td>
                            <table class="sub-table">
                                <tr>
                                    <td style="width: 25%;">
                                        <div class="label-cell"><span class="label-text">MÃ ĐƠN HÀNG</span><br><span class="sublabel-text">(ORDER CODE)</span></div>
                                    </td>
                                    <td style="width: 75%;">
                                        <div class="data-cell">' . htmlspecialchars($don['MaDonHang'] ?? 'N/A') . '</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="row-height-2">
                        <td>
                            <table class="sub-table">
                                <tr>
                                    <td style="width: 25%;">
                                        <div class="label-cell"><span class="label-text">SỐ LOT</span><br><span class="sublabel-text">(LOT NO.)</span></div>
                                    </td>
                                    <td style="width: 75%;">
                                        <div class="data-cell">' . htmlspecialchars($item['SoLot']) . '</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="row-height-2">
                        <td>
                            <table class="sub-table">
                                <tr>
                                    <td style="width: 25%;">
                                        <div class="label-cell"><span class="label-text">SỐ LƯỢNG</span><br><span class="sublabel-text">(QUANTITY)</span></div>
                                    </td>
                                    <td style="width: 75%;">
                                        <div class="data-cell" style="font-size: 10pt;">' . number_format((float)$item['SoLuong'], 1) . ' ' . htmlspecialchars($tenDVT) . '</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="row-height-3">
                        <td>
                            <table class="sub-table">
                                <tr>
                                    <td style="width: 75%;">
                                        <div class="footer-info">
                                            <div><strong>Website:</strong> <span>www.detkimminhanh.vn</span></div>
                                            <div><strong>Điện thoại:</strong> <span>0283 7662 408 - 083 766 3329</span></div>
                                            <div><strong>Email:</strong> <span>td@detkimminhanh.vn; detkimminhanh@yahoo.com</span></div>
                                            <div><strong>Địa chỉ:</strong> <span>Lô J4-J5, đường số 3, KCN Lê Minh Xuân, Bình Chánh, TP.HCM</span></div>
                                        </div>
                                    </td>
                                    <td style="width: 25%;">
                                        <div class="footer-qr">
                                            <img src="' . $qrCodeUrl . '" class="qr-img" alt="QR Code">
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>';
        }

        $html .= '</body></html>';

        $mpdf->WriteHTML($html);

        // Add watermark
        $watermarkPath = __DIR__ . '/../assets/LogoMinhAnh.png';
        if (file_exists($watermarkPath)) {
            $mpdf->SetWatermarkImage($watermarkPath, 0.2, 'P', 'P');
            $mpdf->showWatermarkImage = true;
        }

        // Output PDF
        $timestamp = date('YmdHis');
        $safeMaSoMe = preg_replace('/[^A-Za-z0-9_-]/', '_', $maSoMe);
        $pdfFileName = "Tem_NhapKho_{$safeMaSoMe}_{$timestamp}.pdf";

        ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $pdfFileName . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        $mpdf->Output($pdfFileName, 'I');
        exit;

    } catch (Throwable $e) {
        sendError("Lỗi nghiêm trọng khi tạo hoặc xuất PDF", $e);
    }
}
?>