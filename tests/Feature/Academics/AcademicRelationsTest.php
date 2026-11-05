<?php

namespace Tests\Feature\Academics;

use App\Models\Grade;
use App\Models\Registration;
use App\Models\Schedule;

class AcademicRelationsTest extends AcademicTestCase
{
    public function test_class_schedules_use_class_id_and_do_not_include_another_class(): void
    {
        $class = $this->classroom();
        $other = $this->classroom();
        $schedule = Schedule::create(['class_id' => $class->id, 'day_of_week' => 'Monday',
            'start_time' => '09:00:00', 'end_time' => '10:00:00']);
        Schedule::create(['class_id' => $other->id, 'day_of_week' => 'Tuesday',
            'start_time' => '11:00:00', 'end_time' => '12:00:00']);

        $loaded = $class->fresh()->load('schedules');
        $this->assertSame([$schedule->id], $loaded->schedules->modelKeys());
        $this->assertSame($class->id, $schedule->class->id);
        $this->assertSame(1, $class->schedules()->count());
    }

    public function test_registration_relations_read_current_pivot_columns_in_both_directions(): void
    {
        $class = $this->classroom();
        $student = $this->account('student')->student;
        $registration = Registration::create(['class_id' => $class->id, 'student_id' => $student->id]);
        $this->classroom();
        $this->account('student');

        $registered = $class->fresh()->load('registeredStudents')->registeredStudents;
        $classes = $student->fresh()->load('registredClasses')->registredClasses;
        $this->assertSame([$student->id], $registered->modelKeys());
        $this->assertSame([$class->id], $classes->modelKeys());
        foreach ([$registered->first()->pivot, $classes->first()->pivot] as $pivot) {
            $this->assertSame($registration->id, $pivot->id);
            $this->assertEquals($registration->created_at, $pivot->created_at);
            $this->assertEquals($registration->updated_at, $pivot->updated_at);
            $this->assertArrayNotHasKey('registration_date', $pivot->toArray());
        }
    }

    public function test_registration_attach_populates_timestamps_from_both_relations(): void
    {
        $class = $this->classroom();
        $other = $this->classroom();
        $student = $this->account('student')->student;
        $class->registeredStudents()->attach($student->id);
        $student->registredClasses()->attach($other->id);

        $rows = Registration::where('student_id', $student->id)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertNotNull($row->created_at);
            $this->assertNotNull($row->updated_at);
        }
    }

    public function test_grade_relations_read_grade_and_timestamps_without_removed_entered_at(): void
    {
        $class = $this->classroom();
        $student = $this->account('student')->student;
        $grade = Grade::create(['class_id' => $class->id, 'student_id' => $student->id, 'grade' => '17.5']);

        $students = $class->fresh()->load('studentsWithGrades')->studentsWithGrades;
        $classes = $student->fresh()->load('classesWithGrades')->classesWithGrades;
        $this->assertSame([$student->id], $students->modelKeys());
        $this->assertSame([$class->id], $classes->modelKeys());
        foreach ([$students->first()->pivot, $classes->first()->pivot] as $pivot) {
            $this->assertSame($grade->id, $pivot->id);
            $this->assertSame('17.5', $pivot->grade);
            $this->assertEquals($grade->created_at, $pivot->created_at);
            $this->assertEquals($grade->updated_at, $pivot->updated_at);
            $this->assertArrayNotHasKey('entered_at', $pivot->toArray());
        }
    }

    public function test_removed_grade_field_is_not_written_during_mass_assignment(): void
    {
        $class = $this->classroom();
        $student = $this->account('student')->student;
        $grade = Grade::create(['class_id' => $class->id, 'student_id' => $student->id,
            'grade' => '0', 'entered_at' => '2026-11-06']);

        $this->assertSame('0', $grade->fresh()->grade);
        $this->assertArrayNotHasKey('entered_at', $grade->getAttributes());
    }

    public function test_term_active_flag_serializes_as_boolean_in_class_relations(): void
    {
        $class = $this->classroom();
        $this->assertTrue($class->fresh()->load('term')->toArray()['term']['is_active']);
        $class->term->update(['is_active' => 0]);
        $this->assertFalse($class->fresh()->load('term')->toArray()['term']['is_active']);
    }
}
