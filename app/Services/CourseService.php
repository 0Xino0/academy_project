<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CourseService
{
    public function save(array $data, ?string $id = null): Course
    {
        return DB::transaction(function () use ($data, $id) {
            $course = $id === null ? new Course() : Course::whereKey($id)->lockForUpdate()->firstOrFail();
            $course->fill($data);
            if (! $course->save() || ! $course->exists) {
                throw new RuntimeException('The course could not be saved.');
            }

            return $course->refresh();
        }, 3);
    }
}
