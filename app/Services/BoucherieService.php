<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Boucherie;
use App\Repositories\BoucherieRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class BoucherieService
{
    public function __construct(private readonly BoucherieRepository $repository) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function paginateForFournisseurUser(int $fournisseurUserId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateForFournisseurUser($fournisseurUserId, $perPage);
    }

    public function findById(string $id): Boucherie
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data): Boucherie
    {
        return $this->repository->create($data);
    }

    public function update(string $id, array $data): Boucherie
    {
        return $this->repository->update($id, $data);
    }

    public function delete(string $id): void
    {
        $this->repository->delete($id);
    }
}
