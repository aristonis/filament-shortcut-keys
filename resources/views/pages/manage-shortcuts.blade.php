<x-filament-panels::page>
    <section class="fi-section-content-ctn">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
            {{ trans('filament-shortcut-keys::shortcut-keys.manage.your_maps') }}
        </h2>

        <ul class="mt-2 divide-y divide-gray-200 text-sm dark:divide-white/10">
            @foreach ($maps as $map)
                <li class="flex items-center justify-between py-2">
                    <span class="text-gray-700 dark:text-gray-300">{{ $map['label'] }}</span>
                    <span class="flex gap-1">
                        @if ($map['isActive'])
                            <span class="rounded bg-primary-50 px-1.5 py-0.5 text-xs text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">{{ trans('filament-shortcut-keys::shortcut-keys.manage.active') }}</span>
                        @endif
                        @if ($map['isDefault'])
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ trans('filament-shortcut-keys::shortcut-keys.manage.default') }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    @unless ($isPersonal)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ trans('filament-shortcut-keys::shortcut-keys.manage.locked_note') }}
        </p>
    @endunless

    @if ($canAuthor)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ trans('filament-shortcut-keys::shortcut-keys.manage.authoring_note') }}
        </p>
    @endif
</x-filament-panels::page>
