<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'categoryId' => $this->category_id,
            'productName' => $this->product_name,
            'productDescription' => $this->product_description,
            'productOptions' => [
                'faqs' => $this->whenLoaded('faqs', function () {
                    return collect($this->faqs)->map(function ($faq) {
                        return [
                            'id' => $faq->id,
                            'question' => $faq->question,
                            'answer' => $faq->answer,
                            'sort_order' => $faq->sort_order,
                        ];
                    });
                }, []),
                'advanceOptions' => [
                    'productType' => $this->product_type,
                    'advancedOptions' => [
                        'minwidth' => $this->whenLoaded('dimensionPricing', function () {
                            return $this->dimensionPricing->minwidth;
                        }, 0),
                        'maxwidth' => $this->whenLoaded('dimensionPricing', function () {
                            return $this->dimensionPricing->maxwidth;
                        }, 0),
                        'minheight' => $this->whenLoaded('dimensionPricing', function () {
                            return $this->dimensionPricing->minheight;
                        }, 0),
                        'maxheight' => $this->whenLoaded('dimensionPricing', function () {
                            return $this->dimensionPricing->maxheight;
                        }, 0),
                        'basePricePerSqFt' => $this->whenLoaded('dimensionPricing', function () {
                            return $this->dimensionPricing->basePricePerSqFt;
                        }, 0),
                        'priceRanges' => $this->whenLoaded('priceRanges', function () {
                            return collect($this->priceRanges)->map(function ($range) {
                                return [
                                    'id' => $range->id,
                                    'minQuantity' => $range->min_quantity,
                                    'maxQuantity' => $range->max_quantity,
                                    'pricePerSqFt' => $range->price_per_sq_ft,
                                ];
                            });
                        }, []),
                        'turnaroundRange' => $this->whenLoaded('turnaroundRanges', function () {
                            return collect($this->turnaroundRanges)->map(function ($range) {
                                return [
                                    'id' => $range->id,
                                    'minQuantity' => $range->min_quantity,
                                    'maxQuantity' => $range->max_quantity,
                                    'discount' => $range->discount,
                                    'turnarounds' => $this->whenLoaded('turnaroundRanges.turnarounds', function () use ($range) {
                                        return collect($range->turnarounds)->map(function ($turnaround) {
                                            return [
                                                'id' => $turnaround->id,
                                                'turnaround_id' => $turnaround->turnaround_id,
                                                'name' => $turnaround->name,
                                                'categoryName' => $turnaround->category_name,
                                                'categoryId' => $turnaround->category_id,
                                                'turnaroundLabel' => $turnaround->turnaround_label,
                                                'turnaroundValue' => $turnaround->turnaround_value,
                                                'price' => $turnaround->price,
                                                'discount' => $turnaround->discount,
                                                'note' => $turnaround->note,
                                                'runsize' => $turnaround->runsize,
                                                'createdAt' => $turnaround->created_at,
                                                'updatedAt' => $turnaround->updated_at,
                                            ];
                                        });
                                    }, []),
                                ];
                            });
                        }, []),
                        'shippingRange' => $this->whenLoaded('shippingRanges', function () {
                            return collect($this->shippingRanges)->map(function ($range) {
                                return [
                                    'id' => $range->id,
                                    'minQuantity' => $range->min_quantity,
                                    'maxQuantity' => $range->max_quantity,
                                    'discount' => $range->discount,
                                    'shippings' => $this->whenLoaded('shippingRanges.shippings', function () use ($range) {
                                        return collect($range->shippings)->map(function ($shipping) {
                                            return [
                                                'id' => $shipping->id,
                                                'shipping_id' => $shipping->shipping_id,
                                                'categoryName' => $shipping->category_name,
                                                'categoryId' => $shipping->category_id,
                                                'shippingLabel' => $shipping->shipping_label,
                                                'shippingValue' => $shipping->shipping_value,
                                                'price' => $shipping->price,
                                                'note' => $shipping->note,
                                                'runsize' => $shipping->runsize,
                                                'createdAt' => $shipping->created_at,
                                                'updatedAt' => $shipping->updated_at,
                                            ];
                                        });
                                    }, []),
                                ];
                            });
                        }, []),
                    ],
                    'jobSamplePrice' => $this->job_sample_price,
                    'digitalProofPrice' => $this->digital_proof_price,
                ],
                'dynamicOptions' => $this->dynamicOptions ?? [],
                'extraDynamicOptions' => $this->extraDynamicOptions ?? [],
            ],
            'priceConfig' => $this->whenLoaded('priceConfigurations', function () {
                return collect($this->priceConfigurations)->map(function ($config) {
                    return [
                        'id' => $config->id,
                        'options' => is_array($config->options) ?
                            collect($config->options)->mapWithKeys(function ($value, $key) {
                                return [$key => ['selected' => $value]];
                            }) : [],
                        'runsize' => $config->runsize,
                        'price' => $config->price,
                        'discount' => $config->discount,
                        'shippings' => $this->whenLoaded('priceConfigurations.shippings', function () use ($config) {
                            return collect($config->shippings)->map(function ($shipping) {
                                return [
                                    'id' => $shipping->id,
                                    'shipping_id' => $shipping->shipping_id,
                                    'categoryName' => $shipping->category_name ?? '',
                                    'categoryId' => $shipping->category_id ?? '',
                                    'shippingLabel' => $shipping->shippingLabel,
                                    'shippingValue' => $shipping->shippingValue,
                                    'price' => $shipping->price,
                                    'note' => $shipping->note ?? '',
                                    'runsize' => $shipping->runsize,
                                    'createdAt' => $shipping->created_at,
                                    'updatedAt' => $shipping->updated_at,
                                ];
                            });
                        }, []),
                        'turnarounds' => $this->whenLoaded('priceConfigurations.turnarounds', function () use ($config) {
                            return collect($config->turnarounds)->map(function ($turnaround) {
                                return [
                                    'id' => $turnaround->id,
                                    'turnaround_id' => $turnaround->turnaround_id,
                                    'categoryName' => $turnaround->category_name ?? '',
                                    'categoryId' => $turnaround->category_id ?? '',
                                    'turnaroundLabel' => $turnaround->turnaroundLabel,
                                    'turnaroundValue' => $turnaround->turnaroundValue,
                                    'price' => $turnaround->price,
                                    'discount' => $turnaround->discount ?? null,
                                    'note' => $turnaround->note ?? '',
                                    'runsize' => $turnaround->runsize,
                                    'createdAt' => $turnaround->created_at,
                                    'updatedAt' => $turnaround->updated_at,
                                ];
                            });
                        }, []),
                    ];
                });
            }, []),
            'productImages' => $this->whenLoaded('images', function () {
                return collect($this->images)->map(function ($image) {
                    return $image->image_url;
                });
            }, []),
            'thumbnail' => $this->thumbnail,
            'base_price' => $this->base_price,
            'active' => $this->active,
            'popular_product' => $this->popular_product,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
