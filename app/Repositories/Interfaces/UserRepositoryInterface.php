<?php

namespace App\Repositories\Interfaces;

interface UserRepositoryInterface
{
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15);
    public function getById(string $id, array $with = []);
    public function create(array $data);
    public function update(string $id, array $data);
    public function delete(string $id);
    public function canViewUser($currentUser, $targetUser);
    public function canDeleteUser($currentUser, $targetUser);
    public function getManagersForTenant($tenantId);
    public function getTenantsForManager($managerId);
    public function getRoleByCode($roleCode);
    public function getManagersForHouse(array $houseIds);
    public function getTenantsForHouses(array $houseIds);
    public function getUsersWithoutActiveContract();
} 