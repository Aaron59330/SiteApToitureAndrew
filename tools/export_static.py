"""Exporte une version statique du site dans dist/ (pour un hébergement mutualisé)."""
import os, re, shutil, zipfile
ROOT=os.path.join(os.path.dirname(os.path.abspath(__file__)),'..')
SRC=os.path.join(ROOT,'public'); DST=os.path.join(ROOT,'dist')
shutil.rmtree(DST,ignore_errors=True)
shutil.copytree(SRC,DST,ignore=shutil.ignore_patterns('admin'))
for f in os.listdir(DST):
    if not f.endswith('.html'): continue
    p=os.path.join(DST,f); s=open(p,encoding='utf8').read()
    s=re.sub(r'<a href="/admin"[^>]*>Administration</a>','',s)
    if f=='realisations.html':
        s=s.replace('<div class="gallery" id="gallery"></div>','<div class="gallery-empty"><h3>Nos réalisations arrivent bientôt</h3><p>Contactez-nous pour découvrir nos derniers chantiers.</p><a class="btn" href="contact.html">Nous contacter</a></div>')
    open(p,'w',encoding='utf8').write(s)
shutil.copy(os.path.join(ROOT,'tools','static','contact.php'),DST)
open(os.path.join(DST,'.htaccess'),'w').write('''RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
RewriteCond %{HTTP_HOST} ^www\\.(.*)$ [NC]
RewriteRule ^(.*)$ https://%1/$1 [R=301,L]
DirectoryIndex index.html
''')
open(os.path.join(DST,'style.css'),'a').write('\n.gallery-empty{text-align:center;max-width:560px;margin:20px auto;padding:48px 28px;background:var(--card);border:1px solid var(--line);border-radius:16px}.gallery-empty p{color:var(--muted);margin:10px 0 24px}\n')
z=os.path.join(ROOT,'aptoiture-site.zip')
with zipfile.ZipFile(z,'w',zipfile.ZIP_DEFLATED) as zf:
    for d,_,fs in os.walk(DST):
        for n in fs: zf.write(os.path.join(d,n),os.path.relpath(os.path.join(d,n),DST))
print('ok',z)
