# Mise en ligne sur un VPS IONOS (Ubuntu)

Remplacez `VOTRE-DOMAINE.fr` par votre nom de domaine et `IP_DU_SERVEUR` par l'IP du VPS.

## 1. DNS (espace client IONOS → Domaines → DNS)
- Enregistrement **A** : `@` → `IP_DU_SERVEUR`
- Enregistrement **A** : `www` → `IP_DU_SERVEUR`
(la propagation peut prendre de quelques minutes à quelques heures)

## 2. Connexion et installation
```bash
ssh root@IP_DU_SERVEUR
apt update && apt upgrade -y
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs nginx git certbot python3-certbot-nginx ufw
npm install -g pm2
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw --force enable
```

## 3. Récupérer le site
```bash
cd /var/www
git clone -b claude/stoic-dirac-rmvelg https://github.com/Aaron59330/siteaptoitureandrew.git ap-toiture
cd ap-toiture && npm install --omit=dev
mkdir -p /var/data/ap-toiture
```

## 4. Lancer le site (pm2)
```bash
cd /var/www/ap-toiture
PORT=3000 NODE_ENV=production TRUST_PROXY=1 DATA_DIR=/var/data/ap-toiture \
ADMIN_EMAIL="apcouvreur@gmail.com" ADMIN_PASSWORD="UN_MOT_DE_PASSE_LONG" \
pm2 start server/index.js --name ap-toiture --update-env
pm2 save && pm2 startup
```
(Une fois l'admin créé, vous pourrez relancer sans `ADMIN_PASSWORD`.)

## 5. Nginx
```bash
cat > /etc/nginx/sites-available/ap-toiture <<'EOF'
server {
    listen 80;
    server_name VOTRE-DOMAINE.fr www.VOTRE-DOMAINE.fr;
    client_max_body_size 25M;
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF
ln -s /etc/nginx/sites-available/ap-toiture /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

## 6. HTTPS gratuit
```bash
certbot --nginx -d VOTRE-DOMAINE.fr -d www.VOTRE-DOMAINE.fr
```

## 7. Mises à jour et sauvegarde
```bash
cd /var/www/ap-toiture && git pull && npm install --omit=dev && pm2 restart ap-toiture
tar czf ~/sauvegarde-$(date +%F).tgz /var/data/ap-toiture   # photos + modifications
```
Le dossier `/var/data/ap-toiture` contient tout ce qui est modifié via l'administration : sauvegardez-le régulièrement.
