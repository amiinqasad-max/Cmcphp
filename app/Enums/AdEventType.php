<?php

namespace App\Enums;

/**
 * Internal ad analytics events — explicitly NOT official Google AdSense metrics.
 * See docs/ARCHITECTURE.md section 6/26 for the distinction.
 */
enum AdEventType: string
{
    case Requested = 'ad_requested';
    case Loaded = 'ad_loaded';
    case Rendered = 'ad_rendered';
    case Viewable = 'ad_viewable';
}
