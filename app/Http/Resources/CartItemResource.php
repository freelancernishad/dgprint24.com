<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Expects $this to be either:
     *  - the Laravel model for single cart item (attributes like price_breakdown, shippings, delivery_address, options etc.)
     *  - or an array with same keys.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Helpers for safe reads
        $get = function ($path, $default = null) {
            $keys = is_string($path) ? explode('.', $path) : $path;
            $value = $this->resource;
            foreach ($keys as $k) {
                if (is_array($value) && array_key_exists($k, $value)) $value = $value[$k];
                elseif (is_object($value) && isset($value->{$k})) $value = $value->{$k};
                else return $default;
            }
            return $value;
        };

        // price sources
        $priceBreakdown = $get('price_breakdown', []);
        $basePriceAmount = $get('price_breakdown.base_price.amount', $get('price_breakdown.base_price', null));
        if ($basePriceAmount === null) {
            // some payloads place amount directly
            $basePriceAmount = $get('price_breakdown.subtotal_before_tax', 0);
        }

        // product / options
        $product = $get('product', []);
        $options = $get('options', []);

        // Convert dynamic options to label/value list
        $formattedOptions = [];

        if (!empty($options) && is_array($options)) {
            foreach ($options as $label => $value) {
                $formattedOptions[] = [
                    'label' => (string) $label,
                    'value' => (string) $value
                ];
            }
        }



        // shipment(s)
        $rawShip = $get('shippings', null); // single object
        $shipmentsArr = $get('shipments', null); // maybe array
        $deliveryAddress = $get('delivery_address', []);

        // normalize shipments to array
        $shipmentsSource = [];
        if (!empty($shipmentsArr) && is_array($shipmentsArr)) {
            $shipmentsSource = $shipmentsArr;
        } elseif (!empty($rawShip)) {
            $shipmentsSource = [$rawShip];
        } elseif ($get('payload.shipments')) {
            $shipmentsSource = $get('payload.shipments', []);
        }

        $shipments = array_map(function ($s) use ($deliveryAddress) {
            // address may live in s.address or in top-level delivery_address
            $addr = $s['address'] ?? $s->address ?? null;
            if (!$addr) {
                $addr = $deliveryAddress ?? [];
            }

            $name = trim(
                ($addr['first_name'] ?? $addr->first_name ?? ($addr['firstName'] ?? '') )
                . ' '
                . ($addr['last_name'] ?? $addr->last_name ?? ($addr['lastName'] ?? '') )
            );

            return [
                'id' => isset($s['id']) ? (string)$s['id'] : (string)($s['shipping_id'] ?? ($s['shippingId'] ?? '')),
                'setCount' => (int)($s['set_count'] ?? (isset($s['sets']) ? count($s['sets']) : ($s['setCount'] ?? 0))),
                'shippingAddress' => [
                    'name' => $name ?: ($addr['first_name'] ?? $addr['firstName'] ?? '') . ' ' . ($addr['last_name'] ?? $addr['lastName'] ?? ''),
                    'street' => $addr['local_address'] ?? $addr['street'] ?? '',
                    'city' => $addr['city'] ?? '',
                    'state' => $addr['state'] ?? '',
                    'zip' => $addr['zip_code'] ?? $addr['zip'] ?? '',
                    'company' => $addr['company'] ?? '',
                    'phone' => $addr['phone_number'] ?? $addr['phone'] ?? '',
                ],
                'shippingMethod' => [
                    'name' => $s['shipping_label'] ?? $s->shipping_label ?? ($s['shippingMethod']['name'] ?? ($s['shippingMethod'] ?? '')),
                    'price' => (float) ($s['price'] ?? ($s['shipping_price'] ?? ($s['shippingPrice'] ?? 0))),
                    'productionFacility' => $s['productionFacility'] ?? 'Default Facility',
                ],
                'setNames' => $s['sets'] ?? $s['setNames'] ?? [],
                'uploadedFiles' => $s['uploadedFiles'] ?? []
            ];
        }, $shipmentsSource ?: [ // if no shipment found, build a single shipment from top-level delivery_address
            [
                'id' => $get('shippings.id', $get('shippings.shipping_id', '')),
                'sets' => $get('sets', []),
                'price' => $get('shippings.price', 0)
            ]
        ]);

        // Build final product object (use options mapping)
        $finalProduct = [
            'product_name' => $product['product_name'] ?? $product->product_name ?? $get('product.product_name') ?? $get('product_name'),
            'product_type' => $product['product_type'] ?? $product->product_type ?? $get('product.product_type') ?? $get('product.type'),
            'images' => $product['images'] ?? $product->images ?? [],
            'category' => [
                'id' => $product['category_id'] ?? $get('product.category_id') ?? ($product['category']['id'] ?? $product->category->id ?? null),
                'name' => $product['category']['name'] ?? $product->category->name ?? null
            ],

            'options' => $formattedOptions,

            'runSize' => (int) ($this->resource['quantity'] ?? $this->resource->quantity ?? $product['runSize'] ?? $product->selectedRunSize ?? 0),
            'turnaroundTime' => $get('price_breakdown.base_price.details.selected_turnaround.turnaround_label') ?? $get('turnarounds.turnaround_label') ?? $get('turnaround.turnaround_label') ?? null,
            'projectName' => $product['product_name'] ?? $product->product_name ?? ($this->resource['projectName'] ?? $this->resource->projectName ?? ($product['projectName'] ?? null)),
        ];

        // pricing block
        $pricing = [
            'baseSubtotal' => (float) ($basePriceAmount ?? 0),
            'subtotal' => (float) ($get('price_breakdown.base_price.amount') ?? $basePriceAmount ?? 0),
            'digitalProofs' => (float) ($get('price_breakdown.extras.digital_proof_price') ?? $get('product.digital_proof_price') ?? 0),
            'jobSample' => (float) ($get('price_breakdown.extras.job_sample_price') ?? $get('product.job_sample_price') ?? 0),
            'totalShipping' => (float) ($get('price_breakdown.shipping.amount') ?? $get('shippings.price') ?? 0),
            'totalTax' => (float) ($get('price_breakdown.tax.tax_amount') ?? $get('tax_price') ?? 0),
            'total' => (float) ($get('price_breakdown.total_price') ?? $get('price_at_time') ?? $get('totalPrice') ?? 0)
        ];

        return [
            'id' => (string)($this->resource['id'] ?? $this->resource->id ?? ''),
            'totalPrice' => $pricing['total'],
            'product' => $finalProduct,
            'shipments' => $shipments,
            'pricing' => $pricing,
            // 'raw' => [
            //     'original_payload' => $this->resource
            // ]
        ];
    }
}
