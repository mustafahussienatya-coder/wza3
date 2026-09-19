<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Models\DocumentCounter;
use Illuminate\Support\Facades\DB;

class DocumentCounterService
{
    public function next(string $docType, string $prefix, int $padding = 6): string
    {
        return DB::transaction(function () use ($docType, $prefix, $padding): string {
            $counter = DocumentCounter::query()
                ->where('doc_type', $docType)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                $counter = DocumentCounter::query()->create([
                    'doc_type' => $docType,
                    'prefix' => $prefix,
                    'last_number' => 1,
                ]);
            } else {
                $counter->increment('last_number');
                $counter->refresh();
            }

            return $prefix.str_pad((string) $counter->last_number, $padding, '0', STR_PAD_LEFT);
        });
    }
}
