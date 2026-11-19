<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

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

$renderedHtml = $tpl->renderContent($mockData, false);
$pageConfig = App\Services\MouDocumentRenderer::renderPageConfig($tpl, $mockData, false);

$viewData = [
    'documentTitle'         => $tpl->name,
    'renderedHtml'          => $renderedHtml,
    'canonicalCss'          => App\Services\MouDocumentRenderer::getCanonicalDocumentCss($pageConfig),
    'pageConfig'            => $pageConfig,
    'showHeaderLogos'       => true,
    'showWatermark'         => false,
    'institutionLogoBase64' => App\Services\MouDocumentRenderer::convertLocalPathToBase64('/images/mou/institution-logo-placeholder.png'),
    'companyLogoBase64'     => App\Services\MouDocumentRenderer::convertLocalPathToBase64('/images/mou/seven-inc-logo.png'),
];

$bladeOutput = view('hr.mou.pdf-document', $viewData)->render();
file_put_contents('scratch/test_header_blade.html', $bladeOutput);

echo "Rendered HTML saved to scratch/test_header_blade.html\n";
