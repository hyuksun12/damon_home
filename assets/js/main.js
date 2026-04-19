/* ============================================================
   담온 (DAMON) — SPA Router & UI
   ============================================================ */

'use strict';

const PAGES = {
  home:     'pages/home.html',
  about:    'pages/about.html',
  products: 'pages/products.html',
  process:  'pages/process.html',
  contact:  'pages/contact.html',
};

const mainContent       = document.getElementById('main-content');
const header            = document.getElementById('header');
const hamburger         = document.getElementById('hamburger');
const mobileNav         = document.getElementById('mobileNav');
const mobileNavBackdrop = document.getElementById('mobileNavBackdrop');

/* ── 스크롤 등장 애니메이션 (IntersectionObserver) ── */
let revealObserver = null;

function initReveal() {
  if (revealObserver) revealObserver.disconnect();

  revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        revealObserver.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.12,
    rootMargin: '0px 0px -40px 0px',
  });

  mainContent.querySelectorAll('.reveal').forEach(el => {
    revealObserver.observe(el);
  });
}

/* ── 페이지 로드 ── */
async function loadPage(pageName) {
  const path = PAGES[pageName];
  if (!path) return;

  try {
    const res = await fetch(path, { cache: 'no-store' });
    if (!res.ok) throw new Error(`${res.status}`);
    const html = await res.text();
    mainContent.innerHTML = html;

    // 페이지 내부 링크에 이벤트 바인딩
    bindPageLinks(mainContent);

    // 스크롤 등장 애니메이션 초기화
    initReveal();

    // 페이지별 기능 초기화
    initPageFeatures();

    // 회사소개 히어로 배경 이미지 적용
    if (pageName === 'about') {
      const hero = mainContent.querySelector('.page-hero');
      if (hero) {
        const isMobile = window.innerWidth <= 768;
        hero.style.backgroundImage = "url('cf/banner_top_trimmed.png')";
        hero.style.backgroundSize  = 'cover';
        hero.style.backgroundRepeat = 'no-repeat';
        hero.style.backgroundPosition = isMobile ? '65% center' : '60% center';
        if (isMobile) hero.style.minHeight = '260px';
      }
    }

    // 거래안내 히어로 배경 이미지 적용
    if (pageName === 'process') {
      const hero = mainContent.querySelector('.page-hero');
      if (hero) {
        hero.style.backgroundImage = "url('cf/4191fb0c2fccc1407b286576a2116b75.jpg')";
        hero.style.backgroundSize  = 'cover';
        hero.style.backgroundRepeat = 'no-repeat';
        hero.style.backgroundPosition = 'center center';
      }
    }

    // 고객센터 히어로 배경 이미지 적용
    if (pageName === 'contact') {
      const hero = mainContent.querySelector('.page-hero');
      if (hero) {
        hero.style.backgroundImage = "url('cf/503c16336a9e162bb9f0f062b1fab10b.jpg')";
        hero.style.backgroundSize  = 'cover';
        hero.style.backgroundRepeat = 'no-repeat';
        hero.style.backgroundPosition = 'center center';
      }
    }

    // 스크롤 상단 이동
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (err) {
    mainContent.innerHTML = `
      <div style="padding:120px 40px;text-align:center;color:#888;">
        <p style="font-size:18px;">페이지를 불러오는 중 오류가 발생했습니다.</p>
        <p style="margin-top:8px;font-size:14px;">${err.message}</p>
      </div>`;
  }
}

/* ── 링크 이벤트 위임 ── */
function bindLinks(root) {
  root.querySelectorAll('[data-page]').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      const page = el.dataset.page;
      loadPage(page);
      closeMobileNav();
    });
  });
}

function bindPageLinks(root) {
  root.querySelectorAll('[data-page]').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      loadPage(el.dataset.page);
    });
  });
}

/* ── 모바일 네비 ── */
function closeMobileNav() {
  mobileNav.classList.remove('open');
  hamburger.classList.remove('active');
  mobileNav.setAttribute('aria-hidden', 'true');
  if (mobileNavBackdrop) mobileNavBackdrop.classList.remove('open');
}

hamburger.addEventListener('click', () => {
  const isOpen = mobileNav.classList.toggle('open');
  hamburger.classList.toggle('active', isOpen);
  mobileNav.setAttribute('aria-hidden', String(!isOpen));
  if (mobileNavBackdrop) mobileNavBackdrop.classList.toggle('open', isOpen);
});

// 모바일 nav 내부 닫기(X) 버튼
const mobileNavClose = document.getElementById('mobileNavClose');
if (mobileNavClose) {
  mobileNavClose.addEventListener('click', closeMobileNav);
}

// 백드롭 클릭 시 닫기
if (mobileNavBackdrop) {
  mobileNavBackdrop.addEventListener('click', closeMobileNav);
}

