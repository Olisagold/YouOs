<?php

namespace App\Policies;

use App\Models\Doctrine;
use App\Models\User;

class DoctrinePolicy
{
    public function view(User $user, Doctrine $doctrine): bool
    {
        return $user->id === $doctrine->user_id;
    }

    public function update(User $user, Doctrine $doctrine): bool
    {
        return $user->id === $doctrine->user_id;
    }
}
