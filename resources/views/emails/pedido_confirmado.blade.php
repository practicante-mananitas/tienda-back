<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pedido Confirmado</title>
</head>
<body>
    <h1>¡Gracias por tu compra!</h1>
    <p>Tu pedido fue procesado con éxito.</p>

    <p><strong>Total:</strong> ${{ $pedido->total }}</p>
    <p><strong>Fecha:</strong> {{ $pedido->created_at->format('d/m/Y H:i') }}</p>

    <h3>Productos:</h3>
    <ul>
        @foreach ($pedido->items as $item)
            <li>{{ $item->producto }} × {{ $item->cantidad }} = ${{ $item->precio_unitario * $item->cantidad }}</li>
        @endforeach
    </ul>
</body>
</html>
