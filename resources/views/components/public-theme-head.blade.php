{{-- Blocking, small local assets apply the visitor preference before the first paint.
     Independent of Vite so error pages also work when the build is unavailable. --}}
<link rel="stylesheet" href="{{ asset('css/public-theme.css') }}?v={{ filemtime(public_path('css/public-theme.css')) }}">
<script src="{{ asset('js/public-theme.js') }}?v={{ filemtime(public_path('js/public-theme.js')) }}"></script>
