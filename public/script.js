document.getElementById('year').textContent = new Date().getFullYear();

const burger = document.querySelector('.burger');
const menu = document.querySelector('.menu');
burger.addEventListener('click', () => {
  const open = menu.classList.toggle('open');
  burger.setAttribute('aria-expanded', open);
});

const form = document.getElementById('form');
if (form) {
  const ok = form.querySelector('.ok');
  const btn = form.querySelector('button[type=submit]');
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const f = new FormData(form);
    btn.disabled = true; ok.hidden = true; ok.classList.remove('bad');
    try {
      const r = await fetch('contact.php', { method: 'POST', body: f });
      const j = await r.json();
      if (!r.ok || !j.ok) throw new Error('send');
      ok.textContent = 'Merci ! Votre demande a bien été envoyée, nous vous répondons rapidement.';
      form.reset();
    } catch (err) {
      ok.classList.add('bad');
      ok.innerHTML = 'L\u2019envoi a échoué. Appelez-nous au 06 25 44 11 31 ou écrivez à <a href="mailto:apcouvreur@gmail.com">apcouvreur@gmail.com</a>.';
    }
    ok.hidden = false; btn.disabled = false;
  });
}

// Fenêtre de détail d'un chantier (galerie de photos + description)
(function () {
  if (document.body.hasAttribute('data-admin')) return;
  const cards = document.querySelectorAll('.proj');
  const E = (t, c, x) => { const n = document.createElement(t); if (c) n.className = c; if (x != null) n.textContent = x; return n; };
  let box, photos = [], idx = 0, lastFocus;
  function para(text) { // une ligne = un paragraphe ; **gras**
    const wrap = document.createDocumentFragment();
    String(text || '').split(/\n+/).map(l => l.trim()).filter(Boolean).forEach(line => {
      const p = E('p'); line.split(/(\*\*[^*]+\*\*)/).forEach(seg => {
        if (/^\*\*[^*]+\*\*$/.test(seg)) p.append(E('b', null, seg.slice(2, -2))); else if (seg) p.append(document.createTextNode(seg));
      }); wrap.append(p);
    }); return wrap;
  }
  const show = i => {
    idx = (i + photos.length) % photos.length;
    box.querySelector('.pv-stage img').src = photos[idx];
    box.querySelector('.pv-count').textContent = (idx + 1) + ' / ' + photos.length;
    box.querySelectorAll('.pv-th').forEach((t, k) => t.classList.toggle('on', k === idx));
    const on = box.querySelector('.pv-th.on'); if (on && on.scrollIntoView) on.scrollIntoView({ block: 'nearest', inline: 'center' });
  };
  const close = () => { if (!box) return; box.remove(); box = null; document.body.classList.remove('pv-open'); document.removeEventListener('keydown', key); if (lastFocus) lastFocus.focus(); };
  const key = e => { if (e.key === 'Escape') close(); else if (e.key === 'ArrowRight') show(idx + 1); else if (e.key === 'ArrowLeft') show(idx - 1); };
  function open(card) {
    photos = JSON.parse(card.dataset.photos || '[]'); if (!photos.length) return;
    lastFocus = card;
    box = E('div', 'pv'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
    const dlg = E('div', 'pv-box');
    const media = E('div', 'pv-media'), stage = E('div', 'pv-stage'), img = E('img'); img.alt = card.dataset.title || '';
    stage.append(E('span', 'pv-count'), img);
    if (photos.length > 1) {
      const pv = E('button', 'pv-nav pv-prev', '‹'), nx = E('button', 'pv-nav pv-next', '›'); pv.type = nx.type = 'button'; pv.setAttribute('aria-label', 'Photo précédente'); nx.setAttribute('aria-label', 'Photo suivante');
      pv.onclick = () => show(idx - 1); nx.onclick = () => show(idx + 1); stage.append(pv, nx);
    }
    media.append(stage);
    if (photos.length > 1) {
      const th = E('div', 'pv-thumbs');
      photos.forEach((u, k) => { const b = E('button', 'pv-th'); b.type = 'button'; const im = E('img'); im.src = u; im.alt = ''; im.loading = 'lazy'; b.append(im); b.onclick = () => show(k); th.append(b); });
      media.append(th);
    }
    const info = E('div', 'pv-info');
    if (card.dataset.cat) info.append(E('span', 'pv-cat', card.dataset.cat));
    info.append(E('h3', null, card.dataset.title || ''));
    const body = E('div', 'pv-desc'); body.append(para(card.dataset.desc)); info.append(body);
    const cta = E('a', 'btn', 'Demander un devis similaire'); cta.href = 'contact.html?projet=' + encodeURIComponent(card.dataset.title || ''); info.append(E('div', 'pv-cta')); info.lastChild.append(cta);
    const x = E('button', 'pv-x', '×'); x.type = 'button'; x.setAttribute('aria-label', 'Fermer'); x.onclick = close;
    dlg.append(media, info, x); box.append(dlg);
    box.addEventListener('click', e => { if (e.target === box) close(); });
    document.body.append(box); document.body.classList.add('pv-open'); document.addEventListener('keydown', key);
    show(0); x.focus();
  }
  cards.forEach(c => { c.addEventListener('click', () => open(c)); c.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(c); } }); });
})();

