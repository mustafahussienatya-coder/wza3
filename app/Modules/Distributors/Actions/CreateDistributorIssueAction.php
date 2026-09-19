<?php

namespace App\Modules\Distributors\Actions;

use App\Modules\Distributors\Enums\DistributorIssueStatus;
use App\Modules\Distributors\Exceptions\InvalidCustodyOperationException;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Distributors\Models\DistributorIssueItem;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class CreateDistributorIssueAction
{
    public function execute(array $data, int $userId): DistributorIssue
    {
        return DB::transaction(function () use ($data, $userId) {
            $distributor = Distributor::findOrFail($data['distributor_id']);

            if (! $distributor->isActive()) {
                throw new InvalidCustodyOperationException;
            }

            $warehouse = Warehouse::findOrFail($data['warehouse_id']);

            if (! $warehouse->isActive()) {
                throw new InvalidCustodyOperationException;
            }

            $issue = DistributorIssue::create([
                'issue_number' => DistributorIssue::generateIssueNumber(),
                'distributor_id' => $distributor->id,
                'warehouse_id' => $warehouse->id,
                'status' => DistributorIssueStatus::DRAFT,
                'created_by' => $userId,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach (DistributorIssueItem::normalizeRows($data['items']) as $row) {
                DistributorIssueItem::create([
                    'distributor_issue_id' => $issue->id,
                    'product_id' => $row['product_id'],
                    'unit_id' => $row['unit_id'],
                    'quantity' => $row['quantity'],
                    'base_quantity' => $row['base_quantity'],
                    'conversion_factor' => $row['conversion_factor'],
                    'unit_price' => $row['unit_price'],
                ]);
            }

            return $issue->fresh(['distributor.user', 'warehouse', 'creator', 'items.product.baseUnit', 'items.unit']);
        });
    }
}
