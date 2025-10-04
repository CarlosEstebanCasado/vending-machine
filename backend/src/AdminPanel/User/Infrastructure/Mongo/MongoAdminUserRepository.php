<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Infrastructure\Mongo;

use App\AdminPanel\User\Domain\AdminUser;
use App\AdminPanel\User\Domain\AdminUserRepository;
use App\AdminPanel\User\Infrastructure\Mongo\Document\AdminUserDocument;
use Doctrine\ODM\MongoDB\DocumentManager;

final class MongoAdminUserRepository implements AdminUserRepository
{
    public function __construct(private readonly DocumentManager $documentManager)
    {
    }

    public function findById(string $id): ?AdminUser
    {
        /** @var AdminUserDocument|null $document */
        $document = $this->documentManager->find(AdminUserDocument::class, $id);

        if (null === $document) {
            return null;
        }

        return $this->mapDocumentToDomain($document);
    }

    public function findByEmail(string $email): ?AdminUser
    {
        /** @var AdminUserDocument|null $document */
        $document = $this->documentManager
            ->getRepository(AdminUserDocument::class)
            ->findOneBy(['email' => $this->normalizeEmail($email)]);

        if (null === $document) {
            return null;
        }

        return $this->mapDocumentToDomain($document);
    }

    public function save(AdminUser $adminUser): void
    {
        /** @var AdminUserDocument|null $document */
        $document = $this->documentManager->find(AdminUserDocument::class, $adminUser->id());

        if (null === $document) {
            $document = new AdminUserDocument(
                email: $adminUser->email(),
                passwordHash: $adminUser->passwordHash(),
                roles: $adminUser->roles(),
                active: $adminUser->isActive(),
            );

            $this->documentManager->persist($document);
        } else {
            $document->updatePasswordHash($adminUser->passwordHash());
            $document->updateRoles($adminUser->roles());
            $document->updateActive($adminUser->isActive());
        }

        $this->documentManager->flush();
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower($email);
    }

    private function mapDocumentToDomain(AdminUserDocument $document): AdminUser
    {
        return new AdminUser(
            id: $document->id(),
            email: $document->email(),
            passwordHash: $document->passwordHash(),
            roles: $document->roles(),
            active: $document->isActive(),
        );
    }
}
