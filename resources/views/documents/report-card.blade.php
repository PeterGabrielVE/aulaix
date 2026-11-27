<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $card->title }}</title>
    @include('documents._styles')
</head>
<body>
    @include('documents._report-card-body', ['card' => $card])
</body>
</html>
