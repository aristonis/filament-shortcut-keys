<x-filament-panels::page>
    @forelse ($groups as $group)
        <section class="fi-section-content-ctn">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ trans('filament-shortcut-keys::shortcut-keys.sets.' . $group['set']) }}
            </h2>

            <table class="mt-2 w-full text-sm">
                <thead class="sr-only">
                    <tr>
                        <th>{{ trans('filament-shortcut-keys::shortcut-keys.columns.action') }}</th>
                        <th>{{ trans('filament-shortcut-keys::shortcut-keys.columns.keys') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['rows'] as $row)
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td class="py-2 pe-4 text-gray-700 dark:text-gray-300">{{ $row['label'] }}</td>
                            <td class="py-2 text-end">
                                @foreach ($row['keys'] as $key)
                                    <kbd class="mx-0.5 rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 font-mono text-xs text-gray-700 dark:border-white/20 dark:bg-white/5 dark:text-gray-200">{{ $key }}</kbd>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ trans('filament-shortcut-keys::shortcut-keys.empty') }}
        </p>
    @endforelse

    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ trans('filament-shortcut-keys::shortcut-keys.page_level_note') }}
    </p>
</x-filament-panels::page>
