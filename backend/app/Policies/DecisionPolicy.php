<?php

namespace App\Policies;

use App\Models\Decision;
use App\Models\User;

class DecisionPolicy
{
    public function view(User $user, Decision $decision): bool
    {
        return $user->id === $decision->user_id;
    }

    public function update(User $user, Decision $decision): bool
    {
        return $user->id === $decision->user_id;
    }
}
