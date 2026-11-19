const express = require('express');
const multer = require('multer');
const sharp = require('sharp');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { UPLOADS, readJSON, writeJSON } = require('./store');
const auth = require('./auth');
const { process: processPage, sanitize } = require('./render');

const PUBLIC = path.join(__dirname, '..', 'public');
const VIEWS = path.join(__dirname, 'views');
const PORT = process.env.PORT || 3000;

const app = express();
app.disable('x-powered-by');
if (process.env.TRUST_PROXY) app.set('trust proxy', Number(process.env.TRUST_PROXY) || 1);

app.use((req, res, next) => {
  res.setHeader('Content-Security-Policy', "default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('Referrer-Policy', 'same-origin');
  next();
});
app.use(auth.attach);
app.use(express.json({ limit: '1mb' }));

// ---------- pages
const pages = fs.readdirSync(PUBLIC).filter(f => f.endsWith('.html'));
const rawPages = {};
pages.forEach(f => { rawPages[f] = fs.readFileSync(path.join(PUBLIC, f), 'utf8'); });

const getRealisations = () => readJSON('realisations.json', []);
const getOverrides = () => readJSON('content.json', { global: {}, pages: {} });

function sendPage(file) {
  return (req, res) => {
    const admin = req.isAdmin;
    const { $ } = processPage(rawPages[file], {
      admin, page: file, overrides: getOverrides(),
      gallery: file === 'realisations.html' ? getRealisations() : null,
    });
    res.setHeader('Cache-Control', admin ? 'no-store' : 'no-cache');
    res.type('html').send($.html());
  };
}
pages.forEach(f => app.get('/' + f, sendPage(f)));
app.get('/', sendPage('index.html'));

// ---------- connexion
app.get('/admin', (req, res) => {
  if (req.isAdmin) return res.redirect('/');
  res.setHeader('Cache-Control', 'no-store');
  res.sendFile(path.join(VIEWS, 'login.html'));
});
app.post('/api/login', auth.loginLimiter, async (req, res) => {
  if (req.get('x-requested-with') !== 'fetch') return res.status(403).json({ error: 'Requête refusée' });
  const { email, password } = req.body || {};
  if (await auth.verify(email, password)) {
    auth.resetAttempts(req.ip);
    auth.setSession(req, res);
    return res.json({ ok: true });
  }
  res.status(401).json({ error: 'E-mail ou mot de passe incorrect.' });
});
app.post('/api/logout', (req, res) => { auth.clearSession(res); res.json({ ok: true }); });

// ---------- images
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 25 * 1024 * 1024, files: 30 },
  fileFilter: (req, file, cb) => cb(null, /^image\//.test(file.mimetype)),
});
async function saveImage(buf) {
  const out = await sharp(buf, { failOn: 'error' }).rotate()
    .resize({ width: 2000, height: 2000, fit: 'inside', withoutEnlargement: true })
    .webp({ quality: 82 }).toBuffer();
  const name = crypto.randomBytes(10).toString('hex') + '.webp';
  fs.writeFileSync(path.join(UPLOADS, name), out);
  return '/uploads/' + name;
}
app.post('/api/upload', auth.requireAdmin, upload.single('file'), async (req, res) => {
  if (!req.file) return res.status(400).json({ error: 'Aucune image valide reçue.' });
  try { res.json({ url: await saveImage(req.file.buffer) }); }
  catch { res.status(400).json({ error: 'Image illisible.' }); }
});

// ---------- modification des textes / images du site
app.post('/api/content', auth.requireAdmin, (req, res) => {
  const { page, text = {}, img = {} } = req.body || {};
  if (!rawPages[page]) return res.status(400).json({ error: 'Page inconnue' });
  const { texts, imgs } = processPage(rawPages[page], { page });
  const data = getOverrides();
  data.global = data.global || {}; data.pages = data.pages || {}; data.pages[page] = data.pages[page] || {};
  const bucket = key => (key[0] === 'g' ? data.global : data.pages[page]);

  for (const [key, html] of Object.entries(text)) {
    if (!(key in texts) || typeof html !== 'string' || html.length > 5000) continue;
    const clean = sanitize(html);
    if (clean === texts[key]) delete bucket(key)[key];
    else bucket(key)[key] = { o: texts[key], v: clean };
  }
  for (const [key, url] of Object.entries(img)) {
    if (!(key in imgs) || typeof url !== 'string') continue;
    if (url === imgs[key]) delete bucket(key)[key];
    else if (/^\/(uploads|images)\/[\w.\-]+$/.test(url)) bucket(key)[key] = { o: imgs[key], v: url };
  }
  writeJSON('content.json', data);
  res.json({ ok: true });
});

// ---------- réalisations
const newId = () => crypto.randomBytes(6).toString('hex');
const clip = (v, n) => String(v == null ? '' : v).slice(0, n);

app.post('/api/realisations', auth.requireAdmin, upload.array('files', 30), async (req, res) => {
  if (!req.files || !req.files.length) return res.status(400).json({ error: 'Aucune image valide reçue.' });
  const list = getRealisations();
  const added = [];
  for (const f of req.files) {
    try {
      added.push({ id: newId(), file: await saveImage(f.buffer), title: clip(req.body.title, 120), description: clip(req.body.description, 2000), created: new Date().toISOString() });
    } catch {}
  }
  if (!added.length) return res.status(400).json({ error: 'Images illisibles.' });
  writeJSON('realisations.json', added.reverse().concat(list));
  res.json({ ok: true, count: added.length });
});
app.patch('/api/realisations/:id', auth.requireAdmin, (req, res) => {
  const list = getRealisations();
  const it = list.find(x => x.id === req.params.id);
  if (!it) return res.status(404).json({ error: 'Introuvable' });
  if ('title' in req.body) it.title = clip(req.body.title, 120).trim();
  if ('description' in req.body) it.description = clip(req.body.description, 2000).trim();
  writeJSON('realisations.json', list);
  res.json({ ok: true });
});
app.delete('/api/realisations/:id', auth.requireAdmin, (req, res) => {
  const list = getRealisations();
  const it = list.find(x => x.id === req.params.id);
  if (!it) return res.status(404).json({ error: 'Introuvable' });
  writeJSON('realisations.json', list.filter(x => x !== it));
  fs.rm(path.join(UPLOADS, path.basename(it.file)), { force: true }, () => {});
  res.json({ ok: true });
});
app.post('/api/realisations/move', auth.requireAdmin, (req, res) => {
  const { id, dir } = req.body || {};
  const list = getRealisations();
  const i = list.findIndex(x => x.id === id), j = i + (dir === 'up' ? -1 : 1);
  if (i < 0 || j < 0 || j >= list.length) return res.json({ ok: true });
  [list[i], list[j]] = [list[j], list[i]];
  writeJSON('realisations.json', list);
  res.json({ ok: true });
});

// ---------- fichiers statiques
app.use('/uploads', express.static(UPLOADS, { maxAge: '30d', immutable: true, index: false, fallthrough: false }));
app.use(express.static(PUBLIC, { index: false, maxAge: '1h' }));
app.use((req, res) => res.status(404).type('text').send('Page introuvable'));
app.use((err, req, res, next) => {
  if (req.path.startsWith('/api/')) return res.status(err.status || 500).json({ error: 'Erreur serveur' });
  res.status(err.status || 500).type('text').send('Erreur');
});

app.listen(PORT, () => {
  console.log(`Site AP Toiture : http://localhost:${PORT}`);
  if (!auth.admin()) console.log('Aucun compte admin : lancez « npm run set-admin ».');
});
