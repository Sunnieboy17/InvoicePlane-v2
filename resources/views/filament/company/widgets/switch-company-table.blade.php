@php
    $companies = auth()->user()?->companies()->orderBy('name')->get() ?? collect();
    $current   = request('tenant');
@endphp

<div class="space-y-1 py-1">
    @forelse ($companies as $company)
        <a
            href="{{ url('/' . $company->search_code . '/dashboard') }}"
            @class([
                'flex items-center gap-3 px-4 py-2 rounded-lg text-sm transition',
                'bg-primary-500/10 text-primary-600 font-semibold dark:text-primary-400' => $company->search_code === $current,
                'hover:bg-gray-100 dark:hover:bg-white/5 text-gray-700 dark:text-gray-200' => $company->search_code !== $current,
            ])
        >
            <x-heroicon-o-building-office-2 class="h-4 w-4 shrink-0 opacity-60" />
            <span class="truncate">{{ $company->name }}</span>

            @if ($company->search_code === $current)
                <x-heroicon-o-check class="ml-auto h-4 w-4 text-primary-500" />
            @endif
        </a>
    @empty
        <p class="px-4 py-2 text-sm text-gray-400">No companies available.</p>
    @endforelse
</div>
