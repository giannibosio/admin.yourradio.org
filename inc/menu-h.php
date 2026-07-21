<nav class="navbar navbar-expand-lg navbar-light bg-white flex-row border-bottom shadow mb-0">
        <div class="container-fluid">
          <a class="navbar-brand mx-lg-1 mt-2 mr-0" href="./index.php">
            <img src="./assets/images/logo-yourradio-maxi.png" alt="Yourradio" height="60">
          </a>

          <button type="button" class="navbar-toggler yr-menu-hamburger d-lg-none text-muted ml-auto" aria-label="Apri menu" aria-expanded="false" aria-controls="navbarSupportedContent">
            <i class="fe fe-menu fe-20"></i>
          </button>

          <style>
            /* Desktop: menu inline a destra */
            @media (min-width: 992px) {
              body.horizontal .navbar-slide#navbarSupportedContent {
                position: static !important;
                left: auto !important;
                width: auto !important;
                max-width: none !important;
                height: auto !important;
                overflow: visible !important;
                display: flex !important;
                justify-content: flex-end !important;
                margin-left: auto;
              }
            }

            /* Mobile: menu hamburger off-canvas */
            @media (max-width: 991.98px) {
              body.horizontal .navbar-slide#navbarSupportedContent {
                position: fixed !important;
                top: 0;
                left: 0 !important;
                right: auto !important;
                width: 0 !important;
                max-width: 0 !important;
                height: 100vh;
                overflow-x: hidden;
                overflow-y: auto;
                display: block !important;
                justify-content: flex-start !important;
                margin-left: 0 !important;
                z-index: 1050;
                background: #212529;
                box-shadow: 2px 0 12px rgba(0, 0, 0, 0.35);
                transition: width 0.3s ease, max-width 0.3s ease;
              }
              body.horizontal .navbar-slide#navbarSupportedContent.show {
                width: min(16rem, 85vw) !important;
                max-width: min(16rem, 85vw) !important;
              }
              body.horizontal .navbar-slide#navbarSupportedContent .navbar-nav {
                flex-direction: column;
                width: 100%;
                padding: 0.5rem 0 1.5rem;
              }
              body.horizontal .navbar-slide#navbarSupportedContent .nav-item {
                width: 100%;
              }
              body.horizontal .navbar-slide#navbarSupportedContent .nav-link {
                padding: 0.75rem 1.25rem;
                width: 100%;
              }
              body.horizontal .navbar-slide#navbarSupportedContent .dropdown-menu {
                position: static !important;
                float: none;
                border: none;
                background: transparent;
                box-shadow: none;
                margin: 0;
                padding-left: 0.5rem;
              }
              body.horizontal .navbar-slide#navbarSupportedContent .dropdown-toggle::after {
                float: right;
                margin-top: 0.35rem;
              }
              .yr-mobile-menu-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                z-index: 1040;
              }
              .yr-mobile-menu-backdrop.show {
                display: block;
              }
              body.yr-mobile-menu-open {
                overflow: hidden;
              }
            }

            /* Menu: colore di default voci + dimensione font */
            #navbarSupportedContent .navbar-nav .nav-link,
            #navbarSupportedContent .navbar-nav .dropdown-menu .nav-link {
              color: #fff !important;
              font-size: 18px !important;
            }
            #navbarSupportedContent .navbar-nav .nav-link:hover,
            #navbarSupportedContent .navbar-nav .nav-link:focus {
              color: #fff !important;
            }
            .yr-menu-hamburger-close {
              color: #fff !important;
            }
          </style>

          <div class="yr-mobile-menu-backdrop d-lg-none" id="yrMobileMenuBackdrop" aria-hidden="true"></div>

          <div class="navbar-slide bg-white d-flex justify-content-end" id="navbarSupportedContent">
            <button type="button" class="btn yr-menu-hamburger-close d-lg-none text-muted ml-2 mt-3" aria-label="Chiudi menu">
              <i class="fe fe-x fe-20"><span class="sr-only">Chiudi</span></i>
            </button>
            <ul class="navbar-nav">
              <li class="nav-item">
                <a class="nav-link" href="index.php">
                  <i class="fe fe-home fe-16"></i><span class="ml-lg-2">Home</span>
                </a>
              </li>

              <li class="nav-item dropdown">
                <a href="#" id="musicaDropdown" class="dropdown-toggle nav-link" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fe fe-music fe-16"></i><span class="ml-lg-2">Musica</span><span class="sr-only"></span>
                </a>
                <div class="dropdown-menu" aria-labelledby="musicaDropdown">
                  <a class="nav-link pl-lg-2" href="./songs.php"><span class="ml-1">Songs</span></a>
                  <a class="nav-link pl-lg-2" href="./format.php"><span class="ml-1">Format</span></a>
                </div>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="./gruppi.php">
                  <i class="fe fe-layers fe-16"></i><span class="ml-lg-2">Gruppi </span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="./monitor-sso.php" target="_blank" rel="noopener noreferrer">
                  <i class="fe fe-monitor fe-16"></i><span class="ml-lg-2">Monitor </span>
                </a>
              </li>

              <li class="nav-item dropdown">
                <a href="#" id="impostazioniDropdown" class="dropdown-toggle nav-link" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fe fe-settings fe-16"></i><span class="ml-lg-2">Impostazioni</span><span class="sr-only"></span>
                </a>
                <div class="dropdown-menu" aria-labelledby="impostazioniDropdown">
                  <a class="nav-link pl-lg-2" href="./profili.php"><span class="ml-1">Profili</span></a>
                </div>
              </li>
            </ul>
          </div>

          <ul class="navbar-nav d-flex flex-row">
            <li class="nav-item dropdown ml-lg-0">
              <a class="nav-link dropdown-toggle text-muted" href="#" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="avatar avatar-sm mt-2">
                  <img src="./assets/avatars/face-1.jpg" alt="..." class="avatar-img rounded-circle">
                </span>
              </a>
              <ul class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdownMenuLink">
                <a class="dropdown-item" href="profilo-scheda.php?id=<?=$_SESSION["userID"]?>">Profilo</a>
                <a class="dropdown-item" href="#">Configurazioni</a>
                <a class="dropdown-item" href="auth-login.php?t=logout">Disconnetti</a>
              </ul>
            </li>
          </ul>
        </div>
      </nav>
      <script>
      (function() {
        var panel = document.getElementById('navbarSupportedContent');
        var backdrop = document.getElementById('yrMobileMenuBackdrop');
        var openBtn = document.querySelector('.yr-menu-hamburger');
        var closeBtn = document.querySelector('.yr-menu-hamburger-close');
        if (!panel || !openBtn) return;

        function setOpen(isOpen) {
          panel.classList.toggle('show', isOpen);
          if (backdrop) backdrop.classList.toggle('show', isOpen);
          document.body.classList.toggle('yr-mobile-menu-open', isOpen);
          openBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        function openMenu() { setOpen(true); }
        function closeMenu() { setOpen(false); }

        openBtn.addEventListener('click', function(e) {
          e.preventDefault();
          if (panel.classList.contains('show')) closeMenu();
          else openMenu();
        });

        if (closeBtn) {
          closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            closeMenu();
          });
        }

        if (backdrop) {
          backdrop.addEventListener('click', closeMenu);
        }

        panel.querySelectorAll('a.nav-link[href]').forEach(function(link) {
          var href = link.getAttribute('href');
          if (href && href !== '#') {
            link.addEventListener('click', closeMenu);
          }
        });

        window.addEventListener('resize', function() {
          if (window.innerWidth >= 992) closeMenu();
        });
      })();
      </script>
