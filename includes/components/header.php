<!-- TOPBAR réseaux sociaux -->

<?php
$role = htmlspecialchars($_SESSION['role'] ?? '');
$dashboard_url = ($role === 'admin') ? '/admin' : "/dashboard-{$role}.php";

?>

<div id="topbar-social"
  class="bg-gradient-to-r from-green-500/90 via-green-550/90 to-green-600/90 backdrop-blur-md text-white text-xs select-none header-visible fixed w-full top-0 left-0 transition-transform duration-500 will-change-transform"
  style="z-index: 2000;">
  <div class="flex justify-between items-center px-5 md:px-10 mx-auto h-10">
    <div class="flex items-center space-x-3">
      <button id="lang-btn"
        class="flex items-center gap-2 px-3 py-1 bg-white/10 rounded-full backdrop-blur-sm hover:bg-white/20 transition-all duration-300 cursor-pointer notranslate">
        <svg class="w-3.5 h-3.5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path
            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
        </svg>
        <span id="current-lang" class="text-xs font-medium notranslate">FR</span>
        <svg class="w-3 h-3 fill-current" viewBox="0 0 12 8">
          <path d="M1 1l5 5 5-5" />
        </svg>
      </button>
      <!-- Hidden GTranslate container -->
      <div class="gtranslate_wrapper" style="display: none;"></div>
    </div>

    <div class="flex items-center gap-2">
      <!-- Facebook -->
      <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" aria-label="Facebook"
        class="group w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/25 transition-all duration-300 hover:scale-110">
        <svg class="w-3.5 h-3.5 fill-current group-hover:scale-110 transition-transform duration-300"
          xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path
            d="M22 12a10 10 0 10-11.5 9.9v-7h-2v-3h2v-2c0-1.7 1-2.6 2.5-2.6.7 0 1.4.1 1.4.1v1.6h-.8c-.8 0-1 .5-1 1v1.5h1.8l-.3 3h-1.5v7A10 10 0 0022 12z" />
        </svg>
      </a>
      <!-- Twitter -->
      <a href="https://twitter.com" target="_blank" rel="noopener noreferrer" aria-label="Twitter"
        class="group w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/25 transition-all duration-300 hover:scale-110">
        <svg class="w-3.5 h-3.5 fill-current group-hover:scale-110 transition-transform duration-300"
          xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path
            d="M23 3a10.9 10.9 0 01-3.1.9 4.8 4.8 0 002-2.6 9.7 9.7 0 01-3 1.2A4.8 4.8 0 0016.5 2c-2.7 0-4.9 2.1-4.9 4.8 0 .4 0 .7.1 1A13.7 13.7 0 013 2.5a4.6 4.6 0 001.5 6.4 4.6 4.6 0 01-2.1-.6v.1c0 2.3 1.6 4.2 3.7 4.6a4.9 4.9 0 01-2.1.1c.6 1.8 2.5 3.1 4.7 3.1A9.6 9.6 0 012 19.5a13.7 13.7 0 007.4 2.2c8.8 0 13.7-7.3 13.7-13.7 0-.2 0-.4 0-.6A9.8 9.8 0 0023 3z" />
        </svg>
      </a>
      <!-- LinkedIn -->
      <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"
        class="group w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/25 transition-all duration-300 hover:scale-110">
        <svg class="w-3.5 h-3.5 fill-current group-hover:scale-110 transition-transform duration-300"
          xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path
            d="M4 3a2 2 0 11-.001 3.999A2 2 0 014 3zM2 9h4v12H2zM8 9h4v1.7h.1a4.4 4.4 0 013.9-2.1c4.1 0 4.9 2.7 4.9 6.1v7.3h-4v-6.5c0-1.5 0-3.4-2.1-3.4s-2.4 1.7-2.4 3.3v6.6H8z" />
        </svg>
      </a>
      <!-- Instagram -->
      <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram"
        class="group w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/25 transition-all duration-300 hover:scale-110">
        <svg class="w-3.5 h-3.5 fill-current group-hover:scale-110 transition-transform duration-300"
          xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path
            d="M7 2C4 2 2 4 2 7v10c0 3 2 5 5 5h10c3 0 5-2 5-5V7c0-3-2-5-5-5H7zm10 2a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM12 7a5 5 0 110 10 5 5 0 010-10zm0 2a3 3 0 100 6 3 3 0 000-6z" />
        </svg>
      </a>
    </div>
  </div>
