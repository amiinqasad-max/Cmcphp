<?php

namespace App\Http\Requests\Tracking;

use App\Enums\AdEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdEventBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:'.config('cms.tracking.max_events_per_batch')],
            'events.*.event_type' => ['required', 'string', Rule::in(array_column(AdEventType::cases(), 'value'))],
            'events.*.ad_slot_id' => ['required', 'uuid', 'exists:ad_slots,id'],
            'events.*.ad_placement_id' => ['nullable', 'uuid', 'exists:ad_placements,id'],
            'events.*.post_id' => ['nullable', 'uuid', 'exists:posts,id'],
            'events.*.event_uuid' => ['required', 'uuid', 'distinct'],
        ];
    }
}
