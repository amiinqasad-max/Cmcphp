<?php

return [

    /*
    |--------------------------------------------------------------------
    | Suggested menu locations
    |--------------------------------------------------------------------
    |
    | Populates the Filament "Location" select on the Menu resource. This
    | is a suggestion list, not a constraint — menus.location is a plain
    | string column (see the create_menus_table migration), so a new
    | location can be typed in and used immediately without a migration
    | or a code change here. Add an entry below when a location becomes
    | common enough to deserve a friendly label in the dropdown.
    |
    */
    'locations' => [
        'primary_navigation' => 'Primary Navigation',
        'header' => 'Header',
        'footer' => 'Footer',
        'mobile_navigation' => 'Mobile Navigation',
    ],

];
