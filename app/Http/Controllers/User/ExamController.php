<?php

namespace App\Http\Controllers\User;

use App\DTOs\Vtu\ExamDTO;
use App\Http\Controllers\Controller;
use App\Services\Vtu\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Exam Pins
 * @authenticated
 */
class ExamController extends Controller
{
    public function __construct(private readonly ExamService $examService) {}

    /**
     * Purchase exam scratch card
     *
     * @bodyParam exam_type string required Exam type (waec, neco, nabteb, jamb). Example: waec
     * @bodyParam quantity integer required Number of pins (1-5). Example: 1
     * @bodyParam pin string required 4-digit transaction PIN. Example: 1234
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'exam_type' => 'required|in:waec,neco,nabteb,jamb',
            'quantity'  => 'required|integer|min:1|max:5',
            'pin'       => 'required|digits:4',
        ]);

        if (!$request->user()->verifyTransactionPin($request->pin)) {
            return response()->json(['success' => false, 'message' => 'Invalid transaction PIN.'], 422);
        }

        // Exam pricing config
        $prices = config('vtu.exam_prices', [
            'waec'   => 4000,
            'neco'   => 1500,
            'nabteb' => 900,
            'jamb'   => 3500,
        ]);

        $amount = ($prices[$request->exam_type] ?? 0) * $request->quantity;

        $dto = ExamDTO::fromArray([
            'exam_type' => $request->exam_type,
            'quantity'  => $request->quantity,
            'amount'    => $amount,
            'reference' => 'EXM-' . strtoupper(uniqid()),
        ], $request->user()->id);

        \App\Jobs\Vtu\ProcessExamJob::dispatch($dto);

        return response()->json([
            'success' => true,
            'message' => 'Exam pin purchase queued.',
            'data'    => [
                'reference' => $dto->reference,
                'exam_type' => $dto->examType,
                'quantity'  => $dto->quantity,
                'amount'    => $dto->amount,
                'status'    => 'pending',
            ],
        ], 202);
    }
}
