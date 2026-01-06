<?php
// Start the session
session_start();
include_once('load.php');
if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}

// Include la funzione callApi
require_once('inc/functions.php');

// Rileva se è una richiesta AJAX (caricata dentro un div)
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Se non è AJAX, include head e menu
if (!$isAjaxRequest) {
    include_once('inc/head.php');
}

$jingleId = $_GET["id"] ?? '';

// Carica jingle tramite API
$j = [];
$gruppoNome = '';
$file_audio_ok = false;

if(!empty($jingleId) && $jingleId !== 'nuova' && $jingleId !== '0') {
    $apiResponse = callApi("jingles/" . intval($jingleId));
    
    if($apiResponse && isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data'])) {
        $jingleData = $apiResponse['data'];
        $j = [$jingleData];
        $gruppoNome = $jingleData['gr_nome'] ?? '';
        
        // Il file audio esiste se c'è un ID jingle (il file viene generato dal nome basato sull'ID)
        $file_audio_ok = !empty($jingleId) && $jingleId !== 'nuova' && $jingleId !== '0';
    }
} else {
    $j = [['jingle_id' => '', 'jingle_nome' => '', 'jingle_attivo' => 0, 'jingle_file' => '', 'gr_nome' => '']];
}

// Recupera il nome del gruppo da sessionStorage (se disponibile)
// Verrà impostato da JavaScript se non è già presente nei dati
$gruppoNomeFromStorage = '';

if(!empty($j) && isset($j[0])) {
    $id = $j[0]['jingle_id'] ?? '';
    $active = $j[0]['jingle_attivo'] ?? 0;
    if(empty($gruppoNome)) {
        $gruppoNome = $j[0]['gr_nome'] ?? '';
    }
} else {
    $id = '';
    $active = 0;
}

if(!isset($id) || $id==0 || $id==''){
  $disabled=" disabled ";
  $id='nuova';
  $title='Nuovo Jingle';
}else{
  $disabled="";
  $title=$j[0]['jingle_nome'] ?? '';
}

if($active==1){
    $chbox_active="checked";
    $chbox_active_lab="Attivo";
}else{
    $chbox_active="";
    $chbox_active_lab="Disattivato";
}

?>

<?php if (!$isAjaxRequest): ?>
<body class="horizontal dark">
  <div class="wrapper">
    <?php include_once('inc/menu-h.php'); ?>
    <main role="main" class="main-content">
      <div class="container-fluid">
        <div class="row justify-content-center">
          <div class="col-12 col-lg-10 col-xl-6">
<?php else: ?>
           <div class="col-12 col-lg-10 col-xl-12">
