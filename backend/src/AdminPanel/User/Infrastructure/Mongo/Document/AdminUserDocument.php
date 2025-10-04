<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Infrastructure\Mongo\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: 'admin_users')]
class AdminUserDocument
{
    #[MongoDB\Id]
    private string $id;

    public function __construct(
        #[MongoDB\Field(type: 'string')]
        private string $email,
        #[MongoDB\Field(type: 'string')]
        private string $passwordHash,
        #[MongoDB\Field(type: 'collection')]
        private array $roles = ['admin'],
        #[MongoDB\Field(type: 'bool')]
        private bool $active = true,
    ) {
        $this->email = strtolower($this->email);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function roles(): array
    {
        return $this->roles;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function updatePasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function updateRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function updateActive(bool $active): void
    {
        $this->active = $active;
    }
}
