<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== faculties ===\n";
foreach (DB::table('faculties')->orderBy('institution_id')->orderBy('id')->get() as $f) {
    echo "ins={$f->institution_id} id={$f->id} {$f->code}: {$f->name}\n";
}
echo "\n=== schools ===\n";
foreach (DB::table('schools')->orderBy('institution_id')->orderBy('id')->get() as $s) {
    echo "ins={$s->institution_id} id={$s->id} fac={$s->faculty_id} {$s->code}: {$s->name}\n";
}
echo "\n=== academic_units ===\n";
foreach (DB::table('academic_units')->orderBy('institution_id')->orderBy('id')->get() as $u) {
    echo "ins={$u->institution_id} id={$u->id} fac={$u->faculty_id} scl={$u->school_id} {$u->code}: {$u->name}\n";
}
