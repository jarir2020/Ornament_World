<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Ornaments World' }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body class="layout-svelte">
    @yield('content')
</body>
</html>
