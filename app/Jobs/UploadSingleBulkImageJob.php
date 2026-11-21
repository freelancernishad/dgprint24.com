<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Product;
use App\Services\FileSystem\FileUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadSingleBulkImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;

    public function __construct(
        public string $type,
        public int $targetId,
        public string $source,
        public int $sortOrder = 0,
        public int $imageIndex = 0,
        public int $totalImages = 0
    ) {}

    public function handle(): void
    {
        $progress = $this->totalImages > 0
            ? "[{$this->imageIndex}/{$this->totalImages}]"
            : "[#{$this->targetId}]";
        $fileName = basename($this->source);
        $bucket = config('filesystems.disks.s3.bucket') ?: 'local-public';

        try {
            if ($this->type === 'category') {
                $category = Category::find($this->targetId);
                if (!$category) {
                    $this->outputProgress("{$progress} Category #{$this->targetId} not found, skipped.");
                    return;
                }
                if (!empty($category->category_image)) {
                    $this->outputProgress("{$progress} Category '{$category->name}' already has image, skipped.");
                    return;
                }

                $catSlug = Str::slug($category->name) ?: 'category-' . $category->id;
                $this->outputProgress("{$progress} Uploading Category '{$category->name}' ({$fileName}) -> {$bucket}...");
                $url = $this->resolveAndUploadImage(
                    $this->source,
                    'laravel-project/category/' . $catSlug
                );

                if ($url) {
                    $category->update(['category_image' => $url]);
                    $this->outputProgress("{$progress} Uploaded OK -> {$url}");
                } else {
                    $this->outputProgress("{$progress} Failed/Not found: {$this->source}");
                }
                return;
            }

            if ($this->type === 'product') {
                $product = Product::with('category')->find($this->targetId);
                if (!$product) {
                    $this->outputProgress("{$progress} Product #{$this->targetId} not found, skipped.");
                    return;
                }

                $catSlug = Str::slug($product->category->name ?? 'uncategorized') ?: 'uncategorized';
                $prodSlug = Str::slug($product->product_name) ?: 'product-' . $product->id;

                $this->outputProgress("{$progress} Uploading Product '{$product->product_name}' ({$fileName}) -> {$bucket}...");
                $url = $this->resolveAndUploadImage(
                    $this->source,
                    'laravel-project/product/' . $catSlug . '/' . $prodSlug
                );

                if ($url) {
                    $product->images()->create([
                        'image_url' => $url,
                        'sort_order' => $this->sortOrder,
                    ]);

                    if (empty($product->thumbnail)) {
                        $product->update(['thumbnail' => $url]);
                    }
                    $this->outputProgress("{$progress} Uploaded OK -> {$url}");
                } else {
                    $this->outputProgress("{$progress} Failed/Not found: {$this->source}");
                }
            }
        } catch (\Throwable $e) {
            $this->outputProgress("{$progress} ERROR: " . $e->getMessage());
            Log::error('UploadSingleBulkImageJob error: ' . $e->getMessage(), [
                'type' => $this->type,
                'targetId' => $this->targetId,
                'source' => $this->source,
                'sortOrder' => $this->sortOrder,
            ]);
            throw $e;
        }
    }

    private function outputProgress(string $message): void
    {
        Log::info($message);
        if (app()->runningInConsole()) {
            fwrite(STDOUT, $message . PHP_EOL);
        }
    }

    private function resolveAndUploadImage(string $source, string $s3Dir): ?string
    {
        $source = trim($source);
        if ($source === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $source)) {
            return $source;
        }

        $candidatePaths = [
            $source,
            base_path('../' . ltrim($source, '/\\')),
            base_path($source),
        ];

        $localPath = null;
        foreach ($candidatePaths as $path) {
            if (is_file($path) && is_readable($path)) {
                $localPath = $path;
                break;
            }
        }

        if ($localPath === null) {
            return null;
        }

        $safeName = time() . '_' . Str::random(6) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($localPath));
        $s3Path = trim($s3Dir, '/') . '/' . $safeName;
        $content = file_get_contents($localPath);

        if ($content === false) {
            return null;
        }

        if (!empty(config('filesystems.disks.s3.bucket'))) {
            return (new FileUploadService())->uploadContentToS3($content, $s3Path);
        }

        Storage::disk('public')->put($s3Path, $content);
        return rtrim(config('app.url'), '/') . '/storage/' . $s3Path;
    }
}
