<?php

namespace App\Modules\Users\Services;

use App\Enums\UserRole;
use App\Modules\Distributors\Enums\DistributorStatus;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = User::query()->with('distributor.areas');

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if (isset($filters['role']) && UserRole::tryFrom($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $roleName = null;
            if (isset($data['role'])) {
                $roleName = UserRole::from($data['role'])->value;
                $data['role'] = $roleName;
            }

            $data['must_change_password'] = $data['must_change_password'] ?? true;

            $distributorData = $data['distributor'] ?? null;
            unset($data['distributor']);

            $user = User::create($data);

            if ($roleName !== null) {
                $user->assignRole($roleName);
            }

            if ($roleName === UserRole::DISTRIBUTOR->value) {
                $distributor = Distributor::firstOrCreate(
                    ['user_id' => $user->id],
                    ['status' => DistributorStatus::ACTIVE->value]
                );

                if (is_array($distributorData)) {
                    $this->applyDistributorData($distributor, $distributorData);
                }
            }

            return $user->fresh('distributor.areas');
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (isset($data['role'])) {
                $roleName = UserRole::from($data['role'])->value;
                $data['role'] = $roleName;
            }

            $distributorData = $data['distributor'] ?? null;
            unset($data['distributor']);

            if (isset($data['password'])) {
                $data['must_change_password'] = true;
            }

            $user->update($data);

            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            $distributor = $user->distributor()->first();

            if ($user->role === UserRole::DISTRIBUTOR->value) {
                if ($distributor === null) {
                    $distributor = $user->distributor()->create([
                        'status' => DistributorStatus::ACTIVE->value,
                    ]);
                }

                if (is_array($distributorData)) {
                    $this->applyDistributorData($distributor, $distributorData);
                }
            } elseif ($distributor !== null) {
                $this->deleteDistributorFiles($distributor);
                $distributor->delete();
            }

            return $user->fresh('distributor.areas');
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $distributor = $user->distributor()->first();
            if ($distributor !== null) {
                $this->deleteDistributorFiles($distributor);
            }

            $user->tokens()->delete();
            $user->delete();
        });
    }

    public function toggleStatus(User $user): User
    {
        $user = DB::transaction(function () use ($user) {
            $isActive = ! $user->is_active;
            $user->update(['is_active' => $isActive]);

            if (! $isActive) {
                $user->tokens()->delete();
            }

            return $user->fresh();
        });

        return $user;
    }

    private function applyDistributorData(Distributor $distributor, array $data): void
    {
        $updates = [];

        if (array_key_exists('vehicle_number', $data)) {
            $updates['vehicle_number'] = $data['vehicle_number'];
        }

        if (array_key_exists('vehicle_type', $data)) {
            $updates['vehicle_type'] = $data['vehicle_type'];
        }

        if (isset($data['national_id_photo_front']) && $data['national_id_photo_front'] instanceof UploadedFile) {
            $updates['national_id_photo_front'] = $this->storeNationalIdPhoto($data['national_id_photo_front']);
            $this->deleteNationalIdPhoto($distributor->national_id_photo_front);
        }

        if (isset($data['national_id_photo_back']) && $data['national_id_photo_back'] instanceof UploadedFile) {
            $updates['national_id_photo_back'] = $this->storeNationalIdPhoto($data['national_id_photo_back']);
            $this->deleteNationalIdPhoto($distributor->national_id_photo_back);
        }

        $distributor->update($updates);

        if (array_key_exists('area_ids', $data)) {
            $distributor->areas()->sync($data['area_ids'] ?? []);
        }
    }

    private function storeNationalIdPhoto(UploadedFile $file): string
    {
        return $file->store('distributor-documents', 'private');
    }

    private function deleteNationalIdPhoto(?string $path): void
    {
        if ($path !== null && Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }
    }

    private function deleteDistributorFiles(Distributor $distributor): void
    {
        $this->deleteNationalIdPhoto($distributor->national_id_photo_front);
        $this->deleteNationalIdPhoto($distributor->national_id_photo_back);
    }
}
