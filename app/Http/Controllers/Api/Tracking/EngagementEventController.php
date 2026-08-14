<?php

namespace App\Http\Controllers\Api\Tracking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\EngagementEventBatchRequest;
use App\Jobs\ProcessEngagementEventBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngagementEventController extends Controller
{
    /**
     * Ingests a batch of client-buffered milestone events (reading and/or
     * video). Validated structurally here; queued for processing so the
     * request returns immediately regardless of traffic volume (§29/§35).
     */
    public function store(EngagementEventBatchRequest $request): JsonResponse
    {
        ProcessEngagementEventBatch::dispatch(
            $request->validated('events'),
            $request->attributes->get('anonymous_session_id'),
            $request->user()?->id,
        );

        return response()->json(['status' => 'accepted'], 202);
    }
}
