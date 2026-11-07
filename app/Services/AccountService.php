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
            if (! $user->exists) throw new RuntimeException('The account could not be saved.');
            $user->assignRole($role);

            if ($role === 'student') {
                $profile = $user->student()->create([]);
            } elseif ($role === 'teacher') {
                $profile = $user->teacher()->create([]);
            }
            if (isset($profile) && ! $profile->exists) throw new RuntimeException('The profile could not be saved.');

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

            $profile = $owner->student()->create(Arr::only($attributes, [
                'father_name', 'father_phone', 'mother_name', 'mother_phone',
            ]));
            if (! $profile->exists) throw new RuntimeException('The profile could not be saved.');
            return $profile->load('user');
        });
    }

    public function updateUser(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->fill(Arr::only($attributes, ['national_id', 'first_name', 'last_name', 'phone', 'email']));
            if (! $user->save()) throw new RuntimeException('The account could not be updated.');
            return $user->load('roles');
        }, 3);
    }

    public function updateStudentProfile(Student $student, array $attributes): Student
    {
        return $this->updateProfile($student, Arr::only($attributes, ['father_name', 'father_phone', 'mother_name', 'mother_phone']));
    }

    public function updateTeacherProfile(Teacher $teacher, array $attributes): Teacher
    {
        return $this->updateProfile($teacher, Arr::only($attributes, ['salary', 'resume', 'bio', 'degree']));
    }

    private function updateProfile(Model $profile, array $attributes): Model
    {
        return DB::transaction(function () use ($profile, $attributes) {
            User::whereKey($profile->user_id)->lockForUpdate()->firstOrFail();
            $profile = $profile->newQuery()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $profile->fill($attributes);
            if (! $profile->save()) throw new RuntimeException('The profile could not be updated.');
            return $profile->load('user');
        }, 3);
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

            foreach ([$user->student, $user->teacher] as $profile) {
                if ($profile && ! $profile->delete()) throw new RuntimeException('The profile could not be archived.');
            }

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
            $profile = $profile->newQuery()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $owner->removeRole($role);

            if (! $profile->delete()) {
                throw new RuntimeException('The profile could not be deleted.');
            }

            return true;
        }, 3);
    }
}
