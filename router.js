
async function loadPage(path) {
  const map = {
    '/':         'home.html',
    '/about':    'about.html',
    '/product':  'product.html',
    '/services': 'services.html',
    '/contact':  'contact.html',
  };
  const file = map[path] || map['/'];
  try {
    const res = await fetch(file);
    if (!res.ok) throw new Error('not found');
    return await res.text();
  } catch {
    return `<div class="placeholder-page"><div class="ph-icon"><i class="fa-solid fa-circle-exclamation"></i></div><h2>페이지를 찾을 수 없습니다</h2></div>`;
  }
}

async function navigate() {
  const hash = location.hash.replace('#', '') || '/';
  document.getElementById('header-container').innerHTML = renderHeader();
  if (typeof initMobileMenu === 'function') initMobileMenu();
  const content = await loadPage(hash);
  document.getElementById('app').innerHTML = content;
  document.getElementById('footer-container').innerHTML = renderFooter();
  window.scrollTo({ top: 0, behavior: 'smooth' });

  // re-bind nav active state
  document.querySelectorAll('nav a').forEach(a => {
    a.classList.toggle('active', a.getAttribute('href') === '#' + hash);
  });

  initHeroSlider();
}

window.addEventListener('hashchange', navigate);
window.addEventListener('DOMContentLoaded', navigate);


function initHeroSlider() {
  const root = document.querySelector('[data-hero-slider]');
  if (!root) return;
  const slides = Array.from(root.querySelectorAll('.hero-slide'));
  const dots = Array.from(root.querySelectorAll('.hero-dot'));
  const prevBtn = root.querySelector('.hero-slider-prev');
  const nextBtn = root.querySelector('.hero-slider-next');
  let current = 0;
  let timer;

  function render(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => slide.classList.toggle('active', i === current));
    dots.forEach((dot, i) => dot.classList.toggle('active', i === current));
  }

  function start() {
    stop();
    timer = setInterval(() => render(current + 1), 3000);
  }

  function stop() {
    if (timer) clearInterval(timer);
  }

  prevBtn?.addEventListener('click', () => { render(current - 1); start(); });
  nextBtn?.addEventListener('click', () => { render(current + 1); start(); });
  dots.forEach((dot, i) => dot.addEventListener('click', () => { render(i); start(); }));
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  render(0);
  start();
}
