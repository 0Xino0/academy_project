<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TermRequest;
use App\Models\Term;
use App\Services\TermService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TermController extends Controller
{
    public function __construct(private TermService $terms) {}

    public function index()
    {
        return $this->respond(fn () => response()->json([
            'status' => true, 'message' => 'Terms fetched successfully', 'terms' => Term::all(),
        ]));
    }

    public function store(TermRequest $request)
    {
        return $this->respond(fn () => response()->json([
            'status' => true, 'message' => 'Term created successfully', 'term' => $this->terms->save($request->validated()),
        ], 201));
    }

    public function show(string $id)
    {
        return $this->respond(fn () => response()->json([
            'status' => true, 'message' => 'Term fetched successfully', 'term' => Term::findOrFail($id),
        ]));
    }

    public function update(TermRequest $request, string $id)
    {
        return $this->respond(fn () => response()->json([
            'status' => true, 'message' => 'Term updated successfully', 'term' => $this->terms->save($request->validated(), $id),
        ]));
    }

    public function destroy(string $id)
    {
        return $this->respond(function () use ($id) {
            $term = Term::findOrFail($id);
            if (! $term->delete()) {
                throw new RuntimeException('The term could not be deleted.');
            }

            return response()->json(['status' => true, 'message' => 'Term deleted successfully']);
        });
    }

    private function respond(callable $action)
    {
        try {
            return $action();
        } catch (ModelNotFoundException | ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json(['status' => false, 'message' => 'Unable to process the term request'], 500);
        }
    }
}
