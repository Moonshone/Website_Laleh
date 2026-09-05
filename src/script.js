const body = document.body;
const toggle = document.querySelector('.menu-toggle');
const overlay = document.querySelector('.menu-overlay');

function setMenu(open) {
  body.classList.toggle('menu-open', open);
  toggle?.setAttribute('aria-expanded', String(open));
  toggle?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  overlay?.setAttribute('aria-hidden', String(!open));
}

toggle?.addEventListener('click', () => setMenu(!body.classList.contains('menu-open')));
overlay?.addEventListener('click', (event) => {
  if (event.target.closest('a')) setMenu(false);
});
document.addEventListener('click', (event) => {
  if (body.classList.contains('menu-open') && !overlay?.contains(event.target) && !toggle?.contains(event.target)) {
    setMenu(false);
  }
});
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && body.classList.contains('menu-open')) {
    setMenu(false);
  }
});

const homeImages = [
  { src: 'assets/images/home/06.jpeg', alt: '' },
  { src: 'assets/images/home/04.jpeg', alt: '' },
  { src: 'assets/images/home/03F.jpeg', alt: '' },
  { src: 'assets/images/home/02.jpeg', alt: '' },
];
const galleryTrigger = document.querySelector('.home-gallery-trigger');
const galleryImage = document.querySelector('.home-gallery-image');
const galleryStatus = document.querySelector('[data-gallery-status]');
let homeImageIndex = 0;
let galleryChangeTimer;

function showHomeImage(index) {
  if (!galleryImage) return;
  homeImageIndex = (index + homeImages.length) % homeImages.length;
  galleryImage.classList.add('is-changing');
  window.clearTimeout(galleryChangeTimer);
  galleryChangeTimer = window.setTimeout(() => {
    const image = homeImages[homeImageIndex];
    galleryImage.src = image.src;
    galleryImage.alt = image.alt;
    galleryImage.classList.remove('is-changing');
    if (galleryStatus) galleryStatus.textContent = `Artwork ${homeImageIndex + 1} of ${homeImages.length}`;
  }, 160);
}

galleryTrigger?.addEventListener('click', () => showHomeImage(homeImageIndex + 1));
document.addEventListener('keydown', (event) => {
  if (!galleryImage || body.classList.contains('menu-open')) return;
  if (event.key === 'ArrowRight') showHomeImage(homeImageIndex + 1);
  if (event.key === 'ArrowLeft') showHomeImage(homeImageIndex - 1);
});

homeImages.slice(1).forEach(({ src }) => {
  const image = new Image();
  image.src = src;
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
