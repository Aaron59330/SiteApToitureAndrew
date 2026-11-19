const cheerio = require('cheerio');

const INLINE = new Set(['br', 'b', 'strong', 'em', 'i']);
const SKIP = new Set(['script', 'style', 'svg', 'textarea', 'select', 'option', 'input', 'noscript', 'iframe']);

const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

// Nettoie un fragment HTML : ne garde que texte, <br>, <b>, <strong>, <em>, <i>
function sanitize(html) {
  const $ = cheerio.load('<div id="r">' + String(html || '') + '</div>', null, false);
  const walk = nodes => {
    let out = '';
    nodes.each((_, n) => {
      if (n.type === 'text') out += esc(n.data.replace(/\s+/g, ' '));
      else if (n.type === 'tag') {
        if (n.name === 'br') out += '<br>';
        else if (n.name === 'b' || n.name === 'strong') out += '<b>' + walk($(n).contents()) + '</b>';
        else if (n.name === 'em' || n.name === 'i') out += '<em>' + walk($(n).contents()) + '</em>';
        else if (n.name === 'div' || n.name === 'p') out += (out && !out.endsWith('<br>') ? '<br>' : '') + walk($(n).contents());
        else if (!SKIP.has(n.name)) out += walk($(n).contents());
      }
    });
    return out;
  };
  return walk($('#r').contents()).replace(/(<br>)+$/, '').trim();
}

const isScopeGlobal = ($, el) => $(el).closest('header, footer, .topbar').length > 0;

// Parcourt la page, repère textes et images modifiables, applique les modifications enregistrées.
function process(raw, { admin = false, overrides = {}, page = '', gallery = null } = {}) {
  const $ = cheerio.load(raw);
  const $body = $('body');

  if (gallery) fillGallery($, gallery, admin);

  // 1. Entoure les textes « mixtes » (texte + éléments non-inline) dans des <span>
  $body.find('*').each((_, el) => {
    if (SKIP.has(el.name) || $(el).closest('[data-noedit]').length || $(el).closest('svg').length) return;
    const kids = el.children || [];
    const mixed = kids.some(k => k.type === 'tag' && !INLINE.has(k.name));
    if (!mixed) return;
    kids.forEach(k => {
      if (k.type === 'text' && k.data.trim()) $(k).replaceWith($('<span></span>').text(k.data));
    });
  });

  const texts = {}, imgs = {};
  const counters = { g: 0, p: 0, gi: 0, pi: 0 };
  const ov = { g: overrides.global || {}, p: (overrides.pages || {})[page] || {} };

  const visit = el => {
    if (el.type !== 'tag' || SKIP.has(el.name) || $(el).is('[data-noedit]')) return;
    if (el.name === 'img') {
      const sc = isScopeGlobal($, el) ? 'g' : 'p';
      const key = sc + 'i' + counters[sc + 'i']++;
      const orig = $(el).attr('src') || '';
      imgs[key] = orig;
      const o = (sc === 'g' ? ov.g : ov.p)[key];
      if (o && o.o === orig && typeof o.v === 'string') $(el).attr('src', o.v);
      if (admin) $(el).attr('data-edi', key).attr('data-edi-orig', orig);
      return;
    }
    const kids = el.children || [];
    const leaf = kids.length > 0
      && kids.every(k => k.type === 'text' || k.type === 'comment' || (k.type === 'tag' && INLINE.has(k.name)))
      && $(el).text().trim() !== '';
    if (leaf) {
      const sc = isScopeGlobal($, el) ? 'g' : 'p';
      const key = sc + counters[sc]++;
      const orig = sanitize($(el).html());
      texts[key] = orig;
      const o = (sc === 'g' ? ov.g : ov.p)[key];
      if (o && o.o === orig && typeof o.v === 'string') {
        $(el).html(o.v);
        syncLink($, el);
      }
      if (admin) $(el).attr('data-ed', key).attr('data-ed-orig', orig);
      return;
    }
    kids.forEach(visit);
  };
  $body.children().each((_, el) => visit(el));

  if (admin) {
    $('head').append('<link rel="stylesheet" href="/admin/admin.css">');
    $body.attr('data-admin', '').attr('data-page', page);
    $body.append('<script src="/admin/admin.js" defer></script>');
  }
  return { $, texts, imgs };
}

// Garde les liens « tel: » et « mailto: » cohérents avec le texte modifié
function syncLink($, el) {
  const a = el.name === 'a' ? $(el) : $(el).closest('a');
  if (!a.length) return;
  const href = a.attr('href') || '';
  const txt = a.text().trim();
  if (href.startsWith('tel:')) {
    const d = txt.replace(/[^\d+]/g, '');
    if (d.length >= 6) a.attr('href', 'tel:' + (d.startsWith('0') ? '+33' + d.slice(1) : d));
  } else if (href.startsWith('mailto:') && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(txt)) {
    a.attr('href', 'mailto:' + txt);
  }
}

function fillGallery($, items, admin) {
  const g = $('#gallery');
  if (!g.length) return;
  g.attr('data-noedit', '');
  if (!items.length) {
    g.html('<p class="empty">' + (admin ? 'Aucune photo pour le moment. Utilisez « Ajouter des photos » ci-dessus.' : 'Les réalisations seront bientôt publiées.') + '</p>');
    return;
  }
  const html = items.map(it => {
    const alt = esc((it.title || it.description || 'Réalisation AP Toiture').slice(0, 120)).replace(/"/g, '&quot;');
    return `<figure class="shot" data-id="${it.id}"><a class="shot-img" href="${it.file}"><img src="${it.file}" alt="${alt}" loading="lazy"></a><figcaption><b class="t" data-ph="Ajouter un titre">${esc(it.title || '')}</b><span class="d" data-ph="Ajouter une description du chantier">${esc(it.description || '')}</span></figcaption></figure>`;
  }).join('');
  g.html(html);
}

module.exports = { process, sanitize };
