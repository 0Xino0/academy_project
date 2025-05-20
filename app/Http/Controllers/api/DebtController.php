<?php

namespace App\Http\Controllers\api;

use App\Models\Debt;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DebtController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $debts = Debt::with('registration.student.user')
                            ->get();
            return response()->json([
                'status' => true,
                'message' => 'debts retrieved successfully',
                'data' => $debts
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'debts not found',
                'error' => $e->getMessage()
            ],500);
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Debt $debt , string $student_id)
    {
        try{
            $debt = $debt->with('registration.student.user','registration.class')
                            ->whereHas('registration.student',function($query) use ($student_id){
                                $query->where('id',$student_id);
                            })
                            ->get();

            if($debt->isEmpty()){
                return response()->json([
                    'status' => false,
                    'message' => 'debt not found',
                ],404);
            }
            return response()->json([
                'status' => true,
                'message' => 'debt retrieved successfully',
                'data' => $debt,                
                'remaining_debt' => $debt->sum('total_amount') - $debt->sum('paid_amount'),
                'paid_debt' => $debt->sum('paid_amount')
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'debt not found',
                'error' => $e->getMessage()
            ]);
        }
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Debt $debt)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Debt $debt)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Debt $debt)
    {
        //
    }
}
