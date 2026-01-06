<?php
// Start the session
session_start();
include_once('load.php');
if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}

// Rileva se è una richiesta AJAX (caricata dentro un div)
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Se non è AJAX, include head e menu
if (!$isAjaxRequest) {
    include_once('inc/head.php');
}

$id=$_GET["id"] ?? '';
$gruppoIdFromUrl = isset($_GET["gruppo_id"]) ? intval($_GET["gruppo_id"]) : null;

if(!isset($_GET["id"]) || $_GET["id"]==0 || $_GET["id"]==''){
  $disabled=" disabled ";
  $id='nuova';
  $title='Nuovo Player';
  $p = [];
  // Se viene passato gruppo_id dall'URL, precompila pl_idGruppo per il nuovo player
  if($gruppoIdFromUrl !== null && $gruppoIdFromUrl > 0) {
    $p[0] = ['pl_idGruppo' => $gruppoIdFromUrl];
  }
  
  // Ottieni l'ultimo pl_id dall'API per mostrarlo nell'intestazione
  $nextPlayerId = 1;
  $apiResponse = callApi("monitor");
  if($apiResponse && isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data']) && is_array($apiResponse['data'])) {
    $maxId = 0;
    foreach($apiResponse['data'] as $player) {
      if(isset($player['player_id']) && intval($player['player_id']) > $maxId) {
        $maxId = intval($player['player_id']);
      }
    }
    $nextPlayerId = $maxId + 1;
  }
  // Imposta il prossimo ID nel player data
  if(!isset($p[0])) {
    $p[0] = [];
  }
  $p[0]['pl_id'] = $nextPlayerId;
  // Genera la data di creazione nel formato richiesto
  $p[0]['pl_dataCreazione'] = date('Y-m-d H:i:s');
}else{
  // Carica player tramite API - SEMPRE usa https://yourradio.org/api
  error_log("PLAYER-SCHEDA: Caricamento player ID " . intval($id) . " tramite API");
  $apiResponse = callApi("players/" . intval($id));
  
  if(!$apiResponse) {
    error_log("PLAYER-SCHEDA ERROR: Nessuna risposta dall'API");
    $disabled=" disabled ";
    $title='Errore: API non disponibile';
    $p = [];
  } elseif(isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data'])) {
    error_log("PLAYER-SCHEDA: Player caricato con successo");
    $playerData = $apiResponse['data'];
    // Mappa TUTTI i campi dall'API - l'API restituisce SELECT * quindi tutti i campi sono disponibili
    // Usa direttamente i dati dell'API invece di mapparli manualmente per evitare perdite
    $p = [$playerData];
    
    // Assicurati che i campi critici abbiano valori di default se mancanti
    if(!isset($p[0]['pl_id'])) $p[0]['pl_id'] = $id;
    if(!isset($p[0]['pl_nome'])) $p[0]['pl_nome'] = '';
    if(!isset($p[0]['pl_active'])) $p[0]['pl_active'] = 0;
    if(!isset($p[0]['pl_idGruppo'])) $p[0]['pl_idGruppo'] = 0;
    if(!isset($p[0]['gr_nome'])) $p[0]['gr_nome'] = '';
    if(!isset($p[0]['pl_mem_percent'])) $p[0]['pl_mem_percent'] = 0;
    if(!isset($p[0]['pl_mem_size'])) $p[0]['pl_mem_size'] = '';
    if(!isset($p[0]['pl_mem_used'])) $p[0]['pl_mem_used'] = '';
    if(!isset($p[0]['pl_mem_available'])) $p[0]['pl_mem_available'] = '';
    if(!isset($p[0]['pl_player_ultimaDataEstesa'])) $p[0]['pl_player_ultimaDataEstesa'] = '';
    if(!isset($p[0]['pl_keyword_md5'])) $p[0]['pl_keyword_md5'] = '';
    if(!isset($p[0]['pl_keyword'])) $p[0]['pl_keyword'] = '';
    if(!isset($p[0]['pl_dataCreazione'])) $p[0]['pl_dataCreazione'] = '';
    $disabled="";
    $title=$p[0]['pl_nome'] ?? '';
  } else {
    error_log("PLAYER-SCHEDA ERROR: " . (isset($apiResponse['error']) ? json_encode($apiResponse['error']) : 'Risposta non valida'));
    $disabled=" disabled ";
    $title='Player non trovato';
    if(isset($apiResponse['error']['message'])) {
      error_log("PLAYER-SCHEDA ERROR MESSAGE: " . $apiResponse['error']['message']);
    }
    $p = [];
  }
}

if(!empty($p) && isset($p[0])) {
  if(substr(strtoupper($p[0]['pl_player_pc'] ?? ''),0,4)=="RSPI"){
        $logo="raspi_logo_200.png";
        $type="RASPI";
        $ipDevice=substr($p[0]['pl_player_pc'] ?? '',5);

        $external_url='';
        if(isset($p[0]['gr_nat_port']) && $p[0]['gr_nat_port']){
          $external_url="http://".($p[0]['pl_player_ip'] ?? '').":".$p[0]['gr_nat_port'];
        }
        
      }else{
        $logo="pc_logo_200.png";
        $type="PC";
        $ipDevice='';
        $external_url='';
      }

  if(isset($p[0]['pl_active']) && $p[0]['pl_active']==1){$chbox_active="checked";$chbox_active_lab="Attivo";}else{$chbox_active="";$chbox_active_lab="Disattivato";}
} else {
  $logo="pc_logo_200.png";
  $type="PC";
  $ipDevice='';
  $external_url='';
  $chbox_active="";
  $chbox_active_lab="Disattivato";
}

