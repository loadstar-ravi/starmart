<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    /**
     * Take the payment for an order.
     *
     * Answers 200 with the payment when it goes through and 402 with the payment when it is declined.
     */
    public function store(PaymentRequest $request, PaymentService $paymentService): JsonResponse
    {
        try {
            $payment = $paymentService->process(
                $request->user(),
                $request->integer('order_id'),
                $request->validated('amount'),
                $request->simulatesFailure(),
            );
        } catch (PaymentException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $succeeded = $payment->status === PaymentStatus::Success;

        return (new PaymentResource($payment))
            ->additional(['message' => $succeeded ? 'Payment successful.' : $payment->failure_reason])
            ->response()
            ->setStatusCode($succeeded ? Response::HTTP_OK : Response::HTTP_PAYMENT_REQUIRED);
    }
}
