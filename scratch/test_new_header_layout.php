<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tpl = App\Models\MouTemplate::find(1);
$mockData = array_merge(App\Models\MouTemplate::getDefaultCompanyValues(), [
    'institution_name' => 'UNIVERSITAS MUHAMMADIYAH PONOROGO',
    'institution_signatory_name' => 'Dr. Happy Susanto, M.A.',
    'institution_signatory_title' => 'Rektor',
    'institution_address' => 'Jl. Budi Utomo No. 10, Ponorogo',
    'company_mou_number' => '001/SPK.MAGANG/CEO-SEVEN/XI/2026',
]);

// Coba modifikasi header teks
$originalHtml = $tpl->template_html;
$tpl->template_html = str_replace(
    '<p style="text-align: center; text-transform: uppercase; margin-bottom: 4pt"><strong>DENGAN [company_name]</strong></p>',
    "<p style=\"text-align: center; margin-bottom: 2pt\"><strong>DENGAN</strong></p>\n    <p style=\"text-align: center; text-transform: uppercase; margin-bottom: 4pt\"><strong>[company_name]</strong></p>",
    $originalHtml
);

$renderedHtml = $tpl->renderContent($mockData, false);
$pageConfig = App\Services\MouDocumentRenderer::renderPageConfig($tpl, $mockData, false);

$pdfOutput = App\Services\MouDocumentRenderer::renderPdfBinary($renderedHtml, $pageConfig, 'Test PDF Render With New Header');
if (preg_match_all("/\/Page\W/", $pdfOutput, $dummy)) {
    echo "Total Page Count With New Title Layout: " . count($dummy[0]) . " pages\n";
} else {
    echo "Could not count pages\n";
}
