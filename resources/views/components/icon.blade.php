@props(['name'=>'grid'])
@php
$paths = [
 'grid'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
 'cart'=>'M3 3h2l3 12h10l3-9H6 M9 20h.01 M18 20h.01',
 'box'=>'m3 7 9-5 9 5v10l-9 5-9-5V7z M3 7l9 5 9-5 M12 12v10 M7 4.8l10 5.5',
 'users'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
 'truck'=>'M1 3h14v13H1z M15 8h4l3 4v4h-7 M5.5 20a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5 M18.5 20a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5',
 'cash'=>'M3 5h18v14H3z M3 9h18 M7 15h3 M16 15h1',
 'chart'=>'M4 3v18h17 M8 16v-5 M13 16V7 M18 16v-9',
 'arrow'=>'M7 17 17 7 M7 7h10v10',
 'plus'=>'M12 5v14 M5 12h14',
 'search'=>'M21 21l-6-6 M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0',
 'logout'=>'M9 21H4V3h5 M14 8l5 4-5 4 M9 12h10',
 'settings'=>'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1 1-3z',
 'shield'=>'m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6l9-4z M8 12l3 3 5-6',
 'return'=>'M9 4 3 10l6 6 M3 10h12a6 6 0 0 1 0 12',
 'list'=>'M9 6h12 M9 12h12 M9 18h12 M3 6h.01 M3 12h.01 M3 18h.01',
 'tag'=>'M3 3h8l10 10-8 8L3 11V3z M7 7h.01',
 'download'=>'M12 3v12 M7 10l5 5 5-5 M3 16v5h18v-5',
 'clock'=>'M12 7v5l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
 'check'=>'M5 12l4 4L19 6',
 'menu'=>'M3 6h18 M3 12h18 M3 18h18',
 'edit'=>'m16 3 5 5-12 12H4v-5L16 3z',
 'store'=>'M3 10h18l-2-7H5l-2 7z M5 10v11h14V10 M9 21v-7h6v7',
];
@endphp
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}"/></svg>
