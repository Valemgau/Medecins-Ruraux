<!-- TOPBAR réseaux sociaux -->
<div id="topbar-social"
  class="bg-gray-900 text-white text-xs select-none header-visible fixed w-full top-0 left-0 transition-transform duration-500 will-change-transform"
  style="z-index: 2000;">
  <div class="flex justify-between items-center px-5 md:px-10 mx-auto h-10">
    <div class="flex items-center space-x-3">
      <span class="text-gray-300 font-medium">Langue:</span>
      <div class="gtranslate_wrapper"></div>
    </div>

    <div class="flex items-center space-x-3">
      <!-- Facebook -->
      <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" aria-label="Facebook"
        class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/10 transition-all duration-200">
        <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path d="M22 12a10 10 0 10-11.5 9.9v-7h-2v-3h2v-2c0-1.7 1-2.6 2.5-2.6.7 0 1.4.1 1.4.1v1.6h-.8c-.8 0-1 .5-1 1v1.5h1.8l-.3 3h-1.5v7A10 10 0 0022 12z" />
        </svg>
      </a>
      <!-- Twitter -->
      <a href="https://twitter.com" target="_blank" rel="noopener noreferrer" aria-label="Twitter"
        class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/10 transition-all duration-200">
        <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path d="M23 3a10.9 10.9 0 01-3.1.9 4.8 4.8 0 002-2.6 9.7 9.7 0 01-3 1.2A4.8 4.8 0 0016.5 2c-2.7 0-4.9 2.1-4.9 4.8 0 .4 0 .7.1 1A13.7 13.7 0 013 2.5a4.6 4.6 0 001.5 6.4 4.6 4.6 0 01-2.1-.6v.1c0 2.3 1.6 4.2 3.7 4.6a4.9 4.9 0 01-2.1.1c.6 1.8 2.5 3.1 4.7 3.1A9.6 9.6 0 012 19.5a13.7 13.7 0 007.4 2.2c8.8 0 13.7-7.3 13.7-13.7 0-.2 0-.4 0-.6A9.8 9.8 0 0023 3z" />
        </svg>
      </a>
      <!-- LinkedIn -->
      <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"
        class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/10 transition-all duration-200">
        <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path d="M4 3a2 2 0 11-.001 3.999A2 2 0 014 3zM2 9h4v12H2zM8 9h4v1.7h.1a4.4 4.4 0 013.9-2.1c4.1 0 4.9 2.7 4.9 6.1v7.3h-4v-6.5c0-1.5 0-3.4-2.1-3.4s-2.4 1.7-2.4 3.3v6.6H8z" />
        </svg>
      </a>
      <!-- Instagram -->
      <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram"
        class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/10 transition-all duration-200">
        <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path d="M7 2C4 2 2 4 2 7v10c0 3 2 5 5 5h10c3 0 5-2 5-5V7c0-3-2-5-5-5H7zm10 2a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM12 7a5 5 0 110 10 5 5 0 010-10zm0 2a3 3 0 100 6 3 3 0 000-6z" />
        </svg>
      </a>
    </div>
  </div>
</div>

<!-- HEADER principal -->
<header id="header-main"
  class="bg-white/95 backdrop-blur-lg shadow-sm fixed w-full left-0 header-visible transition-transform duration-500 will-change-transform border-b border-gray-100"
  style="top: 40px; z-index: 2000;">
  <div class="max-w-7xl mx-auto px-4 md:px-8">
    <!-- Ligne principale -->
    <div class="flex justify-between items-center h-16">
      <!-- Logo -->
      <a href="/index.php" class="flex items-center space-x-3 hover:opacity-80 transition-opacity duration-200">
        <div class="w-10 h-10 rounded-full overflow-hidden shadow-sm bg-gradient-to-br from-green-400 to-green-600">
          <img src="/assets/img/logo.jpg" alt="Logo" class="w-full h-full object-cover" onerror="this.style.display='none'" />
        </div>
        <span class="font-semibold text-lg text-gray-900 select-none whitespace-nowrap tracking-tight">Médecins Ruraux</span>
      </a>

      <!-- Menu desktop -->
      <nav class="hidden md:flex items-center space-x-2">
        <a href="/login.php?role=recruteur"
          class="px-5 py-2 bg-gradient-to-r from-green-400 to-green-600 text-white rounded-full font-medium hover:from-green-500 hover:to-green-700 transition-all duration-200 shadow-sm">
          Recruteur
        </a>
        <a href="/login.php?role=candidat"
          class="px-5 py-2 border border-green-500 text-green-600 rounded-full font-medium hover:bg-green-50 transition-all duration-200">
          Candidat
        </a>
      </nav>

      <!-- Hamburger mobile -->
      <button id="menu-btn" aria-label="Menu" type="button"
        class="md:hidden w-10 h-10 flex flex-col items-center justify-center space-y-1.5 rounded-full bg-gradient-to-r from-green-400 to-green-600 shadow-sm focus:outline-none">
        <span class="block w-5 h-0.5 bg-white rounded transition-all duration-300"></span>
        <span class="block w-5 h-0.5 bg-white rounded transition-all duration-300"></span>
        <span class="block w-5 h-0.5 bg-white rounded transition-all duration-300"></span>
      </button>
    </div>

    <!-- Navigation secondaire desktop -->
    <nav class="hidden md:flex items-center space-x-8 h-12 border-t border-gray-100">
      <a href="/index.php" class="text-sm font-medium text-gray-700 hover:text-green-600 transition-colors duration-200">
        Accueil
      </a>
      <a href="/contact.php" class="text-sm font-medium text-gray-700 hover:text-green-600 transition-colors duration-200">
        Contact
      </a>
    </nav>
  </div>
