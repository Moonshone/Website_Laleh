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
form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const note = form.querySelector('.form-note');
  const submitButton = form.querySelector('button[type="submit"]');
  const submitLabel = submitButton?.textContent;

  if (!form.reportValidity()) return;

  if (note) note.textContent = 'Sending…';
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = 'Sending…';
  }

  try {
    const response = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' },
    });
    const responseText = await response.text();
    let result;

    try {
      result = JSON.parse(responseText);
    } catch (error) {
      console.error('Contact form returned invalid JSON.', {
        endpoint: response.url,
        status: response.status,
        response: responseText,
        error,
      });
      throw new Error('Your message could not be sent. Please try again later.');
    }

    if (!response.ok || result.success !== true) {
      console.error('Contact form request failed.', {
        endpoint: response.url,
        status: response.status,
        response: result,
      });
      throw new Error(result.message || 'Your message could not be sent. Please try again.');
    }

    if (note) note.textContent = result.message;
    form.reset();
  } catch (error) {
    console.error('Contact form submission failed.', error);
    if (note) note.textContent = error.message || 'Your message could not be sent. Please try again.';
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = submitLabel;
    }
  }
});

document.querySelectorAll('[data-year]').forEach((element) => { element.textContent = new Date().getFullYear(); });

document.querySelectorAll('[data-painting-slideshow]').forEach((slideshow) => {
  const slides = Array.from(slideshow.querySelectorAll('[data-painting-slide]'));
  const current = slideshow.querySelector('[data-painting-current]');
  let activeIndex = 0;

  if (slides.length < 2) return;

  const showSlide = (nextIndex) => {
    activeIndex = (nextIndex + slides.length) % slides.length;
    slides.forEach((slide, index) => {
      const isActive = index === activeIndex;
      slide.classList.toggle('is-active', isActive);
      slide.hidden = !isActive;
    });
    if (current) current.textContent = String(activeIndex + 1);
  };

  slideshow.querySelector('[data-painting-previous]')?.addEventListener('click', () => showSlide(activeIndex - 1));
  slideshow.querySelector('[data-painting-next]')?.addEventListener('click', () => showSlide(activeIndex + 1));

  setInterval(() => showSlide(activeIndex + 1), 5000);
});
