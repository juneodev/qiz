<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jouer à un quizz</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji"; margin:0; background:#0f172a; color:#e2e8f0; }
        .container { max-width: 560px; margin: 80px auto; padding: 24px; background: #111827; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.35); }
        h1 { margin:0 0 8px; font-size: 28px; }
        p { margin: 0 0 20px; color:#93c5fd; }
        form { display:flex; gap:12px; }
        input[type="text"] { flex:1; padding:12px 14px; border-radius: 8px; border:1px solid #334155; background:#0b1220; color:#e2e8f0; font-size:16px; }
        button { padding:12px 16px; border:0; border-radius: 8px; background:#3b82f6; color:white; font-weight:600; cursor:pointer; }
        button:hover { background:#2563eb; }
        .error { margin-top: 10px; color:#fecaca; }
        .hint { font-size: 14px; color: #94a3b8; margin-top: 8px; }
        .footer { margin-top: 20px; font-size: 13px; color:#94a3b8; }
        a { color:#93c5fd; }
    </style>
</head>
<body>
<div class="container">
    <h1>Jouer à un quizz</h1>
    <p>Entrez l'identifiant (UUID) du quizz pour commencer à jouer.</p>

    <form action="{{ route('quiz.enter.submit') }}" method="POST">
        @csrf
        <input type="text" name="uuid" value="{{ old('uuid') }}" placeholder="Ex: 8f14e45f-ea9e-4a16-9b2d-1234567890ab" required>
        <button type="submit">Continuer</button>
    </form>

    @error('uuid')
    <div class="error">{{ $message }}</div>
    @enderror

    <div class="hint">Vous ne connaissez pas l'identifiant ? Demandez le lien de partage au créateur du quizz.</div>

    <div class="footer">
        <a href="{{ route('home') }}">Retour à l'accueil</a>
    </div>
</div>
</body>
</html>
