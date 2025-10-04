<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Domain;

interface AdminUserRepository
{
    public function findById(string $id): ?AdminUser;

    public function findByEmail(string $email): ?AdminUser;

    public function save(AdminUser $adminUser): void;
}
