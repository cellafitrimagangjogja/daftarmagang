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

// Test 1: Coba render tanpa footer
$configNoFooter = $mockData;
$configNoFooter['_page_config'] = [
    'show_paraf_box' => false,
    'show_paraf_footer' => false,
    'footer_html' => '',
    'first_page_footer_html' => '',
];

try {
    $pdf = App\Services\MouDocumentRenderer::renderPdfBinary($tpl, $configNoFooter, 'Test No Footer');
    echo "Test 1 (No footer table) SUCCESS! Size: " . strlen($pdf) . "\n";
} catch (\Throwable $e) {
    echo "Test 1 FAILED: " . $e->getMessage() . "\n";
}

// Test 2: Coba render dengan footer table tapi tanpa body (hanya <p>Hello</p>)
try {
    $tplEmpty = clone $tpl;
    $tplEmpty->template_html = '<p>Test body only</p>';
    $pdf2 = App\Services\MouDocumentRenderer::renderPdfBinary($tplEmpty, $mockData, 'Test Empty Body');
    echo "Test 2 (Footer table with empty body) SUCCESS! Size: " . strlen($pdf2) . "\n";
} catch (\Throwable $e) {
    echo "Test 2 FAILED: " . $e->getMessage() . "\n";
}
