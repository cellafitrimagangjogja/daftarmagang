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

$renderedHtml = $tpl->renderContent($mockData, false);
$pageConfig = App\Services\MouDocumentRenderer::renderPageConfig($tpl, $mockData, false);

$pdfOutput = App\Services\MouDocumentRenderer::renderPdfBinary($renderedHtml, $pageConfig, 'Test PDF Render');
echo "PDF Generation Success!\n";
echo "PDF size: " . strlen($pdfOutput) . " bytes\n";

if (preg_match_all("/\/Page\W/", $pdfOutput, $dummy)) {
    echo "Total Page Count: " . count($dummy[0]) . " pages\n";
}
