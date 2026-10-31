<?php

[, $appDir, $base, $mapFile] = $argv;
$dryRun = getenv('DRY_RUN') === '1';

require $appDir.'/vendor/autoload.php';
$app = require $appDir.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$models = ['product' => Product::class, 'category' => Category::class];
$columns = ['image' => 'image', 'icon' => 'icon_image', 'background_light' => 'bg_image_light', 'background_dark' => 'bg_image_dark'];
$directories = [
    'product.image' => 'products',
    'product.icon' => 'products/icons',
    'category.image' => 'categories',
    'category.background_light' => 'categories/backgrounds',
    'category.background_dark' => 'categories/backgrounds',
];

$disk = Storage::disk('public');
$downloads = [];
$set = $kept = $empty = $missing = 0;

foreach (require $mapFile as [$entity, $id, $slot, $file]) {
    $record = $models[$entity]::withoutGlobalScopes()->find($id);
    if (! $record) {
        echo "skip  {$entity} {$id}: not found\n";
        $missing++;
        continue;
    }
    $column = $columns[$slot];
    $current = $record->{$column};
    if (! $current) {
        $empty++;
        continue;
    }
    if ($disk->exists($current)) {
        echo "keep  {$entity} {$id} {$slot}: {$current} is still there\n";
        $kept++;
        continue;
    }
    if ($dryRun) {
        echo "would {$entity} {$id} {$slot}: {$file}.webp\n";
        $set++;
        continue;
    }
    $downloads[$file] ??= Http::timeout(60)->retry(3, 2000)->get("{$base}/{$file}.webp")->throw()->body();
    if (strlen($downloads[$file]) < 100 || substr($downloads[$file], 8, 4) !== 'WEBP') {
        fwrite(STDERR, "Download of {$file}.webp did not return a WebP image.\n");
        exit(1);
    }
    $path = $directories["{$entity}.{$slot}"].'/'.Str::uuid()->toString().'.webp';
    if (! $disk->put($path, $downloads[$file])) {
        fwrite(STDERR, "Could not write {$path}.\n");
        exit(1);
    }
    $record->update([$column => $path]);
    echo "set   {$entity} {$id} {$slot}: {$path}\n";
    $set++;
}

echo "\n".($dryRun ? 'Would set' : 'Set')." {$set} image(s); {$kept} still had their file; {$empty} slot(s) were never set; {$missing} record(s) not found.\n";
