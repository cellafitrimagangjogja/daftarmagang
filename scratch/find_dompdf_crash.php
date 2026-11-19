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

// Mari kita panggil bagian demi bagian dari renderPdfBinary untuk melihat baris mana yang merusak HTML
$renderedHtml = $tpl->renderContent($mockData, false);
$htmlWithBase64 = App\Services\MouDocumentRenderer::embedLocalImagesAsBase64($renderedHtml);
$htmlWithBase64 = preg_replace('/<img[^>]*class="[^"]*mou-watermark-center[^"]*"[^>]*>/i', '', $htmlWithBase64);
$htmlWithBase64 = preg_replace('/<div[^>]*class="[^"]*mou-watermark-layer[^"]*"[^>]*>[\s\S]*?<\/div>\s*<\/div>/i', '', $htmlWithBase64);
$htmlWithBase64 = preg_replace('/<div[^>]*class="[^"]*mou-watermark-fixed-header[^"]*"[^>]*>[\s\S]*?<\/div>/i', '', $htmlWithBase64);
$htmlWithBase64 = preg_replace('/<img[^>]*class="[^"]*mou-watermark-logo-(?:left|right)[^"]*"[^>]*>/i', '', $htmlWithBase64);

$htmlWithBase64 = preg_replace(
    '/<(?:div|hr)[^>]*class="[^"]*mou-page-break[^"]*"[^>]*><\/(?:div|hr)>|<(?:div|hr)[^>]*data-page-break="true"[^>]*><\/(?:div|hr)>|<hr[^>]*class="[^"]*mou-page-break[^"]*"[^>]*\/?>/i',
    '<div class="mou-page-break" data-page-break="true" style="page-break-after: always; break-after: page; height: 0; line-height: 0; font-size: 0; margin: 0; padding: 0;"></div>',
    $htmlWithBase64
);

$htmlWithBase64 = preg_replace_callback(
    '/(<p[^>]*><strong>\s*Pasal\s+\d+[^<]*<\/strong><\/p>\s*<p[^>]*><strong>[^<]+<\/strong><\/p>)/iu',
    function ($matches) {
        return preg_replace('/style="([^"]*)"/i', 'style="$1; page-break-after: avoid; break-after: avoid;"', $matches[1]);
    },
    $htmlWithBase64
);

function testDompdfHtml($h, $label) {
    $tpl = App\Models\MouTemplate::find(1);
    $viewData = [
        'documentTitle'         => 'Test',
        'renderedHtml'          => $h,
        'canonicalCss'          => App\Services\MouDocumentRenderer::getCanonicalDocumentCss($tpl->getPageConfig()),
        'pageConfig'            => $tpl->getPageConfig(),
        'showHeaderLogos'       => true,
        'showWatermark'         => false,
        'institutionLogoBase64' => App\Services\MouDocumentRenderer::convertLocalPathToBase64('/images/mou/institution-logo-placeholder.png'),
        'companyLogoBase64'     => App\Services\MouDocumentRenderer::convertLocalPathToBase64('/images/mou/seven-inc-logo.png'),
    ];
    $html = view('hr.mou.pdf-document', $viewData)->render();
    $options = new Dompdf\Options();
    $options->setIsHtml5ParserEnabled(true);
    $options->setIsRemoteEnabled(false);
    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->setPaper('a4', 'portrait');
    $dompdf->loadHtml($html);
    try {
        $dompdf->render();
        echo "SUCCESS at: {$label}!\n";
    } catch (\Throwable $e) {
        echo "FAILED at: {$label} -> " . $e->getMessage() . "\n";
    }
}

testDompdfHtml($htmlWithBase64, "Step 1: before table transformations");

// Step 2: table-layout fixed
$s2 = preg_replace_callback('/<table\b([^>]*)>/i', function ($matches) {
    $attrs = $matches[1];
    if (!preg_match('/table-layout\s*:\s*fixed/i', $attrs)) {
        if (preg_match('/style="([^"]*)"/i', $attrs, $sm)) {
            $newStyle = rtrim(trim($sm[1]), ';') . '; table-layout: fixed; width: 100%;';
            $attrs = str_replace($sm[0], 'style="' . $newStyle . '"', $attrs);
        } else {
            $attrs .= ' style="table-layout: fixed; width: 100%;"';
        }
    }
    return '<table' . $attrs . '>';
}, $htmlWithBase64);
testDompdfHtml($s2, "Step 2: table-layout fixed");

// Step 3: table komparisi
$s3 = preg_replace_callback('/<table[^>]*class="[^"]*mou-table-borderless[^"]*"[^>]*>.*?<\/table>/is', function ($matches) {
    $tableHtml = $matches[0];
    if (preg_match('/<td[^>]*>\s*(?:<p[^>]*>)?\s*1\.\s*(?:<\/p>)?\s*<\/td>/iu', $tableHtml)) {
        $compColgroup = '<colgroup><col style="width: 5%;"><col style="width: 34%;"><col style="width: 3%;"><col style="width: 58%;"></colgroup>';
        if (preg_match('/<colgroup>.*?<\/colgroup>/is', $tableHtml)) {
            $tableHtml = preg_replace('/<colgroup>.*?<\/colgroup>/is', $compColgroup, $tableHtml);
        } else {
            $tableHtml = preg_replace('/(<table[^>]*>)/i', '$1' . $compColgroup, $tableHtml);
        }
        $tableHtml = preg_replace_callback('/<tr[^>]*>(.*?)<\/tr>/is', function ($rowMatches) {
            $rowContent = $rowMatches[1];
            if (preg_match_all('/<td\b([^>]*)>(.*?)<\/td>/is', $rowContent, $tdMatches)) {
                if (count($tdMatches[0]) === 4) {
                    $newTd0 = '<td style="width: 5%; vertical-align: top; white-space: nowrap; padding-right: 2pt;">' . $tdMatches[2][0] . '</td>';
                    $newTd1 = '<td style="width: 34%; vertical-align: top; padding-left: 2pt; word-break: normal; overflow-wrap: break-word;">' . $tdMatches[2][1] . '</td>';
                    $newTd2 = '<td style="width: 3%; vertical-align: top; text-align: center; padding-left: 0; padding-right: 0;">' . $tdMatches[2][2] . '</td>';
                    $newTd3 = '<td style="width: 58%; vertical-align: top; text-align: justify; word-break: normal; overflow-wrap: break-word;">' . $tdMatches[2][3] . '</td>';
                    return '<tr>' . $newTd0 . $newTd1 . $newTd2 . $newTd3 . '</tr>';
                }
            }
            return $rowMatches[0];
        }, $tableHtml);
    }
    return $tableHtml;
}, $s2);
testDompdfHtml($s3, "Step 3: table komparisi");

// Step 4: normalizeCorrespondenceTablesHtml
$s4 = App\Services\MouDocumentRenderer::normalizeCorrespondenceTablesHtml($s3);
testDompdfHtml($s4, "Step 4: correspondence table");
