"""Prépare le dossier dist/ + aptoiture-site.zip pour un hébergement mutualisé PHP (IONOS)."""
import os, shutil, zipfile, secrets
ROOT=os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)),'..'))
SRC=os.path.join(ROOT,'public'); ST=os.path.join(ROOT,'tools','static'); DST=os.path.join(ROOT,'dist')
shutil.rmtree(DST,ignore_errors=True)
os.makedirs(os.path.join(DST,'_app','pages')); os.makedirs(os.path.join(DST,'admin')); os.makedirs(os.path.join(DST,'uploads'))
# fichiers publics (css, js, images)
for f in os.listdir(SRC):
    p=os.path.join(SRC,f)
    if f=='admin': continue
    if f.endswith('.html'): shutil.copy(p,os.path.join(DST,'_app','pages',f)); continue
    (shutil.copytree if os.path.isdir(p) else shutil.copy)(p,os.path.join(DST,f))
# admin : css + js (adapté aux routes PHP)
shutil.copy(os.path.join(SRC,'admin','admin.css'),os.path.join(DST,'admin','admin.css'))
js=open(os.path.join(SRC,'admin','admin.js'),encoding='utf8').read()
i0=js.index("  // ---------- réalisations"); i1=js.index("  setEditing(true);")
js=js[:i0]+open(os.path.join(ST,'admin','gallery.js'),encoding='utf8').read()+js[i1:]
for a,b in [("'/api/upload'","'/api.php?a=upload'"),("'/api/content'","'/api.php?a=content'"),("'/api/logout'","'/api.php?a=logout'"),
            ("location.href = '/admin'","location.href = '/admin/'")]:
    assert a in js,a
    js=js.replace(a,b)
js=js.replace("btnOut, msg);","btnOut, msg);\n  bar.insertBefore(el('a', { class: 'adm-link2', href: '/admin/avis.php' }, 'Avis'), btnEdit);\n  bar.insertBefore(el('a', { class: 'adm-link2', href: '/admin/messages.php' }, 'Messages'), btnEdit);\n  bar.insertBefore(el('a', { class: 'adm-link2', href: '/admin/' }, 'Tableau de bord'), btnEdit);",1)
open(os.path.join(DST,'admin','admin.js'),'w',encoding='utf8').write(js)
open(os.path.join(DST,'admin','admin.css'),'a').write('\n.adm-link2{color:#fff;border:1px solid #444;padding:8px 14px;border-radius:999px;font:600 .78rem var(--f1);letter-spacing:.06em}.adm-link2:hover{border-color:#777}\n')
# données de départ : chantiers et photos existants (utilisés seulement si le site n'a pas encore ses propres données)
SEED=os.path.join(ROOT,'tools','seed')
if os.path.isdir(SEED):
    os.makedirs(os.path.join(DST,'_app','seed'),exist_ok=True)
    shutil.copy(os.path.join(SEED,'realisations.json'),os.path.join(DST,'_app','seed','realisations.json'))
    for f in os.listdir(os.path.join(SEED,'uploads')): shutil.copy(os.path.join(SEED,'uploads',f),os.path.join(DST,'uploads',f))
# PHP
for f in ['page.php','api.php','contact.php','avis.php']: shutil.copy(os.path.join(ST,f),DST)
for f in ['index.php','messages.php','avis.php']: shutil.copy(os.path.join(ST,'admin',f),os.path.join(DST,'admin'))
for f in ['lib.php','.htaccess']: shutil.copy(os.path.join(ST,'_app',f),os.path.join(DST,'_app'))
shutil.copy(os.path.join(ST,'root.htaccess'),os.path.join(DST,'.htaccess'))
shutil.copy(os.path.join(ST,'uploads.htaccess'),os.path.join(DST,'uploads','.htaccess'))
kf=os.path.join(ROOT,'tools','.setup_key')
key=open(kf).read().strip() if os.path.exists(kf) else secrets.token_hex(4).upper()
open(kf,'w').write(key)
hf=os.path.join(ROOT,'tools','.admin_hash'); ahash=open(hf).read().strip()
open(os.path.join(DST,'_app','config.php'),'w').write(f"""<?php
define('ADMIN_DEFAULT', ['email' => 'apcouvreur@gmail.com', 'hash' => '{ahash}']);
define('SETUP_KEY', '{key}');
define('SITE_URL', 'https://aptoiture.eu');
define('MAIL_TO', 'apcouvreur@gmail.com');
define('MAIL_DOMAIN', 'aptoiture.eu');
""")
z=os.path.join(ROOT,'aptoiture-site.zip')
with zipfile.ZipFile(z,'w',zipfile.ZIP_DEFLATED) as zf:
    for d,_,fs in os.walk(DST):
        for n in fs: zf.write(os.path.join(d,n),os.path.relpath(os.path.join(d,n),DST))
print('ok',z,'CODE INSTALLATION:',key)
