<?php

$pageReq=substr($_SERVER['REQUEST_URI'],strrpos($_SERVER['REQUEST_URI'],"/")+1);

if(strpos($pageReq,'uth-login.php')==0){
    setcookie("lastpage", $pageReq);
}
?>

<!doctype html>
<html lang="it">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="<?=SITE_DESCRIPTION?>">
    <link rel="icon" href="assets/images/favicon.ico">
    <title><?=isset($pageTitle) && $pageTitle != '' ? $pageTitle . ' - ' . SITE_TITLE : SITE_TITLE?></title>
    <!-- Simple bar CSS -->
    <link rel="stylesheet" href="css/simplebar.css">
    <!-- Fonts CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,100;0,200;0,300;0,400;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <!-- Icons CSS -->
    <link rel="stylesheet" href="css/feather.css">
    <!-- Date Range Picker CSS -->
    <link rel="stylesheet" href="css/daterangepicker.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme" disabled>
    <link rel="stylesheet" href="css/app-dark.css" id="darkTheme">

    <script src="js/yourradio.js"></script>
    <script>
      // Fix per config.js: assicurati che modeSwitcher esista prima che config.js venga caricato
      // Questo previene errori quando config.js cerca di accedere a switcher.dataset.mode
      // Crea l'elemento immediatamente e lo aggiunge quando il body è disponibile
      (function() {
        if (!document.querySelector('#modeSwitcher')) {
          var switcher = document.createElement('a');
          switcher.id = 'modeSwitcher';
          switcher.className = 'nav-link text-muted my-2';
          switcher.href = '#';
          switcher.setAttribute('data-mode', 'dark');
          switcher.style.display = 'none';
          
          // Prova ad aggiungere immediatamente se il body esiste
          if (document.body) {
            document.body.appendChild(switcher);
          } else {
            // Altrimenti aspetta che il body sia disponibile
            var checkBody = setInterval(function() {
              if (document.body) {
                document.body.appendChild(switcher);
                clearInterval(checkBody);
              }
            }, 10);
            // Timeout di sicurezza dopo 1 secondo
            setTimeout(function() {
              clearInterval(checkBody);
              if (document.body && !document.querySelector('#modeSwitcher')) {
                document.body.appendChild(switcher);
              }
            }, 1000);
          }
        }
      })();
    </script>
  </head>