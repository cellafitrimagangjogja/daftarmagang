<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mou = App\Models\Mou::find(1);
$html = $mou->rendered_document_html;
$pos = strpos($html, '</style>');
echo substr($html, $pos + 8, 1500) . "\n";