$playerIdFallback = isset($_GET['id']) ? intval($_GET['id']) : 0;
$playerIdForJs = (!empty($_GET['id']) && isset($_GET['id'])) ? intval($_GET['id']) : 0;
$gruppoIdForJs = (!empty($p) && isset($p[0]['pl_idGruppo'])) ? intval($p[0]['pl_idGruppo']) : 0;
error_log("PLAYER-SCHEDA: gruppoIdForJs calcolato: " . $gruppoIdForJs . " (da pl_idGruppo: " . (isset($p[0]['pl_idGruppo']) ? $p[0]['pl_idGruppo'] : 'non presente') . ")");
$playerIdForPassword = isset($_GET['id']) ? intval($_GET['id']) : 0;
$script = '
<script>
// Funzione per mostrare messaggi in un modale
function showMessageModal(title, message, type) {
  type = type || "info"; // success, error, warning, info
  var $modal = $("#messageModal");
  var $header = $("#messageModalHeader");
  var $title = $("#messageModalTitle");
  var $body = $("#messageModalBody");
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

$(document).ready(function() {
  // Controlla se ci sono dati in sessionStorage per creare un nuovo player da gruppo
  if(!__PLAYER_ID_FOR_JS__ || __PLAYER_ID_FOR_JS__ === 0 || __PLAYER_ID_FOR_JS__ === "nuova") {
    var gruppoId = sessionStorage.getItem("newPlayer_gruppoId");
    var gruppoNome = sessionStorage.getItem("newPlayer_gruppoNome");
    
    if(gruppoId && gruppoId !== "null" && parseInt(gruppoId) > 0) {
      // Mostra la modale per creare nuovo player
      $("#newPlayerGruppoNome").text(gruppoNome || "Gruppo");
      $("#newPlayerFromGruppoModal").modal("show");
    }
  }
  
  // Gestione Annulla nella modale nuovo player
  $("#btnCancelNewPlayer").on("click", function() {
    // Pulisci sessionStorage
    sessionStorage.removeItem("newPlayer_gruppoId");
    sessionStorage.removeItem("newPlayer_gruppoNome");
    // Chiudi modale
    $("#newPlayerFromGruppoModal").modal("hide");
    // Torna alla pagina precedente o alla lista gruppi
    if(window.history.length > 1) {
      window.history.back();
    } else {
      window.location.href = "gruppi.php";
    }
  });
  
  // Gestione Conferma nella modale nuovo player
  $("#btnConfirmNewPlayer").on("click", function() {
    var $form = $("#newPlayerFromGruppoForm");
    var $nomeInput = $("#newPlayerNome");
    var $btn = $(this);
    var $spinner = $btn.find(".spinner-border");
    
    // Validazione
    if(!$nomeInput.val() || $nomeInput.val().trim() === "") {
      $nomeInput.addClass("is-invalid");
      $nomeInput.focus();
      return;
    }
    
    $nomeInput.removeClass("is-invalid");
    
    // Disabilita pulsante e mostra spinner
    $btn.prop("disabled", true);
    $spinner.removeClass("d-none");
    
    // Prepara dati per la creazione
    var gruppoId = parseInt(sessionStorage.getItem("newPlayer_gruppoId"));
    var playerNome = $nomeInput.val().trim().toUpperCase();
    
    // Password di default (puoi cambiarla dopo)
    var defaultPassword = "password123";
    
    var formData = {
      pl_nome: playerNome,
      pl_idGruppo: gruppoId,
      pl_keyword_md5: defaultPassword,
      pl_active: 0,
      pl_dataCreazione: new Date().toISOString().slice(0, 19).replace("T", " ")
    };
    
    // Determina se usare il proxy (da localhost)
    var isLocalhost = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
    var apiUrl = "https://yourradio.org/api/players";
    var proxyPath = "./api-proxy.php";
    var finalUrl = isLocalhost ? proxyPath + "?url=" + encodeURIComponent(apiUrl) : apiUrl;
    
    console.log("API CALL - Create Player from Gruppo");
    console.log("  Server: https://yourradio.org");
    console.log("  Endpoint: " + apiUrl);
    console.log("  Method: POST");
    console.log("  Using proxy: " + isLocalhost);
    console.log("  Final URL: " + finalUrl);
    console.log("  Data:", formData);
    
    $.ajax({
      method: "POST",
      url: finalUrl,
      contentType: "application/json",
      data: JSON.stringify(formData),
      success: function(res) {
        console.log("API RESPONSE - Create Player SUCCESS:", res);
        $btn.prop("disabled", false);
        $spinner.addClass("d-none");
        
        if(res.success && res.data && res.data.pl_id) {
          var newPlayerId = res.data.pl_id;
          
          // Pulisci sessionStorage
          sessionStorage.removeItem("newPlayer_gruppoId");
          sessionStorage.removeItem("newPlayer_gruppoNome");
          
          // Chiudi modale creazione
          $("#newPlayerFromGruppoModal").modal("hide");
          
          // Mostra modale di conferma
          $("#confirmNewPlayerModalHeader").removeClass("bg-success bg-danger bg-warning bg-info");
          $("#confirmNewPlayerModalHeader").addClass("bg-success text-white");
          $("#confirmNewPlayerModalTitle").html("<span class=\"fe fe-check-circle fe-16 mr-2\"></span>Player Creato");
          $("#confirmNewPlayerModalText").text("Player \"" + playerNome + "\" creato con successo sul gruppo!");
          $("#confirmNewPlayerModal").modal("show");
          
          // Salva l ID del nuovo player per il reindirizzamento
          $("#confirmNewPlayerModal").data("newPlayerId", newPlayerId);
        } else {
          // Errore nella creazione
          $("#newPlayerFromGruppoModal").modal("hide");
          $("#confirmNewPlayerModalHeader").removeClass("bg-success bg-danger bg-warning bg-info");
          $("#confirmNewPlayerModalHeader").addClass("bg-danger text-white");
          $("#confirmNewPlayerModalTitle").html("<span class=\"fe fe-alert-circle fe-16 mr-2\"></span>Errore");
          $("#confirmNewPlayerModalText").text("Errore nella creazione del player: " + (res.error ? res.error.message : "Errore sconosciuto"));
          $("#confirmNewPlayerModal").modal("show");
        }
      },
      error: function(xhr, status, error) {
        console.error("API RESPONSE - Create Player ERROR:", {xhr: xhr, status: status, error: error});
        $btn.prop("disabled", false);
        $spinner.addClass("d-none");
        
        var errorMsg = "Errore durante la creazione del player";
        if(xhr.responseJSON && xhr.responseJSON.error) {
          errorMsg = xhr.responseJSON.error.message || errorMsg;
        } else if(xhr.statusText) {
          errorMsg = "Errore " + xhr.status + ": " + xhr.statusText;
        }
        
        // Mostra modale di errore
        $("#newPlayerFromGruppoModal").modal("hide");
        $("#confirmNewPlayerModalHeader").removeClass("bg-success bg-danger bg-warning bg-info");
        $("#confirmNewPlayerModalHeader").addClass("bg-danger text-white");
        $("#confirmNewPlayerModalTitle").html("<span class=\"fe fe-alert-circle fe-16 mr-2\"></span>Errore");
        $("#confirmNewPlayerModalText").text(errorMsg);
        $("#confirmNewPlayerModal").modal("show");
      }
    });
  });
  
  // Gestione OK nella modale di conferma
  $("#btnOkConfirmNewPlayer").on("click", function() {
    var newPlayerId = $("#confirmNewPlayerModal").data("newPlayerId");
    
    // Pulisci sessionStorage
    sessionStorage.removeItem("newPlayer_gruppoId");
    sessionStorage.removeItem("newPlayer_gruppoNome");
    
    // Chiudi modale
    $("#confirmNewPlayerModal").modal("hide");
    
    // Reindirizza alla scheda del player creato
    if(newPlayerId) {
      window.location.href = "player-scheda.php?id=" + newPlayerId;
    } else {
      // Se c e stato un errore, torna alla lista gruppi
      window.location.href = "gruppi.php";
    }
  });
  
  // Gestione chiusura modale conferma (pulsante X)
  $("#closeConfirmModal").on("click", function() {
    var newPlayerId = $("#confirmNewPlayerModal").data("newPlayerId");
    
    // Pulisci sessionStorage
    sessionStorage.removeItem("newPlayer_gruppoId");
    sessionStorage.removeItem("newPlayer_gruppoNome");
    
    // Reindirizza alla scheda del player creato o alla lista gruppi
    if(newPlayerId) {
      window.location.href = "player-scheda.php?id=" + newPlayerId;
    } else {
      window.location.href = "gruppi.php";
    }
  });
  
  // Gestione chiusura modale nuovo player senza conferma (ESC o click fuori)
  $("#newPlayerFromGruppoModal").on("hidden.bs.modal", function() {
    // Se la modale viene chiusa senza aver creato il player, pulisci sessionStorage
    // e torna alla lista gruppi solo se non è stata creata con successo
    if(!$("#confirmNewPlayerModal").data("newPlayerId")) {
      sessionStorage.removeItem("newPlayer_gruppoId");
      sessionStorage.removeItem("newPlayer_gruppoNome");
      // Non reindirizzare automaticamente, lascia l utente sulla pagina
    }
  });
  
  $( "#update" ).on("click", function(e) {
    e.preventDefault();
    e.stopPropagation();
    $("#formActionPlayer").val("update");
    console.log("Click su update, formAction impostato a update");
    $( "#scheda-profilo" ).submit();
    return false;
  });
  
  $( "#delete" ).on("click", function() {
    $("#formActionPlayer").val("delete");
    console.log("cancella scheda ");
    $( "#scheda-profilo" ).submit();
  });
  
  $( ".back-lista" ).on("click", function() {
    $("#formActionPlayer").val("back");
    console.log("torna alla scheda del gruppo");
    var gruppoId = __GRUPPO_ID_FOR_JS__;
    console.log("gruppoId per back-lista:", gruppoId, "| tipo:", typeof gruppoId);
    if(gruppoId > 0) {
      window.location.href = "gruppo-scheda.php?id=" + gruppoId;
    } else {
      console.log("gruppoId è 0 o non valido, vado a gruppi.php");
      window.location.href = "gruppi.php";
    }
  });
  
  // Funzione per aggiornare il testo della password identità
  function updateIdentityPasswordText() {
    var playerId = __PLAYER_ID_FOR_JS__;
    var plKeyword = $("#pl_keyword").val() || "";
    if(playerId && playerId !== "" && playerId !== "nuova" && playerId !== "0" && plKeyword !== "") {
      var identityPassword = plKeyword + "_" + playerId;
      $("#playerIdentityPassword").text(identityPassword);
    }
  }
  
  // Aggiorna il testo quando pl_keyword cambia
  $("#pl_keyword").on("input change blur", function() {
    updateIdentityPasswordText();
  });
  
  // Gestione copia password identità
  $("#btnCopyIdentityPassword").on("click", function() {
    var identityPassword = $("#playerIdentityPassword").text();
    if(identityPassword) {
      // Crea un elemento temporaneo per copiare
      var tempInput = $("<input>");
      $("body").append(tempInput);
      tempInput.val(identityPassword).select();
      document.execCommand("copy");
      tempInput.remove();
      
      // Feedback visivo
      var $btn = $(this);
      var originalHtml = $btn.html();
      $btn.html("<span class=\"fe fe-check fe-12\"></span>").addClass("btn-success").removeClass("btn-outline-secondary");
      setTimeout(function() {
        $btn.html(originalHtml).removeClass("btn-success").addClass("btn-outline-secondary");
      }, 1000);
    }
  });
  
  // Inizializza il testo al caricamento se il player esiste
  $(document).ready(function() {
    var playerId = __PLAYER_ID_FOR_JS__;
    if(playerId && playerId !== "" && playerId !== "nuova" && playerId !== "0") {
      // Rimuovi eventuale suffisso dal campo pl_keyword al caricamento
      var plKeyword = $("#pl_keyword").val();
      if(plKeyword) {
        var cleanedKeyword = plKeyword.replace(/_\d+$/, "");
        if(cleanedKeyword !== plKeyword) {
          $("#pl_keyword").val(cleanedKeyword);
        }
      }
      updateIdentityPasswordText();
    }
  });
  
  $( "#changePassword" ).on("click", function() {
    $("#formActionPlayer").val("changepassword");
    var newValue = $("#newPassword").val();
    if(newValue == ""){
      showMessageModal("Errore", "Inserisci un valore", "error");
      return;
    }
    
    var playerId = __PLAYER_ID_FOR_JS__;
    if(!playerId || playerId === "" || playerId === "nuova") {
      showMessageModal("Errore", "ID player non trovato", "error");
      return;
    }
    
    // Verifica se stiamo modificando pl_keyword o pl_keyword_md5
    var editingField = $("#passwordModal").data("editing-field");
    
    if(editingField === "pl_keyword") {
      // Modifica pl_keyword - salva senza suffisso
      // Rimuovi eventuali suffissi precedenti se presenti
      var finalValue = newValue.replace(/_\d+$/, "");
      
      // Aggiorna il campo pl_keyword direttamente
      $("#pl_keyword").val(finalValue);
      
      // Salva via API - includi sempre pl_idGruppo per non perderlo
      var plIdGruppo = $("#pl_idGruppo").val();
      var updateData = {
        pl_keyword: finalValue,
        pl_idGruppo: (plIdGruppo !== null && plIdGruppo !== undefined && plIdGruppo !== "") ? parseInt(plIdGruppo) : 0
      };
      
      var isLocalhost = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
      var apiUrl = "https://yourradio.org/api/players/" + playerId;
      var proxyPath = \'./api-proxy.php\';
      var finalUrl = isLocalhost ? proxyPath + "?url=" + encodeURIComponent(apiUrl) : apiUrl;
      
      $.ajax({
        method: "PUT",
        url: finalUrl,
        contentType: "application/json",
        data: JSON.stringify(updateData),
        success: function(res){
          console.log("API RESPONSE - Update pl_keyword SUCCESS:", res);
          if(res.success) {
            // Aggiorna il testo informativo della password identità
            updateIdentityPasswordText();
            showMessageModal("Successo", "Keyword aggiornata con successo!", "success");
            $("#newPassword").val("");
            $("#passwordModal").modal("hide");
            // Reset del flag
            $("#passwordModal").data("editing-field", null);
          }
        },
        error: function(xhr, status, error){
          console.error("API RESPONSE - Update pl_keyword ERROR:", {xhr: xhr, status: status, error: error});
          showMessageModal("Errore", "Errore nell\'aggiornamento della keyword", "error");
        }
      });
    } else {
      // Modifica pl_keyword_md5 (password)
      // Nota: l endpoint /password gestisce solo la password, quindi dopo il cambio password
      // dobbiamo aggiornare anche pl_idGruppo per non perderlo
      var isLocalhost = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
      var apiUrl = "https://yourradio.org/api/players/" + playerId + "/password";
      var proxyPath = \'./api-proxy.php\';
      var finalUrl = isLocalhost ? proxyPath + "?url=" + encodeURIComponent(apiUrl) : apiUrl;
      
      console.log("API CALL - Change Password");
      console.log("  Server: https://yourradio.org");
      console.log("  Method: PUT");
      console.log("  Using proxy: " + isLocalhost);
      console.log("  Final URL: " + finalUrl);
      
      $.ajax({
        method: "PUT",
        url: finalUrl,
        contentType: "application/json",
        data: JSON.stringify({newpass: newValue}),
        success: function(res){
          console.log("API RESPONSE - Change Password SUCCESS:", res);
          if(res.success) {
            // Aggiorna pl_keyword se presente
            if(res.data && res.data.pl_keyword) {
              var plKeyword = res.data.pl_keyword.replace(/_\d+$/, "");
              $("#pl_keyword").val(plKeyword);
              updateIdentityPasswordText();
            } else if(res.data && res.data.pl_keyword_md5) {
              // Usa pl_keyword_md5 senza suffisso
              var plKeyword = res.data.pl_keyword_md5.replace(/_\d+$/, "");
              $("#pl_keyword").val(plKeyword);
              updateIdentityPasswordText();
            }
            console.log("nuova password cambiata");
            
            // Dopo il cambio password, aggiorna anche pl_idGruppo per non perderlo
            var plIdGruppo = $("#pl_idGruppo").val();
            if(plIdGruppo !== null && plIdGruppo !== undefined && plIdGruppo !== "") {
              var updateGruppoUrl = isLocalhost ? proxyPath + "?url=" + encodeURIComponent("https://yourradio.org/api/players/" + playerId) : "https://yourradio.org/api/players/" + playerId;
              $.ajax({
                method: "PUT",
                url: updateGruppoUrl,
                contentType: "application/json",
                data: JSON.stringify({
                  pl_idGruppo: parseInt(plIdGruppo) || 0
                }),
                success: function(updateRes) {
                  console.log("pl_idGruppo aggiornato dopo cambio password");
                },
                error: function() {
                  console.error("Errore nell aggiornamento di pl_idGruppo dopo cambio password");
                }
              });
            }
            
            showMessageModal("Successo", "Password cambiata con successo!", "success");
            $("#newPassword").val("");
            $("#passwordModal").modal("hide");
            $(".alert").hide();
          }
        },
        error: function(xhr, status, error){
          console.error("API RESPONSE - Change Password ERROR:", {xhr: xhr, status: status, error: error});
          showMessageModal("Errore", "Purtroppo non ho potuto cambiare la password... qualcosa è andato storto", "error");
        }
      });
    }
  });

  // Log del valore iniziale di pl_idGruppo al caricamento della pagina
  $(document).ready(function() {
    var serverPlIdGruppo = ' . (isset($p[0]['pl_idGruppo']) ? intval($p[0]['pl_idGruppo']) : 0) . ';
    var initialPlIdGruppo = $("#pl_idGruppo").val();
    console.log("VALORE INIZIALE pl_idGruppo (hidden) al caricamento pagina:", initialPlIdGruppo, "| tipo:", typeof initialPlIdGruppo);
    console.log("Valore pl_idGruppo dal server (PHP):", serverPlIdGruppo);
    
    // Gestione switch Router Dinamico
    $("#pl_player_router_dinamico").on("change", function() {
      var $switchContainer = $(this).closest(".d-flex");
      var $statusSpan = $switchContainer.find("span.ml-2");
      if($(this).is(":checked")) {
        $statusSpan.text("DNS DINAMICO ON");
      } else {
        $statusSpan.text("DNS DINAMICO");
      }
    });
    
    // Gestione switch Online
    $("#pl_player_online").on("change", function() {
      var $switchContainer = $(this).closest(".d-flex");
      var $statusSpan = $switchContainer.find("span.ml-2");
      if($(this).is(":checked")) {
        $statusSpan.text("PLAYER ONLINE");
      } else {
        $statusSpan.text("PLAYER OFFLINE");
      }
    });
    
    // Gestione del campo pl_keyword: readonly e cliccabile per aprire modale
    var playerId = __PLAYER_ID_FOR_JS__;
    var $plKeywordField = $("#pl_keyword");
    
    if(playerId && playerId !== "" && playerId !== "nuova" && playerId !== "0") {
      // Apri la modale quando si clicca sul campo pl_keyword
      $plKeywordField.on("click", function(e) {
        e.preventDefault();
        // Imposta un flag per indicare che stiamo modificando pl_keyword
        $("#passwordModal").data("editing-field", "pl_keyword");
        // Pulisci il campo della modale
        $("#newPassword").val("");
        // Cambia il titolo della modale
        $("#varyModalLabel").text("Keyword");
        // Cambia la label del campo
        $("#passwordModal .col-form-label").text("Scrivi la keyword (max 10chr):");
        // Apri la modale
        $("#passwordModal").modal("show");
        return false;
      });
    }
    
    // Reset della modale quando viene aperta dal pulsante "Cambia Password"
    $("#btn-change-password").on("click", function() {
      $("#passwordModal").data("editing-field", "password");
      $("#varyModalLabel").text("Password");
      $("#passwordModal .col-form-label").text("Scrivi la password:");
      $("#newPassword").val("");
    });
    
    // Reset della modale quando viene chiusa
    $("#passwordModal").on("hidden.bs.modal", function() {
      $("#passwordModal").data("editing-field", null);
      $("#varyModalLabel").text("Password");
      $("#passwordModal .col-form-label").text("Scrivi la password:");
      $("#newPassword").val("");
    });
  });

  // Gestione submit form tramite API
  $( "#scheda-profilo" ).on("submit", function(e) {
    var formAction = $("#formActionPlayer").val();
    console.log("Form submit - formAction:", formAction);
    
    // Se formAction è vuoto, potrebbe essere un submit diretto del form (non dal pulsante)
    // In quel caso, imposta "update" come default
    if(!formAction || formAction === "") {
      console.log("FormAction vuoto, imposto update come default");
      formAction = "update";
      $("#formActionPlayer").val("update");
    }
    
    if(formAction !== "update") {
      console.log("FormAction non è update, procedo con submit normale");
      return true; // Lascia gestire altri formAction normalmente
    }
    
    e.preventDefault();
    console.log("Intercettato submit form per update");
    
    // Funzione per salvare i sottogruppi del player
    function savePlayerSubgruppi(playerId) {
      if(!playerId || playerId === "" || playerId === "nuova" || playerId === "0") {
        console.log("Salvataggio sottogruppi saltato: playerId non valido");
        return;
      }
      
      // Raccogli tutti i sottogruppi selezionati
      var selectedSubgruppi = [];
      console.log("=== RACCOLTA SOTTOGRUPPI ===");
      console.log("Player ID:", playerId);
      
      // Conta tutti i checkbox dei sottogruppi (selezionati e non)
      var allSubgruppi = $("input[name^=\'subgruppo_\']");
      console.log("Totale checkbox sottogruppi trovati:", allSubgruppi.length);
      
      allSubgruppi.each(function() {
        var $checkbox = $(this);
        var name = $checkbox.attr("name");
        var sgrId = name.replace("subgruppo_", "");
        var isChecked = $checkbox.is(":checked");
        console.log("Checkbox:", name, "| ID:", sgrId, "| Checked:", isChecked);
        
        if(isChecked && sgrId && parseInt(sgrId) > 0) {
          selectedSubgruppi.push(parseInt(sgrId));
        }
      });
      
      console.log("Sottogruppi selezionati (array finale):", selectedSubgruppi);
      console.log("=== FINE RACCOLTA SOTTOGRUPPI ===");
      
      var isLocalhostSubgruppi = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
      var apiUrlSubgruppi = "https://yourradio.org/api/players/" + playerId + "/subgruppi";
      var proxyPathSubgruppi = \'./api-proxy.php\';
      var finalUrlSubgruppi = isLocalhostSubgruppi ? proxyPathSubgruppi + "?url=" + encodeURIComponent(apiUrlSubgruppi) : apiUrlSubgruppi;
      
      $.ajax({
        method: "PUT",
        url: finalUrlSubgruppi,
        contentType: "application/json",
        data: JSON.stringify({subgruppi: selectedSubgruppi}),
        success: function(res) {
          console.log("API RESPONSE - Update Subgruppi SUCCESS:", res);
        },
        error: function(xhr, status, error) {
          console.error("API RESPONSE - Update Subgruppi ERROR:", {xhr: xhr, status: status, error: error});
        }
      });
    }
    
    var formData = {};
    var $form = $(this);
    
    // Raccogli tutti i dati del form
    $form.find("input, select, textarea").each(function() {
      var $field = $(this);
      var name = $field.attr("name");
      var type = $field.attr("type");
      
      if(name && name !== "formAction" && name !== "formActionPlayer") {
        if(type === "checkbox") {
          formData[name] = $field.is(":checked") ? 1 : 0;
        } else if(type === "radio") {
          if($field.is(":checked")) {
            formData[name] = $field.val();
          }
        } else {
          var val = $field.val();
          // Gestisci esplicitamente il campo pl_idGruppo (hidden) - deve essere sempre incluso anche se 0
          if(name === "pl_idGruppo") {
            console.log("Campo pl_idGruppo (hidden) trovato nel form - valore:", val, "| tipo:", typeof val);
            formData[name] = (val !== null && val !== undefined && val !== "") ? parseInt(val) : 0;
            console.log("Campo pl_idGruppo dopo conversione:", formData[name], "| tipo:", typeof formData[name]);
          } else if(val !== null && val !== undefined) {
            formData[name] = val;
          }
        }
      }
    });
    
    // Assicurati che pl_idGruppo sia sempre presente e valorizzato correttamente
    // Prendi il valore dal campo hidden del form (che contiene il gruppo attuale)
    var currentPlIdGruppo = $("#pl_idGruppo").val();
    if(currentPlIdGruppo !== null && currentPlIdGruppo !== undefined && currentPlIdGruppo !== "") {
      formData.pl_idGruppo = parseInt(currentPlIdGruppo) || 0;
    } else if(!("pl_idGruppo" in formData)) {
      formData.pl_idGruppo = 0;
    }
    console.log("pl_idGruppo da salvare:", formData.pl_idGruppo, "| tipo:", typeof formData.pl_idGruppo);
    
    // Assicurati che pl_keyword finisca sempre con "_[ID]" se il player esiste
    var playerId = formData.pl_id;
    if(!playerId) {
      playerId = __PLAYER_ID_FOR_JS__;
    }
    
    // Rimuovi eventuale suffisso "_[ID]" da pl_keyword prima di salvare
    if(formData.pl_keyword && formData.pl_keyword !== "") {
      formData.pl_keyword = formData.pl_keyword.replace(/_\d+$/, "");
    } else if(formData.pl_keyword_md5) {
      // Se pl_keyword è vuoto ma c\'è pl_keyword_md5, usa pl_keyword_md5 senza suffisso
      formData.pl_keyword = formData.pl_keyword_md5;
    }
    
    // Determina se è un nuovo player controllando se il campo password è presente e visibile
    // Il campo password è visibile solo per nuovi player
    var passwordField = $("#playerPassword");
    var isNewPlayer = (passwordField.length > 0 && passwordField.is(":visible"));
    
    var playerId = formData.pl_id;
    if(!playerId) {
      playerId = __PLAYER_ID_FOR_JS__;
    }
    
    // Se è un nuovo player, non usare l\'ID calcolato
    if(isNewPlayer) {
      playerId = null;
    }
    
    if(isNewPlayer) {
      var playerPassword = $("#playerPassword").val();
      if(!playerPassword || playerPassword === "") {
        showMessageModal("Errore", "Per creare un nuovo player è necessario inserire una password", "error");
        $("#playerPassword").focus();
        return false;
      }
      // Salva la password in chiaro (come richiesto)
      formData.pl_keyword_md5 = playerPassword;
      // Aggiungi la data di creazione
      var now = new Date();
      var year = now.getFullYear();
      var month = (now.getMonth() + 1 < 10 ? "0" : "") + (now.getMonth() + 1);
      var day = (now.getDate() < 10 ? "0" : "") + now.getDate();
      var hours = (now.getHours() < 10 ? "0" : "") + now.getHours();
      var minutes = (now.getMinutes() < 10 ? "0" : "") + now.getMinutes();
      var seconds = (now.getSeconds() < 10 ? "0" : "") + now.getSeconds();
      formData.pl_dataCreazione = year + "-" + month + "-" + day + " " + hours + ":" + minutes + ":" + seconds;
    }
    
    // Determina se usare il proxy (da localhost)
    var isLocalhost = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
    var apiUrl, method;
    
    if(isNewPlayer) {
      // Crea nuovo player con POST
      apiUrl = "https://yourradio.org/api/players";
      method = "POST";
      // Rimuovi pl_id dal formData per la creazione
      delete formData.pl_id;
    } else {
      // Aggiorna player esistente con PUT
      apiUrl = "https://yourradio.org/api/players/" + playerId;
      method = "PUT";
    }
    
    // Usa percorso assoluto per il proxy quando caricato via AJAX
    // Usa percorso relativo alla root del sito
    var proxyPath = \'./api-proxy.php\';
    var finalUrl = isLocalhost ? proxyPath + "?url=" + encodeURIComponent(apiUrl) : apiUrl;
    
    console.log("API CALL - " + (isNewPlayer ? "Create" : "Update") + " Player");
    console.log("  Server: https://yourradio.org");
    console.log("  Endpoint: " + apiUrl);
    console.log("  Method: " + method);
    console.log("  Using proxy: " + isLocalhost);
    console.log("  Final URL: " + finalUrl);
    console.log("  Data:", formData);
    
    var $submitBtn = $("#update");
    var originalText = $submitBtn.html();
    $submitBtn.prop("disabled", true).html("Salvataggio...");
    
    $.ajax({
      method: method,
      url: finalUrl,
      contentType: "application/json",
      data: JSON.stringify(formData),
      success: function(res) {
        console.log("API RESPONSE - " + (isNewPlayer ? "Create" : "Update") + " Player SUCCESS:", res);
        console.log("  Server: https://yourradio.org");
        console.log("  Response:", res);
        if(res.success) {
          // Determina l ID del player (nuovo o esistente)
          var currentPlayerId = isNewPlayer && res.data && res.data.pl_id ? res.data.pl_id : playerId;
          
          if(isNewPlayer && res.data && res.data.pl_id) {
            // Nuovo player creato, aggiorna pl_keyword con l\'ID reale
            var newPlayerId = res.data.pl_id;
            var playerPassword = $("#playerPassword").val();
            if(playerPassword) {
              var plKeyword = playerPassword + "_" + newPlayerId;
              // Aggiorna pl_keyword via API
              $.ajax({
                method: "PUT",
                url: isLocalhost ? proxyPath + "?url=" + encodeURIComponent("https://yourradio.org/api/players/" + newPlayerId) : "https://yourradio.org/api/players/" + newPlayerId,
                contentType: "application/json",
                data: JSON.stringify({pl_keyword: plKeyword}),
                success: function(updateRes) {
                  if(updateRes.success) {
                    console.log("pl_keyword aggiornato:", plKeyword);
                  }
                  // Dopo aver aggiornato pl_keyword, salva i sottogruppi
                  savePlayerSubgruppi(currentPlayerId);
                },
                error: function() {
                  console.error("Errore nell\'aggiornamento di pl_keyword");
                  // Salva comunque i sottogruppi anche in caso di errore
                  savePlayerSubgruppi(currentPlayerId);
                }
              });
            } else {
              // Se non c\'è password, salva direttamente i sottogruppi
              savePlayerSubgruppi(currentPlayerId);
            }
            // Reindirizza alla scheda del player con l\'ID reale
            showMessageModal("Successo", "Player creato con successo!", "success");
            setTimeout(function() {
              window.location.href = "player-scheda.php?id=" + newPlayerId;
            }, 1500);
          } else {
            // Player esistente aggiornato, salva i sottogruppi
            savePlayerSubgruppi(currentPlayerId);
            showMessageModal("Successo", "Player aggiornato con successo!", "success");
            setTimeout(function() {
              window.location.reload();
            }, 1500);
          }
        } else {
          showMessageModal("Errore", "Errore: " + (res.error ? res.error.message : "Errore sconosciuto"), "error");
          $submitBtn.prop("disabled", false).html(originalText);
        }
      },
      error: function(xhr, status, error) {
        console.error("API RESPONSE - Update Player ERROR:");
        console.error("  Server: https://yourradio.org");
        console.error("  Status:", xhr.status);
        console.error("  StatusText:", xhr.statusText);
        console.error("  Error:", error);
        console.error("  Response:", xhr.responseText);
        var errorMsg = "Errore durante il salvataggio";
        if(xhr.responseJSON && xhr.responseJSON.error) {
          errorMsg = xhr.responseJSON.error.message || errorMsg;
        } else if(xhr.statusText) {
          errorMsg = "Errore " + xhr.status + ": " + xhr.statusText;
        }
        if(xhr.status === 404) {
          errorMsg = "Endpoint API non trovato. Verifica che l API sia installata su yourradio.org";
        }
        showMessageModal("Errore", errorMsg, "error");
        $submitBtn.prop("disabled", false).html(originalText);
      }
    });
    return false;
  });

  if($("#password").val()=="" && $("#login").val()!="" ){
  }

  // Inizializza il grafico SDmem - deve essere eseguito dopo che gauge.min.js è caricato
  // e dopo che l elemento SDmem è nel DOM

}); // Fine document.ready
</script>
';
$script = str_replace('__PLAYER_ID_FOR_JS__', $playerIdForJs, $script);
$script = str_replace('__GRUPPO_ID_FOR_JS__', $gruppoIdForJs, $script);
// Placeholder già gestito con __PLAYER_ID_FOR_JS__


// Carica gruppi per la select - SEMPRE usa https://yourradio.org/api
error_log("PLAYER-SCHEDA: Caricamento gruppi tramite API");
$gruppiApi = callApi("gruppi");
$gruppi = [];
if($gruppiApi && isset($gruppiApi['success']) && $gruppiApi['success'] && isset($gruppiApi['data'])) {
  error_log("PLAYER-SCHEDA: Gruppi caricati con successo (" . count($gruppiApi['data']) . " gruppi)");
  $gruppi = $gruppiApi['data'];
} else {
  error_log("PLAYER-SCHEDA ERROR: Errore nel caricamento gruppi - " . (isset($gruppiApi['error']) ? json_encode($gruppiApi['error']) : 'Risposta non valida'));
}

// Usa sempre pl_idGruppo dal database (recuperato dall'API)
$rete_id = (!empty($p) && isset($p[0]['pl_idGruppo'])) ? intval($p[0]['pl_idGruppo']) : 0;
error_log("PLAYER-SCHEDA: pl_idGruppo recuperato dal DB: " . (isset($p[0]['pl_idGruppo']) ? $p[0]['pl_idGruppo'] : 'non presente') . " | rete_id impostato a: " . $rete_id . " | tipo: " . gettype($rete_id));

///seleziona sottogruppi - TODO: implementare API endpoint se necessario
$sg = [];

///seleziona tutte le campagne by gruppoid - TODO: implementare API endpoint se necessario
$campagne = [];



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
                     <img src="./assets/images/<?=$logo?>" class="navbar-brand-img brand-sm mx-auto mb-4" alt="...">
                      <h2 class="mb-0 text-uppercase"><?=$p[0]['pl_nome'] ?? ($id == 'nuova' ? 'Nuovo Player' : '')?></h2>
                      <h4 class="mb-0 text-uppercase"><?=$p[0]['gr_nome'] ?? ''?></h4>
                      <small class="text-muted text-uppercase">ID.</small> <?=$p[0]['pl_id'] ?? ($id == 'nuova' ? '?' : '')?>
                       <?php if($external_url!=''){?>
                          <button type="button" class="btn btn-primary" onclick="window.open('<?=$external_url?>','_blank');">Player on-line</button>
                        <?php }?>
                  </div>
                </div>
                <?php else: ?>
                <div class="row mt-2 align-items-center">
                  <div class="col-md-2 text-center mb-0">
                     <img src="./assets/images/<?=$logo?>" class="navbar-brand-img brand-sm mx-auto mb-4" alt="...">
                  </div>
                  <div class="col-md-10 text-left mb-0">
                      <?php if($external_url!=''){?>
                        <button type="button" class="btn btn-primary" onclick="window.open('<?=$external_url?>','_blank');">Player on-line</button>
                      <?php }?>
                  </div>
                </div>
                <?php endif; ?>

                <div class="card-body">

                  <form id="scheda-profilo" class="needs-validation" novalidate method="post">
                    <input type="hidden" class="form-control" id="pl_id" name="pl_id" value="<?=(!empty($p) && isset($p[0]['pl_id'])) ? $p[0]['pl_id'] : ''?>" required>
                    <?php
                    // Determina il pl_idGruppo da usare: priorità a gruppo_id dall'URL, poi al valore del player
                    $currentPlIdGruppo = 0;
                    if($gruppoIdFromUrl !== null && $gruppoIdFromUrl > 0) {
                      // Se c'è gruppo_id nell'URL, usa quello (per nuovi player o quando si apre da gruppo-scheda)
                      $currentPlIdGruppo = $gruppoIdFromUrl;
                    } elseif(!empty($p) && isset($p[0]['pl_idGruppo'])) {
                      // Altrimenti usa il valore del player corrente
                      $currentPlIdGruppo = intval($p[0]['pl_idGruppo']);
                    }
                    error_log("PLAYER-SCHEDA: pl_idGruppo per form - gruppoIdFromUrl: " . ($gruppoIdFromUrl ?? 'null') . " | pl_idGruppo dal player: " . (isset($p[0]['pl_idGruppo']) ? $p[0]['pl_idGruppo'] : 'non presente') . " | valore finale: " . $currentPlIdGruppo);
                    ?>
                    <input type="hidden" id="pl_idGruppo" name="pl_idGruppo" value="<?=$currentPlIdGruppo?>">
                    <!-- username-nome -->
                    <div class="form-row">
                      <div class="col-md-12 mb-3">
                        <div class="custom-control custom-switch">
                          <input type="checkbox" class="custom-control-input" <?=$chbox_active?> id="pl_active" name="pl_active" value="1">
                          <label class="custom-control-label" for="pl_active"><?=$chbox_active_lab?></label>
                        </div>
                      </div>
                      <div class="col-md-<?=$id == 'nuova' ? '8' : '12'?> mb-3">
                        <label class="form-scheda-label">Nome</label>
                        <input type="text" class="form-control input-uppercase" id="pl_nome" name="pl_nome" value="<?=$p[0]['pl_nome'] ?? ''?>" required>
                      </div>
                      <?php if($id == 'nuova'): ?>
                      <div class="col-md-4 mb-3">
                        <label class="form-scheda-label">Password</label>
                        <input type="password" class="form-control" id="playerPassword" name="playerPassword" placeholder="Inserisci password" autocomplete="new-password" required>
                        <div class="invalid-feedback">La password è obbligatoria per i nuovi player</div>
                      </div>
                      <?php endif; ?>
                    </div>

                    <!-- Riferimento -->
                    <div class="form-row">
                      <div class="col-md-5 mb-3">
                        <label class="form-scheda-label">Riferimento</label>
                        <input type="email" class="form-control input-uppercase" id="pl_riferimento" name="pl_riferimento" aria-describedby="emailHelp1" value="<?=strtolower($p[0]['pl_riferimento'] ?? '')?>" required>
                        <div class="invalid-feedback"> Inserisci il nome del riferimento </div>
                      </div>
                      <div class="col-md-4 mb-3">
                        <label class="form-scheda-label">Email</label>
                        <input type="email" class="form-control" id="pl_mail" name="pl_mail" aria-describedby="emailHelp1" value="<?=strtolower($p[0]['pl_mail'] ?? '')?>" required>
                        <div class="invalid-feedback"> Inserisci un indirizzo email valido </div>
                      </div>
                      <div class="col-md-3 mb-3">
                        <label class="form-scheda-label">Telefono</label>
                        <input class="form-control input-phoneus" id="custom-phone" maxlength="14" name="tel"value="<?=$p[0]['pl_telefono'] ?? ''?>" required>
                        <div class="invalid-feedback"> Inserisci un numero di telefono </div>
                      </div>
                    </div> 

                    <!-- città-rete-provincia-cap -->
                    <div class="form-row">
                      <div class="col-md-5 mb-3">
                        <label class="form-scheda-label">Indirizzo</label>
                        <input type="text" id="pl_indirizzo" class="form-control input-uppercase" placeholder="Enter your address" name="pl_indirizzo" value="<?=$p[0]['pl_indirizzo'] ?? ''?>" >
                        <div class="invalid-feedback"> Bad address </div>
                      </div>

                      <div class="col-md-4 mb-3">
                        <label class="form-scheda-label">Città</label>
                        <input type="text" class="form-control input-uppercase" id="pl_citta" name="pl_citta" value="<?=$p[0]['pl_citta'] ?? ''?>" required>
                        <div class="invalid-feedback"> Inserisci la città o la località </div>
                      </div>
                      <div class="col-md-1 mb-1">
                        <label class="form-scheda-label">PROV</label>
                        <input class="form-control input-uppercase" id="pl_pro" maxlength="2" name="pl_pro" value="<?=$p[0]['pl_pro'] ?? ''?>" >
                        <div class="invalid-feedback"> Inserisci la provincia </div>
                      </div>
                      <div class="col-md-2 mb-2">
                        <label class="form-scheda-label">CAP</label>
                        <input class="form-control" id="pl_cap" autocomplete="off" maxlength="5" name="pl_cap" value="<?=$p[0]['pl_cap'] ?? ''?>" >
                        <div class="invalid-feedback"> Inserisci un CAP. </div>
                      </div>
                    </div>
                    
                    <!-- Note -->
                    <div class="form-row">
                      <div class="col-md-12 mb-3">
                        <label class="form-scheda-label">Note</label>
                        <textarea class="form-control" id="pl_note" name="pl_note" placeholder="Scrivi nota" ="" rows="3"><?=$p[0]['pl_note'] ?? ''?></textarea>
                        <div class="invalid-feedback"> Please enter a message in the textarea. </div>
                      </div>
                    </div>
                   
                    <div class="accordion w-100 <?php echo $isAjaxRequest ? 'inner-scheda' : ''; ?>" id="accordion-<?php echo $isAjaxRequest ? 'player-inner-scheda' : '1'; ?>">

                      <div class="card shadow">
                        <div class="card-header" id="heading-innsk-1">
                          <a role="button" href="#collapse-innsk-1" data-toggle="collapse" data-target="#collapse1-innsk-" aria-expanded="true" aria-controls="collapse-innsk-1" class="title-tab">
                            <span class="fe fe-activity fe-20"></span><strong>Ping<?php echo $m=($type=="RASPI")? ' e SD Memory' : '';;?> </strong>
                          </a>
                        </div>
                        <div id="collapse1-innsk-" class="collapse show" aria-labelledby="heading-innsk-1" data-parent="#accordion-<?php echo $isAjaxRequest ? 'player-inner-scheda' : '1'; ?>" style="">
                          <div class="card-body">
                            <i><h10>
                            ultimo ping: <?=$p[0]['pl_player_ultimaDataEstesa'] ?? ''?><br>
                            PC/IP player: <?=$p[0]['pl_player_pc'] ?? ''?><br>
                            IP esterno: <?=$p[0]['pl_player_ip'] ?? ''?>
                            </h10></i>
                            <?php if($type=="RASPI"){?>
                              <br><br>
                              <h5 class="mb-0 text-uppercase">SD memory status</h5>
                              <div class="row">
                                
                                <div class="col-md-2">
                                  <div id="SDmem" class="gauge-yr-container g3 mx-auto"></div>
                                </div>
                                <div class="col-md-5">
                                  <div id="SDmemText" >
                                    <i><h10>
                                    size: <?=$p[0]['pl_mem_size'] ?? ''?><br>
                                    used: <?=$p[0]['pl_mem_used'] ?? ''?><br>
                                    spazio disponibile: <?=$p[0]['pl_mem_available'] ?? ''?><br>
                                    percentuale occupata: <?=$p[0]['pl_mem_percent'] ?? 0?>%<br>
                                    </h10></i>
                                  </div>
                                </div>
                              </div>
                            <?php } ?>
                          </div>
                        </div>
                      </div>

                      <div class="card shadow">
                        <div class="card-header" id="heading-innsk-2">
                          <a role="button" href="#collapse-innsk-2" data-toggle="collapse" data-target="#collapse-innsk-2" aria-expanded="false" aria-controls="collapse-innsk-2" class="title-tab collapsed">
                            <span class="fe fe-tool fe-20"></span><strong>Configurazioni avanzate</strong>
                          </a>
                        </div>
                        <div id="collapse-innsk-2" class="collapse" aria-labelledby="heading-innsk-2" data-parent="#accordion-<?php echo $isAjaxRequest ? 'player-inner-scheda' : '1'; ?>" style="">
                          <div class="card-body">
                            <?php echo buildCheckByDbField("Ora esatta","pl_time",$p[0]['pl_time'] ?? 0);?>
                            <?php echo buildCheckByDbField("Multiutente","pl_player_freeaccess",$p[0]['pl_player_freeaccess'] ?? 0);?>
                            <?php echo buildCheckByDbField("Monitor","pl_monitor",$p[0]['pl_monitor'] ?? 0);?>
                            <?php echo buildCheckByDbField("Test","pl_test",$p[0]['pl_test'] ?? 0);?>
                            <?php echo buildCheckByDbField("Send Mail","pl_sendmail",$p[0]['pl_sendmail'] ?? 0);?>
                            <?php echo buildCheckByDbField("Edit selector","pl_client_edit_selector",$p[0]['pl_client_edit_selector'] ?? 0);?>
                            <?php echo buildCheckByDbField("Edit Spot","pl_client_edit_spot",$p[0]['pl_client_edit_selector'] ?? 0);?>
                            <?php echo buildCheckByDbField("Edit Rubriche","pl_client_edit_rubriche",$p[0]['pl_client_edit_rubriche'] ?? 0);?>
                            <hr><p class="mb-2"><strong>Orari funzionamento</strong></p>
                            <div class="row">
                              <div class="col-md-3 mb-3">
                                <label class="form-scheda-label">Dalle</label>
                                <input class="form-control" id="timeOn" name="timeOn" type="time">
                              </div>
                              <div class="col-md-3 mb-3">
                                <label class="form-scheda-label">Alle</label>
                                <input class="form-control" id="timeOff" type="time" name="timeOff">
                              </div>
                            </div>
                            
                            <input class="form-control" id="pl_client_ora_on_ora" name="pl_client_ora_on_ora" type="hidden" value="<?=$p[0]['pl_client_ora_on_ora'] ?? ''?>">
                            <input class="form-control" id="pl_client_ora_on_min" name="pl_client_ora_on_min" type="hidden" value="<?=$p[0]['pl_client_ora_on_min'] ?? ''?>">
                            <input class="form-control" id="pl_oraOnCalcolata" name="pl_oraOnCalcolata" type="hidden" value="<?=$p[0]['pl_oraOnCalcolata'] ?? ''?>">
                            <input class="form-control" id="pl_client_ora_off_ora" name="pl_client_ora_off_ora" type="hidden" value="<?=$p[0]['pl_client_ora_off_ora'] ?? ''?>">
                            <input class="form-control" id="pl_client_ora_off_min" name="pl_client_ora_off_min" type="hidden" value="<?=$p[0]['pl_client_ora_off_min'] ?? ''?>">
                            <input class="form-control" id="pl_oraOffCalcolata" name="pl_oraOffCalcolata" type="hidden" value="<?=$p[0]['pl_oraOffCalcolata'] ?? ''?>">
                          </div>
                        </div>
                      </div>

                      <div class="card shadow">
                        <div class="card-header" id="heading-innsk-3">
                          <a role="button" href="#collapse-innsk-3" data-toggle="collapse" data-target="#collapse-innsk-3" aria-expanded="false" aria-controls="collapse-innsk-3" class="title-tab ">
                            <span class="fe fe-layers fe-20"></span><strong>Sottogruppi</strong>
                          </a>
                        </div>
                        <div id="collapse-innsk-3" class="collapse" aria-labelledby="heading-innsk-3" data-parent="#accordion-<?php echo $isAjaxRequest ? 'player-inner-scheda' : '1'; ?>">
                          <div class="card-body">
                            <?php echo buildCheckSubGroupByIdPlayer($p[0]['pl_id'] ?? 0);?>
                          </div>
                        </div>
                      </div>

                      <div class="card shadow">
                        <div class="card-header" id="heading-innsk-4">
                          <a role="button" href="#collapse-innsk-4" data-toggle="collapse" data-target="#collapse-innsk-4" aria-expanded="false" aria-controls="collapse-innsk-4" class="title-tab collapsed">
                            <span class="fe fe-link fe-20"></span><strong>Collegamenti</strong>
                          </a>
                        </div>
                        <div id="collapse-innsk-4" class="collapse" aria-labelledby="heading-innsk-4" data-parent="#accordion-<?php echo $isAjaxRequest ? 'player-inner-scheda' : '1'; ?>">
                          <div class="card-body">
                            <style>
                              #collapse-innsk-4 .badge {
                                font-size: 100%;
                              }
                            </style>
                            <div class="mb-3">
                              <strong>Free Access:</strong> <?php echo ($p[0]['pl_player_freeaccess'] ?? 0) == 1 ? '<span class="badge badge-success">Attivo</span>' : '<span class="badge badge-secondary">Disattivo</span>'; ?>
                            </div>
                            <div class="mb-3">
                              <strong>IP Pubblico:</strong> <?=$p[0]['pl_player_ip'] ?? ''?>
                            </div>
                            <div class="mb-3">
                              <strong>Porta 80:</strong> <?php 
                              $port80 = $p[0]['port_80'] ?? null;
                              if($port80 !== null && ($port80 === 1 || $port80 === '1' || (is_string($port80) && (strtolower($port80) === 'open' || strtolower($port80) === 'aperta')))) {
                                echo '<span class="badge badge-success">OPEN</span>';
                              } elseif($port80 !== null && ($port80 === 0 || $port80 === '0' || (is_string($port80) && (strtolower($port80) === 'closed' || strtolower($port80) === 'chiusa')))) {
                                echo '<span class="badge badge-danger">CLOSED</span>';
                              } else {
                                echo '<span class="badge badge-secondary">NOT TESTED</span>';
                              }
                              ?>
                            </div>
                            <div class="mb-3">
                              <strong>Porta 22:</strong> <?php 
                              $port22 = $p[0]['port_22'] ?? null;
                              if($port22 !== null && ($port22 === 1 || $port22 === '1' || (is_string($port22) && (strtolower($port22) === 'open' || strtolower($port22) === 'aperta')))) {
                                echo '<span class="badge badge-success">OPEN</span>';
                              } elseif($port22 !== null && ($port22 === 0 || $port22 === '0' || (is_string($port22) && (strtolower($port22) === 'closed' || strtolower($port22) === 'chiusa')))) {
                                echo '<span class="badge badge-danger">CLOSED</span>';
                              } else {
                                echo '<span class="badge badge-secondary">NOT TESTED</span>';
                              }
                              ?>
                            </div>
                            <div class="mb-3">
                              <strong>IP Device:</strong> <?=$p[0]['pl_player_pc'] ?? ''?>
                            </div>
                            
                            
                              
                              <div class="mb-3">
                                <div class="d-flex align-items-center">
                                  <div class="custom-control custom-switch">
                                    <?php 
                                    $routerDinamicoRaw = $p[0]['pl_player_router_dinamico'] ?? null;
                                    $routerDinamicoValue = false;
                                    if($routerDinamicoRaw !== null) {
                                      $routerDinamicoValue = ($routerDinamicoRaw == 1 || $routerDinamicoRaw == '1' || (is_string($routerDinamicoRaw) && (strtolower($routerDinamicoRaw) == 'yes' || strtolower($routerDinamicoRaw) == 'si')));
                                    }
                                    ?>
                                    <input type="checkbox" class="custom-control-input" id="pl_player_router_dinamico" name="pl_player_router_dinamico" value="1" <?php echo $routerDinamicoValue ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="pl_player_router_dinamico"></label>
                                  </div>
                                  <span class="ml-2"><?php echo $routerDinamicoValue ? 'DNS DINAMICO ON' : 'DNS DINAMICO'; ?></span>
                                </div>
                              </div>


                              <div class="mb-3">
                                <label class="form-scheda-label">Network</label>
                                <select class="form-control" id="pl_player_network" name="pl_player_network" style="max-width: 150px;">
                                  <?php
                                  // Recuperare le networks dalla tabella Networks tramite API
                                  $currentNetwork = isset($p[0]['pl_player_network']) ? intval($p[0]['pl_player_network']) : 1;
                                  $networksApi = callApi("networks");
                                  $networks = [];
                                  if($networksApi && isset($networksApi['success']) && $networksApi['success'] && isset($networksApi['data'])) {
                                    foreach($networksApi['data'] as $net) {
                                      $networks[$net['id']] = $net['name'];
                                    }
                                  } else {
                                    // Fallback se l'API non funziona
                                    $networks = [1 => 'Free'];
                                  }
                                  foreach($networks as $netId => $netName) {
                                    $selected = ($currentNetwork == $netId) ? 'selected' : '';
                                    echo '<option value="' . $netId . '" ' . $selected . '>' . $netName . '</option>';
                                  }
                                  ?>
                                </select>
                              </div>

                              
                              
  
                            <div class="form-row">
                              <div class="col-md-6 mb-3">
                                <label class="form-scheda-label">Note Network</label>
                                <textarea class="form-control" id="pl_player_network_note" name="pl_player_network_note" rows="3"><?=$p[0]['pl_player_network_note'] ?? ''?></textarea>
                              </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex align-items-center">
                                  <div class="custom-control custom-switch">
                                    <?php 
                                    $onlineRaw = $p[0]['pl_player_online'] ?? null;
                                    $onlineValue = false;
                                    if($onlineRaw !== null) {
                                      $onlineValue = ($onlineRaw == 1 || $onlineRaw == '1' || (is_string($onlineRaw) && strtolower($onlineRaw) == 'on'));
                                    }
                                    ?>
                                    <input type="checkbox" class="custom-control-input" id="pl_player_online" name="pl_player_online" value="1" <?php echo $onlineValue ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="pl_player_online"></label>
                                  </div>
                                  <span class="ml-2" style="white-space: nowrap;"><?php echo $onlineValue ? 'PLAYER ONLINE' : 'PLAYER OFFLINE'; ?></span>
                                </div>
                              </div>
                              
                          </div>
                        </div>
                      </div>







                    <!-- CREATED/LOGIN -->
                    <div class="form-row">
                      <div class="col-md-12 mb-3">
                        <label class="form-scheda-label">Keyword (Max 10chr)</label>
                        <div class="d-flex align-items-center">
                          <?php 
                          // Rimuovi eventuale suffisso dal valore visualizzato
                          $plKeywordValue = isset($p[0]['pl_keyword']) ? $p[0]['pl_keyword'] : '';
                          $plKeywordValue = preg_replace('/_\d+$/', '', $plKeywordValue);
                          ?>
                          <input name="pl_keyword" id="pl_keyword" type="text" class="form-control" value="<?=$plKeywordValue?>" readonly style="max-width: 120px; cursor: pointer;" maxlength="10">
                          <?php if($id != 'nuova' && !empty($id) && is_numeric($id)): ?>
                          <span class="ml-3">
                            l'identità di questo player è "<span id="playerIdentityPassword" class="font-weight-bold"><?=$plKeywordValue . '_' . ($p[0]['pl_id'] ?? $id)?></span>"
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2" id="btnCopyIdentityPassword" title="Copia password identità">
                              <span class="fe fe-copy fe-12"></span>
                            </button>
                          </span>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="col-md-12 mb-4">
                        <input name="formAction" id="formActionPlayer" type="hidden" value="<?=$_POST["formAction"] ?? ''?>" >
                        scheda creata il <?=$p[0]['pl_dataCreazione'] ?? ''?></h10></i>
                      </div>
                    </div>


                    <!-- Button bar -->
                    <div class="button-bar <?php echo $isAjaxRequest ? 'skinn' : ''; ?>">
                      <button title="Salva" class="btn <?php echo $isAjaxRequest ? 'btn-success' : 'btn-outline-success'; ?>" type="button" id="<?php echo $isAjaxRequest ? 'player-skinn-update' : 'update'; ?>"><?php echo $isAjaxRequest ? 'Salva' : '<span class="fe fe-save fe-16"></span>'; ?></button>
                      <?php if ($isAjaxRequest): ?>
                      <button title="Chiudi" class="btn btn-success" id="player-skinn-chiudi" >Chiudi</button>
                      <?php else: ?>
                      <button title="lista" class="btn btn-outline-success back-lista" ><span class="fe fe-list fe-16"></span></button>
                      <?php endif; ?>
                      
                      <button <?=$disabled?>title="cancella" type="button" class="btn btn-<?php echo $isAjaxRequest ? 'danger' : 'outline-danger'; ?>" data-toggle="modal" data-target="#verticalModal"><span class="fe fe-trash fe-16"></span></button>
                    </div>
                  </form>

                  <!-- Modal Cancella -->
                  <div class="modal fade" id="verticalModal" tabindex="-1" role="dialog" aria-labelledby="verticalModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="verticalModalTitle">Cancella profilo</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>
                        <div class="modal-body">Eliminare definitivamente il profilo di <?=strtoupper($p[0]['pl_nome'] ?? '')?>?</div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
                          <button class="btn mb-2 btn-danger" id="delete">Cancella</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- modal password -->
                  <div class="modal fade" id="passwordModal" tabindex="-1" role="dialog" aria-labelledby="varyModalLabel" style="display: none;" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="varyModalLabel">Password</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                          </button>
                        </div>
                        <div class="modal-body">
                          <form>
                            <div class="form-group">
                              <label for="recipient-name" class="col-form-label">Scrivi la password:</label>
                              <input type="text" class="form-control" name="newPassword" id="newPassword" maxlength="10" style="max-width: 200px;">
                            </div>
                          </form>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
                          <button type="button" class="btn mb-2 btn-primary" data-dismiss="modal" id="changePassword">Salva</button>
                        </div>
                      </div>
                    </div>
                  </div>

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

                  <!-- Modal Nuovo Player da Gruppo -->
                  <div class="modal fade" id="newPlayerFromGruppoModal" tabindex="-1" role="dialog" aria-labelledby="newPlayerFromGruppoModalTitle" aria-hidden="true" data-backdrop="static" data-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                      <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                          <h5 class="modal-title" id="newPlayerFromGruppoModalTitle">
                            <span class="fe fe-plus-circle fe-16 mr-2"></span>Nuovo Player
                          </h5>
                        </div>
                        <div class="modal-body">
                          <div class="alert alert-info" role="alert">
                            <strong>Registrare un nuovo player sul gruppo:</strong><br>
                            <span id="newPlayerGruppoNome" class="font-weight-bold"></span>
                          </div>
                          <form id="newPlayerFromGruppoForm">
                            <div class="form-group">
                              <label for="newPlayerNome" class="col-form-label">Nome del Player <span class="text-danger">*</span></label>
                              <input type="text" class="form-control input-uppercase" id="newPlayerNome" name="newPlayerNome" required autofocus>
                              <div class="invalid-feedback">Il nome del player è obbligatorio</div>
                            </div>
                          </form>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-secondary" id="btnCancelNewPlayer">Annulla</button>
                          <button type="button" class="btn mb-2 btn-primary" id="btnConfirmNewPlayer">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Conferma
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Modal Conferma Creazione Player -->
                  <div class="modal fade" id="confirmNewPlayerModal" tabindex="-1" role="dialog" aria-labelledby="confirmNewPlayerModalTitle" aria-hidden="true" data-backdrop="static" data-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                      <div class="modal-content">
                        <div class="modal-header" id="confirmNewPlayerModalHeader">
                          <h5 class="modal-title" id="confirmNewPlayerModalTitle">Risultato</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close" id="closeConfirmModal">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>
                        <div class="modal-body" id="confirmNewPlayerModalBody">
                          <p id="confirmNewPlayerModalText"></p>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-primary" id="btnOkConfirmNewPlayer">OK</button>
                        </div>
                      </div>
                    </div>
                  </div>




                </div> <!-- /.card-body -->
              </div> <!-- /.my-4 -->
            </div> <!-- /.my-4 -->
          </div> <!-- /.col-12 col-lg-10 col-xl-8 -->
