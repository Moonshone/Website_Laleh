const body = document.body;
const toggle = document.querySelector('.menu-toggle');
const overlay = document.querySelector('.menu-overlay');

function setMenu(open) {
  body.classList.toggle('menu-open', open);
  toggle?.setAttribute('aria-expanded', String(open));
  toggle?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  overlay?.setAttribute('aria-hidden', String(!open));
  if (open) overlay?.querySelector('a')?.focus();
}

toggle?.addEventListener('click', () => setMenu(!body.classList.contains('menu-open')));
overlay?.addEventListener('click', (event) => {
  if (event.target === overlay || event.target.closest('a')) setMenu(false);
});
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && body.classList.contains('menu-open')) {
    setMenu(false);
    toggle?.focus();
  }
});

const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.08 });
document.querySelectorAll('.reveal').forEach((element) => observer.observe(element));

const form = document.querySelector('.contact-form');
form?.addEventListener('submit', (event) => {
  event.preventDefault();
  const note = form.querySelector('.form-note');
  if (note) note.textContent = 'Thank you. This demo form is ready to be connected to your email service.';
  form.reset();
});

document.querySelectorAll('[data-year]').forEach((element) => { element.textContent = new Date().getFullYear(); });
