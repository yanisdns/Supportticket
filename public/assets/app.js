// Glisser-déposer des tickets + recherche instantanée
const csrf = document.querySelector('meta[name=csrf]')?.content;
document.querySelectorAll('.ticket[draggable]').forEach(c => {
  c.addEventListener('dragstart', e => { e.dataTransfer.setData('text/plain', c.dataset.id); c.classList.add('drag'); });
  c.addEventListener('dragend', () => c.classList.remove('drag'));
});
document.querySelectorAll('.col[data-status]').forEach(col => {
  col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('over'); });
  col.addEventListener('dragleave', () => col.classList.remove('over'));
  col.addEventListener('drop', async e => {
    e.preventDefault(); col.classList.remove('over');
    const id = e.dataTransfer.getData('text/plain'), card = document.querySelector(`.ticket[data-id="${id}"]`);
    if (!card) return;
    col.appendChild(card); card.querySelector('select').value = col.dataset.status;
    document.querySelectorAll('.col').forEach(c => c.querySelector('.count').textContent = c.querySelectorAll('.ticket').length);
    await fetch('?api=move', { method: 'POST', headers: { 'X-CSRF': csrf, 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ id, status: col.dataset.status }) });
  });
});
document.getElementById('q')?.addEventListener('input', e => {
  const v = e.target.value.toLowerCase();
  document.querySelectorAll('.ticket,[data-row]').forEach(el => el.style.display = el.textContent.toLowerCase().includes(v) ? '' : 'none');
});