<?php if (!$isAjaxRequest): ?>
        </div> <!-- .row -->
      </div> <!-- .container-fluid -->
    <?php include_once('./inc/slide-right.php');?>
    </main> <!-- main -->
  </div> <!-- .wrapper -->
<?php endif; ?>

<?php if (!$isAjaxRequest): ?>
  <script src="js/jquery.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/moment.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/simplebar.min.js"></script>
  <script src='js/daterangepicker.js'></script>
  <script src='js/jquery.stickOnScroll.js'></script>
  <script src="js/tinycolor-min.js"></script>
  <script src="js/config.js"></script>

  <script src='js/jquery.mask.min.js'></script>
  <script src='js/select2.min.js'></script>
  <script src='js/jquery.steps.min.js'></script>
  <script src='js/jquery.validate.min.js'></script>
  <script src='js/jquery.timepicker.js'></script>
  <script src='js/dropzone.min.js'></script>
  <script src='js/uppy.min.js'></script>
  <script src='js/quill.min.js'></script>

  <script src="js/gauge.min.js"></script>
<?php else: ?>
  <!-- Script caricati solo se gauge.min.js non è già presente -->
  <script>
    if (typeof Gauge === 'undefined') {
      var script = document.createElement('script');
      script.src = 'js/gauge.min.js';
      document.head.appendChild(script);
    }
  </script>
