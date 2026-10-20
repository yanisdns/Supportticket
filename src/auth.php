<?php
// Connexion / inscription (le premier compte créé ouvre l'espace de travail)
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? ''; $em = strtolower(trim($_POST['email'] ?? '')); $pw = $_POST['password'] ?? '';
    if ($a === 'register') {
        if (!filter_var($em, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8 || trim($_POST['name'] ?? '') === '') $err = 'Nom, email valide et mot de passe (8 caractères min.) requis.';
        elseif (q("SELECT 1 FROM users WHERE email=?", [$em])->fetch()) $err = 'Cet email est déjà utilisé.';
        else { q("INSERT INTO users(name,email,hash) VALUES(?,?,?)", [trim($_POST['name']), $em, password_hash($pw, PASSWORD_DEFAULT)]);
               session_regenerate_id(true); $_SESSION['uid'] = (int)$db->lastInsertId(); $_SESSION['name'] = trim($_POST['name']); header('Location: ?'); exit; }
    } elseif ($a === 'login') {
        $u = q("SELECT * FROM users WHERE email=?", [$em])->fetch(PDO::FETCH_ASSOC);
        if ($u && password_verify($pw, $u['hash'])) { session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; $_SESSION['name'] = $u['name']; header('Location: ?'); exit; }
        $err = 'Email ou mot de passe incorrect.';
    }
}
$reg = ($_GET['page'] ?? '') === 'register';
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TicketFlow</title>
<link rel="stylesheet" href="assets/style.css"><script>document.documentElement.dataset.theme=localStorage.theme||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light')</script></head>
<body><main class="public"><div class="card form-card"><div class="logo">◆ TicketFlow</div>
<h1><?= $reg ? 'Créer un compte' : 'Connexion' ?></h1><p class="muted"><?= $reg ? 'Rejoignez votre espace de travail.' : 'Content de vous revoir.' ?></p>
<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="action" value="<?= $reg ? 'register' : 'login' ?>">
<?php if ($reg): ?><label>Nom<input name="name" required></label><?php endif; ?>
<label>Email<input type="email" name="email" required></label>
<label>Mot de passe<input type="password" name="password" required minlength="8"></label>
<button class="btn primary"><?= $reg ? 'Créer mon compte' : 'Se connecter' ?></button></form>
<p class="auth-switch"><?= $reg ? '<a href="?page=login">Déjà inscrit ? Connexion</a>' : '<a href="?page=register">Créer un compte</a>' ?></p>
</div></main></body></html>
