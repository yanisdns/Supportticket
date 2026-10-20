<?php
// Bootstrap : session sécurisée, CSRF, base SQLite, helpers.
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$tok = $_SESSION['csrf'];
// Injecte automatiquement le jeton CSRF dans tous les formulaires POST
ob_start(fn($b) => preg_replace('/(<form[^>]*method="post"[^>]*>)/', '$1<input type="hidden" name="csrf" value="' . $tok . '">', $b));

@mkdir(__DIR__ . '/../data', 0775, true);
$db = new PDO('sqlite:' . __DIR__ . '/../data/app.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY,name TEXT,email TEXT UNIQUE,hash TEXT);
CREATE TABLE IF NOT EXISTS projects(id INTEGER PRIMARY KEY,name TEXT);
CREATE TABLE IF NOT EXISTS fields(id INTEGER PRIMARY KEY,project_id INT,label TEXT,type TEXT,options TEXT,required INT DEFAULT 0);
CREATE TABLE IF NOT EXISTS tickets(id INTEGER PRIMARY KEY,project_id INT,title TEXT,status TEXT DEFAULT 'todo',assignee TEXT,due TEXT,data TEXT,created TEXT);
CREATE TABLE IF NOT EXISTS comments(id INTEGER PRIMARY KEY,ticket_id INT,body TEXT,created TEXT);");

$S = ['todo' => 'À faire', 'doing' => 'En cours', 'done' => 'Terminé'];
$T = ['text' => 'Texte', 'email' => 'Email', 'textarea' => 'Texte long', 'date' => 'Date', 'select' => 'Liste déroulante'];
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
function q($sql, $a = []) { global $db; $s = $db->prepare($sql); $s->execute($a); return $s; }
function back() { $b = $_POST['back'] ?? '?'; header('Location: ' . ($b[0] === '?' ? $b : '?')); exit; }
function input($f, $v = '') {
    $n = 'f[' . $f['id'] . ']'; $r = $f['required'] ? 'required' : '';
    if ($f['type'] === 'textarea') return "<textarea name='$n' rows='3' $r>" . h($v) . "</textarea>";
    if ($f['type'] === 'select') {
        $o = "<option value=''>—</option>";
        foreach (array_filter(array_map('trim', explode(',', $f['options']))) as $x) $o .= "<option" . ($x === $v ? ' selected' : '') . ">" . h($x) . "</option>";
        return "<select name='$n' $r>$o</select>";
    }
    return "<input type='{$f['type']}' name='$n' value='" . h($v) . "' $r>";
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'ticket'
    && !hash_equals($tok, (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? ''))) {
    http_response_code(419); exit('Session expirée, rechargez la page.');
}
