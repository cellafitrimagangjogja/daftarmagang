<?php
require 'vendor/autoload.php';
$html = file_get_contents('scratch/test_header_blade.html');

$options = new Dompdf\Options();
$options->setIsHtml5ParserEnabled(true);
$options->setIsRemoteEnabled(false);

$dompdf = new Dompdf\Dompdf($options);
$dompdf->setPaper('a4', 'portrait');
$dompdf->loadHtml($html);

try {
    $dompdf->render();
    echo "SUCCESS render test_header_blade.html! Size: " . strlen($dompdf->output()) . "\n";
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