<?php endif; ?>
            <div class="my-4">

              <div class="alert alert-primary" style="display:none" role="alert">
                <span class="fe fe-alert-circle fe-16 mr-2"></span>
                <span id="msg_alert"></span>
              </div>

              <div class="my-4">

                <?php if (!$isAjaxRequest): ?>
                <div class="row mt-5 align-items-center">
                  <div class="col-md-12 text-center mb-5">
                     <h2 class="mb-0 text-uppercase"><?=$title?></h2>
                     <small class="text-muted text-uppercase">ID.</small> <?=$id !== 'nuova' ? $id : '?'?>
                  </div>
                </div>
                <?php else: ?>
                <div class="row mt-2 align-items-center">
                  <div class="col-md-2 text-center mb-0">
                     <h2 class="mb-0 text-uppercase"><?=$title?></h2>
                     <small class="text-muted text-uppercase">ID.</small> <?=$id !== 'nuova' ? $id : '?'?>
                  </div>
                </div>
                <?php endif; ?>
              </div>

            <div class="card-body">

              <form id="scheda-jingle" class="needs-validation" novalidate method="post" action="">
                <!-- hidden variables -->
                <input name="jingle_id" id="jingle_id" type="hidden" value="<?=$id !== 'nuova' ? $id : ''?>" >
                <input name="jingle_file" id="jingle_file" type="hidden" value="<?=($j[0]['jingle_file'] ?? '')?>">
                <input name="jingle_gr_id" id="jingle_gr_id" type="hidden" value="<?=($j[0]['jingle_gr_id'] ?? '')?>">
                <input name="gruppo_id_for_back" id="gruppo_id_for_back" type="hidden" value="<?=($j[0]['jingle_gr_id'] ?? '')?>">
                
                <div class="form-row">
                  <!-- ACTIVE -->
                  <div class="mb-3">
                    <div class="custom-control custom-switch">
                      <input type="checkbox" class="custom-control-input" <?=$chbox_active?> id="jingle_attivo" name="jingle_attivo" value="1">
                      <label class="custom-control-label" for="jingle_attivo"><?=$chbox_active_lab?></label>
                    </div>
                  </div>
                </div>
                
                <div class="form-row">
                  <div class="col-md-6 mb-3">
                    <label for="jingle_nome">Nome</label>
                    <input type="text" class="form-control" id="jingle_nome" name="jingle_nome" value="<?=($j[0]['jingle_nome'] ?? '')?>" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="gruppo_nome">Gruppo</label>
                    <input type="text" class="form-control" id="gruppo_nome" name="gruppo_nome" value="<?=$gruppoNome?>" readonly>
                  </div>
                </div>


                <!-- Player audio frame -->
                <div class="form-row" id="form-row-audio-player" style="display:none;">
                  <div class="col-md-12 mb-3">
                    <div class="card">
                      <div class="card-header">
                        <h5 class="card-title mb-0">Player Audio</h5>
                        <button type="button" class="close" id="closeAudioPlayer" aria-label="Close">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="card-body">
                        <audio id="audioPlayer" controls style="width: 100%;">
                          Il tuo browser non supporta l'elemento audio.
                        </audio>
                      </div>
                    </div>
                  </div>
                </div>

                <input name="formAction" id="formAction" type="hidden" value="<?=isset($_POST["formAction"]) ? $_POST["formAction"] : ''?>" >
                
                <!-- Button bar -->
                <div class="button-bar">
                  <button type="button" title="torna alla lista" class="btn btn-outline-success" id="btnBackToList"><span class="fe fe-list fe-16"></span></button>
                  <span class="fe fe-tool fe-16"> </span>
                  
                  <button type="button" title="SALVA" class="btn btn-outline-danger" id="btnSave"><span class="fe fe-save fe-16"></span></button>
                  
                  <?php if($id !== 'nuova' && !empty($id)): ?>
                  <button title="PLAY file" type="button" class="btn btn-outline-danger" id="playFile"><span class="fe fe-play fe-16"></span></button>
                  <?php endif; ?>
                </div>
              </form>

            </div> <!-- /.card-body -->
          </div> <!-- /.my-4 -->
        </div> <!-- /.my-4 -->
      </div> <!-- /.col-12 col-lg-10 col-xl-8 -->
<?php if (!$isAjaxRequest): ?>
        </div> <!-- /.row justify-content-center -->
      </div> <!-- /.container-fluid -->
    </main> <!-- /.main-content -->
  </div> <!-- /.wrapper -->
<?php endif; ?>

