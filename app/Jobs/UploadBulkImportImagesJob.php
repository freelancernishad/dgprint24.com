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

class UploadBulkImportImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(
        public array $categoryImageTasks = [],
        public array $productImageTasks = []
    ) {}

    public function handle(): void
    {
        foreach ($this->categoryImageTasks as $task) {
            try {
                $category = Category::find($task['category_id'] ?? null);
                if (!$category || !empty($category->category_image)) {
                    continue;
                }

                $url = $this->resolveAndUploadImage(
                    (string) ($task['source'] ?? ''),
                    'dgprint24/uploads/images/category'
                );

                if ($url) {
                    $category->update(['category_image' => $url]);
                }
            } catch (\Throwable $e) {
                Log::error('UploadBulkImportImagesJob category error: ' . $e->getMessage(), $task);
            }
        }

        foreach ($this->productImageTasks as $task) {
            try {
                $product = Product::find($task['product_id'] ?? null);
                if (!$product || $product->images()->count() > 0) {
                    continue;
                }

                $firstUrl = null;
                foreach (array_values($task['sources'] ?? []) as $index => $imgSource) {
                    $url = $this->resolveAndUploadImage(
                        (string) $imgSource,
                        'dgprint24/uploads/images/products'
                    );

                    if ($url) {
                        $product->images()->create([
                            'image_url' => $url,
                            'sort_order' => $index,
                        ]);
                        if ($firstUrl === null) {
                            $firstUrl = $url;
                        }
                    }
                }

                if ($firstUrl && empty($product->thumbnail)) {
                    $product->update(['thumbnail' => $firstUrl]);
                }
            } catch (\Throwable $e) {
                Log::error('UploadBulkImportImagesJob product error: ' . $e->getMessage(), $task);
            }
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
