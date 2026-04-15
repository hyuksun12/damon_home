
function renderHeader() {
  const routes = [
    { href: '#/about', label: '회사소개' },
    { href: '#/product', label: '상품목록' },
    { href: '#/services', label: '거래안내' },
    { href: '#/contact', label: '고객센터' },
  ];
  const current = location.hash || '#/';
  const navLinks = routes.map(r =>
    `<a href="${r.href}" class="${current === r.href ? 'active' : ''}">${r.label}</a>`
  ).join('');

  return `
  <header id="site-header">
    <div class="header-inner">
      <a href="#/" class="logo">
        <div class="logo-icon"><i class="fa-solid fa-leaf"></i></div>
        <div>
          <div class="logo-text">담온</div>
          <div class="logo-sub">Fresh Food Supply</div>
        </div>
      </a>
      <nav>${navLinks}</nav>
      <a href="#/contact" class="header-cta">거래 문의하기</a>
    </div>
  </header>`;
}

function renderFooter() {
  return `
  <footer id="site-footer">
    <div class="footer-inner">
      <div class="footer-grid">
        <div class="footer-brand">
          <div class="logo"><div class="logo-text">담온</div></div>
          <p>신선함을 가장 빠르게 전달합니다.<br>철저한 품질관리와 체계적인 공급 시스템으로<br>매장 운영을 든든하게 지원합니다.</p>
        </div>
        <div class="footer-col">
          <h4>메뉴</h4>
          <ul>
            <li><a href="#/">홈</a></li>
            <li><a href="#/about">회사소개</a></li>
            <li><a href="#/product">상품목록</a></li>
            <li><a href="#/services">거래안내</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>취급품목</h4>
          <ul>
            <li><a href="#/product">냉장 · 김치/계란</a></li>
            <li><a href="#/product">냉동 · 확장 품목</a></li>
            <li><a href="#/product">가공 · 쌀/소스류</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>고객센터</h4>
          <ul>
            <li><a href="#/contact">거래 문의</a></li>
            <li><a href="#/contact">주문 안내</a></li>
            <li><a href="#/contact">실시간 상담</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>© 2025 담온 식자재유통. All Rights Reserved.</p>
        <p>사업자등록번호 000-00-00000 | 대표: 홍길동</p>
      </div>
    </div>
  </footer>`;
}
