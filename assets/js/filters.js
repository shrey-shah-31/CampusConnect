document.addEventListener('DOMContentLoaded', () => {
  const q = document.getElementById('jobSearch');
  if (!q) return;
  q.addEventListener('input', () => {
    const term = q.value.toLowerCase().trim();
    document.querySelectorAll('[data-job-row]').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
  });
});
