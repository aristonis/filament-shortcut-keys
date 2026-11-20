<?php

return [

    'nav_label' => 'Keyboard shortcuts',
    'title' => 'Keyboard shortcuts',
    'subheading' => 'Shortcuts available across this panel.',
    'empty' => 'No shortcuts are configured yet.',
    'page_level_note' => 'Page actions change from page to page, so they appear on each page and in the ? overlay.',

    'columns' => [
        'action' => 'Action',
        'keys' => 'Keys',
    ],

    'sets' => [
        'navigation' => 'Navigation',
        'custom' => 'Custom bindings',
        'global' => 'Actions',
        'table' => 'Table',
        'row-action' => 'Row actions',
        'page' => 'Page',
    ],

    'table_behaviors' => [
        'search' => 'Search',
        'row-up' => 'Move up a row',
        'row-down' => 'Move down a row',
        'select' => 'Select the row',
        'edit' => 'Edit the row',
        'delete' => 'Delete the row',
        'page-prev' => 'Previous page',
        'page-next' => 'Next page',
        'cursor-down' => 'Start or move the row cursor down',
        'cursor-up' => 'Start or move the row cursor up',
        'cursor-release' => 'Leave the row cursor',
    ],

    'overlay' => [
        'title' => 'Keyboard shortcuts',
        'close' => 'Close',
        'full_reference' => 'View full reference',
        // Between the two keys of a sequence such as g then o.
        'then' => 'then',
        'single_keys' => 'Single-key shortcuts',
        'single_keys_help' => 'Turn off to stop keys that fire without Ctrl, Alt or Cmd, such as g then a letter. Saved in this browser.',
    ],

    'manage' => [
        'nav_label' => 'Manage shortcuts',
        'title' => 'Manage shortcuts',
        'your_maps' => 'Available maps',
        'active' => 'Active',
        'default' => 'Default',
        'change_active' => 'Change active map',
        'remap' => 'Remap a shortcut',
        'disable' => 'Disable a shortcut',
        'reset' => 'Reset to default',
        'add_custom' => 'Add custom binding',
        'create_preset' => 'Create preset',
        'clone_preset' => 'Clone preset',
        'edit_preset' => 'Edit preset',
        'edit_preset_confirm' => 'I understand this changes the map for every admin using it.',
        'set_default' => 'Set panel default',
        'set_default_confirm' => 'I understand every admin without a map of their own switches to this preset.',
        'saved' => 'Saved.',
        'failed' => 'Something went wrong. Please try again.',
        'map_custom' => 'Your customizations',
        'map_default' => 'Default preset',
        'map_preset' => 'Preset :number',
        'locked_note' => 'This panel uses a single shared map, so personal customization is turned off.',
        'authoring_note' => 'You can author the shared system presets from the actions above.',

        'fields' => [
            'map' => 'Map',
            'source' => 'Preset to copy',
            'target' => 'Shortcut',
            'letter' => 'Letter',
            'name' => 'Name',
            'kind' => 'Opens',
            'value' => 'Destination',
        ],

        'kinds' => [
            'route' => 'A page in this app',
            'selector' => 'An element on the page',
        ],
    ],

];
