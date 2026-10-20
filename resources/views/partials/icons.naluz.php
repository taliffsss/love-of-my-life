{{-- Every icon a browser, phone or crawler asks for, from the brand (marketing/brand/build_brand.py). The version in the address makes
     browsers, which keep favicons for a long time, fetch a new one when the files change. --}}
@php $iconVersion = (string) @filemtime(base_path('public/favicon.svg')); @endphp
<link rel="icon" href="/favicon.ico?v={{ $iconVersion }}" sizes="48x48" />
<link rel="icon" href="/favicon.svg?v={{ $iconVersion }}" type="image/svg+xml" sizes="any" />
<link rel="icon" href="/icons/favicon-32.png?v={{ $iconVersion }}" type="image/png" sizes="32x32" />
<link rel="icon" href="/icons/favicon-16.png?v={{ $iconVersion }}" type="image/png" sizes="16x16" />
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ $iconVersion }}" sizes="180x180" />
<link rel="mask-icon" href="/safari-pinned-tab.svg?v={{ $iconVersion }}" color="#b0456a" />
<meta name="msapplication-TileColor" content="#1a0d17" />
<meta name="msapplication-config" content="/browserconfig.xml" />
