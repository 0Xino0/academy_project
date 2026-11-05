<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function view(User $user, Teacher $teacher): bool
    {
        return $user->hasRole('teacher') && (int) $teacher->user_id === (int) $user->id;
    }

    public function update(User $user, Teacher $teacher): bool
    {
        return $this->view($user, $teacher);
    }
}
