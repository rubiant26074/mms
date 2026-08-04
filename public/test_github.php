<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');
echo "=== DATABASE DIAGNOSTIC SCRIPT ===\n\n";

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $columns = Illuminate\Support\Facades\Schema::getColumnListing('boms');
    echo "Columns of 'boms' table:\n";
    print_r($columns);
    
    echo "\nSample Bom data:\n";
    $sample = App\Models\Bom::with('item')->first();
    print_r($sample?->toArray());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
