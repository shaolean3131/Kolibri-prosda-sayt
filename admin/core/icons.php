<?php
defined('KOLIBRI') or exit;

/**
 * Inline SVG icons (24×24). Filled glyphs use var(--icon-cut) for inner
 * details so they read as cut-outs on any background.
 */
function icon(string $name, string $class = 'icon'): string
{
    $cut = 'var(--icon-cut, #fff)';
    $gearTeeth = '';
    for ($i = 0; $i < 8; $i++) {
        $gearTeeth .= '<rect x="10.4" y="1.6" width="3.2" height="5" rx="1" transform="rotate(' . ($i * 45) . ' 12 12)"/>';
    }

    $icons = [
        // sidebar
        'analytics' => '<rect x="2" y="2.5" width="20" height="14" rx="2"/><path d="M11 16h2v3.3l3.3 2.1-1.1 1.6L12 21l-3.2 2-1.1-1.6 3.3-2.1z"/><path d="M6 12.5l3.5-4 3 2.8 5.5-5.3" fill="none" stroke="' . $cut . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'catalog'   => '<rect x="3" y="3" width="18" height="18" rx="2.5"/><g fill="' . $cut . '"><rect x="6" y="6.5" width="3" height="3" rx=".6"/><rect x="10.5" y="7" width="7" height="2" rx="1"/><rect x="6" y="11" width="3" height="3" rx=".6"/><rect x="10.5" y="11.5" width="7" height="2" rx="1"/><rect x="6" y="15.5" width="3" height="2.5" rx=".6"/><rect x="10.5" y="16" width="4.5" height="2" rx="1"/></g>',
        'clients'   => '<path d="M2 6h2.2v13.8H18V22H4a2 2 0 0 1-2-2z"/><rect x="6" y="2" width="16" height="16" rx="2"/><g fill="' . $cut . '"><circle cx="14" cy="8" r="2.6"/><path d="M9.3 15.2c.7-2.4 2.5-3.7 4.7-3.7s4 1.3 4.7 3.7z"/></g>',
        'push'      => '<rect x="8" y="2" width="12" height="20" rx="2.6"/><rect x="11.5" y="17.6" width="5" height="1.6" rx=".8" fill="' . $cut . '"/><rect x="2.5" y="6.5" width="10" height="7" rx="2" stroke="' . $cut . '" stroke-width="1.6"/>',
        'orders'    => '<rect x="2" y="3.5" width="20" height="17" rx="2"/><g fill="' . $cut . '"><rect x="5" y="7" width="3.2" height="2.2" rx=".5"/><rect x="10" y="7" width="9" height="2.2" rx=".5"/><rect x="5" y="10.9" width="3.2" height="2.2" rx=".5"/><rect x="10" y="10.9" width="9" height="2.2" rx=".5"/><rect x="5" y="14.8" width="3.2" height="2.2" rx=".5"/><rect x="10" y="14.8" width="9" height="2.2" rx=".5"/></g>',
        'percent'   => '<rect x="3" y="3" width="18" height="18" rx="2.5"/><g fill="' . $cut . '"><circle cx="8.6" cy="8.6" r="1.9"/><circle cx="15.4" cy="15.4" r="1.9"/></g><path d="M16.5 7.5l-9 9" stroke="' . $cut . '" stroke-width="2" stroke-linecap="round"/>',
        'reviews'   => '<path d="M6 3h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H8.5L4 21.8V5a2 2 0 0 1 2-2z"/><path fill="' . $cut . '" d="M13 6.2l1.25 2.6 2.85.4-2.07 2 .5 2.83L13 12.7l-2.53 1.33.5-2.83-2.07-2 2.85-.4z"/>',
        'app'       => '<rect x="2" y="3" width="18" height="15" rx="2"/><g fill="' . $cut . '"><circle cx="5" cy="5.8" r=".95"/><circle cx="7.8" cy="5.8" r=".95"/></g><rect x="13.5" y="9" width="9" height="13.5" rx="2" stroke="' . $cut . '" stroke-width="1.6"/><rect x="16.4" y="19.2" width="3.2" height="1.2" rx=".6" fill="' . $cut . '"/>',
        'loyalty'   => '<ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 7c0 1.7 3.6 3 8 3s8-1.3 8-3v4.5c0 1.7-3.6 3-8 3s-8-1.3-8-3z"/><path d="M4 13c0 1.7 3.6 3 8 3s8-1.3 8-3v4.5c0 1.7-3.6 3-8 3s-8-1.3-8-3z"/>',
        'marketing' => '<rect x="2" y="2.5" width="20" height="12.5" rx="1.6"/><rect x="5.5" y="15" width="2.6" height="7" rx=".6"/><rect x="15.9" y="15" width="2.6" height="7" rx=".6"/><g fill="' . $cut . '"><rect x="5.5" y="6" width="8.5" height="2.2" rx="1.1"/><rect x="5.5" y="10" width="5" height="2.2" rx="1.1"/></g>',
        'settings'  => '<g>' . $gearTeeth . '<circle cx="12" cy="12" r="7"/></g><circle cx="12" cy="12" r="2.9" fill="' . $cut . '"/>',
        'api'       => '<path d="M20.5 11H19V7c0-1.1-.9-2-2-2h-4V3.5C13 2.12 11.88 1 10.5 1S8 2.12 8 3.5V5H4c-1.1 0-1.99.9-1.99 2v3.8H3.5c1.49 0 2.7 1.21 2.7 2.7s-1.21 2.7-2.7 2.7H2V20c0 1.1.9 2 2 2h3.8v-1.5c0-1.49 1.21-2.7 2.7-2.7s2.7 1.21 2.7 2.7V22H17c1.1 0 2-.9 2-2v-4h1.5c1.38 0 2.5-1.12 2.5-2.5S21.88 11 20.5 11z"/>',

        // ui (stroke)
        'bell'      => '<path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.6 2H4.4z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 20.5a2 2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'user'      => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="9.4" r="3.3" fill="' . $cut . '"/><path d="M5.9 18.3c1.4-2.3 3.6-3.5 6.1-3.5s4.7 1.2 6.1 3.5A8.4 8.4 0 0 1 12 20.6a8.4 8.4 0 0 1-6.1-2.3z" fill="' . $cut . '"/>',
        'chevron'   => '<path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'dots'      => '<circle cx="5.5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="18.5" cy="12" r="1.6"/>',
        'burger'    => '<path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'close'     => '<path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'check'     => '<path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
        'external'  => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'logout'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'refresh'   => '<path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v4.5h-4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'download'  => '<path d="M12 4v11M7 10.5l5 5 5-5M5 20h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'heart'     => '<path d="M12 20.5s-8-4.8-8-10.6A4.4 4.4 0 0 1 12 7.3a4.4 4.4 0 0 1 8 2.6c0 5.8-8 10.6-8 10.6z"/>',
        'camera'    => '<path d="M4 8.6A1.6 1.6 0 0 1 5.6 7h2.1l1.6-2.2h5.4L16.3 7h2.1A1.6 1.6 0 0 1 20 8.6v9a1.6 1.6 0 0 1-1.6 1.6H5.6A1.6 1.6 0 0 1 4 17.6z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12.8" r="3.2" fill="none" stroke="currentColor" stroke-width="1.7"/>',
        'trash'     => '<path d="M4.5 6.5h15M9.5 6.5V4.8c0-.4.4-.8.8-.8h3.4c.4 0 .8.4.8.8v1.7M6.5 6.5l.8 12.2c.1.8.7 1.3 1.4 1.3h6.6c.7 0 1.3-.5 1.4-1.3l.8-12.2M10 10.5v5.5M14 10.5v5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
        'gear'      => '<g fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" transform="translate(1.2 1.2) scale(.9)"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></g>',
        'sort'      => '<path d="M8 19V5M4.5 8.5 8 5l3.5 3.5M16 5v14M12.5 15.5 16 19l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
        'search'    => '<circle cx="10.8" cy="10.8" r="6.3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M15.6 15.6l4.6 4.6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'plus'      => '<path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'arrow-up'  => '<path d="M12 19V5M6 11l6-6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'arrow-down'=> '<path d="M12 5v14M6 13l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'image'     => '<rect x="3" y="4" width="18" height="16" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="9" cy="9.8" r="1.8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3.8 17.5l5-5 4 4 3-3 4.4 4.4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'image-up'  => '<path d="M14 4H5.5A2.5 2.5 0 0 0 3 6.5v11A2.5 2.5 0 0 0 5.5 20H14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M3.6 16.5l4.4-4.4 3.8 3.8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="8.6" cy="8.6" r="1.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M18.5 21v-9M15 15.5l3.5-3.5 3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'apple'     => '<path d="M16.4 12.7c0-2.4 2-3.6 2.1-3.7-1.1-1.7-2.9-1.9-3.5-1.9-1.5-.2-2.9.9-3.6.9-.8 0-1.9-.9-3.1-.8-1.6 0-3.1.9-3.9 2.4-1.7 2.9-.4 7.2 1.2 9.6.8 1.2 1.7 2.4 3 2.4 1.2 0 1.6-.8 3.1-.8s1.8.8 3.1.8c1.3 0 2.1-1.2 2.9-2.3.9-1.3 1.3-2.6 1.3-2.7 0 0-2.5-1-2.6-3.9zM14 5.6c.7-.8 1.1-1.9 1-3-.9 0-2.1.6-2.8 1.4-.6.7-1.2 1.8-1 2.9 1.1.1 2.1-.5 2.8-1.3z"/>',
        'play'      => '<path d="M5.2 3.3c-.3.3-.4.7-.4 1.2v15c0 .5.2.9.4 1.2l8.3-8.7z"/><path d="M16.3 15.1 13.5 12l2.8-2.9 3.3 1.9c.9.5.9 1.5 0 2z" opacity=".75"/><path d="M13.5 12 5.2 20.7c.4.4 1 .4 1.6.1l9.5-5.7z" opacity=".55"/><path d="M13.5 12 16.3 9.1 6.8 3.3c-.6-.4-1.2-.3-1.6 0z" opacity=".9"/>',
        'globe'     => '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 12h17M12 3.5c2.4 2.4 3.5 5.2 3.5 8.5s-1.1 6.1-3.5 8.5c-2.4-2.4-3.5-5.2-3.5-8.5s1.1-6.1 3.5-8.5z" fill="none" stroke="currentColor" stroke-width="1.6"/>',
        'calendar'  => '<rect x="3.5" y="5" width="17" height="15" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 10h17M8 3v4M16 3v4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'box'       => '<path d="M3.5 7.5L12 3l8.5 4.5v9L12 21l-8.5-4.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M3.5 7.5L12 12l8.5-4.5M12 12v9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
    ];

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">' . ($icons[$name] ?? '') . '</svg>';
}