<script src="js/jquery.min.js"></script>
<script src="js/popper.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
// Recupera il nome del gruppo da sessionStorage se non è già presente
$(document).ready(function() {
  var gruppoNomeFromStorage = sessionStorage.getItem("jingle_gruppoNome");
  var gruppoIdFromStorage = sessionStorage.getItem("jingle_gruppoId");
  
  if(gruppoNomeFromStorage && (!$("#gruppo_nome").val() || $("#gruppo_nome").val() === '')) {
    $("#gruppo_nome").val(gruppoNomeFromStorage);
  }
  
  // Se non abbiamo jingle_gr_id ma abbiamo il gruppo_id da sessionStorage, impostalo
  if(gruppoIdFromStorage && (!$("#jingle_gr_id").val() || $("#jingle_gr_id").val() === '')) {
    $("#jingle_gr_id").val(gruppoIdFromStorage);
    $("#gruppo_id_for_back").val(gruppoIdFromStorage);
  }
  
  // Pulisci sessionStorage dopo averlo letto
  sessionStorage.removeItem("jingle_gruppoNome");
  sessionStorage.removeItem("jingle_gruppoId");
  
  // Gestione pulsante torna alla lista
  $("#btnBackToList").on("click", function() {
    var gruppoId = $("#gruppo_id_for_back").val();
    if(gruppoId && gruppoId !== '' && gruppoId !== '0') {
      // Imposta il cookie per aprire il tab Jingles
      document.cookie = "gruppo-tab=jingles; path=/";
      window.location.href = "gruppo-scheda.php?id=" + gruppoId;
    } else {
      // Fallback: torna indietro
      window.history.back();
    }
  });
  
  // Funzione per mostrare modale messaggi
  function showMessageModal(title, message, type) {
    type = type || "info"; // success, error, warning, info
    var $modal = $("#messageModal");
    var $header = $("#messageModalHeader");
    var $title = $("#messageModalTitle");
    var $text = $("#messageModalText");
    
    // Rimuovi tutte le classi di colore precedenti
    $header.removeClass("bg-success bg-danger bg-warning bg-info");
    
    // Imposta il colore in base al tipo
    if(type === "success") {
      $header.addClass("bg-success text-white");
      $title.html("<span class=\"fe fe-check-circle fe-16 mr-2\"></span>" + title);
    } else if(type === "error") {
      $header.addClass("bg-danger text-white");
      $title.html("<span class=\"fe fe-alert-circle fe-16 mr-2\"></span>" + title);
    } else if(type === "warning") {
      $header.addClass("bg-warning text-white");
      $title.html("<span class=\"fe fe-alert-triangle fe-16 mr-2\"></span>" + title);
    } else {
      $header.addClass("bg-info text-white");
      $title.html("<span class=\"fe fe-info fe-16 mr-2\"></span>" + title);
    }
    
    $text.text(message);
    $modal.modal("show");
  }
  
  // Funzione per verificare se l'audio esiste usando un elemento audio
  function verifyAudioExists(audioUrl) {
    console.log("[verifyAudioExists] Verifica file:", audioUrl);
    
    // Crea un elemento audio nascosto nel DOM
    var audio = document.createElement('audio');
    audio.style.display = 'none';
    document.body.appendChild(audio);
    
    var checkComplete = false;
    var timeout = setTimeout(function() {
      if (!checkComplete) {
        checkComplete = true;
        console.log("[verifyAudioExists] Timeout - file non disponibile");
        document.body.removeChild(audio);
        showMessageModal("File Audio Non Disponibile", "Il file audio per questo jingle non è disponibile sul server.\nURL: " + audioUrl, "warning");
      }
    }, 5000); // Timeout di 5 secondi
    
    audio.addEventListener('error', function(e) {
      if (!checkComplete) {
        checkComplete = true;
        clearTimeout(timeout);
        console.log("[verifyAudioExists] Errore nel caricamento audio");
        document.body.removeChild(audio);
        showMessageModal("File Audio Non Disponibile", "Il file audio per questo jingle non è disponibile sul server.\nURL: " + audioUrl, "warning");
      }
    }, true);
    
    audio.addEventListener('loadeddata', function() {
      if (!checkComplete) {
        checkComplete = true;
        clearTimeout(timeout);
        console.log("[verifyAudioExists] File audio disponibile");
        document.body.removeChild(audio);
      }
    }, true);
    
    audio.addEventListener('canplaythrough', function() {
      if (!checkComplete) {
        checkComplete = true;
        clearTimeout(timeout);
        console.log("[verifyAudioExists] File audio disponibile (canplaythrough)");
        document.body.removeChild(audio);
      }
    }, true);
    
    // Prova a caricare il file
    audio.src = audioUrl;
    audio.load();
  }
  
  // Mostra l'URL dell'audio all'avvio se c'è un jingle caricato
  var jingleId = $("#jingle_id").val();
  if(jingleId && jingleId !== '' && jingleId !== 'nuova' && jingleId !== '0') {
    var jingleIdFormatted = ("0000" + parseInt(jingleId)).slice(-4);
    var filename = jingleIdFormatted + ".mp3";
    var gruppoNome = $("#gruppo_nome").val() || '';
    var gruppoNomeLower = gruppoNome.toLowerCase().replace(/\s+/g, '');
    var audioUrl = "https://yourradio.org/player/" + gruppoNomeLower + "/jingle/" + filename;
    console.log("[jingle-scheda] ID jingle:", jingleId);
    console.log("[jingle-scheda] ID formattato (4 cifre):", jingleIdFormatted);
    console.log("[jingle-scheda] Nome gruppo:", gruppoNome);
    console.log("[jingle-scheda] Nome gruppo (lowercase):", gruppoNomeLower);
    console.log("[jingle-scheda] Filename:", filename);
    console.log("[jingle-scheda] URL audio completo:", audioUrl);
    
    // Verifica se il file audio esiste
    verifyAudioExists(audioUrl);
  }
  
  // Gestione play file
  $(document).on("click", "#playFile", function () {  
    var jingleId = $("#jingle_id").val();
    if (!jingleId || jingleId === '' || jingleId === 'nuova' || jingleId === '0') {
      console.log("[playFile] Nessun ID jingle disponibile");
      return;
    }
    
    // Formatta l'ID come 4 cifre (es. 141 -> 0141)
    var jingleIdFormatted = ("0000" + parseInt(jingleId)).slice(-4);
    var filename = jingleIdFormatted + ".mp3";
    
    var gruppoNome = $("#gruppo_nome").val() || gruppoNomeFromStorage || '';
    var gruppoNomeLower = gruppoNome.toLowerCase().replace(/\s+/g, '');
    
    var audioUrl = "https://yourradio.org/player/" + gruppoNomeLower + "/jingle/" + filename;
    console.log("[playFile] ID jingle:", jingleId);
    console.log("[playFile] ID formattato (4 cifre):", jingleIdFormatted);
    console.log("[playFile] Nome gruppo:", gruppoNome);
    console.log("[playFile] Nome gruppo (lowercase):", gruppoNomeLower);
    console.log("[playFile] Filename:", filename);
    console.log("[playFile] URL audio completo:", audioUrl);
    
    // Mostra il player audio nella pagina
    var $audioPlayer = $("#audioPlayer");
    var $playerRow = $("#form-row-audio-player");
    
    if ($audioPlayer.length === 0 || $playerRow.length === 0) {
      console.error("[playFile] Player audio non trovato");
      return;
    }
    
    $audioPlayer.attr("src", audioUrl);
    $playerRow.fadeIn("fast");
    // Avvia la riproduzione
    $audioPlayer[0].play().catch(function(error) {
      console.log("Errore nella riproduzione:", error);
    });
  });
  
  // Chiudi il player audio
  $(document).on("click", "#closeAudioPlayer", function() {
    var $audioPlayer = $("#audioPlayer");
    $audioPlayer[0].pause();
    $audioPlayer.attr("src", "");
    $("#form-row-audio-player").fadeOut("fast");
  });
  
  // Gestione salvataggio
  $("#btnSave").on("click", function() {
    var jingleId = $("#jingle_id").val();
    var jingleNome = $("#jingle_nome").val();
    var jingleAttivo = $("#jingle_attivo").is(":checked") ? 1 : 0;
    var jingleGrId = $("#jingle_gr_id").val();
    
    if(!jingleNome || jingleNome === '') {
      alert("Il nome del jingle è obbligatorio");
      return;
    }
    
    // Se è un nuovo jingle, recupera il gruppo_id da sessionStorage o dall'URL
    if((!jingleId || jingleId === 'nuova' || jingleId === '') && (!jingleGrId || jingleGrId === '')) {
      // Prova a recuperare da sessionStorage
      var gruppoIdFromStorage = sessionStorage.getItem("jingle_gruppoId");
      if(gruppoIdFromStorage) {
        jingleGrId = gruppoIdFromStorage;
        $("#jingle_gr_id").val(jingleGrId);
      } else {
        // Prova a recuperare dall'URL
        var urlParams = new URLSearchParams(window.location.search);
        var gruppoIdFromUrl = urlParams.get('gruppo_id');
        if(gruppoIdFromUrl) {
          jingleGrId = gruppoIdFromUrl;
          $("#jingle_gr_id").val(jingleGrId);
        } else {
          alert("Errore: gruppo_id non trovato. Torna alla lista e riprova.");
          return;
        }
      }
    }
    
    var formData = {
      jingle_nome: jingleNome,
      jingle_attivo: jingleAttivo
    };
    
    // Aggiungi jingle_gr_id solo per nuovi jingles
    if(!jingleId || jingleId === 'nuova' || jingleId === '') {
      formData.jingle_gr_id = parseInt(jingleGrId);
    }
    
    var isLocalhost = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
    var apiUrl = jingleId && jingleId !== 'nuova' ? "https://yourradio.org/api/jingles/" + jingleId : "https://yourradio.org/api/jingles";
    var method = jingleId && jingleId !== 'nuova' ? "PUT" : "POST";
    var proxyPath = './api-proxy.php';
    var finalUrl = isLocalhost ? proxyPath + "?url=" + encodeURIComponent(apiUrl) : apiUrl;
    
    $.ajax({
      method: method,
      url: finalUrl,
      contentType: "application/json",
      data: JSON.stringify(formData),
      success: function(res) {
        if(res.success) {
          alert("Jingle salvato con successo!");
          if(method === "POST" && res.data && res.data.jingle_id) {
            window.location.href = "jingle-scheda.php?id=" + res.data.jingle_id;
          } else {
            window.location.reload();
          }
        } else {
          alert("Errore: " + (res.error ? res.error.message : "Errore sconosciuto"));
        }
      },
      error: function(xhr, status, error) {
        console.error("Errore nel salvataggio:", {xhr: xhr, status: status, error: error});
        alert("Errore nel salvataggio del jingle");
      }
    });
  });
});
</script>

<!-- Modal Messaggi -->
<div class="modal fade" id="messageModal" tabindex="-1" role="dialog" aria-labelledby="messageModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header" id="messageModalHeader">
        <h5 class="modal-title" id="messageModalTitle">Messaggio</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="messageModalBody">
        <p id="messageModalText"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn mb-2 btn-primary" data-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<?php if (!$isAjaxRequest): ?>
</body>
</html>
<?php endif; ?>

