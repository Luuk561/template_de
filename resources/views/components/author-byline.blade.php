@props(['teamMember', 'date' => null, 'compact' => false])

@php
    $primaryColor = getSetting('primary_color', '#7c3aed');
@endphp

@if($teamMember)
    @if($compact)
        {{-- Compact version - voor aan begin van artikel --}}
        <div class="flex items-center gap-3 py-4 border-y border-gray-200">
            @if($teamMember->photo_url)
            <img
                src="{{ $teamMember->photo_url }}"
                alt="{{ $teamMember->name }}"
                class="w-12 h-12 rounded-full object-cover"
            >
            @else
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-100 to-blue-100 flex items-center justify-center">
                <span class="text-lg font-bold text-white">{{ substr($teamMember->name, 0, 1) }}</span>
            </div>
            @endif

            <div class="flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('team.show', $teamMember->slug) }}" class="font-medium text-gray-900 hover:underline">
                        {{ $teamMember->name }}
                    </a>
                    <span class="text-gray-400">•</span>
                    <span class="text-sm text-gray-600">{{ $teamMember->role }}</span>
                </div>
                @if($date)
                <div class="text-sm text-gray-500 mt-0.5">
                    {{ $date->format('d M Y') }}
                </div>
                @endif
            </div>
        </div>
    @else
        {{-- Full version - voor aan einde van artikel --}}
        <div class="bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-xl p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row gap-6 items-start">
                @if($teamMember->photo_url)
                <img
                    src="{{ $teamMember->photo_url }}"
                    alt="{{ $teamMember->name }}"
                    class="w-24 h-24 sm:w-32 sm:h-32 rounded-xl object-cover flex-shrink-0 shadow-lg"
                >
                @else
                <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-xl bg-gradient-to-br from-purple-100 to-blue-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-4xl font-bold text-white">{{ substr($teamMember->name, 0, 1) }}</span>
                </div>
                @endif

                <div class="flex-1 space-y-3">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900">{{ $teamMember->name }}</h3>
                        <p class="text-lg font-medium" style="color: {{ $primaryColor }}">{{ $teamMember->role }}</p>
                    </div>

                    <blockquote class="italic text-gray-600 border-l-4 pl-4" style="border-color: {{ $primaryColor }}">
                        "{{ $teamMember->quote }}"
                    </blockquote>

                    <div class="flex flex-wrap gap-2">
                        <span class="inline-block px-3 py-1 text-xs font-medium bg-white border border-gray-300 text-gray-700 rounded-full">
                            Focus: {{ ucfirst($teamMember->focus) }}
                        </span>
                        <span class="inline-block px-3 py-1 text-xs font-medium bg-white border border-gray-300 text-gray-700 rounded-full">
                            {{ ucfirst($teamMember->tone) }}
                        </span>
                    </div>

                    <a
                        href="{{ route('team.show', $teamMember->slug) }}"
                        class="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                        style="color: {{ $primaryColor }}"
                    >
                        Ansehen profiel van {{ $teamMember->name }} →
                    </a>
                </div>
            </div>
        </div>
    @endif
@endif
