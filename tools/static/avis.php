<?php
require __DIR__ . '/_app/lib.php';
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
$t = (string)($_GET['t'] ?? ''); $r = null;
foreach (read_reviews() as $x) if ($t !== '' && hash_equals($x['token'], $t)) $r = $x;
$state = !$r ? 'invalid' : ($r['status'] === 'requested' ? 'form' : 'done');
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Votre avis | AP Toiture</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/admin/admin.css"></head>
<body class="login-body"><main class="login">
<a href="/" class="logo"><img src="/images/logo.png" alt="AP Toiture" width="134" height="40"></a>
<?php if ($state === 'form'): ?>
<form class="form" id="f"><h3>Votre avis nous intéresse</h3>
<p class="rv-intro">Merci de votre confiance ! Dites-nous ce que vous avez pensé de nos travaux.</p>
<div class="rate" role="radiogroup" aria-label="Note">
<?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" name="rating" id="r<?= $i ?>" value="<?= $i ?>"><label for="r<?= $i ?>" title="<?= $i ?> sur 5">★</label><?php endfor; ?>
</div>
<label>Votre nom<input type="text" name="name" maxlength="80" required value="<?= h($r['name'] ?? '') ?>" placeholder="Ex : Marie D."></label>
<label>Votre avis<textarea name="text" rows="6" maxlength="1500" required placeholder="Comment s'est passé le chantier ? Ponctualité, qualité du travail, propreté…"></textarea></label>
<label class="chk"><input type="checkbox" name="consent" value="1"> J'accepte que mon avis soit publié sur le site d'AP Toiture avec mon nom.</label>
<button class="btn" type="submit">Envoyer mon avis</button><p class="err" id="err" hidden></p></form>
<?php elseif ($state === 'done'): ?>
<div class="form"><h3>Merci pour votre avis&nbsp;!</h3><p class="rv-intro">Votre avis a bien été enregistré. Nous vous remercions pour votre confiance.</p><a class="btn" href="/">Retour au site</a></div>
<?php else: ?>
<div class="form"><h3>Lien invalide</h3><p class="rv-intro">Ce lien n'est pas valide ou a expiré. Contactez-nous au 06 25 44 11 31.</p><a class="btn" href="/">Retour au site</a></div>
<?php endif; ?>
<script>
const f=document.getElementById('f');
if(f)f.addEventListener('submit',async e=>{e.preventDefault();const err=document.getElementById('err');err.hidden=true;const b=f.querySelector('button');b.disabled=true;
 const o=Object.fromEntries(new FormData(f));o.t=<?= json_encode($t) ?>;
 try{const r=await fetch('/api.php?a=review_submit',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},body:JSON.stringify(o)});const d=await r.json().catch(()=>({}));
 if(!r.ok)throw new Error(d.error||'Erreur');location.reload()}catch(x){err.textContent=x.message;err.hidden=false;b.disabled=false}});
</script></main></body></html>
