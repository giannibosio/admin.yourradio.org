<?php
// Start the session
session_start();
include_once('load.php');

if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}

include_once('inc/head.php');

$scripts = '';
$cards = '';

$scripts.='
$(document).ready(function() {
  // Carica i format dall\'API e crea le card
  $.ajax({
    url: "https://yourradio.org/api/formats?all=1",
    method: "GET",
    dataType: "json",
    cache: false,
    success: function(response) {
      if(response.success && response.data) {
        var $container = $("#format-cards-container");
        $container.empty();
        
        // Ordina i format per nome
        var formats = response.data.sort(function(a, b) {
          var nomeA = (a.frmt_nome || \'\').toUpperCase();
          var nomeB = (b.frmt_nome || \'\').toUpperCase();
          if (nomeA < nomeB) return -1;
          if (nomeA > nomeB) return 1;
          return 0;
        });
        
        formats.forEach(function(format) {
          var formatId = format.frmt_id || \'\';
          var formatNome = format.frmt_nome || \'Format #\' + formatId;
          var formatDescrizione = format.frmt_descrizione || \'\';
          var formatPermettiRipetizione = format.frmt_permettiRipetizioneArtista || 0;
          var songsCount = format.songs_count || 0;
          var active = format.frmt_active || 0;
          
          // Determina il colore della card:
          // - Rosso (danger) se attivo ma songs = 0
          // - Verde (success) se attivo e songs > 0
          // - Grigio (secondary) se inattivo
          var bgClass;
          if (active == 1 && songsCount == 0) {
            bgClass = \'bg-danger\';
          } else if (active == 1 && songsCount > 0) {
            bgClass = \'bg-success\';
          } else {
            bgClass = \'bg-secondary\';
          }
          
          var textClass = \'text-dark\'; // Testi neri
          
          var cardHtml = \'<div class="col-md-6 col-xl-3 mb-4">\' +
            \'<div class="card shadow \' + bgClass + \' \' + textClass + \'">\' +
            \'<div class="card-body" style="cursor:pointer;" onclick="window.location = \\\'format-scheda.php?id=\' + formatId + \'\\\';">\' +
            \'<div class="row align-items-center">\' +
            \'<div class="col pr-0">\' +
            \'<p class="h5 \' + textClass + \' mb-0">\' + formatNome.toUpperCase() + \'</p>\';
          
          if (formatDescrizione) {
            cardHtml += \'<span class="h6 small \' + textClass + \' d-block mt-1">\' + formatDescrizione + \'</span>\';
          }
          
          // Se format è attivo ma songs = 0, mostra in nero e grassetto
          // Altrimenti testo normale
          var songsTextClass = (active == 1 && songsCount == 0) ? \'text-dark font-weight-bold\' : textClass;
          cardHtml += \'<span class="h3 small \' + songsTextClass + \' d-block mt-2">\' + songsCount + \' songs</span>\';
          
          var ripetizioneText = formatPermettiRipetizione == 1 ? \'SI\' : \'NO\';
          cardHtml += \'<span class="h6 small \' + textClass + \' d-block">Ripetizione artista: \' + ripetizioneText + \'</span>\';
          
          cardHtml += \'</div>\' +
            \'</div>\' +
            \'</div>\' +
            \'</div>\' +
            \'</div>\';
          
          $container.append(cardHtml);
        });
      }
    },
    error: function(xhr, status, error) {
      console.error("Errore nel caricamento format:", error, xhr);
      $("#format-cards-container").html(\'<div class="col-12"><div class="alert alert-danger">Errore nel caricamento dei format</div></div>\');
    }
  });
});
';

?>
          <body class="horizontal dark">
            <div class="wrapper">
              <?php include_once('inc/menu-h.php'); ?>
              <main role="main" class="main-content">
                <div class="container-fluid">
                  <div class="row justify-content-center">
                    <div class="col-12">
                      <div class="row">
                        <!-- Format cards -->
                        <div class="col-md-12 my-4 monitor-table">
                          <div class="row align-items-center mb-4">
                            <div class="col">
                              <h2 class="mb-2 page-title">
                                <span class="avatar avatar-sm mt-2">
                                  <span class="fe fe-list fe-20"></span> Format
                                </span>
                              </h2>
                            </div>
                            <div class="col-auto">
                              <button class="btn btn-outline-secondary back-lista" onclick="window.open('format-scheda.php?id=nuova','_self');"><span class="fe fe-plus fe-16"></span> Nuovo</button>
                            </div>
                          </div>
                          <div class="row" id="format-cards-container">
                            <!-- Le card verranno caricate dinamicamente via JavaScript -->
                            <div class="col-12 text-center">
                              <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Caricamento...</span>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div> <!-- .col-12 -->
                  </div> <!-- .row -->
                </div> <!-- .container-fluid -->
                <?php include_once('./inc/slide-right.php');?>
              </main> <!-- main -->


            </div> <!-- .wrapper -->
            <script src="js/jquery.min.js"></script>
            <script src="js/popper.min.js"></script>
            <script src="js/moment.min.js"></script>
            <script src="js/bootstrap.min.js"></script>
            <script src="js/simplebar.min.js"></script>
            <script src='js/daterangepicker.js'></script>
            <script src='js/jquery.stickOnScroll.js'></script>
            <script src="js/tinycolor-min.js"></script>
            <script src="js/config.js"></script>

            <script>
             <?=$scripts?>
            </script>  

            <script src="js/apps.js"></script>
            <!-- Global site tag (gtag.js) - Google Analytics -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=UA-56159088-1"></script>
            <script>
              window.dataLayer = window.dataLayer || [];

              function gtag()
              {
                dataLayer.push(arguments);
              }
              gtag('js', new Date());
              gtag('config', 'UA-56159088-1');
            </script>
          </body>

        </html>
