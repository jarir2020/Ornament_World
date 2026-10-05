@extends($layout ?? 'layouts.app')

@section('content')
<div id="ornaments-world-app"></div>
@vite('resources/css/app.css')
@vite('resources/js/svelte/app.js')
@endsection
