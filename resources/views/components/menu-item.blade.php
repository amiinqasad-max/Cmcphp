@props(['item', 'mobile' => false])

@php
    $href = $item->resolvedUrl();
    $hasChildren = $item->children && $item->children->isNotEmpty();
@endphp

@if ($href)
    <li @if ($hasChildren) x-data="{ open: false }" @if (! $mobile) @click.outside="open = false" @endif class="{{ $mobile ? '' : 'relative' }}" @endif>
        <div class="flex items-center gap-1 {{ $mobile ? 'justify-between' : '' }}">
            <a href="{{ $href }}"
                @if ($item->target->value === '_blank') target="_blank" @endif
                @if ($item->rel) rel="{{ $item->rel }}" @elseif ($item->target->value === '_blank') rel="noopener noreferrer" @endif
                class="{{ $mobile ? 'block flex-1 rounded px-3 py-2 text-gray-700 hover:bg-gray-50' : 'hover:text-amber-600' }}">
                {{ $item->label }}
            </a>

            @if ($hasChildren)
                <button type="button" @click="open = !open" :aria-expanded="open"
                    aria-label="Show submenu for {{ $item->label }}"
                    class="{{ $mobile ? 'px-3 text-gray-400 hover:text-amber-600' : 'text-gray-400 hover:text-amber-600' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            @endif
        </div>

        @if ($hasChildren)
            @if ($mobile)
                <ul x-show="open" x-cloak class="ml-4 space-y-1 border-l border-gray-100 pl-2">
                    @foreach ($item->children as $child)
                        <x-menu-item :item="$child" :mobile="true" />
                    @endforeach
                </ul>
            @else
                <ul x-show="open" x-cloak x-transition
                    class="absolute left-0 top-full z-20 mt-2 min-w-[10rem] space-y-1 rounded-md border border-gray-100 bg-white p-2 shadow-lg">
                    @foreach ($item->children as $child)
                        <x-menu-item :item="$child" />
                    @endforeach
                </ul>
            @endif
        @endif
    </li>
@endif