/* ── 스크롤 시 헤더 스타일 + 플로팅 버튼 ── */
const floatBtns = document.getElementById('floatBtns');

window.addEventListener('scroll', () => {
  const scrolled = window.scrollY > 10;
  header.classList.toggle('scrolled', scrolled);

  if (floatBtns) {
    floatBtns.classList.toggle('visible', window.scrollY > 200);
  }
}, { passive: true });

/* ── 상품 카테고리 필터 ── */
function initProductFilter() {
  const filterBtns = mainContent.querySelectorAll('.filter-btn');
  const cards      = mainContent.querySelectorAll('.pcard');
  const emptyMsg   = mainContent.querySelector('#productsEmpty');
  if (!filterBtns.length) return;

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      // 활성 버튼 교체
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.dataset.filter;

      let visibleCount = 0;
      cards.forEach(card => {
        const match = filter === 'all' || card.dataset.category === filter;
        card.classList.toggle('hidden', !match);
        if (match) visibleCount++;
      });

      if (emptyMsg) emptyMsg.style.display = visibleCount === 0 ? 'block' : 'none';
    });
  });
}

/* ── FAQ 아코디언 ── */
function initFaq() {
  const items = mainContent.querySelectorAll('.faq-item');
  if (!items.length) return;

  items.forEach(item => {
    const btn    = item.querySelector('.faq-question');
    const answer = item.querySelector('.faq-answer');
    if (!btn || !answer) return;

    btn.addEventListener('click', () => {
      const isOpen = item.classList.contains('open');

      // 모두 닫기
      items.forEach(i => {
        i.classList.remove('open');
        const a = i.querySelector('.faq-answer');
        if (a) a.style.display = 'none';
      });

      // 클릭한 항목 토글
      if (!isOpen) {
        item.classList.add('open');
        answer.style.display = 'block';
      }
    });
  });
}

