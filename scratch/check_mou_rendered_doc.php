<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mou = App\Models\Mou::find(1);
$html = $mou->rendered_document_html;
if (preg_match('/<p[^>]*>.*?NOTA KESEPAHAMAN.*?<\/p>/is', $html, $m)) {
    echo "Found match: " . $m[0] . "\n";
} elseif (preg_match('/<p[^>]*>.*?PERJANJIAN.*?<\/p>/is', $html, $m)) {
    echo "Found match: " . $m[0] . "\n";
}
// Show all paragraphs before Pasal 1
if (preg_match('/(.*?)Pasal 1/is', $html, $m)) {
    // strip images
    $clean = preg_replace('/src="data:[^"]+"/i', 'src="[BASE64]"', $m[1]);
    echo substr($clean, -1000) . "\n";
}
