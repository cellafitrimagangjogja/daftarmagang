<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$mou = App\Models\Mou::latest()->first();
if ($mou) {
    echo "Latest MOU ID: {$mou->id} | Document Number: {$mou->document_number}\n";
    echo "Title: {$mou->title}\n";
    echo "Institution: " . ($mou->institution?->name ?? 'None') . "\n";
    echo "Institution MOU Number: " . ($mou->institution_mou_number ?? 'None') . "\n";
    echo "Company MOU Number: " . ($mou->company_mou_number ?? 'None') . "\n";
    echo "Render snapshot keys: " . implode(', ', array_keys($mou->render_data_snapshot ?? [])) . "\n";
} else {
    echo "No MOU records found\n";
}
