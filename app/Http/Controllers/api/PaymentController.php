<?php

namespace App\Http\Controllers\api;

use App\Models\Debt;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $payments = Payment::with('debt')->get();
            return response()->json([
                'status' => true,
                'message' => 'Payments fetched successfully',
                'data' => $payments
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'error while fetching payments',
                'error' => $e->getMessage()
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
    public function store(PaymentRequest $request , string $debt_id)
    {
        try {
            $debt = Debt::findOrFail($debt_id);
            
            // Calculate remaining debt
            $remainingDebt = $debt->total_amount - $debt->paid_amount;
            
            // Check if payment amount is greater than remaining debt
            if ($request->amount > $remainingDebt) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment amount cannot be greater than remaining debt',
                    'remaining_debt' => $remainingDebt
                ], 422);
            }
            
            // Calculate new paid amount
            $newPaidAmount = $debt->paid_amount + $request->amount;
            
            // Check if payment will make the debt fully paid
            if ($newPaidAmount == $debt->total_amount) {
                $debt->is_paid = true;
            }
            
            // Update debt's paid_amount
            $debt->paid_amount = $newPaidAmount;
            $debt->save();
            
            // Create payment record
            $payment = Payment::create([
                'debt_id' => $debt_id,
                'amount' => $request->validated('amount')
            ]);
            
            return response()->json([
                'status' => true,
                'message' => 'Payment processed successfully',
                'data' => $payment,
                'remaining_debt' => $debt->total_amount - $debt->paid_amount,
                'is_paid' => $debt->is_paid
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $payment_id)
    {
        try{
            $payment = Payment::with(['debt','debt.registration.student.user','debt.registration.class.term'])->findOrFail($payment_id);
            return response()->json([
                'status' => true,
                'message' => 'Payment fetched successfully',
                'data' => $payment
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error fetching payment',
                'error' => $e->getMessage()
            ], 500);
        } 
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $payment_id)
    {
        //
    }
}
