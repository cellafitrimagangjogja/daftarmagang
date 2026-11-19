<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$template = App\Models\MouTemplate::find(1);
$mou = App\Models\Mou::find(1);

if (!$template || !$mou) {
    echo "Template or MOU not found\n";
    exit(1);
}

// Update sementara HTML dengan header baru untuk pengujian
$originalHtml = $template->template_html;

$newHeaderHtml = str_replace(
    '<p style="text-align: center; text-transform: uppercase; margin-bottom: 4pt"><strong>DENGAN [company_name]</strong></p>',
    "<p style=\"text-align: center; margin-bottom: 2pt\"><strong>DENGAN</strong></p>\n    <p style=\"text-align: center; text-transform: uppercase; margin-bottom: 4pt\"><strong>[company_name]</strong></p>",
    $originalHtml
);

$template->template_html = $newHeaderHtml;

$snapshot = $mou->render_data_snapshot ?? [];
$snapshot['_page_config'] = $template->getPageConfig();
$snapshot['_media_assets'] = $template->getMediaAssets();

$pdfOutput = App\Services\MouDocumentRenderer::renderPdfBinary($template, $snapshot, 'Test MOU Render');
$pdfPath = __DIR__ . '/test_output.pdf';
file_put_contents($pdfPath, $pdfOutput);

// Hitung jumlah halaman PDF
$pageCount = 0;
if (preg_match_all("/\/Page\W/", $pdfOutput, $dummy)) {
    $pageCount = count($dummy[0]);
}

echo "PDF generated successfully!\n";
echo "File size: " . strlen($pdfOutput) . " bytes\n";
echo "Page count estimate: " . $pageCount . " pages\n";

// Kembalikan ke original
$template->template_html = $originalHtml;
