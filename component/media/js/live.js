(() => {
  'use strict';
  const root = document.querySelector('[data-xdd-live]');
  if (!root) return;
  const url = root.dataset.snapshotUrl;
  const grid = root.querySelector('[data-xdd-grid]');
  const status = root.querySelector('[data-xdd-status]');
  const progress = root.querySelector('[data-xdd-progress]');
  const verification = root.querySelector('[data-xdd-verification]');
  const seen = new Set(Array.from(grid.querySelectorAll('[data-sequence]')).map(el => Number(el.dataset.sequence)));
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const renderCard = a => {
    const card = document.createElement('article');
    card.className = 'xdd-live__card';
    card.dataset.sequence = String(a.sequence_no);
    card.innerHTML = `<div class="xdd-live__number">#${Number(a.sequence_no)}</div><div class="xdd-live__entry">${escapeHtml(a.display_name)}</div><div class="xdd-live__target">${escapeHtml(a.target_key)}${a.position_no !== null ? ' · ' + Number(a.position_no) : ''}</div>`;
    grid.appendChild(card);
  };
  const apply = data => {
    status.textContent = data.status;
    progress.textContent = `${Number(data.revealed_count)}/${Number(data.total)}`;
    (data.assignments || []).forEach(a => { if (!seen.has(Number(a.sequence_no))) { seen.add(Number(a.sequence_no)); renderCard(a); } });
    if (data.completed) {
      verification.classList.remove('is-hidden');
      verification.querySelector('[data-xdd-seed] code').textContent = data.seed || '';
      verification.querySelector('[data-xdd-input-hash] code').textContent = data.input_hash || '';
      verification.querySelector('[data-xdd-result-hash] code').textContent = data.result_hash || '';
    }
  };
  const poll = async () => {
    try {
      const response = await fetch(url, {headers:{Accept:'application/json'}, cache:'no-store'});
      if (!response.ok) return;
      const payload = await response.json();
      if (!payload.success || !payload.data) return;
      apply(payload.data);
    } catch (_) {}
  };
  window.setInterval(poll, 2000);
})();
