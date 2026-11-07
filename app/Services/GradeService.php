<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GradeService
{
    public function save(User $actor, string $classId, array $entries): Collection
    {
        return DB::transaction(function () use ($actor, $classId, $entries) {
            $class = ClassModel::findOrFail($classId);
            Term::whereKey($class->term_id)->lockForUpdate()->firstOrFail();
            $studentIds = array_column($entries, 'student_id');
            $students = Student::whereIn('id', $studentIds)->orderBy('id')->lockForUpdate()->get(['id']);
            $class = ClassModel::whereKey($classId)->lockForUpdate()->firstOrFail();
            // Recheck ownership and active membership after locks, including cancellation races.
            Gate::forUser($actor)->authorize('recordGrades', $class);
            $members = Registration::where('class_id', $class->id)->whereIn('student_id', $studentIds)
                ->orderBy('student_id')->lockForUpdate()->pluck('student_id')->all();
            foreach ($entries as $index => $entry) {
                if (! $students->contains('id', $entry['student_id']) || ! in_array((int) $entry['student_id'], $members)) {
                    throw ValidationException::withMessages(["grades.$index.student_id" => 'Select an active student enrolled in this class.']);
                }
            }
            $saved = collect();
            foreach ($entries as $entry) {
                $grade = Grade::firstOrNew(['student_id' => $entry['student_id'], 'class_id' => $class->id]);
                $grade->grade = $entry['grade'];
                if (! $grade->save()) throw new RuntimeException('The grade could not be saved.');
                $saved->push($grade->load(['student.user', 'class.teacher.user']));
            }
            return $saved;
        }, 3);
    }
}
