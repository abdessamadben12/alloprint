/* AlloPrint — affiche les prix définis dans l'admin (api/prix.php) sur les éléments [data-prix]. */
(() => {
  const slots = document.querySelectorAll('[data-prix]');
  if (!slots.length) return;
  fetch('api/prix.php', { headers: { Accept: 'application/json' } })
    .then(r => (r.ok ? r.json() : {}))
    .then(prices => {
      slots.forEach(el => {
        const p = prices[el.dataset.prix];
        if (!p) return;
        el.textContent = '';
        const main = document.createElement('strong');
        main.className = 'prix-tag__value';
        main.textContent = (p.mention === 'Prix fixe' ? '' : p.mention + ' ') + p.prix + ' MAD';
        el.append(main, ' ' + p.taxe + (p.unite ? ' / ' + p.unite : ''));
        if (p.note) {
          const n = document.createElement('small');
          n.className = 'prix-tag__note';
          n.textContent = p.note;
          el.append(n);
        }
        el.hidden = false;
      });
    })
    .catch(() => {});
})();
