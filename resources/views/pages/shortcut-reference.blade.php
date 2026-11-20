<x-filament-panels::page>
    {{--
        Filament's compiled theme ships its own component classes but not Tailwind's utilities, so the
        markup this page adds is styled here, on Filament's colour and size tokens, and needs no custom
        theme. The class names stay clear of the cheatsheet's, which renders on this page too.
    --}}
    <style>
        .fsk-ref-table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); line-height: 1.25rem; }
        .fsk-ref-table td { padding-block: calc(var(--spacing) * 2); color: var(--gray-700); vertical-align: middle; }
        .fsk-ref-table td:where(.dark, .dark *) { color: var(--gray-300); }
        .fsk-ref-table tbody tr + tr td { border-top: 1px solid var(--gray-200); }
        .fsk-ref-table tbody tr + tr td:where(.dark, .dark *) { border-top-color: rgb(255 255 255 / .1); }
        .fsk-ref-keys { text-align: end; white-space: nowrap; padding-inline-start: calc(var(--spacing) * 4); }
        .fsk-ref-key {
            display: inline-block; margin-inline-start: calc(var(--spacing) * 1); padding: .125rem .375rem;
            border: 1px solid var(--gray-300); border-radius: var(--radius-md); background: var(--gray-50);
            color: var(--gray-700); font-family: var(--font-mono); font-size: var(--text-xs); line-height: 1rem;
        }
        .fsk-ref-key:where(.dark, .dark *) { border-color: rgb(255 255 255 / .2); background: rgb(255 255 255 / .05); color: var(--gray-200); }
        .fsk-ref-then { margin-inline-start: calc(var(--spacing) * 1); font-size: var(--text-xs); color: var(--gray-500); }
        .fsk-ref-then:where(.dark, .dark *) { color: var(--gray-400); }
        .fsk-ref-note { font-size: var(--text-sm); color: var(--gray-500); }
        .fsk-ref-note:where(.dark, .dark *) { color: var(--gray-400); }
        .fsk-ref-visually-hidden {
            position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden;
            clip-path: inset(50%); white-space: nowrap; border: 0;
        }
    </style>

    @forelse ($groups as $group)
        <x-filament::section :heading="trans('filament-shortcut-keys::shortcut-keys.sets.' . $group['set'])">
            <table class="fsk-ref-table">
                {{-- Hidden from sight but not from screen readers, which announce each cell by its column. --}}
                <thead class="fsk-ref-visually-hidden">
                    <tr>
                        <th scope="col">{{ trans('filament-shortcut-keys::shortcut-keys.columns.action') }}</th>
                        <th scope="col">{{ trans('filament-shortcut-keys::shortcut-keys.columns.keys') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['rows'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="fsk-ref-keys">
                                @foreach ($row['keys'] as $key)
                                    @if ($row['sequence'] && ! $loop->first)
                                        <span class="fsk-ref-then">{{ trans('filament-shortcut-keys::shortcut-keys.overlay.then') }}</span>
                                    @endif
                                    <kbd class="fsk-ref-key">{{ $key }}</kbd>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @empty
        <p class="fsk-ref-note">
            {{ trans('filament-shortcut-keys::shortcut-keys.empty') }}
        </p>
    @endforelse

    <p class="fsk-ref-note">
        {{ trans('filament-shortcut-keys::shortcut-keys.page_level_note') }}
    </p>
</x-filament-panels::page>
