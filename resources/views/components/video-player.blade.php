@props(['video'])

@php
    $media = $video->media;
@endphp

<div
    class="video-player my-8 overflow-hidden rounded-lg bg-black"
    data-video-tracker
    data-post-video-id="{{ $video->id }}"
    data-post-id="{{ $video->post_id }}"
    data-position="{{ $video->position }}"
    data-duration="{{ $video->duration_seconds }}"
    data-completion-threshold="{{ $video->completion_threshold }}"
    data-required="{{ $video->is_required ? 'true' : 'false' }}"
>
    <video
        class="aspect-video w-full"
        controls
        playsinline
        preload="none"
        {{-- Never autoplay with sound — §21. --}}
        poster="{{ $media?->thumbnail?->url }}"
        aria-label="{{ $media?->title ?? 'Article video ' . $video->position }}"
    >
        <source src="{{ $media?->url }}" type="{{ $media?->mime_type }}">
        Your browser does not support embedded video.
    </video>
</div>
