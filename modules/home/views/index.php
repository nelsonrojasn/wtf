<div class='card'>
    <h1>¡Hola desde What The Framework!</h1>
    <p>Lo que quieras hacer!</p>
    <p>FSP Rules Engine: 15 + 27 = <strong><?= h($fsp_result ?? 'N/A') ?></strong></p>
    <p>Petición procesada en <code><?= number_format($time_sec ?? 0, 6) ?> s</code></p>
</div>