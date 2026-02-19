<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeeklyReview;

class WeeklyReviewPolicy
{
    public function view(User $user, WeeklyReview $weeklyReview): bool
    {
        return $user->id === $weeklyReview->user_id;
    }
}