</div>

<!-- HEADER principal -->
<header id="header-main"
  class="bg-white/80 backdrop-blur-xl shadow-sm fixed w-full left-0 header-visible transition-transform duration-500 will-change-transform border-b border-white/20"
  style="top: 40px; z-index: 2000;">
  <div class="max-w-7xl mx-auto px-4 md:px-8">
    <!-- Ligne principale -->
    <div class="flex justify-between items-center h-16">
      <!-- Logo -->
      <a href="/index.php" class="group flex items-center space-x-3 transition-all duration-300">
        <div
          class="relative w-10 h-10 rounded-full overflow-hidden shadow-md bg-gradient-to-br from-green-400 via-green-500 to-green-600 group-hover:shadow-lg group-hover:scale-105 transition-all duration-300">
          <img src="/assets/img/logo.jpg" alt="Logo" class="w-full h-full object-cover"
            onerror="this.style.display='none'" />
          <div class="absolute inset-0 bg-white/0 group-hover:bg-white/10 transition-colors duration-300"></div>
        </div>
        <span
          class="font-bold text-lg text-gray-900 select-none whitespace-nowrap tracking-tight uppercase group-hover:text-green-600 transition-colors duration-300">
          Médecins Ruraux
        </span>
      </a>

      <!-- Menu desktop -->
      <nav class="hidden md:flex items-center gap-3">
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="<?php echo $dashboard_url; ?>"
            class="group relative px-6 py-2.5 bg-gradient-to-r from-green-400 via-green-500 to-green-600 text-white rounded-full font-medium overflow-hidden transition-all duration-300 shadow-md hover:shadow-xl hover:scale-105">
            <span class="relative z-10 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
              </svg>
              Espace <?php echo $role; ?>
            </span>
          </a>

          <a href="/logout.php"
            class="group px-6 py-2.5 border-2 border-green-500 text-green-600 rounded-full font-medium hover:bg-green-500 hover:text-white transition-all duration-300 hover:shadow-lg hover:scale-105">
            <span class="flex items-center gap-2">
              <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              Se déconnecter
            </span>
          </a>

          <!-- Navigation Accueil/Contact intégrée -->
          <div class="flex items-center gap-2 ml-2 pl-2 border-l border-gray-200">
            <a href="/index.php"
              class="group px-4 py-2 text-sm font-medium text-gray-700 hover:text-green-600 rounded-lg hover:bg-green-50 transition-all duration-300">
              <span class="flex items-center gap-2">
                <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Accueil
              </span>
            </a>
            <a href="/contact.php"
              class="group px-4 py-2 text-sm font-medium text-gray-700 hover:text-green-600 rounded-lg hover:bg-green-50 transition-all duration-300">
              <span class="flex items-center gap-2">
                <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Contact
              </span>
            </a>
          </div>

        <?php else: ?>
          <a href="/login.php?role=recruteur"
            class="group relative px-6 py-2.5 bg-gradient-to-r from-green-400 via-green-500 to-green-600 text-white rounded-full font-medium overflow-hidden transition-all duration-300 shadow-md hover:shadow-xl hover:scale-105">
            <span class="relative z-10 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              Recruteur
            </span>
          </a>

          <a href="/login.php?role=candidat"
            class="group px-6 py-2.5 border-2 border-green-500 text-green-600 rounded-full font-medium hover:bg-green-500 hover:text-white transition-all duration-300 hover:shadow-lg hover:scale-105">
            <span class="flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Candidat
            </span>
          </a>

          <!-- Navigation Accueil/Contact intégrée -->
          <div class="flex items-center gap-2 ml-2 pl-2 border-l border-gray-200">
            <a href="/index.php"
              class="group px-4 py-2 text-sm font-medium text-gray-700 hover:text-green-600 rounded-lg hover:bg-green-50 transition-all duration-300">
              <span class="flex items-center gap-2">
                <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Accueil
              </span>
            </a>
            <a href="/contact.php"
              class="group px-4 py-2 text-sm font-medium text-gray-700 hover:text-green-600 rounded-lg hover:bg-green-50 transition-all duration-300">
              <span class="flex items-center gap-2">
                <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Contact
              </span>
            </a>
          </div>
        <?php endif; ?>
      </nav>


      <!-- Hamburger mobile -->
      <button id="menu-btn" aria-label="Menu" type="button"
        class="md:hidden relative w-11 h-11 flex flex-col items-center justify-center gap-1.5 rounded-full bg-gradient-to-r from-green-400 via-green-500 to-green-600 shadow-md hover:shadow-lg focus:outline-none hover:scale-105 transition-all duration-300">
        <span class="block w-5 h-0.5 bg-white rounded transition-all duration-300"></span>
        <span class="block w-5 h-0.5 bg-white rounded transition-all duration-300"></span>
        <span class="block w-5 h-0.5 bg-white rounded transition-all duration-300"></span>
      </button>
    </div>
  </div>
