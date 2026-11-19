<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$t = App\Models\MouTemplate::find(1);
if ($t) {
    echo "=== Template ID: {$t->id} | Name: {$t->name} | Version: {$t->version_number} ===\n";
    echo "--- Top 1000 chars of template_html ---\n";
    echo substr($t->template_html, 0, 1000) . "\n";
    echo "--- Page Config ---\n";
    print_r($t->getPageConfig());
} else {
    echo "Template not found\n";
}
