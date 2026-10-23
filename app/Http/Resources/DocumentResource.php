<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Models\DocumentLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document as the shared document form shows it: header, audit (created by / executed by)
 * and lines. Load `lines.product`, `lines.location.parent`, `author` and `executor` first.
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'number' => $this->number,
            'status' => $this->status,
            'note' => $this->note,
            'author' => $this->author->name,
            'executor' => $this->executor?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'executed_at' => $this->executed_at?->toIso8601String(),
            'lines' => $this->lines->map(fn (DocumentLine $line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product->name,
                'location_id' => $line->location_id,
                'location_name' => $line->location?->fullName(),
                'quantity' => $line->quantity,
                'pieces_per_block' => $line->pieces_per_block,
                'price' => $line->price,
            ])->all(),
        ];
    }
}
