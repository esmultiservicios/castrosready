const menuBtn = document.querySelector('.menu-btn');
const nav = document.querySelector('.main-nav');
menuBtn?.addEventListener('click', () => {
  const open = nav.classList.toggle('open');
  menuBtn.setAttribute('aria-expanded', String(open));
});
document.querySelectorAll('.main-nav a').forEach(a => a.addEventListener('click', () => nav.classList.remove('open')));

const form = document.getElementById('estimateForm');
const toast = document.getElementById('toast');
form?.addEventListener('submit', (e) => {
  e.preventDefault();
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 4200);
});
