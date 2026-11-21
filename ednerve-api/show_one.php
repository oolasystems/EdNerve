<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['faculties', 'schools', 'users'] as $table) {
    echo "=== $table ===\n";
    $rows = DB::select("SHOW CREATE TABLE `$table`");
    if (empty($rows)) { echo "  (table missing)\n\n"; continue; }
    echo $rows[0]->{'Create Table'}, "\n\n";
}
