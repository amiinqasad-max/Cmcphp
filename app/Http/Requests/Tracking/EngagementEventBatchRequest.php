<?php

namespace App\Http\Requests\Tracking;

use App\Enums\EventType;
use App\Models\PostVideo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Strict, defensive validation for the tracking-ingestion batch endpoint
 * (§46). This is public, unauthenticated input from anonymous browsers —
 * nothing here is trusted further than "structurally plausible"; the
 * authoritative numbers (is_completed, etc.) are computed server-side from
 * what actually gets persisted, never from anything the client asserts.
 */
class EngagementEventBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:'.config('cms.tracking.max_events_per_batch')],
            'events.*.event_type' => ['required', 'string', Rule::in(array_column(EventType::cases(), 'value'))],
            'events.*.post_id' => ['required', 'uuid', 'exists:posts,id'],
            'events.*.post_video_id' => ['nullable', 'uuid', 'exists:post_videos,id'],
            'events.*.event_uuid' => ['required', 'uuid', 'distinct'],
            'events.*.payload' => ['sometimes', 'array'],
            'events.*.payload.percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'events.*.payload.watched_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'events.*.payload.time_spent_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('events', []) as $index => $event) {
                $type = EventType::tryFrom($event['event_type'] ?? '');

                if (! $type) {
                    continue; // already flagged by the `rules()` in-list check
                }

                if ($type->isVideoEvent() && blank($event['post_video_id'] ?? null)) {
                    $validator->errors()->add("events.{$index}.post_video_id", 'A video event requires post_video_id.');

                    continue;
                }

                if ($type->isVideoEvent() && filled($event['post_video_id'] ?? null)) {
                    $belongsToPost = PostVideo::query()
                        ->whereKey($event['post_video_id'])
                        ->where('post_id', $event['post_id'] ?? null)
                        ->exists();

                    if (! $belongsToPost) {
                        $validator->errors()->add("events.{$index}.post_video_id", 'This video does not belong to the given article.');
                    }
                }
            }
        });
    }
}
