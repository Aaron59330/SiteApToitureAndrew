<?php
require dirname(__DIR__) . '/_app/lib.php';
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
if (!is_admin()) { header('Location: /admin/'); exit; }
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Messages | AP Toiture</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/admin/admin.css"></head>
<body class="msg-body">
<header class="msg-top"><a href="/admin/" class="back">&larr; Tableau de bord</a><h1>Messages</h1><a href="/" class="back">Voir le site</a></header>
<div class="msg-wrap"><aside id="list" class="msg-list"></aside><section id="view" class="msg-view"><p class="empty">Sélectionnez un message.</p></section></div>
<script>
const $=s=>document.querySelector(s);let all=[],cur=new URLSearchParams(location.search).get('id');
async function api(a,body){const r=await fetch('/api.php?a='+a,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},body:JSON.stringify(body||{})});const d=await r.json().catch(()=>({}));if(r.status===401)location.href='/admin/';if(!r.ok)throw new Error(d.error||'Erreur');return d}
const fmt=d=>new Date(d).toLocaleString('fr-FR',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});
const el=(t,c,x)=>{const n=document.createElement(t);if(c)n.className=c;if(x!=null)n.textContent=x;return n};
function renderList(){const l=$('#list');l.replaceChildren();if(!all.length){l.append(el('p','empty','Aucun message pour le moment.'));return}
 all.forEach(m=>{const b=el('button','mi'+(m.id===cur?' on':'')+(m.read?'':' unread'));b.append(el('b',null,m.nom),el('span',null,(m.type||'Devis')+' · '+fmt(m.date)),el('small',null,(m.message||'').slice(0,70)));b.onclick=()=>open(m.id);l.append(b)})}
async function open(id){cur=id;const m=all.find(x=>x.id===id);renderList();const v=$('#view');v.replaceChildren();if(!m)return;
 if(!m.read){m.read=true;api('msg_read',{id}).catch(()=>{});renderList()}
 const head=el('div','mh');head.append(el('h2',null,m.nom),el('p',null,fmt(m.date)));
 const info=el('div','mi2');const a1=el('a',null,m.tel);a1.href='tel:'+m.tel.replace(/[^\d+]/g,'');const a2=el('a',null,m.email);a2.href='mailto:'+m.email;
 info.append(el('span',null,'Téléphone : '),a1,el('br'),el('span',null,'E-mail : '),a2,el('br'),el('span',null,'Travaux : '+(m.type||'—')));
 const body=el('div','mb',m.message||'(aucun message)');v.append(head,info,body);
 (m.replies||[]).forEach(r=>{const x=el('div','mr');x.append(el('small',null,'Votre réponse · '+fmt(r.date)),el('p',null,r.text));v.append(x)});
 const f=el('form','mf');const ta=el('textarea');ta.rows=6;ta.placeholder='Votre réponse à '+m.nom+'…';ta.required=true;
 const row=el('div','mrow');const send=el('button','btn btn-sm','Envoyer la réponse');send.type='submit';const del=el('button','btn btn-ghost btn-sm','Supprimer');del.type='button';const st=el('span','mst');row.append(send,del,st);f.append(ta,row);v.append(f);
 f.onsubmit=async e=>{e.preventDefault();send.disabled=true;st.textContent='Envoi…';st.className='mst';try{await api('msg_reply',{id,text:ta.value});(m.replies=m.replies||[]).push({date:new Date().toISOString(),text:ta.value});open(id)}catch(x){st.textContent=x.message;st.className='mst bad';send.disabled=false}};
 del.onclick=async()=>{if(!confirm('Supprimer ce message ?'))return;await api('msg_del',{id});all=all.filter(x=>x.id!==id);cur=null;renderList();v.replaceChildren(el('p','empty','Message supprimé.'))}}
(async()=>{all=(await api('msg_list')).messages;renderList();if(cur)open(cur)})();
</script></body></html>
