<div class="card">
    <h1>Usuarios Registrados</h1>
    <table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%; margin-top: 15px;">
        <thead>
            <tr style="background-color: #f4f4f4;">
                <th>ID</th>
                <th>Nombre</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="3" align="center">No hay usuarios registrados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= h($user['id']) ?></td>
                        <td><strong><?= h($user['name']) ?></strong></td>
                        <td><?= h($user['email']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <p style="margin-top: 15px;"><a href="/">← Volver al Inicio</a></p>
</div>
