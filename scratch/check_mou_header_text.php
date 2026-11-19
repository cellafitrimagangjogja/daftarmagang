<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mou = App\Models\Mou::find(1);
$html = $mou->rendered_document_html;

$pos = strpos($html, '<p style="text-align: center; margin-bottom: 6pt"><strong>NOTA KESEPAHAMAN</strong></p>');
if ($pos !== false) {
    $end = strpos($html, 'Pasal 1', $pos);
    echo substr($html, $pos, $end - $pos) . "\n";
}
