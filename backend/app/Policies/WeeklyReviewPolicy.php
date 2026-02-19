<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeeklyReview;

class WeeklyReviewPolicy
{
    public function create(User $user): bool
    {
        return $user->id > 0;
    }

    public function view(User $user, WeeklyReview $weeklyReview): bool
    {
        return $user->id === $weeklyReview->user_id;
    }
}