</header>

<!-- Overlay -->
<div id="mobile-menu-overlay"
  class="fixed inset-0 bg-black/60 backdrop-blur-sm opacity-0 invisible transition-all duration-300"
  style="z-index: 2500;"></div>

<!-- MENU mobile -->
<div id="mobile-menu"
  class="fixed top-0 left-0 w-80 h-full bg-white shadow-2xl transition-all duration-300 flex flex-col"
  style="z-index: 3000; transform: translateX(-100%);">

  <!-- Header menu mobile avec logo -->
  <div
    class="flex items-center justify-between p-6 border-b border-gray-100 bg-gradient-to-r from-green-50 to-green-100">
    <div class="flex items-center gap-3">
      <div
        class="w-10 h-10 rounded-full overflow-hidden shadow-md bg-gradient-to-br from-green-400 via-green-500 to-green-600">
        <img src="/assets/img/logo.jpg" alt="Logo" class="w-full h-full object-cover"
          onerror="this.style.display='none'" />
      </div>
      <span class="font-bold text-base text-gray-900 uppercase tracking-tight">Médecins Ruraux</span>
    </div>
    <button id="mobile-menu-close" aria-label="Fermer menu"
      class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-white/50 transition-all duration-300 hover:rotate-90">
      <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 overflow-y-auto p-6 space-y-3">
    <a href="/index.php"
      class="group flex items-center gap-3 px-4 py-3 text-gray-700 font-medium rounded-xl hover:bg-gradient-to-r hover:from-green-50 hover:to-green-100 hover:text-green-700 transition-all duration-300">
      <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" stroke="currentColor"
        viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
      </svg>
      <span>Accueil</span>
    </a>
    <a href="/contact.php"
      class="group flex items-center gap-3 px-4 py-3 text-gray-700 font-medium rounded-xl hover:bg-gradient-to-r hover:from-green-50 hover:to-green-100 hover:text-green-700 transition-all duration-300">
      <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" stroke="currentColor"
        viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
      </svg>
      <span>Contact</span>
    </a>

    <div class="pt-4 mt-4 border-t border-gray-200">
      <p class="px-4 mb-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Informations légales</p>
      <a href="/legal.php?policy=mentions"
        class="group flex items-center gap-3 px-4 py-3 text-gray-700 font-medium rounded-xl hover:bg-gradient-to-r hover:from-green-50 hover:to-green-100 hover:text-green-700 transition-all duration-300">
        <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" stroke="currentColor"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span>Mentions légales</span>
      </a>
      <a href="/legal.php?policy=privacy_policy"
        class="group flex items-center gap-3 px-4 py-3 text-gray-700 font-medium rounded-xl hover:bg-gradient-to-r hover:from-green-50 hover:to-green-100 hover:text-green-700 transition-all duration-300">
        <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" stroke="currentColor"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        <span>Confidentialité</span>
      </a>
      <a href="/legal.php?policy=cookies"
        class="group flex items-center gap-3 px-4 py-3 text-gray-700 font-medium rounded-xl hover:bg-gradient-to-r hover:from-green-50 hover:to-green-100 hover:text-green-700 transition-all duration-300">
        <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" stroke="currentColor"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>Cookies</span>
      </a>
    </div>
  </nav>

  <!-- Actions -->
  <div class="p-6 border-t border-gray-100 space-y-3 bg-gradient-to-b from-transparent to-gray-50">
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="/dashboard-<?php echo $_SESSION['role']; ?>.php"
        class="group relative block w-full text-center px-6 py-3.5 bg-gradient-to-r from-green-400 via-green-500 to-green-600 text-white rounded-xl font-semibold overflow-hidden transition-all duration-300 shadow-md hover:shadow-xl">
        <span class="relative z-10 flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
          </svg>
          Espace <?php echo $role; ?>
        </span>
        <div
          class="absolute inset-0 bg-white/20 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left">
        </div>
      </a>
      <a href="/logout.php"
        class="group block w-full text-center px-6 py-3.5 border-2 border-green-500 text-green-600 rounded-xl font-semibold hover:bg-green-500 hover:text-white transition-all duration-300">
        <span class="flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          Se déconnecter
        </span>
      </a>
    <?php else: ?>
      <a href="/login.php?role=candidat"
        class="group relative block w-full text-center px-6 py-3.5 bg-gradient-to-r from-green-400 via-green-500 to-green-600 text-white rounded-xl font-semibold overflow-hidden transition-all duration-300 shadow-md hover:shadow-xl">
        <span class="relative z-10 flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          Espace candidat
        </span>
        <div
          class="absolute inset-0 bg-white/20 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left">
        </div>
      </a>
      <a href="/login.php?role=recruteur"
        class="group block w-full text-center px-6 py-3.5 border-2 border-green-500 text-green-600 rounded-xl font-semibold hover:bg-green-500 hover:text-white transition-all duration-300">
        <span class="flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          Espace recruteur
        </span>
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Langues -->
<div id="lang-modal"
  class="fixed inset-0 bg-black/60 backdrop-blur-sm opacity-0 invisible transition-all duration-300 flex items-start justify-center pt-20"
  style="z-index: 3500;">
  <div class="bg-white rounded-2xl shadow-2xl w-80 transform scale-95 transition-all duration-300"
    id="lang-modal-content">
    <!-- Header Modal -->
    <div class="flex items-center justify-between p-5 border-b border-gray-100">
      <div class="flex items-center gap-3">
        <div
          class="w-10 h-10 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path
              d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
          </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900">Choisir la langue</h3>
      </div>
      <button id="lang-modal-close"
        class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors duration-200">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Liste des langues - GTranslate injectera les drapeaux ici -->
    <div id="lang-flags-container" class="p-4">
      <div class="gtranslate_wrapper_modal"></div>
    </div>
  </div>
