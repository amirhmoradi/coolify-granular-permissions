<?php

namespace AmirhMoradi\CoolifyPermissions\Policies;

use AmirhMoradi\CoolifyPermissions\Services\PermissionService;
use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Viewing server details is allowed for any team member.
     * Servers are team-level resources but view-only users may need to see
     * server status on project pages.
     */
    public function view(User $user, Server $server): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        if (! PermissionService::isEnabled()) {
            return true;
        }

        return PermissionService::hasRoleBypass($user);
    }

    public function update(User $user, Server $server): bool
    {
        if (! PermissionService::isEnabled()) {
            return true;
        }

        return PermissionService::hasRoleBypass($user);
    }

    public function delete(User $user, Server $server): bool
    {
        if (! PermissionService::isEnabled()) {
            return true;
        }

        return PermissionService::hasRoleBypass($user);
    }
}
