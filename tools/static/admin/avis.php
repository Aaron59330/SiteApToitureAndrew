<?php
require dirname(__DIR__) . '/_app/lib.php';
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
if (!is_admin()) { header('Location: /admin/'); exit; }
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Avis clients | AP Toiture</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/admin/admin.css"></head>
<body class="msg-body">
<header class="msg-top"><a href="/admin/" class="back">&larr; Tableau de bord</a><h1>Avis clients</h1><a href="/" class="back">Voir le site</a></header>
<main class="av-wrap">
<section class="av-new"><h2>Demander un avis à un client</h2>
<p>Saisissez l'e-mail du client : il reçoit un lien personnel pour laisser son avis. Vous validez ensuite l'avis avant qu'il apparaisse sur le site.</p>
<form id="nf" class="av-form"><input type="email" name="email" placeholder="E-mail du client" required><input type="text" name="name" placeholder="Prénom / nom (facultatif)" maxlength="80"><select name="project_id"><option value="">Chantier : à choisir plus tard</option></select><button class="btn btn-sm" type="submit">Envoyer la demande</button></form>
<p class="mst" id="nmsg"></p></section>
<section><h2>Avis reçus et demandes</h2><div id="list" class="av-list"></div></section>
</main>
<script>
const $=s=>document.querySelector(s);let reviews=[],projects=[];
async function api(a,body){const r=await fetch('/api.php?a='+a,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},body:JSON.stringify(body||{})});const d=await r.json().catch(()=>({}));if(r.status===401)location.href='/admin/';if(!r.ok)throw new Error(d.error||'Erreur');return d}
const el=(t,c,x)=>{const n=document.createElement(t);if(c)n.className=c;if(x!=null)n.textContent=x;return n};
const fmt=d=>d?new Date(d).toLocaleDateString('fr-FR',{day:'2-digit',month:'short',year:'numeric'}):'';
const ST={requested:['En attente du client','wait'],submitted:['À valider','new'],published:['Publié','ok'],hidden:['Masqué','off']};
const stars=n=>'★'.repeat(n)+'☆'.repeat(5-n);
function projSelect(cur,onchange){const s=el('select');s.append(new Option('Aucun chantier lié',''));projects.forEach(p=>s.append(new Option(p.title+(p.category?' · '+p.category:''),p.id)));s.value=cur||'';s.onchange=()=>onchange(s.value);return s}
function draw(){const l=$('#list');l.replaceChildren();if(!reviews.length){l.append(el('p','empty','Aucun avis pour le moment.'));return}
 reviews.forEach(r=>{const c=el('article','av-card av-'+ST[r.status][1]);
  const top=el('div','av-top');top.append(el('b',null,r.name||r.email),el('span','av-st',ST[r.status][0]),el('small',null,r.email+' · '+fmt(r.created)));c.append(top);
  if(r.status==='requested'){
   const a=el('div','av-actions');const rs=el('button','btn btn-sm','Renvoyer l\'e-mail');rs.onclick=async()=>{rs.disabled=true;try{const d=await api('review_resend',{id:r.id});alert(d.mailed?'E-mail renvoyé.':'L\'envoi a échoué. Lien à transmettre : '+d.link)}catch(x){alert(x.message)}rs.disabled=false};
   const cp=el('button','btn btn-ghost btn-sm','Copier le lien');cp.onclick=async()=>{const u=location.origin+'/avis.php?t='+r.token;try{await navigator.clipboard.writeText(u);cp.textContent='Copié ✓'}catch(e){prompt('Lien à copier',u)}};
   const dl=el('button','btn btn-ghost btn-sm','Supprimer');dl.onclick=()=>del(r);a.append(rs,cp,dl);c.append(a)}
  else{
   c.append(el('div','av-stars',stars(r.rating)));const t=el('textarea');t.value=r.text;t.rows=3;c.append(t);
   const nm=el('input');nm.value=r.name;nm.placeholder='Nom affiché';nm.maxLength=80;
   const row=el('div','av-row');row.append(el('label',null,'Nom affiché'),nm,el('label',null,'Lié au chantier'),projSelect(r.project_id,async v=>{await api('review_update',{id:r.id,project_id:v});r.project_id=v}));c.append(row);
   const a=el('div','av-actions');
   const save=el('button','btn btn-ghost btn-sm','Enregistrer le texte');save.onclick=async()=>{await api('review_update',{id:r.id,text:t.value,name:nm.value});save.textContent='Enregistré ✓'};
   const pub=el('button','btn btn-sm',r.status==='published'?'Masquer du site':'Publier sur le site');pub.onclick=async()=>{await api('review_update',{id:r.id,text:t.value,name:nm.value,status:r.status==='published'?'hidden':'published'});load()};
   const dl=el('button','btn btn-ghost btn-sm','Supprimer');dl.onclick=()=>del(r);a.append(pub,save,dl);c.append(a)}
  l.append(c)})}
async function del(r){if(!confirm('Supprimer définitivement ?'))return;await api('review_del',{id:r.id});load()}
async function load(){const d=await api('review_list');reviews=d.reviews;projects=d.projects;const s=$('#nf select');while(s.options.length>1)s.remove(1);projects.forEach(p=>s.append(new Option(p.title,p.id)));draw()}
$('#nf').onsubmit=async e=>{e.preventDefault();const f=e.target,b=f.querySelector('button'),m=$('#nmsg');b.disabled=true;m.className='mst';m.textContent='Envoi…';
 try{const d=await api('review_request',Object.fromEntries(new FormData(f)));m.textContent=d.mailed?'Demande envoyée ✓':'Demande créée, mais l\'e-mail n\'a pas pu partir. Lien à transmettre : '+d.link;f.reset();load()}catch(x){m.className='mst bad';m.textContent=x.message}b.disabled=false};
load();
</script></body></html>