</div>

<style>

  .header-hidden {
    transform: translateY(-150px);
    pointer-events: none;
  }

  .header-visible {
    transform: translateY(0);
    pointer-events: all;
  }

  body {
    padding-top: 96px;
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

  /* Modal langues */
  #lang-modal.show {
    opacity: 1 !important;
    visibility: visible !important;
  }

  #lang-modal.show #lang-modal-content {
    transform: scale(1) !important;
  }

  /* Style pour les drapeaux GTranslate dans la modal */
  .gtranslate_wrapper_modal {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .gtranslate_wrapper_modal a.glink {
    display: flex !important;
    align-items: center;
    gap: 16px;
    padding: 12px 16px;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.3s;
    position: relative;
  }

  .gtranslate_wrapper_modal a.glink:hover {
    background: linear-gradient(to right, rgb(240 253 244), rgb(220 252 231));
  }

  .gtranslate_wrapper_modal a.glink img {
    width: 32px !important;
    height: 32px !important;
    border-radius: 50%;
    margin: 0 !important;
    opacity: 1 !important;
  }

  .gtranslate_wrapper_modal a.glink::after {
    content: attr(title);
    font-weight: 600;
    color: #111827;
    flex: 1;
  }

  .gtranslate_wrapper_modal a.glink.gt-current-lang {
    background: linear-gradient(to right, rgb(134 239 172), rgb(187 247 208));
  }

  .gtranslate_wrapper_modal a.glink.gt-current-lang::before {
    content: '✓';
    position: absolute;
    right: 16px;
    color: #16a34a;
    font-weight: bold;
    font-size: 18px;
  }

  /* Masquer le widget GTranslate principal */
  .gtranslate_wrapper:not(.gtranslate_wrapper_modal) {
    display: none !important;
  }

  /* Animation hamburger améliorée */
  #menu-btn.open span:nth-child(1) {
    transform: rotate(45deg) translateY(8px);
  }

  #menu-btn.open span:nth-child(2) {
    opacity: 0;
    transform: scale(0);
  }

  #menu-btn.open span:nth-child(3) {
    transform: rotate(-45deg) translateY(-8px);
  }

  /* Animation pulse pour le globe */
  @keyframes pulse {

    0%,
    100% {
      opacity: 1;
    }

    50% {
      opacity: 0.6;
    }
  }

  .animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  }

  /* Smooth scroll */
  html {
    scroll-behavior: smooth;
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

    // Éléments de la modal langues
    const langBtn = document.getElementById('lang-btn');
    const langModal = document.getElementById('lang-modal');
    const langModalClose = document.getElementById('lang-modal-close');
    const currentLangSpan = document.getElementById('current-lang');

    // Détecter la langue actuelle et mettre à jour le bouton
    function updateCurrentLangButton() {
      const currentLangLink = document.querySelector('.gtranslate_wrapper_modal a.gt-current-lang');
      if (currentLangLink) {
        const langCode = currentLangLink.getAttribute('data-gt-lang');
        if (langCode) {
          // Mapping personnalisé pour affichage
          const langDisplay = {
            'fr': 'FR',
            'en': 'EN',
            'es': 'ES',
            'it': 'IT',
            'de': 'DE'
          };
          currentLangSpan.textContent = langDisplay[langCode] || langCode.toUpperCase();
        }
      }
    }

    // Observer pour détecter quand GTranslate charge
    const observer = new MutationObserver(() => {
      updateCurrentLangButton();
    });

    const langContainer = document.querySelector('.gtranslate_wrapper_modal');
    if (langContainer) {
      observer.observe(langContainer, { childList: true, subtree: true });
    }

    // Mise à jour initiale après un court délai pour laisser GTranslate charger
    setTimeout(updateCurrentLangButton, 500);

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
      menuBtn?.classList.add('open');
      mobileMenu?.classList.add('show');
      overlay?.classList.add('show');
      document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
      menuBtn?.classList.remove('open');
      mobileMenu?.classList.remove('show');
      overlay?.classList.remove('show');
      document.body.style.overflow = '';
    }

    menuBtn?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      openMenu();
    });

    closeBtn?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeMenu();
    });

    overlay?.addEventListener('click', closeMenu);

    // Close on link click
    mobileMenu?.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', closeMenu);
    });

    // Close menu on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (mobileMenu?.classList.contains('show')) {
          closeMenu();
        }
        if (langModal?.classList.contains('show')) {
          closeLangModal();
        }
      }
    });

    // Gestion de la modal langues
    function openLangModal() {
      langModal?.classList.add('show');
      const content = document.getElementById('lang-modal-content');
      if (content) {
        content.style.transform = 'scale(1)';
      }
    }

    function closeLangModal() {
      langModal?.classList.remove('show');
      const content = document.getElementById('lang-modal-content');
      if (content) {
        content.style.transform = 'scale(0.95)';
      }
    }

    langBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      openLangModal();
    });

    langModalClose?.addEventListener('click', closeLangModal);

    langModal?.addEventListener('click', (e) => {
      if (e.target === langModal) {
        closeLangModal();
      }
    });

    // Fermer la modal quand on clique sur un drapeau
    document.addEventListener('click', (e) => {
      if (e.target.closest('.gtranslate_wrapper_modal a.glink')) {
        setTimeout(() => {
          closeLangModal();
          updateCurrentLangButton();
        }, 300);
      }
    });
  });
</script>

<script>
  window.gtranslateSettings = {
    "default_language": "fr",
    "detect_browser_language": true,
    "languages": ["fr", "en", "es", "it", "de"],
    "wrapper_selector": ".gtranslate_wrapper, .gtranslate_wrapper_modal",
    "flag_size": 32
  }
</script>
<script src="/widgets/latest/flags.js" defer></script>