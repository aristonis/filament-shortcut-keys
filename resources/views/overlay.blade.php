{{-- Styles are embedded so the overlay renders correctly even in a host app that purges unused CSS. --}}
<style>
    #filament-shortcut-keys-overlay {
        position: fixed;
        inset: 0;
        z-index: 50;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 4rem 1rem;
        overflow-y: auto;
        background: rgba(0, 0, 0, .5);
    }
    #filament-shortcut-keys-overlay[hidden] { display: none; }
    #filament-shortcut-keys-overlay .fsk-panel {
        width: 100%;
        max-width: 40rem;
        border-radius: .75rem;
        background: #fff;
        color: #111827;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, .2);
    }
    :where(.dark) #filament-shortcut-keys-overlay .fsk-panel { background: #1f2937; color: #f9fafb; }
    #filament-shortcut-keys-overlay .fsk-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1rem 1.25rem; border-bottom: 1px solid rgba(0, 0, 0, .1);
    }
    :where(.dark) #filament-shortcut-keys-overlay .fsk-head { border-color: rgba(255, 255, 255, .1); }
    #filament-shortcut-keys-overlay .fsk-title { font-size: 1rem; font-weight: 600; }
    #filament-shortcut-keys-overlay .fsk-close {
        border: 0; background: transparent; cursor: pointer; font-size: 1.25rem; line-height: 1; color: inherit; opacity: .6;
    }
    #filament-shortcut-keys-overlay .fsk-close:hover { opacity: 1; }
    #filament-shortcut-keys-overlay .fsk-body { padding: 1rem 1.25rem; }
    #filament-shortcut-keys-overlay .fsk-set { font-size: .8rem; font-weight: 600; opacity: .7; margin: 1rem 0 .35rem; }
    #filament-shortcut-keys-overlay .fsk-set:first-child { margin-top: 0; }
    #filament-shortcut-keys-overlay .fsk-row {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .3rem 0; font-size: .875rem;
    }
    #filament-shortcut-keys-overlay kbd {
        margin-inline-start: .25rem; padding: .1rem .35rem; border-radius: .25rem;
        border: 1px solid currentColor; font-family: monospace; font-size: .75rem; opacity: .8;
    }
    #filament-shortcut-keys-overlay .fsk-foot {
        padding: .85rem 1.25rem; border-top: 1px solid rgba(0, 0, 0, .1); font-size: .875rem;
    }
    :where(.dark) #filament-shortcut-keys-overlay .fsk-foot { border-color: rgba(255, 255, 255, .1); }
</style>

<div id="filament-shortcut-keys-overlay" hidden>
    <div class="fsk-panel" role="dialog" aria-modal="true" aria-labelledby="fsk-overlay-title">
        <div class="fsk-head">
            <h2 id="fsk-overlay-title" class="fsk-title">{{ trans('filament-shortcut-keys::shortcut-keys.overlay.title') }}</h2>
            <button type="button" class="fsk-close" data-fsk-overlay-close aria-label="{{ trans('filament-shortcut-keys::shortcut-keys.overlay.close') }}">&times;</button>
        </div>

        <div class="fsk-body">
            @forelse ($groups as $group)
                <p class="fsk-set">{{ trans('filament-shortcut-keys::shortcut-keys.sets.' . $group['set']) }}</p>
                @foreach ($group['rows'] as $row)
                    <div class="fsk-row">
                        <span>{{ $row['label'] }}</span>
                        <span>
                            @foreach ($row['keys'] as $key)
                                <kbd>{{ $key }}</kbd>
                            @endforeach
                        </span>
                    </div>
                @endforeach
            @empty
                <p>{{ trans('filament-shortcut-keys::shortcut-keys.empty') }}</p>
            @endforelse
        </div>

        <div class="fsk-foot">
            <a href="{{ $referenceUrl }}">{{ trans('filament-shortcut-keys::shortcut-keys.overlay.full_reference') }}</a>
        </div>
    </div>
</div>