<?php endif; ?>
  
  <?=$script?>
  
  <script>
    // Gestione chiusura scheda quando caricata via AJAX
    $(document).ready(function() {
      $("#player-skinn-chiudi").on("click", function() {
        closeChildTab("tab-players");
      });
    });
    
    // Funzione closeChildTab se non esiste (per compatibilità)
    if (typeof closeChildTab === 'undefined') {
      function closeChildTab(tab) {
        $(".tabs-scheda-gruppo."+tab+">.mb-3>.child-tab").html(""); 
        $(".tabs-scheda-gruppo."+tab+">.mb-3>.primary-tab").fadeIn("slow");
      }
    }
  </script>


  <script>
    //i numeri sono formattati per l'utilizzo dell'ora nella scheda player
    $(document).ready(function() {
      var timeOn = ("0" + $("#pl_client_ora_on_ora").val()).slice(-2)+":"+("0" + $("#pl_client_ora_on_min").val()).slice(-2);
      $("#timeOn").val(timeOn);
      var timeOff = ("0" + $("#pl_client_ora_off_ora").val()).slice(-2)+":"+("0" + $("#pl_client_ora_off_min").val()).slice(-2);
      $("#timeOff").val(timeOff);

      $("#timeOn").on("change", function(){
        var tm = $("#timeOn").val();
        var tt = tm.split(":");
        var hh = tt[0];
        var mm = tt[1];
        var calc = parseInt(hh*60)+parseInt(mm);
        if(hh.charAt(0)==0){hh=hh.substr(-1);}
        if(mm.charAt(0)==0){mm=mm.substr(-1);}
        $("#pl_client_ora_on_ora").val(hh);
        $("#pl_client_ora_on_min").val(mm);
        $("#pl_oraOnCalcolata").val(calc);
      });
      $("#timeOff").on("change", function(){
        var tm = $("#timeOff").val();
        var tt = tm.split(":");
        var hh = tt[0];
        var mm = tt[1];
        var calc = parseInt(hh*60)+parseInt(mm);
        if(hh.charAt(0)==0){hh=hh.substr(-1);}
        if(mm.charAt(0)==0){mm=mm.substr(-1);}
        $("#pl_client_ora_off_ora").val(hh);
        $("#pl_client_ora_off_min").val(mm);
        $("#pl_oraOffCalcolata").val(calc);
      });
    });
  </script>  

  <script>
    // Inizializza il grafico SDmem dopo che tutto è caricato
    $(window).on('load', function() {
      setTimeout(function() {
        var sdMemElement = document.getElementById("SDmem");
        if(sdMemElement && typeof Gauge !== 'undefined') {
          var memPercent = parseFloat('<?php echo (!empty($p) && isset($p[0]['pl_mem_percent'])) ? (float)$p[0]['pl_mem_percent'] : 0; ?>') || 0;
          console.log("Inizializzazione grafico SDmem - Elemento trovato, valore:", memPercent);
          
          try {
            var cpuGauge = Gauge(sdMemElement, {
              max: 100,
              label: function(value) {
                return Math.round(value) + "%";
              },
              color: function(value) {
                if(value < 20) {
                  return "#5ee432"; // green
                }else if(value < 40) {
                  return "#fffa50"; // yellow
                }else if(value < 60) {
                  return "#f7aa38"; // orange
                }else {
                  return "#ef4655"; // red
                }
              }
            });
            cpuGauge.setValueAnimated(memPercent, 2);
            console.log("Grafico SDmem inizializzato con successo con valore:", memPercent);
          } catch(e) {
            console.error("Errore nell inizializzazione del grafico:", e);
          }
        } else {
          console.error("SDmem non trovato o Gauge non disponibile. Elemento:", sdMemElement, "Gauge:", typeof Gauge);
        }
      }, 1000); // Attendi 1 secondo per essere sicuri che tutto sia caricato
    });
  </script>

