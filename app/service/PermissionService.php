<?php

declare(strict_types=1);

namespace app\service;

final class PermissionService
{
    public function allows(string $roleCode, string $permission): bool
    {
        $permissions = (array) config('permissions.roles.' . $roleCode, []);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
