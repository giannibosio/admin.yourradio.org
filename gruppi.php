<?php
// Start the session
session_start();
include_once('load.php');

if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}

include_once('inc/head.php');

$tabs = '<div id="gruppi-container" class="row"><div class="col-12 text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Caricamento...</span></div></div></div>'; // Container per i gruppi caricati via API
$scripts = ''; // Inizializza anche $scripts per evitare warning

$script='
<script>
  // Carica i gruppi dall\'API
  function loadGruppi() {
    $.ajax({
      url: "https://yourradio.org/api/gruppi",
      type: "GET",
      dataType: "json",
      success: function(response) {
        console.log("=== GRUPPI DALL\'API ===");
        console.log(JSON.stringify(response, null, 2));
        if (response.success && response.data) {
          var html = "";
          response.data.forEach(function(g) {
            var active = g.attivo == 1 ? "primary" : "secondary";
            var active_text = g.attivo == 1 ? "attivo" : "disabilitato";
            html += \'<div class="col-md-6 col-xl-3 mb-4">\';
            html += \'<div class="card shadow bg-\' + active + \' text-white">\';
            html += \'<div class="card-body" style="cursor:pointer;" onclick="window.location = \\\'gruppo-scheda.php?id=\' + g.id + \'\\\';">\';
            html += \'<div class="row align-items-center">\';
            html += \'<div class="col pr-0">\';
            html += \'<p class="h5 text-white mb-0">\' + g.nome + \'</p>\';
            html += \'<span class="h3 small text-white">\' + g.players + \' players</span><br>\';
            html += \'<span class="h3 small text-white">\' + active_text + \'</span>\';
            html += \'</div></div></div></div></div>\';
          });
          $("#gruppi-container").html(html);
        } else {
          $("#gruppi-container").html(\'<div class="col-12"><div class="alert alert-danger">Errore nel caricamento dei gruppi</div></div>\');
        }
      },
      error: function(xhr, status, error) {
        console.error("Errore nel caricamento dei gruppi:", error);
        $("#gruppi-container").html(\'<div class="col-12"><div class="alert alert-danger">Errore nel caricamento dei gruppi: \' + error + \'</div></div>\');
      }
    });
  }

  // Carica i gruppi al caricamento della pagina
  $(document).ready(function() {
    loadGruppi();
  });

  // Gestione creazione nuovo gruppo
  $("#addNewGroup").click(function() {
    var groupName = $("#newName").val().trim().toUpperCase();
    if(groupName === "") {
      alert("Inserisci un nome per il gruppo");
      return;
    }
    console.log("aggiungi nuovo gruppo: " + groupName);
    
    // Chiama l\'API per creare il gruppo (nome già in maiuscolo)
    $.ajax({
      url: "https://yourradio.org/api/gruppi",
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify({ nome: groupName }),
      dataType: "json",
      success: function(response) {
        if (response.success && response.data && response.data.id) {
          // Chiudi il modale
          $("#newGroupAddModal").modal("hide");
          // Reindirizza alla scheda del nuovo gruppo
          window.location = "gruppo-scheda.php?id=" + response.data.id;
        } else {
          alert("Errore nella creazione del gruppo");
        }
      },
      error: function(xhr, status, error) {
        console.error("Errore nella creazione del gruppo:", error);
        alert("Errore nella creazione del gruppo: " + error);
      }
    });
  });
</script>';

          ?>
          <body class="horizontal dark">
            <div class="wrapper">
              <?php include_once('inc/menu-h.php'); ?>
              <main role="main" class="main-content">
                <div class="container-fluid">
                  <div class="row justify-content-center">
                    <div class="col-12">
                      <div class="row">
                        <!-- Small table -->
                        <div class="col-md-12 my-4 monitor-table">
                          <div class="row align-items-center mb-4">
                            <div class="col">
                              <h2 class="mb-2 page-title">
                                <span class="avatar avatar-sm mt-2">
                                  <span class="fe fe-layers fe-20"></span>

                                    Gruppi
                                </span>
                              </h2>
                            </div>
                            <div class="col-auto">
                              <button title="" type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#newGroupAddModal"><span class="fe fe-plus fe-16"></span> Crea nuovo gruppo</button>
                            </div>
                          </div>
                          <div class="row">
                          <?=$tabs?>
                        </div>
                        </div>
                      </div>

<!-- modal nuovo gruppo -->
                  <div class="modal fade" id="newGroupAddModal" tabindex="-1" role="dialog" aria-labelledby="varyModalLabel" style="display: none;" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="varyModalLabel">Nuovo Gruppo</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                          </button>
                        </div>
                        <div class="modal-body">
                          <form id="newGroupForm" action="gruppi.php" method="get">
                            <div class="form-group">
                              <label for="recipient-name" class="col-form-label">Nome del Gruppo:</label>
                              <input type="text" style="text-transform: uppercase" class="form-control" name="newName" id="newName" required>
                              (non sara' possibile cambiarlo!)
                            </div>
                          </form>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
                          <button type="button" class="btn mb-2 btn-primary" id="addNewGroup">Aggiungi</button>
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

            <script src='js/jquery.dataTables.min.js'></script>
            <script src='js/dataTables.bootstrap4.min.js'></script>
            <script src='https://cdn.datatables.net/buttons/1.6.5/js/dataTables.buttons.min.js'></script>
            <script src='https://cdn.datatables.net/buttons/1.6.5/js/buttons.colVis.min.js'></script>
            <?=$script?>
            <?php if(isset($scripts) && !empty($scripts)): ?>
            <script>
             <?=$scripts?>
            </script>
            <?php endif; ?>  

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