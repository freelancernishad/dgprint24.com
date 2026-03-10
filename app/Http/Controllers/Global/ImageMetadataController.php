<?php

namespace App\Http\Controllers\Global;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ImageMetadataController extends Controller
{
    /**
     * Get metadata of an image from a URL or an uploaded file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMetadata(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_url' => 'nullable|url',
            'image_file' => 'nullable|image|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        $originalName = null;

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $imagePath = $file->getRealPath();
            $originalName = $file->getClientOriginalName();
        } elseif ($request->image_url) {
            $imagePath = $request->image_url;
            $originalName = basename($imagePath);
        } else {
            return response()->json(['message' => 'Please provide either an image_url or an image_file.'], 422);
        }

        try {
            // Get basic info: width, height, mime
            $info = @getimagesize($imagePath);

            if (!$info) {
                return response()->json(['message' => 'Unable to read image metadata.'], 400);
            }

            $metadata = [
                'filename' => $originalName,
                'extension' => pathinfo($originalName, PATHINFO_EXTENSION),
                'file_size' => $this->getFileSize($imagePath),
                'width' => $info[0],
                'height' => $info[1],
                'mime' => $info['mime'],
                'channels' => $info['channels'] ?? null,
                'bits' => $info['bits'] ?? null,
                'color_mode' => $this->getColorMode($info),
                'dpi' => $this->getDpi($imagePath, $info['mime']),
                'exif' => $this->getExifData($imagePath, $info['mime']),
                'iptc' => $this->getIptcData($imagePath, $info),
            ];

            return response()->json([
                'success' => true,
                'data' => $metadata,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract DPI from an image.
     *
     * @param  string  $filename
     * @param  string  $mime
     * @return array|null
     */
    private function getDpi($filename, $mime)
    {
        $dpi = ['x' => 72, 'y' => 72]; // Default

        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            // Try EXIF first
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($filename);
                if ($exif) {
                    if (isset($exif['XResolution']) && isset($exif['YResolution'])) {
                        // EXIF resolutions can be fractions like "300/1"
                        $dpi['x'] = $this->parseResolution($exif['XResolution']);
                        $dpi['y'] = $this->parseResolution($exif['YResolution']);
                        return $dpi;
                    }
                }
            }

            // Fallback to reading JFIF header (APP0)
            $handle = @fopen($filename, 'rb');
            if ($handle) {
                $header = fread($handle, 20);
                fclose($handle);

                // Check for JFIF marker
                if (strpos($header, 'JFIF') !== false) {
                    $units = ord($header[13]);
                    $x_res = ord($header[14]) * 256 + ord($header[15]);
                    $y_res = ord($header[16]) * 256 + ord($header[17]);

                    if ($units == 1) { // DPI
                        $dpi['x'] = $x_res;
                        $dpi['y'] = $y_res;
                    } elseif ($units == 2) { // DPC (Dots Per CM)
                        $dpi['x'] = round($x_res * 2.54);
                        $dpi['y'] = round($y_res * 2.54);
                    }
                }
            }
        } elseif ($mime === 'image/png') {
            // Read PNG pHYs chunk
            $handle = @fopen($filename, 'rb');
            if ($handle) {
                // Skip signature (8 bytes)
                fread($handle, 8);
                while (!feof($handle)) {
                    $length = unpack('N', fread($handle, 4))[1];
                    $type = fread($handle, 4);
                    if ($type === 'pHYs') {
                        $data = fread($handle, $length);
                        $pixelsPerMetreX = unpack('N', substr($data, 0, 4))[1];
                        $pixelsPerMetreY = unpack('N', substr($data, 4, 4))[1];
                        $unit = ord($data[8]);
                        if ($unit === 1) { // Metre
                            $dpi['x'] = round($pixelsPerMetreX * 0.0254);
                            $dpi['y'] = round($pixelsPerMetreY * 0.0254);
                        }
                        break;
                    }
                    fseek($handle, $length + 4, SEEK_CUR); // Skip data + CRC
                }
                fclose($handle);
            }
        }

        return $dpi;
    }

    private function parseResolution($res)
    {
        if (is_string($res) && strpos($res, '/') !== false) {
            $parts = explode('/', $res);
            if (count($parts) === 2 && $parts[1] != 0) {
                return round($parts[0] / $parts[1]);
            }
        }
        return (int)$res;
    }

    private function getExifData($filename, $mime)
    {
        if (($mime === 'image/jpeg' || $mime === 'image/jpg' || $mime === 'image/tiff') && function_exists('exif_read_data')) {
            $exif = @exif_read_data($filename);
            if ($exif) {
                // Filter out binary data or very long strings if needed, but for now return all
                return $exif;
            }
        }
        return null;
    }

    private function getIptcData($filename, $info)
    {
        if (isset($info['APP13'])) {
            $iptc = iptcparse($info['APP13']);
            if ($iptc) {
                return $iptc;
            }
        }
        return null;
    }

    private function getFileSize($filename)
    {
        if (filter_var($filename, FILTER_VALIDATE_URL)) {
            $headers = get_headers($filename, 1);
            if (isset($headers['Content-Length'])) {
                return (int)$headers['Content-Length'];
            }
            return null;
        }
        return file_exists($filename) ? filesize($filename) : null;
    }

    private function getColorMode($info)
    {
        $channels = $info['channels'] ?? null;
        $mime = $info['mime'] ?? '';

        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            if ($channels === 3) {
                return 'RGB';
            } elseif ($channels === 4) {
                return 'CMYK';
            }
        } elseif ($mime === 'image/png') {
            // PNG is almost always RGB/RGBA
            return 'RGB';
        }

        return $channels ? "Unknown ($channels channels)" : 'Unknown';
    }
}
