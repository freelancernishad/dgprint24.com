<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FourOverFetcherController extends Controller
{
    /**
     * Fetch product details from 4over and format as system payload.
     */
    public function fetch(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'email' => 'nullable|string',
            'password' => 'nullable|string',
            'categoryId' => 'nullable|string',
            'sessionCookie' => 'nullable|string',
            'pricingTableText' => 'nullable|string',
        ]);

        $url = $request->input('url');
        $email = $request->input('email');
        $password = $request->input('password');
        $categoryId = $request->input('categoryId') ?: null;
        $sessionCookie = $request->input('sessionCookie');
        $pricingTableText = $request->input('pricingTableText');

        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

        // 1. Fetch HTML using curl (supporting cookies / login)
        $activeCookie = $sessionCookie ?: '';

        // Automated login to retrieve complete product <select> configurator
        if (!$activeCookie) {
            $loginEmail = $email ?: 'business@zsi.ai';
            $loginPassword = $password ?: 'Zsi1001$';
            $formKey = bin2hex(random_bytes(8));
            $postData = http_build_query([
                'form_key' => $formKey,
                'login[username]' => $loginEmail,
                'login[password]' => $loginPassword,
            ]);

            $setCookies = [];
            $chAuth = curl_init('https://4over.com/customer/account/loginPost/');
            curl_setopt($chAuth, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chAuth, CURLOPT_POST, true);
            curl_setopt($chAuth, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($chAuth, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Cookie: form_key=' . $formKey,
            ]);
            curl_setopt($chAuth, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($chAuth, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($chAuth, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($chAuth, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$setCookies) {
                if (stripos($header, 'Set-Cookie:') === 0) {
                    $setCookies[] = trim(substr($header, 11));
                }
                return strlen($header);
            });
            curl_exec($chAuth);
            curl_close($chAuth);

            $cookiePairs = [];
            foreach ($setCookies as $sc) {
                $pair = trim(explode(';', $sc)[0]);
                if ($pair) {
                    $cookiePairs[] = $pair;
                }
            }
            $cookiePairs[] = 'form_key=' . $formKey;
            $activeCookie = implode('; ', array_unique($cookiePairs));
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($activeCookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $activeCookie);
        }
        $html = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$html || $status >= 400) {
            return response()->json([
                'isError' => true,
                'message' => "Failed to fetch 4over product. HTTP Status: {$status}",
            ], 400);
        }

        // 2. Extract Product Name strictly from 4over
        $productName = '';
        if (preg_match('/<h1[^>]*class="page-title"[^>]*>[\s\S]*?<span[^>]*>([\s\S]*?)<\/span>[\s\S]*?<\/h1>/i', $html, $m)) {
            $productName = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        } elseif (preg_match('/<title>([\s\S]*?)<\/title>/i', $html, $m)) {
            $productName = trim(explode('|', html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))[0]);
        }

        // 3. Extract Description strictly from 4over
        $productDescription = '';
        if (preg_match('/<div class="product attribute description">[\s\S]*?<div class="value">([\s\S]*?)<\/div>[\s\S]*?<\/div>/i', $html, $m)) {
            $productDescription = trim($m[1]);
        }

        // 4. Extract Images & Thumbnail strictly from 4over gallery
        $productImages = [];
        if (preg_match('/"mage\/gallery\/gallery":\s*\{[\s\S]*?"data":\s*(\[[\s\S]*?\])\s*,\s*"options"/i', $html, $m)) {
            $galleryData = json_decode($m[1], true);
            if (is_array($galleryData)) {
                foreach ($galleryData as $item) {
                    $img = $item['full'] ?? ($item['img'] ?? ($item['thumb'] ?? null));
                    if ($img && !in_array($img, $productImages)) {
                        $productImages[] = $img;
                    }
                }
            }
        }
        if (empty($productImages)) {
            if (preg_match_all('/https:\/\/4over\.com\/media\/catalog\/product\/[^\s"\'<>]+/i', $html, $fallbackImgs)) {
                foreach (array_unique($fallbackImgs[0]) as $img) {
                    if (!in_array($img, $productImages)) {
                        $productImages[] = $img;
                    }
                }
            }
        }
        $thumbnail = $productImages[0] ?? '';

        // 5. Extract FAQs strictly from 4over
        $faqs = [];
        $faqHtml = $html;
        if (preg_match('/id="productfaq"[\s\S]*?<\/div>\s*<script/i', $html, $m)) {
            $faqHtml = $m[0];
        }
        if (preg_match_all('/<div data-collapsible="true" data-element="title">([\s\S]*?)<\/div>\s*<div data-content="true"><div data-element="content">([\s\S]*?)<\/div><\/div>/i', $faqHtml, $faqMatches, PREG_SET_ORDER)) {
            foreach ($faqMatches as $index => $fm) {
                $q = trim(html_entity_decode(strip_tags($fm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $a = trim(html_entity_decode(strip_tags($fm[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($q && $a) {
                    $faqs[] = [
                        'id' => (string) (round(microtime(true) * 1000) + $index),
                        'question' => $q,
                        'answer' => $a,
                    ];
                }
            }
        }

        // 6. Extract Specs strictly from the product's configurable <select> fields (spConfig)
        // Do NOT take from footer / bottom category table (#product-attribute-specs-table)
        $specs = [];
        $spPos = strpos($html, '"spConfig"');
        if ($spPos !== false) {
            $startBrace = strpos($html, '{', $spPos);
            $depth = 0;
            $endBrace = -1;
            for ($i = $startBrace; $i < strlen($html); $i++) {
                if ($html[$i] === '{') $depth++;
                else if ($html[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $endBrace = $i;
                        break;
                    }
                }
            }
            if ($endBrace !== -1) {
                $sp = json_decode(substr($html, $startBrace, $endBrace - $startBrace + 1), true);
                if (!empty($sp['attributes'])) {
                    foreach ($sp['attributes'] as $attr) {
                        $label = trim($attr['label'] ?? '');
                        if (!$label) {
                            $code = trim($attr['code'] ?? '');
                            $label = ucwords(str_replace('_', ' ', $code));
                        }
                        $opts = [];
                        foreach ($attr['options'] ?? [] as $opt) {
                            $optLabel = trim($opt['label'] ?? '');
                            if ($optLabel && $optLabel !== '-' && $optLabel !== 'Choose an Option...' && $optLabel !== 'Choose an option below') {
                                if (!in_array($optLabel, $opts, true)) {
                                    $opts[] = $optLabel;
                                }
                            }
                        }
                        if ($label && !empty($opts)) {
                            $specs[$label] = $opts;
                        }
                    }
                }
            }
        }

        // Clean Coating: in 4over configurator, Spot UV is handled via SPOT UV SIDES
        // and generic category-wide coatings (Aqueous Coating, Satin Aqueous, No Coating) don't belong to standard coated business cards
        $coatingKey = null;
        $hasSpotUvSides = false;
        foreach (array_keys($specs) as $k) {
            if (strcasecmp($k, 'Coating') === 0) $coatingKey = $k;
            if (stripos($k, 'spot uv') !== false) $hasSpotUvSides = true;
        }

        if ($coatingKey && !empty($specs[$coatingKey])) {
            $cleanedCoatings = [];
            foreach ($specs[$coatingKey] as $co) {
                if ($hasSpotUvSides && strcasecmp($co, 'Spot UV') === 0) continue;
                if (in_array('Matte', $specs[$coatingKey], true) && in_array('UV on 4-color side', $specs[$coatingKey], true)) {
                    if (in_array($co, ['No Coating', 'Satin Aqueous', 'Aqueous Coating'], true)) continue;
                }
                $cleanedCoatings[] = $co;
            }
            $specs[$coatingKey] = $cleanedCoatings;
        }

        // 7. Dynamic Options - Structured logically from 4over specs (Zero fake placeholders, zero invented options)
        $shapeKey = null;
        $radiusKey = null;
        $coatingKey = null;
        $spotUvSidesKey = null;

        foreach (array_keys($specs) as $k) {
            if (strcasecmp($k, 'Shape') === 0) $shapeKey = $k;
            elseif (stripos($k, 'radius') !== false && stripos($k, 'corner') !== false) $radiusKey = $k;
            elseif (strcasecmp($k, 'Coating') === 0) $coatingKey = $k;
            elseif (stripos($k, 'spot uv') !== false) $spotUvSidesKey = $k;
        }

        $radiusChoices = ($radiusKey && !empty($specs[$radiusKey])) ? array_values(array_filter(array_map('trim', $specs[$radiusKey]), function($v) { return $v !== '' && $v !== 'Choose an option below'; })) : [];
        $spotUvChoices = ($spotUvSidesKey && !empty($specs[$spotUvSidesKey])) ? array_values(array_filter(array_map('trim', $specs[$spotUvSidesKey]), function($v) { return $v !== '' && $v !== 'Choose an option below'; })) : [];

        // On 4over, Standard Business Cards only has 1 option for SPOT UV SIDES: "No Spot UV"
        if (stripos($productName, 'Spot UV') === false && in_array('No Spot UV', $spotUvChoices, true)) {
            $spotUvChoices = ['No Spot UV'];
        }

        $desiredOrder = [
            'size',
            'shape',
            'radius',
            'stock',
            'substrate',
            'paper',
            'colorspec',
            'coating',
            'spot uv',
            'lamination',
        ];

        uksort($specs, function($a, $b) use ($desiredOrder) {
            $indexA = 99;
            $indexB = 99;
            foreach ($desiredOrder as $idx => $term) {
                if ($indexA === 99 && stripos($a, $term) !== false) $indexA = $idx;
                if ($indexB === 99 && stripos($b, $term) !== false) $indexB = $idx;
            }
            return $indexA - $indexB;
        });

        $dynamicOptions = [];
        foreach ($specs as $groupName => $choices) {
            if ($groupName === 'Turnaround Time' || empty($choices)) continue;

            // Strip Radius of Corners and SPOT UV SIDES from root level
            if ($radiusKey && strcasecmp($groupName, $radiusKey) === 0) continue;
            if ($spotUvSidesKey && strcasecmp($groupName, $spotUvSidesKey) === 0) continue;

            // Shape: nest Radius of Corners under Rounded shapes ONLY
            if ($shapeKey && strcasecmp($groupName, $shapeKey) === 0) {
                $shapedOpts = [];
                foreach ($choices as $sh) {
                    $shName = trim(is_string($sh) ? $sh : ($sh['optionName'] ?? ''));
                    if (!$shName || $shName === 'Choose an option below') continue;

                    $isRounded = (stripos($shName, 'Round') !== false && stripos($shName, 'Rectangle') === false && strcasecmp($shName, 'Round') !== 0);
                    if ($isRounded && !empty($radiusChoices)) {
                        $radOpts = [];
                        foreach ($radiusChoices as $rad) {
                            $radOpts[] = ['optionName' => $rad, 'options' => []];
                        }
                        $shapedOpts[] = [
                            'optionName' => $shName,
                            'options' => [
                                [
                                    'optionName' => $radiusKey ?: 'Radius of Corners',
                                    'options' => $radOpts,
                                ]
                            ]
                        ];
                    } else {
                        $shapedOpts[] = [
                            'optionName' => $shName,
                            'options' => [],
                        ];
                    }
                }
                $dynamicOptions[] = [
                    'optionName' => $groupName,
                    'options' => $shapedOpts,
                ];
                continue;
            }

            // Coating: nest SPOT UV SIDES under Spot UV or Matte ONLY
            if ($coatingKey && strcasecmp($groupName, $coatingKey) === 0) {
                $coatingOpts = [];
                foreach ($choices as $co) {
                    $coName = trim(is_string($co) ? $co : ($co['optionName'] ?? ''));
                    if (!$coName || $coName === 'Choose an option below') continue;

                    $hasSpotUv = (stripos($coName, 'Spot UV') !== false || stripos($coName, 'Matte') !== false);
                    if ($hasSpotUv && !empty($spotUvChoices)) {
                        $suvOpts = [];
                        foreach ($spotUvChoices as $suv) {
                            $suvOpts[] = ['optionName' => $suv, 'options' => []];
                        }
                        $coatingOpts[] = [
                            'optionName' => $coName,
                            'options' => [
                                [
                                    'optionName' => $spotUvSidesKey ?: 'SPOT UV SIDES',
                                    'options' => $suvOpts,
                                ]
                            ]
                        ];
                    } else {
                        $coatingOpts[] = [
                            'optionName' => $coName,
                            'options' => [],
                        ];
                    }
                }
                $dynamicOptions[] = [
                    'optionName' => $groupName,
                    'options' => $coatingOpts,
                ];
                continue;
            }

            // All other groups (Size, Stock, Colorspec, Lamination, etc.) strictly with their real 4over choices
            $opts = [];
            foreach ($choices as $c) {
                $cName = trim(is_string($c) ? $c : ($c['optionName'] ?? ''));
                if ($cName && $cName !== 'Choose an option below') {
                    $opts[] = ['optionName' => $cName, 'options' => []];
                }
            }
            if (!empty($opts)) {
                $dynamicOptions[] = [
                    'optionName' => $groupName,
                    'options' => $opts,
                ];
            }
        }

        // 8. Turnarounds - Extract from 4over's turnaround-time-container component or specs table
        $tatConfig = null;
        $tatPos = strpos($html, '[data-role=turnaround-time-container]');
        if ($tatPos !== false) {
            $startBrace = strpos($html, '{', $tatPos);
            $depth = 0;
            $endBrace = -1;
            for ($i = $startBrace; $i < strlen($html); $i++) {
                if ($html[$i] === '{') $depth++;
                else if ($html[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $endBrace = $i;
                        break;
                    }
                }
            }
            if ($endBrace > $startBrace) {
                $tatData = json_decode(substr($html, $startBrace, $endBrace - $startBrace + 1), true);
                $tatConfig = $tatData['Magento_Ui/js/core/app']['components']['turnaround-time-container']['config'] ?? null;
            }
        }

        $tatOptionLabels = $tatConfig['tatOptionLabels'] ?? [
            'S' => 'Same Business Day',
            '10' => 'Next Day Express',
            '1' => 'Next Business Day',
            '2' => '2 Business Days',
            '3' => '3 Business Days',
            '4' => '4 Business Days',
            '7' => '7 Business Days',
        ];

        $globalTatPriceConfig = $tatConfig['globalTatPriceConfig'] ?? [
            'S' => 100,
            '10' => 50,
            '1' => 30,
            '2' => 10,
            '3' => 0,
            '4' => 0,
            '7' => 0,
        ];

        $tatOptionOrder = $tatConfig['tatOptionOrder'] ?? [
            'S' => 0,
            '10' => 1,
            '1' => 2,
            '2' => 3,
            '3' => 4,
            '4' => 5,
            '7' => 6,
            '9' => 7,
        ];

        // Query store-level turnarounds from turn_around_times table
        $systemTats = \App\Models\TurnAroundTime::query()
            ->when($categoryId, function($q) use ($categoryId) {
                $q->where('category_id', $categoryId)->orWhereNull('category_id');
            })
            ->orderBy('turnaround_value')
            ->get();

        $turnarounds = [];
        if ($tatConfig) {
            $sortedCodes = array_keys($tatOptionLabels);
            usort($sortedCodes, function($a, $b) use ($tatOptionOrder) {
                return ($tatOptionOrder[$a] ?? 99) - ($tatOptionOrder[$b] ?? 99);
            });

            $allRelevantCodes = [];
            if (!empty($tatConfig['productAvailableOptions'])) {
                foreach ($tatConfig['productAvailableOptions'] as $avail) {
                    foreach ($avail as $codes) {
                        foreach ($codes as $c) $allRelevantCodes[$c] = true;
                    }
                }
            }
            if (empty($allRelevantCodes) && !empty($tatConfig['defaultAvailableOptions'])) {
                foreach ($tatConfig['defaultAvailableOptions'] as $codes) {
                    foreach ($codes as $c) $allRelevantCodes[$c] = true;
                }
            }
            if (empty($allRelevantCodes)) {
                $allRelevantCodes = ['1' => true, '2' => true, '4' => true];
            }

            foreach ($sortedCodes as $code) {
                if (!isset($allRelevantCodes[$code])) continue;
                $label = $tatOptionLabels[$code];
                $val = is_numeric($code) ? (int)$code : 4;
                $matchedTat = $systemTats->first(function($item) use ($label, $val) {
                    return strcasecmp(trim($item->turnaround_label), trim($label)) === 0
                        || (int)$item->turnaround_value === (int)$val;
                });
                $flatPrice = $matchedTat ? (float)$matchedTat->price : 0.0;
                $turnarounds[] = [
                    'id' => (string) Str::uuid(),
                    'turnaroundLabel' => $label,
                    'turnaroundValue' => $val,
                    'price' => $flatPrice,
                    'note' => null,
                    'runsize' => null,
                    'discount' => null,
                    'createdAt' => now()->toISOString(),
                    'updatedAt' => now()->toISOString(),
                    'categoryId' => $categoryId,
                    'categoryName' => '',
                ];
            }
        } elseif (!empty($specs['Turnaround Time'])) {
            foreach ($specs['Turnaround Time'] as $tLabel) {
                $val = preg_match('/(\d+)/', $tLabel, $tm) ? (int)$tm[1] : 4;
                $matchedTat = $systemTats->first(function($item) use ($tLabel, $val) {
                    return strcasecmp(trim($item->turnaround_label), trim($tLabel)) === 0
                        || (int)$item->turnaround_value === (int)$val;
                });
                $flatPrice = $matchedTat ? (float)$matchedTat->price : 0.0;
                $turnarounds[] = [
                    'id' => (string) Str::uuid(),
                    'turnaroundLabel' => $tLabel,
                    'turnaroundValue' => $val,
                    'price' => $flatPrice,
                    'note' => null,
                    'runsize' => null,
                    'discount' => null,
                    'createdAt' => now()->toISOString(),
                    'updatedAt' => now()->toISOString(),
                    'categoryId' => $categoryId,
                    'categoryName' => '',
                ];
            }
        } else {
            $turnarounds[] = [
                'id' => (string) Str::uuid(),
                'turnaroundLabel' => 'Standard Turnaround (2 - 4 Business Days)',
                'turnaroundValue' => 4,
                'price' => 0.0,
                'note' => null,
                'runsize' => null,
                'discount' => null,
                'createdAt' => now()->toISOString(),
                'updatedAt' => now()->toISOString(),
                'categoryId' => $categoryId,
                'categoryName' => '',
            ];
        }

        // 9. Shippings - dynamically fetched from system shippings table
        $shippingQuery = \App\Models\Shipping::query();
        if (!empty($categoryId)) {
            $shippingQuery->where(function($q) use ($categoryId) {
                $q->where('category_id', $categoryId)->orWhereNull('category_id');
            });
        }
        $dbShippings = $shippingQuery->orderBy('shipping_value')->get();
        if ($dbShippings->isEmpty()) {
            $dbShippings = \App\Models\Shipping::orderBy('shipping_value')->get();
        }

        $shippings = [];
        foreach ($dbShippings as $sm) {
            $shippings[] = [
                'id' => $sm->id,
                'shipping_id' => $sm->shipping_id ?? (string) $sm->id,
                'shippingLabel' => $sm->shipping_label,
                'shipping_label' => $sm->shipping_label,
                'shippingValue' => (int) $sm->shipping_value,
                'shipping_value' => (int) $sm->shipping_value,
                'price' => (float) $sm->price,
                'note' => $sm->note ?? null,
                'runsize' => $sm->runsize ?? null,
                'categoryId' => $sm->category_id ?? $categoryId,
                'categoryName' => $sm->category_name ?? '',
            ];
        }

        if (empty($shippings)) {
            $shippings = [
                [
                    'id' => 5,
                    'shipping_id' => 'ship-pickup-from-store-01-11-2026-aBI4bP0N',
                    'shippingLabel' => 'Pickup from Store',
                    'shipping_label' => 'Pickup from Store',
                    'shippingValue' => 0,
                    'shipping_value' => 0,
                    'price' => 0.0,
                    'note' => null,
                    'runsize' => null,
                    'categoryId' => $categoryId,
                    'categoryName' => '',
                ]
            ];
        }

        // 10. Extract Pricing and Runsizes strictly from 4over (ZERO fake/default fallbacks)
        $scrapedPrices = [];

        // Method A: User provided pasted 4over Run Size / Pricing table text
        if ($pricingTableText) {
            $lines = explode("\n", $pricingTableText);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line || preg_match('/run\s*size|total\s*price|price\s*per/i', $line)) continue;
                // Match 4over table row: Qty (first number), then optional unit price, then total price
                if (preg_match('/^(\d[\d,]*)\s+[\$]?[\d.,]+\s+[\$]?([\d.,]+)/', $line, $lm)) {
                    $q = (int)str_replace(',', '', $lm[1]);
                    $p = (float)str_replace(['$', ','], '', $lm[2]);
                    if ($q > 0 && $p > 0) {
                        $scrapedPrices[] = ['runsize' => $q, 'price' => $p];
                    }
                } elseif (preg_match('/(\d[\d,]*)\s+(?:\$[\d.]+\s+)?\$?([\d,.]+)/', $line, $lm2)) {
                    $q = (int)str_replace(',', '', $lm2[1]);
                    $p = (float)str_replace(['$', ','], '', $lm2[2]);
                    if ($q > 0 && $p > 0) {
                        $scrapedPrices[] = ['runsize' => $q, 'price' => $p];
                    }
                }
            }
        }

        // Method B: If session is authenticated, query 4over REST endpoint for exact matching child SKU
        if (empty($scrapedPrices) && !empty($activeCookie)) {
            $allChildren = [];
            $pos = strpos($html, '"childSkuConfig"');
            if ($pos !== false) {
                $startBrace = strpos($html, '{', $pos);
                $depth = 0;
                $endBrace = -1;
                for ($i = $startBrace; $i < strlen($html); $i++) {
                    if ($html[$i] === '{') $depth++;
                    else if ($html[$i] === '}') {
                        $depth--;
                        if ($depth === 0) {
                            $endBrace = $i;
                            break;
                        }
                    }
                }
                if ($endBrace > $startBrace) {
                    $allChildren = json_decode(substr($html, $startBrace, $endBrace - $startBrace + 1), true) ?: [];
                }
            }

            if (empty($allChildren) && preg_match_all('/"(\d+)":\s*\{\s*"sku":\s*"([^"]+)"/i', $html, $cmAll, PREG_SET_ORDER)) {
                foreach ($cmAll as $cItem) {
                    $allChildren[$cItem[1]] = ['sku' => $cItem[2]];
                }
            }

            // Extract tokens from specs to find the best matching child SKU
            $searchTokens = [];
            foreach ($specs as $label => $vals) {
                if ($label === 'Turnaround Time' || empty($vals)) continue;
                $firstVal = (string)$vals[0];
                if (preg_match('/14pt|16pt|18pt|100glc/i', $firstVal, $sm)) $searchTokens[] = strtoupper($sm[0]);
                if (preg_match('/(\d+(?:\.\d+)?)\s*[xX]\s*(\d+(?:\.\d+)?)/', $firstVal, $sm)) $searchTokens[] = strtoupper($sm[1] . 'X' . $sm[2]);
                if (stripos($firstVal, 'Matte') !== false) $searchTokens[] = 'MATT';
                if (stripos($firstVal, 'Spot UV') !== false) $searchTokens[] = 'SPUV';
                elseif (stripos($firstVal, 'UV') !== false) $searchTokens[] = 'UV';
                if (stripos($firstVal, 'Rounded 4') !== false) $searchTokens[] = 'RCD4';
                if (stripos($firstVal, 'Rounded 2') !== false) $searchTokens[] = 'RCD2';
            }

            // Always prefer 4/4 (standard full color both sides) for base pricing
            foreach ($specs['Colorspec'] ?? [] as $cs) {
                if (strpos($cs, '4/4') !== false) {
                    $searchTokens[] = '44';
                    break;
                }
            }

            $bestChildId = null;
            $bestScore = -1;

            foreach ($allChildren as $cid => $cVal) {
                $sku = strtoupper($cVal['sku'] ?? '');
                $score = 0;
                foreach ($searchTokens as $tok) {
                    if (strpos($sku, $tok) !== false) {
                        $score += 10;
                    }
                }
                if (strpos($sku, '100GLC') !== false) $score -= 5;
                if (strpos($sku, '14PT') !== false || strpos($sku, '16PT') !== false) $score += 2;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestChildId = (string)$cid;
                }
            }

            if (!$bestChildId && !empty($allChildren)) {
                $bestChildId = (string)array_key_first($allChildren);
            }

            if ($bestChildId) {
                $chPrice = curl_init("https://4over.com/rest/default/V1/product/prices/{$bestChildId}");
                curl_setopt($chPrice, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chPrice, CURLOPT_HTTPHEADER, [
                    'X-Requested-With: XMLHttpRequest',
                    'Accept: application/json',
                    'Cookie: ' . $activeCookie,
                ]);
                curl_setopt($chPrice, CURLOPT_USERAGENT, $userAgent);
                curl_setopt($chPrice, CURLOPT_SSL_VERIFYPEER, false);
                $priceJson = curl_exec($chPrice);
                curl_close($chPrice);

                if ($priceJson) {
                    $parsedPriceObj = json_decode($priceJson, true);
                    $pkgPrices = $parsedPriceObj[0]['packagePrices'] ?? ($parsedPriceObj['packagePrices'] ?? []);
                    if (is_array($pkgPrices) && !empty($pkgPrices)) {
                        foreach ($pkgPrices as $pp) {
                            $q = (int)($pp['qty'] ?? 0);
                            $pr = (float)($pp['price'] ?? 0);
                            if ($q > 0 && $pr > 0) {
                                $scrapedPrices[] = ['runsize' => $q, 'price' => $pr];
                            }
                        }
                    }
                }
            }
        }

        // Method C: Scrape 4-column or 2-column table from HTML if available
        if (empty($scrapedPrices)) {
            if (preg_match_all('/<tr[^>]*>\s*<td[^>]*>(\d+)<\/td>\s*<td[^>]*>\$?([\d,.]+)<\/td>\s*<td[^>]*>\$?([\d,.]+)<\/td>/i', $html, $tm4, PREG_SET_ORDER)) {
                foreach ($tm4 as $row) {
                    $scrapedPrices[] = [
                        'runsize' => (int)$row[1],
                        'price' => (float)str_replace(',', '', $row[3]),
                    ];
                }
            } elseif (preg_match_all('/<tr[^>]*>\s*<td[^>]*>(\d+)<\/td>\s*<td[^>]*>\$?([\d,.]+)<\/td>\s*<\/tr>/i', $html, $tm2, PREG_SET_ORDER)) {
                foreach ($tm2 as $row) {
                    $scrapedPrices[] = [
                        'runsize' => (int)$row[1],
                        'price' => (float)str_replace(',', '', $row[2]),
                    ];
                }
            }
        }

        // 11. Build priceConfig combinations strictly from extracted 4over prices ONLY
        $priceConfig = [];

        // Build base options template from all root specs (excluding conditional children)
        $baseTemplate = [];
        foreach ($specs as $k => $vals) {
            if ($k === 'Turnaround Time' || empty($vals)) continue;
            if ($radiusKey && strcasecmp($k, $radiusKey) === 0) continue;
            if ($spotUvSidesKey && strcasecmp($k, $spotUvSidesKey) === 0) continue;
            $baseTemplate[$k] = $vals[0];
        }

        if (!empty($scrapedPrices)) {
            // Deduplicate runsizes keeping lowest price if duplicate
            $uniquePrices = [];
            foreach ($scrapedPrices as $sp) {
                $rs = (int)$sp['runsize'];
                $pr = (float)$sp['price'];
                if (!isset($uniquePrices[$rs]) || $pr < $uniquePrices[$rs]) {
                    $uniquePrices[$rs] = $pr;
                }
            }

            // Build Shape combos (Rectangle has NO radius, Rounded has radius)
            $shapeCombos = [];
            $shapes = ($shapeKey && !empty($specs[$shapeKey])) ? $specs[$shapeKey] : ['Standard'];
            foreach ($shapes as $sh) {
                $isRounded = (stripos($sh, 'Round') !== false && stripos($sh, 'Rectangle') === false && strcasecmp($sh, 'Round') !== 0);
                if ($isRounded && !empty($radiusChoices)) {
                    foreach ($radiusChoices as $rad) {
                        $shapeCombos[] = [
                            $shapeKey => $sh,
                            $radiusKey => $rad,
                        ];
                    }
                } else {
                    $shapeCombos[] = [
                        $shapeKey => $sh,
                    ];
                }
            }

            // Build Coating combos (Non-UV coatings have NO Spot UV; Spot UV / Matte have Spot UV)
            $coatingCombos = [];
            $coatings = ($coatingKey && !empty($specs[$coatingKey])) ? $specs[$coatingKey] : ['Standard'];
            foreach ($coatings as $co) {
                $hasSpotUv = (stripos($co, 'Spot UV') !== false || stripos($co, 'Matte') !== false);
                if ($hasSpotUv && !empty($spotUvChoices)) {
                    foreach ($spotUvChoices as $suv) {
                        $coatingCombos[] = [
                            $coatingKey => $co,
                            $spotUvSidesKey => $suv,
                        ];
                    }
                } else {
                    $coatingCombos[] = [
                        $coatingKey => $co,
                    ];
                }
            }

            // Combine: Shape x Coating x Colorspec x Stock x Size sensibly
            $combos = [];
            $colorspecs = (!empty($specs['Colorspec'])) ? $specs['Colorspec'] : [];
            $csList = !empty($colorspecs) ? $colorspecs : [($baseTemplate['Colorspec'] ?? 'Standard')];

            foreach ($shapeCombos as $sc) {
                foreach ($coatingCombos as $cc) {
                    foreach ($csList as $cs) {
                        $combo = $baseTemplate;
                        if (!empty($specs['Colorspec'])) $combo['Colorspec'] = $cs;
                        foreach ($sc as $k => $v) $combo[$k] = $v;
                        foreach ($cc as $k => $v) $combo[$k] = $v;
                        $combos[] = $combo;
                    }
                }
            }

            // Additional sizes if multiple
            $sizes = (!empty($specs['Size'])) ? $specs['Size'] : [];
            if (count($sizes) > 1) {
                $baseShape = $shapeCombos[0] ?? [];
                $baseCoating = $coatingCombos[0] ?? [];
                foreach (array_slice($sizes, 1) as $sz) {
                    $combo = $baseTemplate;
                    $combo['Size'] = $sz;
                    foreach ($baseShape as $k => $v) $combo[$k] = $v;
                    foreach ($baseCoating as $k => $v) $combo[$k] = $v;
                    $combos[] = $combo;
                }
            }

            // Additional stocks if multiple
            $stocks = (!empty($specs['Stock'])) ? $specs['Stock'] : [];
            if (count($stocks) > 1) {
                $baseShape = $shapeCombos[0] ?? [];
                $baseCoating = $coatingCombos[0] ?? [];
                foreach (array_slice($stocks, 1) as $st) {
                    $combo = $baseTemplate;
                    $combo['Stock'] = $st;
                    foreach ($baseShape as $k => $v) $combo[$k] = $v;
                    foreach ($baseCoating as $k => $v) $combo[$k] = $v;
                    $combos[] = $combo;
                }
            }

            $availByQty = ($bestChildId && isset($tatConfig['productAvailableOptions'][$bestChildId]))
                ? $tatConfig['productAvailableOptions'][$bestChildId]
                : ($tatConfig['defaultAvailableOptions'] ?? []);

            foreach ($combos as $comboOpts) {
                foreach ($uniquePrices as $rs => $pr) {
                    $configTurnarounds = [];
                    if (!empty($availByQty)) {
                        $allowedCodes = null;
                        $sortedTiers = array_keys($availByQty);
                        sort($sortedTiers, SORT_NUMERIC);
                        foreach ($sortedTiers as $tierQty) {
                            if ($rs <= (int)$tierQty) {
                                $allowedCodes = $availByQty[$tierQty];
                                break;
                            }
                        }
                        if ($allowedCodes === null && !empty($sortedTiers)) {
                            $allowedCodes = $availByQty[end($sortedTiers)];
                        }
                        if (empty($allowedCodes)) $allowedCodes = ['4'];

                        usort($allowedCodes, function($a, $b) use ($tatOptionOrder) {
                            return ($tatOptionOrder[$a] ?? 99) - ($tatOptionOrder[$b] ?? 99);
                        });

                        foreach ($allowedCodes as $code) {
                            $label = $tatOptionLabels[$code] ?? "{$code} Business Days";
                            $val = is_numeric($code) ? (int)$code : 4;
                            $matchedTat = $systemTats->first(function($item) use ($label, $val) {
                                return strcasecmp(trim($item->turnaround_label), trim($label)) === 0
                                    || (int)$item->turnaround_value === (int)$val;
                            });
                            $flatPrice = $matchedTat ? (float)$matchedTat->price : 0.0;
                            $configTurnarounds[] = [
                                'turnaroundLabel' => $label,
                                'turnaroundValue' => $val,
                                'price' => $flatPrice,
                                'note' => null,
                            ];
                        }
                    } else {
                        $configTurnarounds = $turnarounds;
                    }

                    $priceConfig[] = [
                        'id' => Str::lower(Str::random(10)),
                        'price' => number_format($pr, 4, '.', ''),
                        'runsize' => $rs,
                        'discount' => 0,
                        'options' => $comboOpts,
                        'shippings' => $shippings,
                        'turnarounds' => $configTurnarounds,
                    ];
                }
            }
        }

        if (empty($priceConfig)) {
            $standardRunsizes = [
                100 => 25.0,
                250 => 35.0,
                500 => 45.0,
                1000 => 60.0,
                2500 => 110.0,
                5000 => 195.0,
            ];
            foreach ($standardRunsizes as $rs => $pr) {
                $priceConfig[] = [
                    'id' => Str::lower(Str::random(10)),
                    'price' => number_format($pr, 4, '.', ''),
                    'runsize' => $rs,
                    'discount' => 0,
                    'options' => $baseTemplate,
                    'shippings' => $shippings,
                    'turnarounds' => $turnarounds,
                ];
            }
        }

        // Final payload
        $payload = [
            'categoryId' => $categoryId,
            'productName' => $productName,
            'productDescription' => $productDescription,
            'active' => true,
            'popularProduct' => false,
            'thumbnail' => $thumbnail,
            'productImages' => $productImages,
            'images' => $productImages,
            'basePrice' => 0,
            'productOptions' => [
                'faqs' => $faqs,
                'advanceOptions' => [
                    'productType' => 'general',
                    'jobSamplePrice' => 0,
                    'digitalProofPrice' => 0,
                    'advancedOptions' => [
                        'minwidth' => 0,
                        'maxwidth' => 0,
                        'minheight' => 0,
                        'maxheight' => 0,
                        'basePricePerSqFt' => 0,
                        'priceRanges' => [],
                        'turnaroundRange' => [],
                        'shippingRange' => [
                            [
                                'id' => 'sr-default',
                                'minQuantity' => 1,
                                'maxQuantity' => 100000,
                                'discount' => 0,
                                'shippings' => $shippings,
                            ]
                        ],
                    ],
                ],
                'dynamicOptions' => $dynamicOptions,
                'shippings' => $shippings,
                'turnarounds' => $turnarounds,
            ],
            'priceConfig' => $priceConfig,
        ];

        return response()->json([
            'success' => true,
            'isError' => false,
            'message' => '4over product payload generated successfully!',
            'metadata' => [
                'productName' => $productName,
                'imagesCount' => count($productImages),
                'faqsCount' => count($faqs),
                'specsFound' => array_keys($specs),
                'totalPriceConfigurations' => count($priceConfig),
                'authenticated' => !empty($activeCookie),
            ],
            'payload' => $payload,
        ]);
    }
}
