<!DOCTYPE html>
<html>

<head>
    <title><?php echo $title ?? 'WTF - What the Framework'; ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            background: #1e293b;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            text-align: center;
        }

        h1 {
            color: #38bdf8;
            margin-top: 0;
        }

        code {
            background: #0f172a;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: monospace;
            color: #fb7185;
        }
    </style>
</head>

<body>
    <?php echo $yield ?? ''; ?>
</body>

</html>