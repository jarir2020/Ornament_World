@extends($layout ?? 'layouts.app')

@section('content')
<?php
$pagePropsJson = htmlspecialchars(
    json_encode($pageProps ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ENT_QUOTES,
    'UTF-8'
);
?>
<div id="ornaments-world-app" data-props="<?= $pagePropsJson ?>"></div>
@vite('resources/css/app.css')
@vite('resources/js/svelte/app.js')
@endsection
