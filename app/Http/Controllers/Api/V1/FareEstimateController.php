<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rides\EstimateRideOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FareEstimateRequest;
use Illuminate\Http\JsonResponse;

class FareEstimateController extends Controller
{
    /**
     * Upfront fare and pickup ETA for each ride category.
     */
    public function __invoke(FareEstimateRequest $request, EstimateRideOptions $estimateRideOptions): JsonResponse
    {
        return response()->json([
            'data' => $estimateRideOptions->handle($request->pickup(), $request->dropoff()),
        ]);
    }
}
