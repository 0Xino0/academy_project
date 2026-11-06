<?php

namespace App\Services;

use App\Models\Term;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TermService
{
    public function save(array $data, ?string $id = null): Term
    {
        try {
            return DB::transaction(function () use ($data, $id) {
                $term = $id === null ? new Term() : Term::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($id !== null) {
                    $classes = $term->classes()->orderBy('id')->lockForUpdate()->get(['id', 'start_date', 'end_date']);
                    foreach ($classes as $class) {
                        if ($data['start_date'] > $class->start_date) {
                            throw ValidationException::withMessages(['start_date' => 'The term must include the start dates of all its classes.']);
                        }
                        if ($data['end_date'] < $class->end_date) {
                            throw ValidationException::withMessages(['end_date' => 'The term must include the end dates of all its classes.']);
                        }
                    }
                }
                $term->fill($data);
                if (! $term->save()) {
                    throw new RuntimeException('The term could not be saved.');
                }

                return $term;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['season' => 'A term already exists for this year and season.']);
        }
    }
}
