<nav class="navbar navbar-expand-lg navbar-light bg-white flex-row border-bottom shadow mb-0">
        <div class="container-fluid">
          <a class="navbar-brand mx-lg-1 mt-2 mr-0" href="./index.php">
            <img src="./assets/images/logo-yourradio-maxi.png" alt="Yourradio" height="60">
          </a>
          <button class="navbar-toggler mt-2 mr-auto toggle-sidebar text-muted">
            <i class="fe fe-menu navbar-toggler-icon"></i>
          </button>
          <style>
            /* In modalità "horizontal" il tema rende .navbar-slide off-canvas (fixed/width 0),
               quindi l'allineamento del menu a destra non funziona: forziamo layout normale. */
            body.horizontal .navbar-slide#navbarSupportedContent{
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
            /* Menu: colore di default voci + dimensione font */
            #navbarSupportedContent .navbar-nav .nav-link,
            #navbarSupportedContent .navbar-nav .dropdown-menu .nav-link{
              color: #fff !important;
              font-size: 18px !important; /* da 17px */
            }
            /* Mantieni testo bianco anche su hover/focus */
            #navbarSupportedContent .navbar-nav .nav-link:hover,
            #navbarSupportedContent .navbar-nav .nav-link:focus{
              color: #fff !important;
            }
          </style>
          <div class="navbar-slide bg-white d-flex justify-content-end" id="navbarSupportedContent">
            <a href="#" class="btn toggle-sidebar d-lg-none text-muted ml-2 mt-3" data-toggle="toggle">
              <i class="fe fe-x"><span class="sr-only"></span></i>
            </a>
            <ul class="navbar-nav">
              <li class="nav-item">
                <a class="nav-link" href="index.php">
                  <i class="fe fe-home fe-16"></i><span class="ml-lg-2">Home</span>
                </a>
              </li>

              <li class="nav-item dropdown">
                <a href="index.php" id="dashboardDropdown" class="dropdown-toggle nav-link" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fe fe-activity fe-16"></i><span class="ml-lg-2">Generale</span><span class="sr-only"></span>
                </a>
                <div class="dropdown-menu" aria-labelledby="dashboardDropdown">
                  <a class="nav-link pl-lg-2" href="./profili.php"><span class="ml-1">Profili</span></a>
                  <a class="nav-link pl-lg-2" href="./songs.php"><span class="ml-1">Songs</span></a>
                  <a class="nav-link pl-lg-2" href="./format.php"><span class="ml-1">Format</span></a>
                  <a class="nav-link pl-lg-2" href="./a-rubriche.php"><span class="ml-1">Rubriche</span></a>
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
            </ul>
          </div>
          <!--<form class="form-inline ml-md-auto d-none d-lg-flex searchform text-muted">
            <input class="form-control mr-sm-2 bg-transparent border-0 pl-4 text-muted" type="search" placeholder="Type something..." aria-label="Search">
          </form>-->
          <ul class="navbar-nav d-flex flex-row">
            
            <!--
            <li class="nav-item nav-notif">
              <a class="nav-link text-muted my-2" href="./#" data-toggle="modal" data-target=".modal-notif">
                <i class="fe fe-bell fe-16"></i>
                <span class="dot dot-md bg-success"></span>
              </a>
            </li>
          -->
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