/* ── 문의 폼 ── */
function initContactForm() {
  const form        = mainContent.querySelector('#contactForm');
  const successMsg  = mainContent.querySelector('#formSuccess');
  const submitBtn   = mainContent.querySelector('.btn-form-submit');
  const errorMsg    = mainContent.querySelector('#formError');
  const privacyBtn  = mainContent.querySelector('#privacyToggle');
  const privacyBox  = mainContent.querySelector('#privacyDetail');

  if (!form) return;

  // 개인정보 토글
  if (privacyBtn && privacyBox) {
    privacyBtn.addEventListener('click', () => {
      const visible = privacyBox.style.display !== 'none';
      privacyBox.style.display = visible ? 'none' : 'block';
      privacyBtn.textContent   = visible ? '내용 보기' : '접기';
    });
  }

  // 폼 제출
  form.addEventListener('submit', async e => {
    e.preventDefault();

    // 필수값 검증
    const required = form.querySelectorAll('[required]');
    let valid = true;

    required.forEach(el => {
      el.classList.remove('error');
      if (el.type === 'checkbox' ? !el.checked : !el.value.trim()) {
        el.classList.add('error');
        valid = false;
      }
    });

    if (!valid) return;

    // 전송 중 버튼 비활성화
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = '전송 중...';
    }

    try {
      const formData = new FormData(form);

      const res = await fetch('api/submit.php', {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();

      if (data.success) {
        // 성공
        if (submitBtn)  submitBtn.style.display = 'none';
        if (successMsg) successMsg.style.display = 'flex';
        form.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
      } else {
        throw new Error(data.message || '전송 실패');
      }
    } catch (err) {
      // 실패 메시지
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '문의 보내기 <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 8H14M10 4L14 8L10 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      }
      if (errorMsg) errorMsg.style.display = 'flex';
    }
  });
}

/* ── 히어로 슬라이더 ── */
function initHeroSlider() {
  const slides = mainContent.querySelectorAll('.hero-slide');
  const dots   = mainContent.querySelectorAll('.hero-dot');
  if (!slides.length) return;

  let current = 0;
  let timer   = null;

  function goTo(index) {
    slides[current].classList.remove('active');
    if (dots[current]) dots[current].classList.remove('active');
    current = index;
    slides[current].classList.add('active');
    if (dots[current]) dots[current].classList.add('active');
  }

  function next() {
    goTo((current + 1) % slides.length);
  }

  function startAuto() {
    clearInterval(timer);
    timer = setInterval(next, 5000);
  }

  dots.forEach((dot, i) => {
    dot.addEventListener('click', () => {
      goTo(i);
      startAuto();
    });
  });

  startAuto();
}

/* ── HTML 이스케이프 ── */
function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/* ── 상품 페이지 동적 로드 ── */
async function initProductsPage() {
  const grid = mainContent.querySelector('#productsGrid');
  if (!grid) return;

  grid.innerHTML = '<li style="padding:60px;text-align:center;color:#aaa;list-style:none;">불러오는 중…</li>';

  try {
    const res  = await fetch('products.json');
    const json = await res.json();
    if (!json.success || !json.data.length) {
      grid.innerHTML = '<li style="padding:60px;text-align:center;color:#aaa;list-style:none;">등록된 상품이 없습니다.</li>';
      return;
    }

    grid.innerHTML = json.data.map((p, i) => buildProductCard(p, i)).join('');
    bindPageLinks(mainContent);
    initReveal();
    initProductFilter();
  } catch (e) {
    grid.innerHTML = '<li style="padding:60px;text-align:center;color:#aaa;list-style:none;">상품을 불러올 수 없습니다.</li>';
  }
}

function buildProductCard(p, i) {
  const catCls = p.category === '냉장' ? 'pcard-badge--green' : p.category === '냉동' ? 'pcard-badge--blue' : 'pcard-badge--orange';
  const imgHtml = p.image_url
    ? `<img src="${escHtml(p.image_url)}" alt="${escHtml(p.name)}" class="pcard-photo-img">`
    : '';
  const tagsHtml = (p.tags || []).map(t => `<span>${escHtml(t)}</span>`).join('');

  return `
    <li class="pcard" data-category="${escHtml(p.category)}">
      <div class="pcard-photo${p.photo_class ? ' pcard-photo--' + escHtml(p.photo_class) : ''}">
        ${imgHtml}
        <span class="pcard-badge ${catCls}">${escHtml(p.category)}</span>
      </div>
      <div class="pcard-body">
        <h3 class="pcard-name">${escHtml(p.name)}</h3>
        <p class="pcard-desc">${escHtml(p.description)}</p>
        <div class="pcard-tags">${tagsHtml}</div>
        <a href="#" class="pcard-cta" data-page="contact">납품 문의하기</a>
      </div>
    </li>`;
}

/* ── 홈 상품 미리보기 동적 로드 ── */
async function initHomeProducts() {
  const grid = mainContent.querySelector('#homeProductsGrid');
  if (!grid) return;

  try {
    const res  = await fetch('products.json');
    const json = await res.json();
    if (!json.success || !json.data.length) return;
    json.data = json.data.slice(0, 5);

    grid.innerHTML = json.data.map((p, i) => buildPreviewCard(p, i)).join('');
    bindPageLinks(mainContent);
    initReveal();
  } catch (e) { console.error('[homeProducts]', e); }
}

function buildPreviewCard(p, i) {
  const imgHtml = p.image_url
    ? `<img src="${escHtml(p.image_url)}" alt="${escHtml(p.name)}" class="product-thumb-img">`
    : '';
  const badgeHtml = p.badge
    ? `<span class="product-badge">${escHtml(p.badge)}</span>`
    : '';

  return `
    <li class="product-card">
      <div class="product-thumb${p.photo_class ? ' product-thumb--' + escHtml(p.photo_class) : ''}">
        ${imgHtml}
      </div>
      <div class="product-info">
        ${badgeHtml}
        <h3 class="product-name">${escHtml(p.name)}</h3>
        <p class="product-desc">${escHtml(p.description)}</p>
        ${p.tags?.[0] ? `<span class="product-tag">${escHtml(p.tags[0])}</span>` : ''}
      </div>
    </li>`;
}

/* ── 페이지별 기능 초기화 ── */
function initPageFeatures() {
  // initProductFilter은 initProductsPage 내부에서 카드 로드 후 호출 (이중 바인딩 방지)
  initFaq();
  initContactForm();
  initHeroSlider();
  initProductsPage();
  initHomeProducts();
}

/* ── 전화 팝업 (데스크탑) ── */
const floatCallBtn      = document.getElementById('floatCallBtn');
const callPopupOverlay  = document.getElementById('callPopupOverlay');
const callPopupClose    = document.getElementById('callPopupClose');

function isMobile() {
  return window.matchMedia('(max-width: 768px)').matches ||
         /Mobi|Android/i.test(navigator.userAgent);
}

if (floatCallBtn) {
  floatCallBtn.addEventListener('click', e => {
    if (!isMobile()) {
      e.preventDefault();
      callPopupOverlay.classList.add('open');
    }
  });
}

if (callPopupClose) {
  callPopupClose.addEventListener('click', () => {
    callPopupOverlay.classList.remove('open');
  });
}

if (callPopupOverlay) {
  callPopupOverlay.addEventListener('click', e => {
    if (e.target === callPopupOverlay) {
      callPopupOverlay.classList.remove('open');
    }
  });
}

/* ── 초기화 ── */
bindLinks(document);
loadPage('home');
