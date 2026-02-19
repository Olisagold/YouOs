<?php

namespace App\Policies;

use App\Models\DailyCheckin;
use App\Models\User;

class DailyCheckinPolicy
{
    public function view(User $user, DailyCheckin $dailyCheckin): bool
    {
        return $user->id === $dailyCheckin->user_id;
    }
}
