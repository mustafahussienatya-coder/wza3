<?php

namespace App\Modules\Customers\Services;

use App\Enums\UserRole;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Customers\Enums\LedgerTransactionType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerLedgerEntry;
use App\Modules\Customers\Models\CustomerTransfer;
use App\Modules\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Customer::query()
            ->with(['area', 'distributor.user', 'creator'])
            ->withCount('ledgerEntries')
            ->withCurrentBalance();

        $this->applyRoleScope($query);
        $this->applySearch($query, $filters);
        $this->applyFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function create(array $data, User $user): Customer
    {
        return DB::transaction(function () use ($data, $user) {
            $isDistributor = $user->hasRole(UserRole::DISTRIBUTOR->value);

            $customer = Customer::create([
                'code' => $this->generateCode(),
                'name' => $data['name'],
                'phone' => $data['phone'],
                'secondary_phone' => $data['secondary_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'distributor_id' => $isDistributor
                    ? $user->distributor?->id
                    : ($data['distributor_id'] ?? null),
                'created_by' => $user->id,
                'credit_limit' => (float) ($data['credit_limit'] ?? 0),
                'status' => ($data['status'] ?? CustomerStatus::ACTIVE->value),
                'notes' => $data['notes'] ?? null,
            ]);

            $obAmount = (float) ($data['opening_balance'] ?? 0);
            $obType = $data['opening_balance_type'] ?? 'debit';
            $obReason = $data['opening_balance_reason'] ?? null;

            if ($obAmount > 0) {
                $debit = $obType === 'debit' ? $obAmount : 0;
                $credit = $obType === 'credit' ? $obAmount : 0;

                CustomerLedgerEntry::create([
                    'customer_id' => $customer->id,
                    'type' => LedgerTransactionType::OPENING_BALANCE->value,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance_after' => $debit - $credit,
                    'user_id' => $user->id,
                    'notes' => $obReason,
                ]);
            }

            return $customer->load(['area', 'distributor.user', 'creator']);
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            // Prevent changing distributor through regular update — use transfer action
            unset($data['distributor_id']);

            $customer->update($data);

            return $customer->fresh(['area', 'distributor.user', 'creator']);
        });
    }

    public function delete(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            if ($customer->ledgerEntries()->exists()) {
                throw new \RuntimeException(__('customer_messages.customer_has_transactions'));
            }

            $customer->delete();
        });
    }

    public function transfer(Customer $customer, int $toDistributorId, ?string $reason, User $user): Customer
    {
        return DB::transaction(function () use ($customer, $toDistributorId, $reason, $user) {
            $fromDistributorId = $customer->distributor_id;

            if ($fromDistributorId === $toDistributorId) {
                throw new \RuntimeException(__('customer_messages.cannot_transfer_to_same_owner'));
            }

            CustomerTransfer::create([
                'customer_id' => $customer->id,
                'from_distributor_id' => $fromDistributorId,
                'to_distributor_id' => $toDistributorId,
                'user_id' => $user->id,
                'reason' => $reason,
            ]);

            $customer->update(['distributor_id' => $toDistributorId]);

            return $customer->fresh(['area', 'distributor.user', 'creator']);
        });
    }

    public function changeStatus(Customer $customer, string $status): Customer
    {
        return DB::transaction(function () use ($customer, $status) {
            $customer->update(['status' => $status]);

            return $customer->fresh(['area', 'distributor.user', 'creator']);
        });
    }

    public function adjustOpeningBalance(Customer $customer, array $data, User $user): CustomerLedgerEntry
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            $amount = (float) $data['amount'];
            $type = $data['type'];

            $debit = $type === 'debit' ? $amount : 0;
            $credit = $type === 'credit' ? $amount : 0;

            $balanceAfter = $this->calculateBalanceAfter($customer->id, $debit - $credit);

            return CustomerLedgerEntry::create([
                'customer_id' => $customer->id,
                'type' => LedgerTransactionType::OPENING_BALANCE->value,
                'debit' => $debit,
                'credit' => $credit,
                'balance_after' => $balanceAfter,
                'user_id' => $user->id,
                'notes' => $data['reason'].($data['notes'] ?? ''),
            ]);
        });
    }

    public function getLedger(Customer $customer, array $filters): LengthAwarePaginator
    {
        $query = $customer->ledgerEntries()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if (isset($filters['type']) && $filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['from']) && $filters['from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to']) && $filters['to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    public function getSummary(User $user): array
    {
        $query = Customer::query();

        $this->applyRoleScope($query, $user);

        $total = (clone $query)->count();
        $active = (clone $query)->where('status', CustomerStatus::ACTIVE->value)->count();
        $inactive = (clone $query)->where('status', CustomerStatus::INACTIVE->value)->count();
        $company = (clone $query)->whereNull('distributor_id')->count();
        $distributorCustomers = (clone $query)->whereNotNull('distributor_id')->count();

        $withOutstanding = (clone $query)
            ->whereRaw(
                '(SELECT COALESCE(SUM(debit - credit), 0) FROM customer_ledger_entries WHERE customer_ledger_entries.customer_id = customers.id) > 0'
            )
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'company' => $company,
            'distributor' => $distributorCustomers,
            'with_outstanding_balance' => $withOutstanding,
        ];
    }

    private function applyRoleScope(Builder $query, ?User $user = null): void
    {
        $user = $user ?? auth()->user();

        if ($user?->hasRole(UserRole::DISTRIBUTOR->value)) {
            $query->where('distributor_id', $user->distributor?->id);
        }
    }

    private function applySearch(Builder $query, array $filters): void
    {
        if (($filters['search'] ?? '') !== '') {
            $query->search($filters['search']);
        }

        if (($filters['q'] ?? '') !== '') {
            $query->search($filters['q']);
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['ownership']) && $filters['ownership'] !== '') {
            if ($filters['ownership'] === 'company') {
                $query->whereNull('distributor_id');
            } elseif ($filters['ownership'] === 'distributor') {
                $query->whereNotNull('distributor_id');
            }
        }

        if (isset($filters['distributor_id']) && $filters['distributor_id'] !== '') {
            $query->where('distributor_id', $filters['distributor_id']);
        }

        if (isset($filters['area_id']) && $filters['area_id'] !== '') {
            $query->where('area_id', $filters['area_id']);
        }

        if (isset($filters['status']) && CustomerStatus::tryFrom($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['balance']) && $filters['balance'] !== '') {
            if ($filters['balance'] === 'outstanding') {
                $query->whereRaw(
                    '(SELECT COALESCE(SUM(debit - credit), 0) FROM customer_ledger_entries WHERE customer_ledger_entries.customer_id = customers.id) > 0'
                );
            } elseif ($filters['balance'] === 'credit') {
                $query->whereRaw(
                    '(SELECT COALESCE(SUM(debit - credit), 0) FROM customer_ledger_entries WHERE customer_ledger_entries.customer_id = customers.id) < 0'
                );
            }
        }
    }

    private function generateCode(): string
    {
        $last = Customer::withTrashed()
            ->where('code', 'like', 'CUS-%')
            ->orderByDesc('id')
            ->value('code');

        $sequence = $last ? (int) substr($last, 4) : 0;
        $next = $sequence + 1;

        return 'CUS-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function calculateBalanceAfter(int $customerId, float $delta): string
    {
        $current = CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('id')
            ->value('balance_after');

        return number_format((float) $current + $delta, 2, '.', '');
    }
}
