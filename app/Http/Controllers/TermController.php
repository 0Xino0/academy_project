<?php

namespace App\Http\Controllers;

use App\Models\Term;
use Illuminate\Http\Request;
use App\Http\Requests\TermRequest;

class TermController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $terms = Term::get();
            return response()->json([
                'message' => 'Terms fetched successfully',
                'terms' => $terms
            ], 200);
        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error fetching terms',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TermRequest $request)
    {
        try{
            $term = Term::create($request->validated());
            return response()->json([
                'message' => 'Term created successfully',
                'term' => $term
            ], 201);
        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error creating term',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Term $term,string $id)
    {
        try{
            $term = Term::findOrFail($id);
            return response()->json([
                'message' => 'Term fetched successfully',
                'term' => $term
            ], 200);
        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error fetching term',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Term $term)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TermRequest $request, Term $term,string $id)
    {
        try{
            $term = Term::findOrFail($id);
            $term->update($request->validated());
            return response()->json([
                'message' => 'Term updated successfully',
                'term' => $term
            ], 200);
        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error updating term',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Term $term,string $id)
    {
        try{
            $term = Term::findOrFail($id);
            $term->delete();
            return response()->json([
                'message' => 'Term deleted successfully',
            ], 200);
        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error deleting term',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