// « Demander un devis similaire » : préremplit le projet dans le formulaire
(function () {
  const f = document.getElementById('form'); if (!f) return;
  const p = new URLSearchParams(location.search).get('projet'); if (!p) return;
  const m = f.querySelector('textarea[name=message]'); if (m && !m.value) m.value = 'Bonjour, je souhaiterais un devis similaire à votre réalisation : « ' + p + ' ».';
})();

// Apparition douce au défilement
(function () {
  if (document.body.hasAttribute('data-admin') || !('IntersectionObserver' in window)) return;
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .12 });
  document.querySelectorAll('.scard,.step,.why,.head').forEach(el => { el.classList.add('reveal'); io.observe(el); });
})();

// Petits effets sonores (synthétisés, aucun fichier) + ondulation au clic
(function () {
  if (document.body.hasAttribute('data-admin')) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let on = true; try { on = localStorage.getItem('ap_sound') !== 'off'; } catch (e) {}
  let ctx = null;
  const audio = () => { if (!ctx) { const A = window.AudioContext || window.webkitAudioContext; if (A) ctx = new A(); } if (ctx && ctx.state === 'suspended') ctx.resume(); return ctx; };
  function tone(f1, f2, dur, vol, type) {
    const c = audio(); if (!c) return;
    const t = c.currentTime, o = c.createOscillator(), g = c.createGain();
    o.type = type || 'sine'; o.frequency.setValueAtTime(f1, t); o.frequency.exponentialRampToValueAtTime(f2, t + dur * .8);
    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(vol, t + .008); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g).connect(c.destination); o.start(t); o.stop(t + dur + .02);
  }
  const pop = () => { tone(420, 880, .11, .09); setTimeout(() => tone(880, 1320, .09, .045, 'triangle'), 45); }; // « pop » montant
  const soft = () => tone(300, 480, .07, .05);                                                                    // clic discret
  const tick = () => tone(1200, 1500, .035, .018, 'triangle');                                                     // survol très léger

  document.addEventListener('click', e => {
    const b = e.target.closest('.btn, .scard, .menu a, .badges li, .step, .more, .burger, .footer a, .logo');
    if (!b) return;
    if (on) (b.classList.contains('btn') ? pop : soft)();
    if (!reduce && b.classList.contains('btn')) {
      const r = b.getBoundingClientRect(), s = Math.max(r.width, r.height) / 2, x = document.createElement('span');
      x.className = 'rip'; x.style.cssText = `width:${s}px;height:${s}px;left:${e.clientX - r.left - s / 2}px;top:${e.clientY - r.top - s / 2}px`;
      b.append(x); setTimeout(() => x.remove(), 650);
    }
  }, true);
  let last = 0;
  document.addEventListener('pointerover', e => {
    if (!on || e.pointerType !== 'mouse') return;
    const b = e.target.closest('.btn, .scard, .menu a'); if (!b || (e.relatedTarget && b.contains(e.relatedTarget))) return;
    const n = performance.now(); if (n - last < 120) return; last = n; if (ctx && ctx.state === 'running') tick();
  });

  // bouton son (coupe / active)
  const ico = v => v
    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>'
    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="m16 9 5 6m0-6-5 6"/></svg>';
  const btn = document.createElement('button');
  btn.className = 'snd' + (on ? '' : ' off'); btn.type = 'button'; btn.title = 'Sons du site'; btn.setAttribute('aria-label', 'Activer ou couper les sons du site');
  btn.innerHTML = ico(on);
  btn.addEventListener('click', e => {
    e.stopPropagation(); on = !on; try { localStorage.setItem('ap_sound', on ? 'on' : 'off'); } catch (x) {}
    btn.classList.toggle('off', !on); btn.innerHTML = ico(on); if (on) pop();
  });
  document.body.append(btn);
})();
