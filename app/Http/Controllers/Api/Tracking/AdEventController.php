<?php

namespace App\Http\Controllers\Api\Tracking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\AdEventBatchRequest;
use App\Jobs\ProcessAdEventBatch;
use Illuminate\Http\JsonResponse;

class AdEventController extends Controller
{
    public function store(AdEventBatchRequest $request): JsonResponse
    {
        ProcessAdEventBatch::dispatch(
            $request->validated('events'),
            $request->attributes->get('anonymous_session_id'),
        );

        return response()->json(['status' => 'accepted'], 202);
    }
}
