<?php
require __DIR__ . '/../src/bootstrap.php';
if (!isset($_SESSION['uid']) && !isset($_GET['form']) && ($_POST['action'] ?? '') !== 'ticket') { require __DIR__ . '/../src/auth.php'; exit; }
if (($_GET['page'] ?? '') === 'logout') { session_destroy(); header('Location: ?page=login'); exit; }
if (($_GET['api'] ?? '') === 'move' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($S[$_POST['status'] ?? ''])) q("UPDATE tickets SET status=? WHERE id=?", [$_POST['status'], (int)$_POST['id']]);
    http_response_code(204); exit;
}
$users = isset($_SESSION['uid']) ? q("SELECT name FROM users ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) : [];
$pid = (int)($_GET['p'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    $pid = (int)($_POST['p'] ?? 0);
    if ($a === 'project' && trim($_POST['name'] ?? '') !== '') {
        q("INSERT INTO projects(name) VALUES(?)", [trim($_POST['name'])]);
        $pid = (int)$db->lastInsertId();
        foreach ([['Email', 'email', ''], ['Priorité', 'select', 'Basse,Moyenne,Haute']] as $f)
            q("INSERT INTO fields(project_id,label,type,options) VALUES(?,?,?,?)", [$pid, ...$f]);
        header("Location: ?p=$pid"); exit;
    }
    if ($a === 'field' && trim($_POST['label'] ?? '') !== '') {
        q("INSERT INTO fields(project_id,label,type,options,required) VALUES(?,?,?,?,?)",
          [$pid, trim($_POST['label']), $_POST['type'], $_POST['options'] ?? '', isset($_POST['required']) ? 1 : 0]);
        back();
    }
    if ($a === 'delfield') { q("DELETE FROM fields WHERE id=?", [(int)$_POST['id']]); back(); }
    if ($a === 'ticket') {
        $d = [];
        foreach (q("SELECT id FROM fields WHERE project_id=?", [$pid]) as $f) $d[$f['id']] = trim($_POST['f'][$f['id']] ?? '');
        q("INSERT INTO tickets(project_id,title,data,created) VALUES(?,?,?,datetime('now'))", [$pid, trim($_POST['title']), json_encode($d)]);
        header("Location: ?form=$pid&sent=1"); exit;
    }
    if ($a === 'status') { q("UPDATE tickets SET status=? WHERE id=?", [$_POST['status'], (int)$_POST['id']]); back(); }
    if ($a === 'update') { q("UPDATE tickets SET assignee=?,due=? WHERE id=?", [trim($_POST['assignee']), $_POST['due'], (int)$_POST['id']]); back(); }
    if ($a === 'comment' && trim($_POST['body']) !== '') { q("INSERT INTO comments(ticket_id,body,created) VALUES(?,?,datetime('now'))", [(int)$_POST['id'], trim($_POST['body'])]); back(); }
    if ($a === 'delticket') { q("DELETE FROM tickets WHERE id=?", [(int)$_POST['id']]); header("Location: ?p=$pid"); exit; }
}

$projects = q("SELECT * FROM projects ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$formId = (int)($_GET['form'] ?? 0);
$view = $_GET['view'] ?? 'board';
$tid = (int)($_GET['t'] ?? 0);
if (!$pid && !$formId && $projects) $pid = $projects[0]['id'];
$cur = $pid ? q("SELECT * FROM projects WHERE id=?", [$pid])->fetch(PDO::FETCH_ASSOC) : null;
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>TicketFlow</title><meta name="csrf" content="<?= $_SESSION['csrf'] ?>"><link rel="stylesheet" href="assets/style.css"><script src="assets/app.js" defer></script>
<script>document.documentElement.dataset.theme=localStorage.theme||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light')</script>
</head><body>
<?php if ($formId):
    $fp = q("SELECT * FROM projects WHERE id=?", [$formId])->fetch(PDO::FETCH_ASSOC);
    $ff = q("SELECT * FROM fields WHERE project_id=? ORDER BY id", [$formId])->fetchAll(PDO::FETCH_ASSOC); ?>
<main class="public"><div class="card form-card">
    <div class="logo">◆ TicketFlow</div>
    <?php if (!$fp): ?><p>Formulaire introuvable.</p>
    <?php elseif (isset($_GET['sent'])): ?>
        <h1>Merci !</h1><p class="muted">Votre ticket a bien été envoyé dans « <?= h($fp['name']) ?> ».</p>
        <a class="btn" href="?form=<?= $formId ?>">Envoyer un autre ticket</a>
    <?php else: ?>
        <h1><?= h($fp['name']) ?></h1><p class="muted">Remplissez le formulaire pour créer un ticket.</p>
        <form method="post"><input type="hidden" name="action" value="ticket"><input type="hidden" name="p" value="<?= $formId ?>">
            <label>Titre du ticket *<input name="title" required></label>
            <?php foreach ($ff as $f): ?><label><?= h($f['label']) ?><?= $f['required'] ? ' *' : '' ?><?= input($f) ?></label><?php endforeach; ?>
            <button class="btn primary">Envoyer</button>
        </form>
    <?php endif; ?>
</div></main>
<?php else: ?>
<div class="app">
<aside class="side">
    <div class="logo">◆ TicketFlow</div>
    <nav>
        <div class="side-title">Projets</div>
        <?php foreach ($projects as $p): ?>
            <a class="nav-item <?= $p['id'] == $pid ? 'active' : '' ?>" href="?p=<?= $p['id'] ?>"><span class="dot"></span><?= h($p['name']) ?></a>
        <?php endforeach; ?>
        <form method="post" class="new-project"><input type="hidden" name="action" value="project">
            <input name="name" placeholder="+ Nouveau projet" required></form>
    </nav>
    <div class="user"><span>👤 <?= h($_SESSION['name'] ?? '') ?></span><a href="?page=logout">Quitter</a></div>
    <button class="btn ghost" id="theme" type="button">🌙 Mode sombre</button>
</aside>
<main class="main">
<?php if (!$cur): ?>
    <div class="empty"><h1>Bienvenue 👋</h1><p class="muted">Créez votre premier projet dans le menu de gauche.</p></div>
<?php else:
    $fields = q("SELECT * FROM fields WHERE project_id=? ORDER BY id", [$pid])->fetchAll(PDO::FETCH_ASSOC);
    $tickets = q("SELECT * FROM tickets WHERE project_id=? ORDER BY id DESC", [$pid])->fetchAll(PDO::FETCH_ASSOC);
    $me = "?p=$pid&view=$view"; ?>
    <header class="top">
        <h1><?= h($cur['name']) ?></h1>
        <div class="actions"><input id="q" type="search" placeholder="Rechercher un ticket…"><a class="btn" target="_blank" href="?form=<?= $pid ?>">Ouvrir le formulaire ↗</a></div>
    </header>
    <div class="stats"><?php foreach ($S as $k => $l): ?><div class="stat"><b><?= count(array_filter($tickets, fn($t) => $t['status'] === $k)) ?></b><span><?= $l ?></span></div><?php endforeach; ?><div class="stat"><b><?= count($tickets) ?></b><span>Total</span></div></div>
    <div class="tabs">
        <a class="<?= $view === 'board' ? 'on' : '' ?>" href="?p=<?= $pid ?>&view=board">Tableau</a>
        <a class="<?= $view === 'list' ? 'on' : '' ?>" href="?p=<?= $pid ?>&view=list">Liste</a>
        <a class="<?= $view === 'fields' ? 'on' : '' ?>" href="?p=<?= $pid ?>&view=fields">Champs du formulaire</a>
    </div>

    <?php if ($view === 'fields'): ?>
        <div class="card">
            <h3>Champs personnalisés</h3>
            <p class="muted">Chaque champ apparaît dans le formulaire public et dans les tickets.</p>
            <table><tr><th>Nom</th><th>Type</th><th>Options</th><th></th></tr>
            <?php foreach ($fields as $f): ?><tr><td><?= h($f['label']) ?><?= $f['required'] ? ' *' : '' ?></td><td><?= $T[$f['type']] ?></td><td><?= h($f['options']) ?></td>
                <td><form method="post"><input type="hidden" name="action" value="delfield"><input type="hidden" name="id" value="<?= $f['id'] ?>"><input type="hidden" name="back" value="<?= h($me) ?>"><button class="link">Supprimer</button></form></td></tr><?php endforeach; ?></table>
            <form method="post" class="inline"><input type="hidden" name="action" value="field"><input type="hidden" name="p" value="<?= $pid ?>"><input type="hidden" name="back" value="<?= h($me) ?>">
                <input name="label" placeholder="Nom du champ" required>
                <select name="type"><?php foreach ($T as $k => $v) echo "<option value='$k'>$v</option>"; ?></select>
                <input name="options" placeholder="Options (a,b,c)">
                <label class="chk"><input type="checkbox" name="required"> Obligatoire</label>
                <button class="btn primary">Ajouter</button></form>
        </div>

    <?php elseif ($view === 'list'): ?>
        <div class="card"><table>
            <tr><th>Ticket</th><th>Statut</th><th>Assigné</th><th>Échéance</th><?php foreach ($fields as $f) echo '<th>' . h($f['label']) . '</th>'; ?></tr>
            <?php foreach ($tickets as $t): $d = json_decode($t['data'], true) ?: []; ?>
                <tr data-row><td><a href="<?= h($me) ?>&t=<?= $t['id'] ?>"><?= h($t['title']) ?></a></td>
                    <td><span class="pill s-<?= $t['status'] ?>"><?= $S[$t['status']] ?></span></td>
                    <td><?= h($t['assignee']) ?: '—' ?></td><td><?= h($t['due']) ?: '—' ?></td>
                    <?php foreach ($fields as $f) echo '<td>' . (h($d[$f['id']] ?? '') ?: '—') . '</td>'; ?></tr>
            <?php endforeach; if (!$tickets) echo '<tr><td colspan="9" class="muted">Aucun ticket pour le moment.</td></tr>'; ?>
        </table></div>

    <?php else: ?>
        <div class="board">
        <?php foreach ($S as $k => $label): $col = array_filter($tickets, fn($t) => $t['status'] === $k); ?>
            <section class="col" data-status="<?= $k ?>"><h3><?= $label ?> <span class="count"><?= count($col) ?></span></h3>
            <?php foreach ($col as $t): $d = json_decode($t['data'], true) ?: []; ?>
                <article class="ticket" draggable="true" data-id="<?= $t['id'] ?>">
                    <a href="<?= h($me) ?>&t=<?= $t['id'] ?>" class="t-title"><?= h($t['title']) ?></a>
                    <div class="tags"><?php foreach ($fields as $f) if (!empty($d[$f['id']])) echo '<span class="tag" title="' . h($f['label']) . '">' . h($d[$f['id']]) . '</span>'; ?></div>
                    <div class="meta"><span><?= $t['due'] ? '📅 ' . h($t['due']) : '' ?></span>
                        <?php if ($t['assignee']): ?><span class="avatar"><?= h(mb_strtoupper(mb_substr($t['assignee'], 0, 1))) ?></span><?php endif; ?></div>
                    <form method="post"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="back" value="<?= h($me) ?>">
                        <select name="status" onchange="this.form.submit()"><?php foreach ($S as $sk => $sl) echo "<option value='$sk'" . ($sk === $t['status'] ? ' selected' : '') . ">$sl</option>"; ?></select></form>
                </article>
            <?php endforeach; ?></section>
        <?php endforeach; ?></div>
    <?php endif; ?>

    <?php if ($tid && ($t = q("SELECT * FROM tickets WHERE id=? AND project_id=?", [$tid, $pid])->fetch(PDO::FETCH_ASSOC))):
        $d = json_decode($t['data'], true) ?: [];
        $cm = q("SELECT * FROM comments WHERE ticket_id=? ORDER BY id", [$tid])->fetchAll(PDO::FETCH_ASSOC); ?>
        <a class="overlay" href="<?= h($me) ?>"></a>
        <aside class="drawer">
            <a class="close" href="<?= h($me) ?>">✕</a>
            <h2><?= h($t['title']) ?></h2>
            <p class="muted">Créé le <?= h($t['created']) ?></p>
            <form method="post" class="props"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="back" value="<?= h("$me&t=$tid") ?>">
                <label>Assigné à<select name="assignee"><option value="">—</option><?php foreach ($users as $u) echo "<option" . ($u === $t['assignee'] ? " selected" : "") . ">" . h($u) . "</option>"; ?></select></label>
                <label>Échéance<input type="date" name="due" value="<?= h($t['due']) ?>"></label>
                <?php foreach ($fields as $f): ?><div class="prop"><span><?= h($f['label']) ?></span><b><?= h($d[$f['id']] ?? '') ?: '—' ?></b></div><?php endforeach; ?>
                <button class="btn primary">Enregistrer</button></form>
            <h3>Commentaires</h3>
            <?php foreach ($cm as $c): ?><div class="comment"><?= nl2br(h($c['body'])) ?><small><?= h($c['created']) ?></small></div><?php endforeach; ?>
            <form method="post"><input type="hidden" name="action" value="comment"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="back" value="<?= h("$me&t=$tid") ?>">
                <textarea name="body" rows="2" placeholder="Écrire un commentaire…" required></textarea><button class="btn">Commenter</button></form>
            <form method="post" onsubmit="return confirm('Supprimer ce ticket ?')"><input type="hidden" name="action" value="delticket"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="p" value="<?= $pid ?>"><button class="link danger">Supprimer le ticket</button></form>
        </aside>
    <?php endif; ?>
<?php endif; ?>
</main></div>
<?php endif; ?>
<script>
const b=document.getElementById('theme'),r=document.documentElement,u=()=>b&&(b.textContent=r.dataset.theme==='dark'?'☀️ Mode clair':'🌙 Mode sombre');u();
b&&b.addEventListener('click',()=>{r.dataset.theme=r.dataset.theme==='dark'?'light':'dark';localStorage.theme=r.dataset.theme;u()});
</script>
</body></html>
