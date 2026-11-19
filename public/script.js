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

// Visionneuse des réalisations
(function () {
  if (document.body.hasAttribute('data-admin')) return;
  const items = () => Array.from(document.querySelectorAll('.shot-img'));
  let idx = 0, box;
  const show = i => {
    const list = items(); idx = (i + list.length) % list.length;
    const fig = list[idx].closest('.shot');
    const t = fig.querySelector('.t'), d = fig.querySelector('.d');
    box.querySelector('img').src = list[idx].getAttribute('href');
    box.querySelector('p').innerHTML = '';
    if (t && t.textContent.trim()) { const b = document.createElement('b'); b.textContent = t.textContent; box.querySelector('p').appendChild(b); }
    if (d && d.textContent.trim()) box.querySelector('p').appendChild(document.createTextNode(d.textContent));
  };
  const close = () => { if (box) { box.remove(); box = null; document.removeEventListener('keydown', key); } };
  const key = e => { if (e.key === 'Escape') close(); if (e.key === 'ArrowRight') show(idx + 1); if (e.key === 'ArrowLeft') show(idx - 1); };
  document.addEventListener('click', e => {
    const a = e.target.closest('.shot-img');
    if (!a) return;
    e.preventDefault();
    box = document.createElement('div'); box.className = 'lb';
    box.innerHTML = '<button class="x" aria-label="Fermer">&times;</button><button class="pv" aria-label="Précédente">&#8249;</button><button class="nx" aria-label="Suivante">&#8250;</button><img alt=""><p></p>';
    document.body.appendChild(box);
    box.addEventListener('click', ev => {
      if (ev.target.classList.contains('pv')) show(idx - 1);
      else if (ev.target.classList.contains('nx')) show(idx + 1);
      else if (ev.target.tagName !== 'IMG' && ev.target.tagName !== 'P' && ev.target.tagName !== 'B') close();
    });
    document.addEventListener('keydown', key);
    show(items().indexOf(a));
  });
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
