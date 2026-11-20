<x-filament-panels::page>
    {{--
        Filament's compiled theme ships its own component classes but not Tailwind's utilities, so the
        markup this page adds is styled here, on Filament's colour and size tokens, and needs no custom
        theme. The class names stay clear of the cheatsheet's, which renders on this page too.
    --}}
    <style>
        .fsk-manage-maps { margin: 0; padding: 0; list-style: none; font-size: var(--text-sm); line-height: 1.25rem; }
        .fsk-manage-map {
            display: flex; align-items: center; justify-content: space-between; gap: calc(var(--spacing) * 4);
            padding-block: calc(var(--spacing) * 2); color: var(--gray-700);
        }
        .fsk-manage-map:where(.dark, .dark *) { color: var(--gray-300); }
        .fsk-manage-map + .fsk-manage-map { border-top: 1px solid var(--gray-200); }
        .fsk-manage-map + .fsk-manage-map:where(.dark, .dark *) { border-top-color: rgb(255 255 255 / .1); }
        .fsk-manage-badges { display: flex; flex-wrap: wrap; gap: calc(var(--spacing) * 1); }
        .fsk-manage-note { font-size: var(--text-sm); color: var(--gray-500); }
        .fsk-manage-note:where(.dark, .dark *) { color: var(--gray-400); }
    </style>

    <x-filament::section :heading="trans('filament-shortcut-keys::shortcut-keys.manage.your_maps')">
        <ul class="fsk-manage-maps">
            @foreach ($maps as $map)
                <li class="fsk-manage-map">
                    <span>{{ $map['label'] }}</span>
                    <span class="fsk-manage-badges">
                        @if ($map['isActive'])
                            <x-filament::badge color="primary">
                                {{ trans('filament-shortcut-keys::shortcut-keys.manage.active') }}
                            </x-filament::badge>
                        @endif
                        @if ($map['isDefault'])
                            <x-filament::badge color="gray">
                                {{ trans('filament-shortcut-keys::shortcut-keys.manage.default') }}
                            </x-filament::badge>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>

    @unless ($isPersonal)
        <p class="fsk-manage-note">
            {{ trans('filament-shortcut-keys::shortcut-keys.manage.locked_note') }}
        </p>
    @endunless

    @if ($canAuthor)
        <p class="fsk-manage-note">
            {{ trans('filament-shortcut-keys::shortcut-keys.manage.authoring_note') }}
        </p>
    @endif
</x-filament-panels::page>
