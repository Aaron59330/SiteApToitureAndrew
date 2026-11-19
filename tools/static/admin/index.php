<?php
require dirname(__DIR__) . '/_app/lib.php';
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
$acc = admin_account(); $admin = is_admin();
$unread = 0; if ($admin) foreach (read_json('messages', []) as $m) if (empty($m['read'])) $unread++;
$mode = !$acc ? 'setup' : (!$admin ? 'login' : 'home');
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Administration | AP Toiture</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/admin/admin.css"></head>
<body class="login-body"><main class="login">
<a href="/" class="logo"><img src="/images/mascotte.png" alt="" width="62" height="35"><span>AP <b>TOITURE</b></span></a>
<?php if ($mode === 'setup'): ?>
<form class="form" id="f" data-a="setup"><h3>Créer votre compte administrateur</h3>
<label>Code d'installation<input type="text" name="key" required autocomplete="off"></label>
<label>Votre e-mail<input type="email" name="email" value="apcouvreur@gmail.com" required></label>
<label>Mot de passe (10 caractères minimum)<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
<label>Confirmer le mot de passe<input type="password" name="password2" minlength="10" required autocomplete="new-password"></label>
<button class="btn" type="submit">Créer mon compte</button><p class="err" id="err" hidden></p></form>
<?php elseif ($mode === 'login'): ?>
<form class="form" id="f" data-a="login"><h3>Espace administrateur</h3>
<label>E-mail<input type="email" name="email" autocomplete="username" required autofocus></label>
<label>Mot de passe<input type="password" name="password" autocomplete="current-password" required></label>
<button class="btn" type="submit">Se connecter</button><p class="err" id="err" hidden></p></form>
<?php else: ?>
<div class="form dash"><h3>Bonjour 👋</h3>
<a class="dcard" href="/"><b>Modifier le site</b><span>Cliquez sur un texte ou une photo pour la changer directement sur les pages.</span></a>
<a class="dcard" href="/realisations.html"><b>Gérer les réalisations</b><span>Ajoutez, ordonnez ou supprimez les photos de chantiers.</span></a>
<a class="dcard" href="/admin/messages.php"><b>Messages <?= $unread ? '<i class="badge">' . $unread . '</i>' : '' ?></b><span>Demandes de devis reçues, avec réponse directe depuis le site.</span></a>
<details class="dcard"><summary><b>Changer mon e-mail / mot de passe</b></summary>
<form id="pw" class="pwform"><label>E-mail de connexion<input type="email" name="email" value="<?= h($acc['email']) ?>" required></label>
<label>Mot de passe actuel<input type="password" name="current" required autocomplete="current-password"></label>
<label>Nouveau mot de passe<input type="password" name="new" minlength="10" required autocomplete="new-password"></label>
<button class="btn btn-sm" type="submit">Enregistrer</button><p class="err" id="err" hidden></p></form></details>
<button class="btn btn-ghost" id="out" type="button">Se déconnecter</button></div>
<?php endif; ?>
<a class="back" href="/">&larr; Retour au site</a></main>
<script>
const err=document.getElementById('err');
async function api(a,body){const r=await fetch('/api.php?a='+a,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},body:JSON.stringify(body||{})});const d=await r.json().catch(()=>({}));if(!r.ok)throw new Error(d.error||'Erreur');return d}
const f=document.getElementById('f');
if(f)f.addEventListener('submit',async e=>{e.preventDefault();err.hidden=true;const b=f.querySelector('button');b.disabled=true;
 try{const o=Object.fromEntries(new FormData(f));if(f.dataset.a==='setup'&&o.password!==o.password2)throw new Error('Les mots de passe ne correspondent pas.');
 await api(f.dataset.a,o);location.href='/admin/'}catch(x){err.textContent=x.message;err.hidden=false;b.disabled=false}});
const pw=document.getElementById('pw');
if(pw)pw.addEventListener('submit',async e=>{e.preventDefault();err.hidden=true;try{await api('pw',Object.fromEntries(new FormData(pw)));err.style.color='#6fd08c';err.textContent='Enregistré.';err.hidden=false;pw.reset()}catch(x){err.style.color='';err.textContent=x.message;err.hidden=false}});
const out=document.getElementById('out');
if(out)out.addEventListener('click',async()=>{await api('logout');location.href='/'});
</script></body></html>
