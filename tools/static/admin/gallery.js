  // ---------- réalisations (chantiers avec plusieurs photos)
  const gallery = document.getElementById('gallery');
  if (gallery) {
    const CATS = ['Rénovation complète', 'Couverture', 'Zinguerie', 'Pignon', 'Velux', 'Réparation / fuite'];
    const addBtn = el('button', {}, '+ Nouveau chantier');
    gallery.before(el('div', { class: 'adm-add' }, el('p', {}, 'Créez un chantier avec son titre, sa catégorie, sa description et plusieurs photos. Cliquez sur un chantier pour le modifier.'), addBtn));
    addBtn.addEventListener('click', () => editor(null));
    gallery.querySelectorAll('.proj').forEach(card => {
      const p = { id: card.dataset.id, title: card.dataset.title, category: card.dataset.cat, description: card.dataset.desc, photos: JSON.parse(card.dataset.photos || '[]') };
      card.addEventListener('click', e => { if (e.target.closest('.adm-tools')) return; e.preventDefault(); editor(p); });
      card.append(el('div', { class: 'adm-tools' },
        el('button', { title: 'Déplacer avant', onclick: e => { e.stopPropagation(); move(p.id, 'up'); } }, '←'),
        el('button', { title: 'Déplacer après', onclick: e => { e.stopPropagation(); move(p.id, 'down'); } }, '→'),
        el('button', { class: 'del', title: 'Supprimer', onclick: e => { e.stopPropagation(); remove(p.id); } }, '×')));
    });
    async function move(id, dir) { await api('/api.php?a=real_move', { method: 'POST', json: { id, dir } }); location.reload(); }
    async function remove(id) {
      if ((await dialog('Supprimer ce chantier ?', 'Le chantier et toutes ses photos seront supprimés définitivement.', [['Supprimer', 'y', true], ['Annuler', null]])) !== 'y') return;
      await api('/api.php?a=proj_del&id=' + id, { method: 'POST' }); location.reload();
    }
    function editor(p) {
      const st = { photos: p ? p.photos.slice() : [] };
      const title = el('input', { type: 'text', maxlength: '140', placeholder: 'Ex : Rénovation de toiture à Hautmont', value: p ? p.title : '' });
      const cat = el('input', { type: 'text', maxlength: '60', list: 'adm-cats', placeholder: 'Ex : Rénovation complète', value: p ? p.category : '' });
      const dl = el('datalist', { id: 'adm-cats' }, ...CATS.map(c => el('option', { value: c })));
      const desc = el('textarea', { rows: '8', placeholder: 'Une ligne = un paragraphe. Mettez **du texte entre doubles étoiles** pour l\'afficher en gras.' }, p ? p.description : '');
      const grid = el('div', { class: 'pm-grid' });
      const msgEl = el('span', { class: 'pm-msg' });
      const mv = (i, d) => { const j = i + d; if (j < 0 || j >= st.photos.length) return; [st.photos[i], st.photos[j]] = [st.photos[j], st.photos[i]]; draw(); };
      const draw = () => {
        grid.replaceChildren();
        if (!st.photos.length) grid.append(el('p', { class: 'pm-empty' }, 'Aucune photo pour le moment.'));
        st.photos.forEach((u, i) => grid.append(el('div', { class: 'pm-th' + (i === 0 ? ' cover' : '') },
          el('img', { src: u, alt: '' }), i === 0 ? el('span', { class: 'pm-tag' }, 'Couverture') : '',
          el('div', { class: 'pm-ct' },
            el('button', { type: 'button', title: 'Avant', onclick: () => mv(i, -1) }, '←'),
            el('button', { type: 'button', title: 'Après', onclick: () => mv(i, 1) }, '→'),
            el('button', { type: 'button', class: 'x', title: 'Retirer', onclick: () => { st.photos.splice(i, 1); draw(); } }, '×')))));
      };
      const addPhotos = el('button', { type: 'button', class: 'pm-add' }, '+ Ajouter des photos');
      addPhotos.addEventListener('click', async () => {
        const files = await chooseFiles(true); if (!files.length) return;
        addPhotos.disabled = true;
        try { for (let i = 0; i < files.length; i++) { msgEl.textContent = `Envoi ${i + 1}/${files.length}…`; st.photos.push(await uploadFile(files[i])); draw(); } msgEl.textContent = ''; }
        catch (ex) { msgEl.textContent = ex.message; }
        addPhotos.disabled = false;
      });
      const save = el('button', { type: 'button', class: 'pri' }, 'Enregistrer');
      const cancel = el('button', { type: 'button' }, 'Annuler');
      const row = el('div', { class: 'row' }, save, cancel);
      if (p) row.append(el('button', { type: 'button', class: 'dng', onclick: () => { m.remove(); remove(p.id); } }, 'Supprimer'));
      const box = el('div', { class: 'adm-box pm' }, el('h3', {}, p ? 'Modifier le chantier' : 'Nouveau chantier'),
        el('label', {}, 'Titre', title), el('label', {}, 'Catégorie', cat, dl), el('label', {}, 'Description', desc),
        el('div', { class: 'pm-photos' }, el('b', {}, 'Photos (la première sert de couverture)'), grid, addPhotos), msgEl, row);
      const m = el('div', { class: 'adm-modal' }, box);
      document.body.append(m); draw(); title.focus();
      cancel.addEventListener('click', () => m.remove());
      save.addEventListener('click', async () => {
        if (!title.value.trim()) { msgEl.textContent = 'Ajoutez un titre.'; return; }
        if (!st.photos.length) { msgEl.textContent = 'Ajoutez au moins une photo.'; return; }
        save.disabled = true; msgEl.textContent = 'Enregistrement…';
        try { await api('/api.php?a=proj_save', { method: 'POST', json: { id: p ? p.id : '', title: title.value, category: cat.value, description: desc.value, photos: st.photos } }); location.reload(); }
        catch (ex) { msgEl.textContent = ex.message; save.disabled = false; }
      });
    }
  }

