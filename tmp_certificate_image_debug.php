<?php
require_once __DIR__ . '/vendor/autoload.php';

$debugDir = __DIR__ . '/admin/uploads/certificates/debug';
if (!is_dir($debugDir)) {
    mkdir($debugDir, 0755, true);
}

$bgPath = __DIR__ . '/admin/img/certificate_bg.png';
$realpath = realpath($bgPath);
$exists = file_exists($bgPath);
$filesize = $exists ? filesize($bgPath) : false;
$imageSize = $exists ? getimagesize($bgPath) : false;

echo 'realpath()=' . var_export($realpath, true) . PHP_EOL;
echo 'file_exists()=' . var_export($exists, true) . PHP_EOL;
echo 'filesize()=' . var_export($filesize, true) . PHP_EOL;
echo 'getimagesize()=' . var_export($imageSize, true) . PHP_EOL;

$createMpdf = static function (): \Mpdf\Mpdf {
    $mpdf = new \Mpdf\Mpdf([
        'tempDir' => __DIR__ . '/admin/tmp',
        'format' => 'A4-L',
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
    ]);
    $mpdf->showImageErrors = true;
    $mpdf->SetCompression(false);

    return $mpdf;
};

$drawsImage = static function (string $pdfPath): bool {
    $pdf = is_file($pdfPath) ? file_get_contents($pdfPath) : '';

    return strpos($pdf, '/Subtype /Image') !== false
        && (bool)preg_match('/\/I\d+\s+Do/', $pdf);
};

$data = file_get_contents($realpath);
$mime = mime_content_type($realpath) ?: 'image/png';
$dataUrl = 'data:' . $mime . ';base64,' . base64_encode($data);
$htmlBase64 = '<!doctype html><html><body style="margin:0;padding:0;"><img src="' . htmlspecialchars($dataUrl, ENT_QUOTES) . '" style="width:297mm;height:210mm;margin:0;padding:0;" /></body></html>';
$htmlWatermark = '<!doctype html><html><body style="margin:0;padding:0;"><div style="position:fixed;top:80mm;width:100%;text-align:center;color:#fff;font-size:28px;">Watermark image test</div></body></html>';

file_put_contents($debugDir . '/certificate_debug.html', $htmlBase64);

$imageOnly = $createMpdf();
$imageOnly->WriteHTML('<img src="' . htmlspecialchars($realpath, ENT_QUOTES) . '" style="width:297mm;height:210mm;margin:0;padding:0;" />');
$imageOnly->Output($debugDir . '/certificate_bg_image_only.pdf', \Mpdf\Output\Destination::FILE);

$base64 = $createMpdf();
$base64->WriteHTML($htmlBase64);
$base64->Output($debugDir . '/certificate_base64_test.pdf', \Mpdf\Output\Destination::FILE);

$watermark = $createMpdf();
$watermark->SetWatermarkImage($realpath, 1, 'F', 'F');
$watermark->showWatermarkImage = true;
$watermark->WriteHTML($htmlWatermark);
$watermark->Output($debugDir . '/certificate_watermark_test.pdf', \Mpdf\Output\Destination::FILE);

foreach ([
    'image_only' => $debugDir . '/certificate_bg_image_only.pdf',
    'base64' => $debugDir . '/certificate_base64_test.pdf',
    'watermark' => $debugDir . '/certificate_watermark_test.pdf',
] as $label => $path) {
    echo $label . '_pdf=' . $path . PHP_EOL;
    echo $label . '_filesize=' . filesize($path) . PHP_EOL;
    echo $label . '_draws_image=' . var_export($drawsImage($path), true) . PHP_EOL;
}
