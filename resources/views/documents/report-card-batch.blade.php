<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boletas de sección</title>
    @include('documents._styles')
</head>
<body>
    @foreach ($cards as $index => $card)
        <div class="{{ $index < count($cards) - 1 ? 'page-break' : '' }}">
            @include('documents._report-card-body', ['card' => $card])
        </div>
    @endforeach
</body>
</html>
