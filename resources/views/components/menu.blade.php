@if ($items && $items->isNotEmpty())
    <ul {{ $attributes->class([$mobile ? 'space-y-1' : 'flex items-center gap-6 text-sm font-medium text-gray-700']) }}>
        @foreach ($items as $item)
            <x-menu-item :item="$item" :mobile="$mobile" />
        @endforeach
    </ul>
@endif
