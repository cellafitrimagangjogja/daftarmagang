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
    'institution_address' => 'Jl. Budi Utomo No. 10, Ronowijayan, Siman, Kabupaten Ponorogo, Jawa Timur 63471',
    'institution_correspondence_unit' => 'Kepala Bidang Kerjasama',
    'institution_phone' => '(0352) 481124',
    'institution_email' => 'humas@umpo.ac.id',
    'company_mou_number' => '001/SPK.MAGANG/CEO-SEVEN/XI/2026',
    'institution_mou_number' => '020/UMPO/MOU/XI/2026',
]);

try {
    $pdfOutput = App\Services\MouDocumentRenderer::renderPdfBinary($tpl, $mockData, 'Test Mock Data PDF');
    echo "Success! PDF size: " . strlen($pdfOutput) . " bytes\n";
    if (preg_match_all("/\/Page\W/", $pdfOutput, $dummy)) {
        echo "Page count: " . count($dummy[0]) . " pages\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
