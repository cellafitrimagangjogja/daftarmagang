<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(App\Models\MouTemplate::all(['id', 'name', 'version_number', 'is_active']) as $t) {
    echo $t->id . ' | ' . $t->name . ' | v' . $t->version_number . ' | active: ' . ($t->is_active ? 'yes' : 'no') . PHP_EOL;
}
