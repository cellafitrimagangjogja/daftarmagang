<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check if any Mou exists
$mou = App\Models\Mou::first();
if ($mou) {
    echo "Found MOU ID: " . $mou->id . "\n";
    echo "company_mou_number: " . $mou->company_mou_number . "\n";
    echo "first_party_institution_name: " . $mou->first_party_institution_name . "\n";
} else {
    echo "No MOU found in database\n";
}

$tpl = App\Models\MouTemplate::find(1);
echo "Template 1: " . $tpl->name . "\n";
