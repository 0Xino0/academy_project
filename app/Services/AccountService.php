<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class AccountService
{
    public function create(array $attributes, string $role): User
    {
        if (! in_array($role, ['manager', 'teacher', 'student'], true)) {
            throw new InvalidArgumentException('Unsupported account role.');
        }

        return DB::transaction(function () use ($attributes, $role) {
            $user = User::create(Arr::only($attributes, [
                'national_id', 'first_name', 'last_name', 'phone', 'email', 'password',
            ]));
            $user->assignRole($role);

            if ($role === 'student') {
                $user->student()->create([]);
            } elseif ($role === 'teacher') {
                $user->teacher()->create([]);
            }

            return $user->load(['roles', 'student', 'teacher']);
        });
    }

    public function createStudentProfile(User $owner, array $attributes): Student
    {
        return DB::transaction(function () use ($owner, $attributes) {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);

            if (! $owner->hasRole('student')) {
                throw ValidationException::withMessages([
                    'user_id' => 'The user must have the student role.',
                ]);
            }

            if ($owner->student()->withTrashed()->exists()) {
                throw ValidationException::withMessages([
                    'user_id' => 'A student profile already exists for this user.',
                ]);
            }

            return $owner->student()->create(Arr::only($attributes, [
                'father_name', 'father_phone', 'mother_name', 'mother_phone',
            ]));
        });
    }

    public function deleteStudentProfile(Student $student): bool
    {
        return $this->deleteProfile($student, 'student');
    }

    public function deleteTeacherProfile(Teacher $teacher): bool
    {
        return $this->deleteProfile($teacher, 'teacher');
    }

    public function deleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            app(AcademicDeletionService::class)->protectUser($user);

            if (! $user->delete()) {
                throw new RuntimeException('The user could not be deleted.');
            }

            return true;
        }, 3);
    }

    private function deleteProfile(Model $profile, string $role): bool
    {
        return DB::transaction(function () use ($profile, $role) {
            $owner = User::query()->lockForUpdate()->findOrFail($profile->user_id);
            $owner->removeRole($role);

            if (! $profile->delete()) {
                throw new RuntimeException('The profile could not be deleted.');
            }

            return true;
        });
    }
}
