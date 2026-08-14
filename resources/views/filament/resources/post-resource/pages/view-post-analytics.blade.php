<x-filament-panels::page>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ([
            'Views' => number_format($stats['views']),
            'Unique sessions' => number_format($stats['unique_sessions']),
            'Avg. progress' => $stats['avg_progress_percent'].'%',
            'Avg. reading time' => gmdate('i:s', $stats['avg_reading_seconds']),
            'Article completion' => $stats['completion_rate'].'%',
        ] as $label => $value)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if (count($stats['videos']))
        <div class="mt-8">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Videos</h2>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ($stats['videos'] as $video)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Video {{ $video['position'] }}</p>
                        <dl class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <div class="flex justify-between"><dt>Played</dt><dd>{{ number_format($video['played']) }}</dd></div>
                            <div class="flex justify-between"><dt>Completed</dt><dd>{{ number_format($video['completed']) }}</dd></div>
                            <div class="flex justify-between font-medium text-gray-950 dark:text-white"><dt>Completion</dt><dd>{{ $video['completion_rate'] }}%</dd></div>
                        </dl>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-8">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
            Ads <span class="text-xs font-normal text-gray-500">(Internal Ad Analytics — not official AdSense reporting)</span>
        </h2>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Requests</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($stats['ads']['requests']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Rendered</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($stats['ads']['rendered']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Render rate</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $stats['ads']['render_rate'] }}%</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
