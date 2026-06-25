<?php
require_once __DIR__ . '/../vendor/autoload.php';

$debugDir = __DIR__ . '/uploads/certificates/debug';
if (!is_dir($debugDir)) {
    mkdir($debugDir, 0755, true);
}

$bgPath = __DIR__ . '/img/certificate_bg.png';
$bgDataUrl = 'data:image/png;base64,' . base64_encode(file_get_contents($bgPath));

function compareMpdf(): \Mpdf\Mpdf {
    $mpdf = new \Mpdf\Mpdf([
        'tempDir' => __DIR__ . '/tmp',
        'format' => 'A4-L',
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
    ]);
    $mpdf->showImageErrors = true;
    $mpdf->SetCompression(false);

    return $mpdf;
}

function writePdf(string $html, string $pdfPath, ?string $watermarkPath = null): void {
    $mpdf = compareMpdf();
    if ($watermarkPath !== null) {
        $mpdf->SetWatermarkImage($watermarkPath);
        $mpdf->showWatermarkImage = true;
    }
    $mpdf->WriteHTML($html);
    $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);
}

function writeHtmlAndPdf(string $name, string $html, ?string $watermarkPath = null): void {
    global $debugDir;
    file_put_contents($debugDir . '/' . $name . '.html', $html);
    writePdf($html, $debugDir . '/' . $name . '.pdf', $watermarkPath);
}

function imageOnlyHtml(string $src): string {
    return <<<HTML
<!doctype html>
<html>
<body style="margin:0; padding:0;">
<img src="{$src}" width="100%">
</body>
</html>
HTML;
}

function imageAndTextHtml(string $src): string {
    return <<<HTML
<!doctype html>
<html>
<body style="margin:0; padding:0; font-family: DejaVu Sans, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr><td><img src="{$src}" width="100%"></td></tr>
  <tr><td style="text-align:center; font-size:24px; padding-top:12px;">Worldison Academy Certificate Render Test</td></tr>
</table>
</body>
</html>
HTML;
}

function tableCertificateHtml(string $backgroundAttr = ''): string {
    $background = $backgroundAttr !== '' ? ' background="' . htmlspecialchars($backgroundAttr, ENT_QUOTES, 'UTF-8') . '"' : '';

    return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; color: #ffffff; }
    table.certificate { width: 100%; height: 210mm; border-collapse: collapse; }
    td { text-align: center; vertical-align: middle; }
    .top-space { height: 56mm; }
    .title { font-size: 42px; font-weight: bold; height: 18mm; }
    .name { font-size: 38px; font-weight: bold; height: 24mm; }
    .copy { font-size: 20px; height: 13mm; }
    .course { font-size: 24px; font-weight: bold; height: 20mm; }
    .verify-label { font-size: 18px; height: 8mm; }
    .verify { font-size: 16px; height: 12mm; }
    .code { font-size: 16px; height: 14mm; }
    .date { font-size: 18px; height: 20mm; }
    .bottom-space { height: 25mm; }
  </style>
</head>
<body>
  <table class="certificate"{$background} cellpadding="0" cellspacing="0">
    <tr><td class="top-space">&nbsp;</td></tr>
    <tr><td class="title">Certificate of Completion</td></tr>
    <tr><td class="name">Ada Student</td></tr>
    <tr><td class="copy">has successfully completed the course</td></tr>
    <tr><td class="course">Rendering Backgrounds with mPDF</td></tr>
    <tr><td class="verify-label">Verify at</td></tr>
    <tr><td class="verify">https://academy.worldison.org/verify.php?code=WLD-TEST</td></tr>
    <tr><td class="code">Certificate Code: <strong>WLD-TEST</strong></td></tr>
    <tr><td class="date">June 25, 2026</td></tr>
    <tr><td class="bottom-space">&nbsp;</td></tr>
  </table>
</body>
</html>
HTML;
}

function normalImgCertificateHtml(string $src): string {
    return <<<HTML
<!doctype html>
<html>
<body style="margin:0; padding:0; font-family: DejaVu Sans, sans-serif; color:#ffffff;">
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr><td><img src="{$src}" width="100%"></td></tr>
  <tr><td style="text-align:center; font-size:32px; padding-top:12px;">Ada Student</td></tr>
  <tr><td style="text-align:center; font-size:20px;">Certificate Code: WLD-TEST</td></tr>
</table>
</body>
</html>
HTML;
}

writeHtmlAndPdf('01_image_only_normal_img', imageOnlyHtml($bgPath));
writeHtmlAndPdf('02_image_plus_text_normal_img', imageAndTextHtml($bgPath));
writeHtmlAndPdf('03_certificate_watermark_table', tableCertificateHtml(), $bgPath);
writeHtmlAndPdf('04_certificate_base64_table_background', tableCertificateHtml($bgDataUrl));
writeHtmlAndPdf('05_certificate_normal_img_tag', normalImgCertificateHtml($bgPath));

echo "Generated certificate render comparison files in {$debugDir}\n";
