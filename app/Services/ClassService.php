<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\Term;
use App\Support\WeeklySchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ClassService
{
    public function __construct(private ScheduleConflictValidator $schedules, private StudentScheduleValidator $students) {}

    public function save(string $termId, array $data, ?string $classId = null): ClassModel
    {
        return DB::transaction(function () use ($termId, $data, $classId) {
            $term = Term::whereKey($termId)->lockForUpdate()->firstOrFail();
            $class = $classId === null ? new ClassModel()
                : ClassModel::where('term_id', $term->id)->findOrFail($classId);
            $studentIds = $classId === null ? [] : $this->students->lockParticipants($class);
            $datesChanged = $classId !== null && ($class->start_date !== $data['start_date'] || $class->end_date !== $data['end_date']);
            $teacherIds = array_unique(array_filter([$class->teacher_id, $data['teacher_id']]));
            $teachers = Teacher::withTrashed()->whereIn('id', $teacherIds)->orderBy('id')->lockForUpdate()->get();
            $teacher = $teachers->firstWhere('id', $data['teacher_id']);
            if (! $teacher || $teacher->trashed()) {
                throw ValidationException::withMessages(['teacher_id' => 'Select an active teacher.']);
            }
            if ($classId !== null) {
                $class = ClassModel::where('term_id', $term->id)->lockForUpdate()->findOrFail($classId);
            }
            Course::whereKey($data['course_id'])->lockForUpdate()->firstOrFail();
            if (! $term->is_active) {
                throw ValidationException::withMessages(['term_id' => 'The term is not active.']);
            }
            if ($data['start_date'] < $term->start_date) {
                throw ValidationException::withMessages(['start_date' => 'Class dates must be within the term dates.']);
            }
            if ($data['end_date'] > $term->end_date) {
                throw ValidationException::withMessages(['end_date' => 'Class dates must be within the term dates.']);
            }
            $registered = $classId === null ? 0 : $class->registrations()->lockForUpdate()->get(['id'])->count();
            if ($data['capacity'] < $registered) {
                throw ValidationException::withMessages(['capacity' => "Capacity cannot be less than {$registered} existing registrations."]);
            }

            $recheckSchedules = $classId !== null && ($class->teacher_id != $data['teacher_id']
                || $class->start_date !== $data['start_date'] || $class->end_date !== $data['end_date']);
            $class->fill($data);
            $class->term_id = $term->id;
            if (! $class->save()) {
                throw new RuntimeException('The class could not be saved.');
            }
            if ($recheckSchedules) {
                foreach ($class->schedules()->lockForUpdate()->get() as $schedule) {
                    $slot = WeeklySchedule::slot($schedule);
                    if ($slot === null) {
                        throw ValidationException::withMessages(['schedule' => 'Correct the existing invalid schedule before changing teacher or class dates.']);
                    }
                    $this->schedules->ensureAvailable($class, $slot, $schedule->id);
                }
            }

            if ($datesChanged) $this->students->ensureEditable($class, $studentIds);

            return $class->load(['course', 'teacher.user', 'term'])->loadCount('registrations');
        }, 3);
    }
}