<?php if (!$isAjaxRequest): ?>
  <script>

    $('.select2').select2(
    {
      theme: 'bootstrap4',
    });
    $('.select2-multi').select2(
    {
      multiple: true,
      theme: 'bootstrap4',
    });
    $('.drgpicker').daterangepicker(
    {
      singleDatePicker: true,
      timePicker: false,
      showDropdowns: true,
      locale:
      {
        format: 'MM/DD/YYYY'
      }
    });
    $('.time-input').timepicker(
    {
      'scrollDefault': 'now',
      'zindex': '9999' /* fix modal open */
    });
    /** date range picker */
    if ($('.datetimes').length)
    {
      $('.datetimes').daterangepicker(
      {
        timePicker: true,
        startDate: moment().startOf('hour'),
        endDate: moment().startOf('hour').add(32, 'hour'),
        locale:
        {
          format: 'M/DD hh:mm A'
        }
      });
    }
    var start = moment().subtract(29, 'days');
    var end = moment();

    function cb(start, end)
    {
      $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
    }
    $('#reportrange').daterangepicker(
    {
      startDate: start,
      endDate: end,
      ranges:
      {
        'Today': [moment(), moment()],
        'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
        'Last 7 Days': [moment().subtract(6, 'days'), moment()],
        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
        'This Month': [moment().startOf('month'), moment().endOf('month')],
        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
      }
    }, cb);
    cb(start, end);
    $('.input-placeholder').mask("00/00/0000",
    {
      placeholder: "__/__/____"
    });
    $('.input-zip').mask('00000-000',
    {
      placeholder: "____-___"
    });
    $('.input-money').mask("#.##0,00",
    {
      reverse: true
    });
    $('.input-phoneus').mask('(000) 000-0000');
    $('.input-mixed').mask('AAA 000-S0S');
    $('.input-ip').mask('0ZZ.0ZZ.0ZZ.0ZZ',
    {
      translation:
      {
        'Z':
        {
          pattern: /[0-9]/,
          optional: true
        }
      },
      placeholder: "___.___.___.___"
    });
      // editor
      var editor = document.getElementById('editor');
      if (editor)
      {
        var toolbarOptions = [
        [
        {
          'font': []
        }],
        [
        {
          'header': [1, 2, 3, 4, 5, 6, false]
        }],
        ['bold', 'italic', 'underline', 'strike'],
        ['blockquote', 'code-block'],
        [
        {
          'header': 1
        },
        {
          'header': 2
        }],
        [
        {
          'list': 'ordered'
        },
        {
          'list': 'bullet'
        }],
        [
        {
          'script': 'sub'
        },
        {
          'script': 'super'
        }],
        [
        {
          'indent': '-1'
        },
        {
          'indent': '+1'
          }], // outdent/indent
          [
          {
            'direction': 'rtl'
          }], // text direction
          [
          {
            'color': []
          },
          {
            'background': []
          }], // dropdown with defaults from theme
          [
          {
            'align': []
          }],
          ['clean'] // remove formatting button
          ];
          var quill = new Quill(editor,
          {
            modules:
            {
              toolbar: toolbarOptions
            },
            theme: 'snow'
          });
        }
      // Example starter JavaScript for disabling form submissions if there are invalid fields
      (function()
      {
        'use strict';
        window.addEventListener('load', function()
        {
          // Fetch all the forms we want to apply custom Bootstrap validation styles to
          var forms = document.getElementsByClassName('needs-validation');
          // Loop over them and prevent submission
          var validation = Array.prototype.filter.call(forms, function(form)
          {
            form.addEventListener('submit', function(event)
            {
              if (form.checkValidity() === false)
              {
                event.preventDefault();
                event.stopPropagation();
              }           
              form.classList.add('was-validated');
            }, false);
          });
        }, false);
      })();
  </script>
  <script>
      var uptarg = document.getElementById('drag-drop-area');
      if (uptarg)
      {
        var uppy = Uppy.Core().use(Uppy.Dashboard,
        {
          inline: true,
          target: uptarg,
          proudlyDisplayPoweredByUppy: false,
          theme: 'dark',
          width: 770,
          height: 210,
          plugins: ['Webcam']
        }).use(Uppy.Tus,
        {
          endpoint: 'https://master.tus.io/files/'
        });
        uppy.on('complete', (result) =>
        {
          console.log('Upload complete! We’ve uploaded these files:', result.successful)
        });
      }
  </script>
<?php endif; ?>
<?php if (!$isAjaxRequest): ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-md5/2.19.0/js/md5.min.js"></script>
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
<?php endif; ?>