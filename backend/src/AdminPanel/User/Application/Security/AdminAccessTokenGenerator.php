<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Application\Security;

use App\AdminPanel\User\Domain\AdminUser;

interface AdminAccessTokenGenerator
{
    public function generate(AdminUser $adminUser): AccessToken;
}