</header>

<!-- Overlay -->
<div id="mobile-menu-overlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm opacity-0 invisible transition-all duration-300" style="z-index: 2500;"></div>

<!-- MENU mobile -->
<div id="mobile-menu"
  class="fixed top-0 right-0 w-80 h-full bg-white shadow-2xl transition-all duration-300 flex flex-col"
  style="z-index: 3000; transform: translateX(100%);">
  
  <!-- Header menu mobile -->
  <div class="flex items-center justify-between p-6 border-b border-gray-100">
    <span class="font-semibold text-lg text-gray-900">Menu</span>
    <button id="mobile-menu-close" aria-label="Fermer menu"
      class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors duration-200">
      <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 overflow-y-auto p-6 space-y-2">
    <a href="/index.php" 
      class="block px-4 py-3 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors duration-200">
      Accueil
    </a>
    <a href="/contact.php" 
      class="block px-4 py-3 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors duration-200">
      Contact
    </a>

    <div class="pt-4 mt-4 border-t border-gray-100">
      <a href="/legal.php?policy=mentions" 
        class="block px-4 py-3 text-sm text-gray-600 rounded-xl hover:bg-gray-50 transition-colors duration-200">
        Mentions légales
      </a>
      <a href="/legal.php?policy=privacy_policy" 
        class="block px-4 py-3 text-sm text-gray-600 rounded-xl hover:bg-gray-50 transition-colors duration-200">
        Confidentialité
      </a>
      <a href="/legal.php?policy=cookies" 
        class="block px-4 py-3 text-sm text-gray-600 rounded-xl hover:bg-gray-50 transition-colors duration-200">
        Cookies
      </a>
    </div>
  </nav>

  <!-- Actions -->
  <div class="p-6 border-t border-gray-100 space-y-3">
    <a href="/login.php?role=candidat"
      class="block w-full text-center px-6 py-3 bg-gradient-to-r from-green-400 to-green-600 text-white rounded-full font-semibold hover:from-green-500 hover:to-green-700 transition-all duration-200 shadow-sm">
      Espace candidat
    </a>
    <a href="/login.php?role=recruteur"
      class="block w-full text-center px-6 py-3 border-2 border-green-500 text-green-600 rounded-full font-semibold hover:bg-green-50 transition-all duration-200">
      Espace recruteur
    </a>
  </div>
</div>

<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

  * {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }

  .header-hidden {
    transform: translateY(-120px);
    pointer-events: none;
  }

  .header-visible {
    transform: translateY(0);
    pointer-events: all;
  }

  body {
    padding-top: 108px;
  }

  @media (max-width: 767px) {
    body {
      padding-top: 96px;
    }
  }

  /* Menu mobile ouvert */
  #mobile-menu.show {
    transform: translateX(0) !important;
  }

  #mobile-menu-overlay.show {
    opacity: 1 !important;
    visibility: visible !important;
  }

  /* Animation hamburger */
  #menu-btn.open span:nth-child(1) {
    transform: rotate(45deg) translateY(7px);
  }

  #menu-btn.open span:nth-child(2) {
    opacity: 0;
  }

  #menu-btn.open span:nth-child(3) {
    transform: rotate(-45deg) translateY(-7px);
  }
</style>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const topbar = document.getElementById('topbar-social');
    const header = document.getElementById('header-main');
    const menuBtn = document.getElementById('menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const closeBtn = document.getElementById('mobile-menu-close');
    const overlay = document.getElementById('mobile-menu-overlay');

    console.log('Elements:', { topbar, header, menuBtn, mobileMenu, closeBtn, overlay });

    // Scroll header animation
    let lastScroll = 0;
    let ticking = false;

    function setHeadersHidden(hidden) {
      if (!header) return;
      if (hidden) {
        header.classList.add('header-hidden');
        header.classList.remove('header-visible');
        if (topbar) {
          topbar.classList.add('header-hidden');
          topbar.classList.remove('header-visible');
        }
      } else {
        header.classList.remove('header-hidden');
        header.classList.add('header-visible');
        if (topbar) {
          topbar.classList.remove('header-hidden');
          topbar.classList.add('header-visible');
        }
      }
    }

    window.addEventListener('scroll', () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          const current = window.pageYOffset || document.documentElement.scrollTop;
          if (current > lastScroll + 15 && current > 80) {
            setHeadersHidden(true);
          } else if (current < lastScroll - 15) {
            setHeadersHidden(false);
          }
          lastScroll = Math.max(current, 0);
          ticking = false;
        });
        ticking = true;
      }
    });

    // Mobile menu toggle
    function openMenu() {
      console.log('Opening menu');
      menuBtn.classList.add('open');
      mobileMenu.classList.add('show');
      overlay.classList.add('show');
      document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
      console.log('Closing menu');
      menuBtn.classList.remove('open');
      mobileMenu.classList.remove('show');
      overlay.classList.remove('show');
      document.body.style.overflow = '';
    }

    if (menuBtn) {
      menuBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        console.log('Menu button clicked');
        openMenu();
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeMenu();
      });
    }

    if (overlay) {
      overlay.addEventListener('click', closeMenu);
    }

    // Close on link click
    if (mobileMenu) {
      mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeMenu);
      });
    }
  });
</script>

<script>
  window.gtranslateSettings = {
    "default_language": "fr",
    "detect_browser_language": true,
    "languages": ["fr", "en"],
    "wrapper_selector": ".gtranslate_wrapper",
    "flag_size": 24
  }
</script>
<script src="/widgets/latest/flags.js" defer></script>