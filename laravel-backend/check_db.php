<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
echo "Tables:\n";
foreach ($tables as $t) {
    echo $t->name . "\n";
}

echo "\n--- Row counts ---\n";
foreach ($tables as $t) {
    $cnt = DB::table($t->name)->count();
    if ($cnt > 0) {
        echo "{$t->name}: $cnt rows\n";
    }
}
