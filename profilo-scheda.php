<?php
// Start the session
session_start();
include_once('load.php');
if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}


DB::init();

if(isset($_POST["formAction"]) && $_POST["formAction"]!=''){

  if($_POST["formAction"]=="back"){
    header("location:profili.php");
  }

  if($_POST["formAction"]=="update"){
    if(isset($_POST["login"]) && $_POST["login"]!=''){
      $userId = isset($_GET["id"]) ? $_GET["id"] : '';
      
      // Prepara i dati per l'API
      $updateData = array(
        'active' => isset($_POST['active']) ? 1 : 0,
        'nome' => isset($_POST['nome']) ? $_POST['nome'] : '',
        'indirizzo' => isset($_POST['indirizzo']) ? $_POST['indirizzo'] : null,
        'citta' => isset($_POST['citta']) ? $_POST['citta'] : '',
        'cap' => isset($_POST['cap']) ? $_POST['cap'] : null,
        'pro' => isset($_POST['pro']) ? $_POST['pro'] : null,
        'tel' => isset($_POST['tel']) ? $_POST['tel'] : null,
        'mail' => isset($_POST['mail']) ? $_POST['mail'] : '',
        'login' => isset($_POST['login']) ? $_POST['login'] : '',
        'permesso' => isset($_POST['permesso']) ? $_POST['permesso'] : 1,
        'rete_id' => isset($_POST['rete_id']) ? $_POST['rete_id'] : 0,
        'contractor' => isset($_POST['contractor']) ? $_POST['contractor'] : 0,
        'role' => isset($_POST['role']) ? $_POST['role'] : 0,
        'require_2fa' => isset($_POST['require_2fa']) ? 1 : 0,
        'gruppi_monitor' => isset($_POST['gruppi_monitor']) ? $_POST['gruppi_monitor'] : null
      );
      
      // NON aggiungere la password qui: viene gestita solo alla creazione o dal modale
      // La password per gli utenti esistenti si cambia SOLO dal modale "Cambia Password"
      
      // Determina se è un nuovo utente o un aggiornamento
      $isNewUser = ($userId == '' || $userId == 'nuova' || $userId == 0);
      
      // Chiamata API esterna (server centrale)
      if ($isNewUser) {
        // Crea nuovo utente
        $apiUrl = 'https://yourradio.org/api/utenti';
        $method = 'POST';
      } else {
        // Aggiorna utente esistente
        $apiUrl = 'https://yourradio.org/api/utenti/' . $userId;
        $method = 'PUT';
      }
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $apiUrl);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
      curl_setopt($ch, CURLOPT_TIMEOUT, 10);
      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
      curl_close($ch);
      
      if (($httpCode == 200 || $httpCode == 201) && !$curlError) {
        $apiResponse = json_decode($response, true);
        if (isset($apiResponse['success']) && $apiResponse['success']) {
          if ($isNewUser && isset($apiResponse['data']['id'])) {
            $_GET["id"] = $apiResponse['data']['id'];
          } else {
            $_GET["id"] = $userId;
          }
          $_POST["formAction"] = '';
        } else {
          echo "Errore nell'aggiornamento: " . (isset($apiResponse['message']) ? $apiResponse['message'] : 'Errore sconosciuto');
        }
      } else {
        // Fallback al metodo diretto se l'API non funziona
        $_GET["id"] = Utenti::updateUtente($_POST);
        $_POST["formAction"] = '';
      }
    } else {
      echo "Login non valido !";
    }
  }
  if($_POST["formAction"]=="delete"){
    $userId = isset($_GET["id"]) ? $_GET["id"] : '';
    
    // Chiamata API esterna per eliminare l'utente (server centrale)
    $apiUrl = 'https://yourradio.org/api/utenti/' . $userId;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode == 200 && !$curlError) {
      $_POST["formAction"] = '';
      header("location:profili.php");
    } else {
      // Fallback al metodo diretto se l'API non funziona
      $_POST["formAction"] = '';
      $res = Utenti::deleteUtente($_GET["id"]);
    header("location:profili.php");
    }
  }

}

// I dati verranno caricati via AJAX lato client per velocità (come profili.php)
// Inizializza le variabili con valori di default
$userId = isset($_GET["id"]) ? $_GET["id"] : '';

// Valori di default (per nuovo utente o durante il caricamento)
$active = 1;
$id = $userId != 'nuova' && $userId != '' ? $userId : '';
$login = '';
$nome = '';
$indirizzo = '';
$citta = '';
$pro = '';
$cap = '';
$tel = '';
$mail = '';
$password = '';
$permesso = 1;
$gruppo = '';
$rete_id = 0;
$data_creazione = '';
$ultimo_accesso = '';

// Campi aggiuntivi dalla tabella user
$contractor = 1; // Default: Yourradio
$role = 0;
$secret_2fa = '';
$require_2fa = 0;
$gruppi_monitor = '';

// Carica il nome dal database solo per impostare il title (se l'utente esiste)
if(!empty($id) && $id != 'nuova' && is_numeric($id)){
  $utenteData = Utenti::selectUtenteByID($id);
  if(!empty($utenteData) && isset($utenteData[0]['nome'])){
    $nome = $utenteData[0]['nome'];
  }
}

if(!isset($id) || $id==0 || $id==''){
  $disabled=" disabled ";
  $id='nuova';
  $title='Nuovo profilo';
  $type='Nuovo Profilo'; // Valore di default per nuovo utente
  $pageTitle = 'Nuovo profilo';
}else{
  $disabled="";
  $title=$nome;
  if($rete_id>0){$type="Account";}else{$type="Admin";}
  $pageTitle = !empty($nome) ? $nome : 'Profilo';
}

include_once('inc/head.php');


$gruppi=Gruppi::selectAllActive();
$contractors=Contractor::selectAll();




if($active==1){$chbox_active="checked";$chbox_active_lab="Attivo";}else{$chbox_active="";$chbox_active_lab="Disattivato";}

$script='
<script>
// Carica i dati utente via AJAX (veloce, come profili.php)
function loadUserData() {
  var userId = "'.($userId != 'nuova' && $userId != '' ? $userId : '').'";
  if (!userId || userId === "") {
    // Nuovo utente, non serve caricare dati
    return;
  }
  
  $.ajax({
    url: "https://yourradio.org/api/utenti/" + userId,
    method: "GET",
    dataType: "json",
    success: function(response) {
      if (response.success && response.data) {
        var data = response.data;
        
        // Popola i campi del form
        $("#active").prop("checked", data.active == 1);
        $("#validationCustom3").val(data.nome || "");
        $("#address-wpalaceholder").val(data.indirizzo || "");
        $("#validationCustom33").val(data.citta || "");
        $("#custom-pro").val(data.pro || "");
        $("#custom-zip").val(data.cap || "");
        $("#custom-phone").val(data.tel || "");
        $("#exampleInputEmail2").val(data.mail || "");
        $("#login").val(data.login || "");
        $("#permesso").val(data.permesso || 1);
        $("#rete_id").val(data.rete_id || 0);
        $("#contractor").val(data.contractor || 0);
        $("#role").val(data.role || 0);
        $("#require_2fa").prop("checked", data.require_2fa == 1);
        $("#gruppi_monitor").val(data.gruppi_monitor || "");
        
        // Aggiorna la multiselect dei gruppi monitor
        if (data.gruppi_monitor && data.gruppi_monitor !== "") {
          // Se ci sono gruppi selezionati, abilita lo switch e la select
          var gruppiIds = data.gruppi_monitor.split(",");
          $("#gruppi_monitor_select option").each(function() {
            if (gruppiIds.indexOf($(this).val()) !== -1) {
              $(this).prop("selected", true);
            } else {
              $(this).prop("selected", false);
            }
          });
          // Abilita lo switch e la select
          $("#tutti_gruppi_switch").prop("checked", true);
          $("#gruppi_monitor_select").prop("disabled", false);
          $("#tutti_gruppi_label").text("Seleziona Gruppi");
          $("#gruppi_help_text").text("Tieni premuto Ctrl (o Cmd su Mac) per selezionare più gruppi");
        } else {
          // Se non ci sono gruppi, disabilita lo switch e la select
          $("#gruppi_monitor_select option").prop("selected", false);
          $("#gruppi_monitor_select").prop("disabled", true);
          $("#tutti_gruppi_switch").prop("checked", false);
          $("#tutti_gruppi_label").text("Tutti i gruppi");
          $("#gruppi_help_text").text("Seleziona i gruppi per abilitare la selezione manuale");
        }
        
        $("#password").val(data.password || "");
        $("#userId").val(data.id || "");
        
        // Aggiorna il titolo della pagina
        if (data.nome) {
          document.title = data.nome + " - Profilo";
        }
        
        // Aggiorna le informazioni sistema
        if (data.id) {
          $("#info-id").text(data.id);
        }
        if (data.dataCreazione) {
          $("#info-data-creazione").text(data.dataCreazione);
        }
        if (data.ultimoAccesso) {
          $("#info-ultimo-accesso").text(data.ultimoAccesso || "Mai");
        }
        if (data.gr_nome) {
          $("#info-gruppo").text(data.gr_nome.toUpperCase());
        } else {
          $("#info-gruppo").text("ADMIN");
        }
      }
    },
    error: function(xhr, status, error) {
      console.error("Errore nel caricamento dati utente:", error);
    }
  });
}

// Aggiorna l\'input hidden gruppi_monitor quando cambiano le selezioni
function updateGruppiMonitor() {
  var selectedIds = [];
  $("#gruppi_monitor_select option:selected").each(function() {
    selectedIds.push($(this).val());
  });
  $("#gruppi_monitor").val(selectedIds.join(","));
}

// Funzione per abilitare la selezione manuale
function enableGruppiSelection() {
  $("#gruppi_monitor_select").prop("disabled", false);
  $("#gruppi_help_text").text("Tieni premuto Ctrl (o Cmd su Mac) per selezionare più gruppi");
}

// Funzione per disabilitare la selezione e svuotare l\'input
function disableGruppiSelection() {
  $("#gruppi_monitor_select").prop("disabled", true);
  $("#gruppi_monitor_select option").prop("selected", false);
  $("#gruppi_monitor").val("");
  $("#gruppi_help_text").text("Seleziona i gruppi per abilitare la selezione manuale");
}

// Event listener per la multiselect
$(document).on("change", "#gruppi_monitor_select", function() {
  if (!$(this).prop("disabled")) {
    updateGruppiMonitor();
  }
});

// Event listener per lo switch
$(document).on("change", "#tutti_gruppi_switch", function() {
  if ($(this).is(":checked")) {
    // Switch ON: abilita la select per selezione manuale
    enableGruppiSelection();
    $("#tutti_gruppi_label").text("Seleziona Gruppi");
  } else {
    // Switch OFF: disabilita la select e svuota l\'input
    disableGruppiSelection();
    $("#tutti_gruppi_label").text("Tutti i gruppi");
  }
});

// Carica i dati quando la pagina è pronta
$(document).ready(function() {
  // Inizializza: switch OFF, select disabilitata, input vuoto (se non ci sono dati)
  if (!$("#gruppi_monitor").val() || $("#gruppi_monitor").val() === "") {
    $("#tutti_gruppi_switch").prop("checked", false);
    $("#gruppi_monitor_select").prop("disabled", true);
    $("#tutti_gruppi_label").text("Tutti i gruppi");
    $("#gruppi_help_text").text("Seleziona i gruppi per abilitare la selezione manuale");
  }
  
  loadUserData();
  
  // Inizializza gruppi_monitor all\'avvio (solo se la select è abilitata)
  if (!$("#gruppi_monitor_select").prop("disabled")) {
    updateGruppiMonitor();
  }
});

// Funzioni per gestire lo spinner
function showLoading() {
  $("#loadingOverlay").css("display", "flex");
  $("body").addClass("loading");
  // Salva nello sessionStorage per mostrarlo anche dopo il reload
  if (typeof(Storage) !== "undefined") {
    sessionStorage.setItem("showLoading", "true");
  }
}

function hideLoading() {
  $("#loadingOverlay").css("display", "none");
  $("body").removeClass("loading");
  if (typeof(Storage) !== "undefined") {
    sessionStorage.removeItem("showLoading");
  }
}

function showSuccess(message) {
  $("#successMsg").text(message);
  $("#successAlert").fadeIn();
  setTimeout(function() {
    $("#successAlert").fadeOut();
  }, 3000);
}

function showError(message) {
  $("#errorMsg").text(message);
  $("#errorAlert").fadeIn();
}

function hideAlerts() {
  $(".alert").hide();
}

// Intercetta il submit del form
$( "#scheda-profilo" ).on("submit", function(e) {
  e.preventDefault();
  var formAction = $("#formAction").val();
  
  // Se formAction è vuoto o non valido, non fare nulla (prevenire submit accidentale con Enter)
  if (!formAction || formAction === "") {
    return false;
  }
  
  if (formAction === "back") {
    window.location.href = "profili.php";
    return false;
  }
  
  if (formAction === "update") {
    showLoading();
    hideAlerts();
    
    var userId = $("#userId").val() || "'.(isset($_GET["id"]) ? $_GET["id"] : '').'";
    var isNewUser = (!userId || userId == "nuova" || userId == "0");
    
    // Validazione: login obbligatorio
    if (!$("#login").val() || $("#login").val() === "") {
      showError("Il campo Username è obbligatorio");
      hideLoading();
      return false;
    }
    
    // Validazione: per nuovi utenti, la password è obbligatoria
    if (isNewUser) {
      var newPassword = $("#newPasswordField").val();
      if (!newPassword || newPassword === "") {
        showError("Per creare un nuovo utente è necessario inserire una password");
        hideLoading();
        $("#newPasswordField").focus();
        return false;
      }
    }
    
    // Prepara i dati del form
    var formData = {
      active: $("#active").is(":checked") ? 1 : 0,
      nome: $("#validationCustom3").val() || "",
      indirizzo: $("#address-wpalaceholder").val() || null,
      citta: $("#validationCustom33").val() || "",
      cap: $("#custom-zip").val() || null,
      pro: $("#custom-pro").val() || null,
      tel: $("#custom-phone").val() || null,
      mail: $("#exampleInputEmail2").val() || "",
      login: $("#login").val(),
      permesso: parseInt($("#permesso").val()) || 1,
      rete_id: parseInt($("#rete_id").val()) || 0,
      contractor: parseInt($("#contractor").val()) || 0,
      role: parseInt($("#role").val()) || 0,
      require_2fa: $("#require_2fa").is(":checked") ? 1 : 0,
      gruppi_monitor: $("#gruppi_monitor").val() || null
    };
    
    // Gestione password: SOLO per nuovi utenti
    // Per utenti esistenti, la password NON viene inviata qui (si cambia solo dal modale)
    if (isNewUser) {
      // Per nuovi utenti, la password è obbligatoria e viene dal campo newPasswordField
      var passwordToUse = $("#newPasswordField").val();
      if (passwordToUse && passwordToUse !== "") {
        // Hasha la password in MD5 prima di inviarla
        if (typeof md5 === "function") {
          formData.password = md5(passwordToUse);
        } else {
          // Fallback: invia la password in chiaro e l\'API la hashera
          formData.password = passwordToUse;
          formData.password_plain = true; // Flag per indicare che va hashata
        }
      }
    }
    // Per utenti esistenti, NON inviare la password (viene cambiata solo dal modale)
    
    // Usa API esterna server centrale
    var apiUrl = "https://yourradio.org/api/utenti";
    var method = "POST";
    if (!isNewUser) {
      apiUrl = "https://yourradio.org/api/utenti/" + userId;
      method = "PUT";
    }
    
    console.log("Salvataggio utente:", {method: method, url: apiUrl, data: formData});
    
    $.ajax({
      method: method,
      url: apiUrl,
      contentType: "application/json",
      data: JSON.stringify(formData),
      success: function(res) {
        console.log("Risposta API:", res);
        if (res.success) {
          showSuccess("Utente salvato con successo!");
          // Non nascondere lo spinner, rimarrà visibile durante il reload
          if (isNewUser && res.data && res.data.id) {
            // Aggiorna l\'ID nel form e nell\'URL
            $("#userId").val(res.data.id);
            window.history.replaceState({}, "", "profilo-scheda.php?id=" + res.data.id);
            // Ricarica i dati per mostrare lutente appena creato
            setTimeout(function() {
              loadUserData();
              hideLoading();
            }, 500);
          } else {
            // Aggiorna i dati mostrati con quelli salvati
            if (res.data) {
              var data = res.data;
              $("#active").prop("checked", data.active == 1);
              $("#validationCustom3").val(data.nome || "");
              $("#address-wpalaceholder").val(data.indirizzo || "");
              $("#validationCustom33").val(data.citta || "");
              $("#custom-pro").val(data.pro || "");
              $("#custom-zip").val(data.cap || "");
              $("#custom-phone").val(data.tel || "");
              $("#exampleInputEmail2").val(data.mail || "");
              $("#login").val(data.login || "");
              $("#permesso").val(data.permesso || 1);
              $("#rete_id").val(data.rete_id || 0);
              $("#contractor").val(data.contractor || 0);
              $("#role").val(data.role || 0);
              $("#require_2fa").prop("checked", data.require_2fa == 1);
              $("#gruppi_monitor").val(data.gruppi_monitor || "");
              
              // Aggiorna la multiselect dei gruppi monitor
              if (data.gruppi_monitor && data.gruppi_monitor !== "") {
                // Se ci sono gruppi selezionati, abilita lo switch e la select
                var gruppiIds = data.gruppi_monitor.split(",");
                $("#gruppi_monitor_select option").each(function() {
                  if (gruppiIds.indexOf($(this).val()) !== -1) {
                    $(this).prop("selected", true);
                  } else {
                    $(this).prop("selected", false);
                  }
                });
                // Abilita lo switch e la select
                $("#tutti_gruppi_switch").prop("checked", true);
                $("#gruppi_monitor_select").prop("disabled", false);
                $("#tutti_gruppi_label").text("Seleziona Gruppi");
                $("#gruppi_help_text").text("Tieni premuto Ctrl (o Cmd su Mac) per selezionare più gruppi");
              } else {
                // Se non ci sono gruppi, disabilita lo switch e la select
                $("#gruppi_monitor_select option").prop("selected", false);
                $("#gruppi_monitor_select").prop("disabled", true);
                $("#tutti_gruppi_switch").prop("checked", false);
                $("#tutti_gruppi_label").text("Tutti i gruppi");
                $("#gruppi_help_text").text("Seleziona i gruppi per abilitare la selezione manuale");
              }
              
              // Aggiorna informazioni sistema
              if (data.id) $("#info-id").text(data.id);
              if (data.dataCreazione) $("#info-data-creazione").text(data.dataCreazione);
              if (data.ultimoAccesso) $("#info-ultimo-accesso").text(data.ultimoAccesso || "Mai");
              if (data.gr_nome) $("#info-gruppo").text(data.gr_nome.toUpperCase());
            }
            hideLoading();
          }
        } else {
          hideLoading();
          showError("Errore: " + (res.message || "Errore sconosciuto"));
        }
      },
      error: function(xhr, status, error) {
        hideLoading();
        var errorMsg = "Errore durante il salvataggio";
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMsg = xhr.responseJSON.message;
        } else if (xhr.status === 0) {
          errorMsg = "Errore di connessione. Verifica la connessione al server.";
        } else if (xhr.status >= 500) {
          errorMsg = "Errore del server. Riprova più tardi.";
        }
        console.error("Errore salvataggio:", xhr, status, error);
        showError(errorMsg);
      }
    });
  }
  
  if (formAction === "delete") {
    if (!confirm("Sei sicuro di voler eliminare questo utente?")) {
      return false;
    }
    
    showLoading();
    hideAlerts();
    
    var userId = "'.(isset($_GET["id"]) ? $_GET["id"] : '').'";
    
    $.ajax({
      method: "DELETE",
      url: "https://yourradio.org/api/utenti/" + userId,
      success: function(res) {
        if (res.success) {
          // Non nascondere lo spinner, rimarrà visibile durante il redirect
          window.location.href = "profili.php";
        } else {
          hideLoading();
          showError("Errore: " + (res.message || "Errore sconosciuto"));
        }
      },
      error: function(xhr, status, error) {
        hideLoading();
        var errorMsg = "Errore durante l\'eliminazione";
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMsg = xhr.responseJSON.message;
        }
        showError(errorMsg);
      }
    });
  }
  
  // Se nessuna azione corrisponde, non fare nulla
  return false;
});

// Prevenire submit accidentale con Enter nei campi input
$( "#scheda-profilo input" ).on("keypress", function(e) {
  if (e.which === 13) { // Enter key
    e.preventDefault();
    return false;
  }
});

$( "#scheda-profilo textarea" ).on("keypress", function(e) {
  if (e.which === 13 && !e.shiftKey) { // Enter senza Shift
    e.preventDefault();
    return false;
  }
});

$( "#update" ).click(function() {
    $("#formAction").val("update");
  });

  $( "#delete" ).click(function() {
    $("#formAction").val("delete");
    $( "#scheda-profilo" ).submit();
  });

  $( ".back-lista" ).click(function() {
    $("#formAction").val("back");
  $( "#scheda-profilo" ).submit();
  });

  $( "#changePassword" ).click(function() {
  var newPass = $("#newPassword").val();
  if (newPass == "") {
    showError("Inserisci una password");
      return;
    }
  
  showLoading();
  hideAlerts();
  
  var userId = "'.(isset($_GET["id"]) ? $_GET["id"] : '').'";
  
    $.ajax({
          method: "PUT",
      url: "https://yourradio.org/api/utenti/" + userId + "/password",
          contentType: "application/json",
          data: JSON.stringify({newpass: newPass}),
    success: function(res) {
      hideLoading();
      if (res.success) {
              $("#password").val(res.data.password_md5);
        showSuccess("Password cambiata con successo!");
        $("#newPassword").val("");
        $("#passwordModal").modal("hide");
      } else {
        showError("Errore: " + (res.message || "Errore sconosciuto"));
      }
    },
    error: function(xhr, status, error) {
      hideLoading();
      var errorMsg = "Errore durante il cambio password";
      if (xhr.responseJSON && xhr.responseJSON.message) {
        errorMsg = xhr.responseJSON.message;
      }
      showError(errorMsg);
          }
        });
  });

// Nascondi lo spinner quando la pagina è completamente caricata (se era stato mostrato)
$(window).on("load", function() {
  setTimeout(function() {
    if (typeof(Storage) !== "undefined" && sessionStorage.getItem("showLoading") === "true") {
      sessionStorage.removeItem("showLoading");
    }
    hideLoading();
  }, 300);
});

// Mostra alert se password mancante
if ($("#password").val() == "" && $("#login").val() != "") {
    $("#msg_alert").html("Ricorda di creare la password!");
    $(".alert").show();
  }

</script>
';
?>

<body class="horizontal dark">
  <!-- Spinner Loading Overlay -->
  <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center;">
    <div style="text-align: center; color: white; background-color: rgba(0, 0, 0, 0.8); padding: 2rem; border-radius: 10px;">
      <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem; border-width: 0.3em;">
        <span class="sr-only">Caricamento...</span>
      </div>
      <div style="margin-top: 1rem; font-size: 1.1rem; font-weight: 500;">Elaborazione in corso...</div>
    </div>
  </div>
  
  <style>
    #loadingOverlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.6);
      z-index: 9999;
      justify-content: center;
      align-items: center;
    }
    
    #loadingOverlay .spinner-border {
      border-color: #ffffff;
      border-right-color: transparent;
    }
    
    /* Mantieni lo spinner visibile anche durante il reload della pagina */
    body.loading #loadingOverlay {
      display: flex !important;
    }
  </style>
  
  <script>
    // Mostra lo spinner immediatamente se la pagina si sta ricaricando dopo un'operazione
    // Usa JavaScript vanilla perché jQuery non è ancora caricato
    (function() {
      if (typeof(Storage) !== "undefined" && sessionStorage.getItem("showLoading") === "true") {
        var body = document.body;
        var overlay = document.getElementById("loadingOverlay");
        if (body) {
          body.classList.add("loading");
        }
        if (overlay) {
          overlay.style.display = "flex";
        }
        
        // Funzione per nascondere lo spinner
        function hideSpinner() {
          if (typeof(Storage) !== "undefined") {
            sessionStorage.removeItem("showLoading");
          }
          if (body) {
            body.classList.remove("loading");
          }
          if (overlay) {
            overlay.style.display = "none";
          }
        }
        
        // Nascondi lo spinner quando la pagina è completamente caricata
        if (document.readyState === "complete") {
          setTimeout(hideSpinner, 300);
        } else {
          window.addEventListener("load", function() {
            setTimeout(hideSpinner, 300);
          });
        }
        
        // Timeout di sicurezza: nascondi lo spinner dopo 3 secondi anche se la pagina non si è caricata
        setTimeout(hideSpinner, 3000);
      }
    })();
  </script>
  
  <div class="wrapper">
    <?php include_once('inc/menu-h.php'); ?>
    <main role="main" class="main-content">
      <div class="container-fluid">
        <div class="row justify-content-center">
          <div class="col-12 col-lg-10 col-xl-8">
            <div class="my-4">

              <div class="alert alert-primary" style="display:none" role="alert">
                <span class="fe fe-alert-circle fe-16 mr-2"></span>
                <span id="msg_alert"></span>
              </div>
              
              <div class="alert alert-success" id="successAlert" style="display:none" role="alert">
                <span class="fe fe-check-circle fe-16 mr-2"></span>
                <span id="successMsg"></span>
              </div>
              
              <div class="alert alert-danger" id="errorAlert" style="display:none" role="alert">
                <span class="fe fe-x-circle fe-16 mr-2"></span>
                <span id="errorMsg"></span>
              </div>

              <div class="my-4">

                <div class="row mt-1 align-items-center">
                  <div class="col-md-3 text-center mb-0">
                    <div class="avatar avatar-xl">
                      <img src="./assets/avatars/face-1.jpg" alt="YourRadio - Profilo" class="avatar-img rounded-circle">
                    </div>
                  </div>
                  
                </div>


                <div class="card-body">

                  <form id="scheda-profilo" class="needs-validation" novalidate method="post">
                    <!-- username-nome -->
                    <div class="form-row">
                      <div class="input-group col-md-5 mb-3">
                        <label for="login">Username</label>
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text" id="inputGroupPrepend">@</span>
                          </div>
                          <input type="text" class="form-control" id="login" name="login"  aria-describedby="inputGroupPrepend" value="<?=$login?>" required>
                          <div class="invalid-feedback"> Indica una username. </div>
                          <div class="input-group-append">
                            <button <?=$disabled?> class="btn btn-outline-danger" type="button" data-toggle="modal" data-target="#passwordModal" data-whatever="@mdo">Cambia Password</button>
                          </div>
                        </div>
                      </div>
                      <?php if($id == 'nuova'): ?>
                      <div class="col-md-4 mb-3">
                        <label for="newPasswordField">Password</label>
                        <input type="password" class="form-control" id="newPasswordField" name="newPasswordField" placeholder="Inserisci password" autocomplete="new-password" required>
                        <div class="invalid-feedback">La password è obbligatoria per i nuovi utenti</div>
                      </div>
                      <?php endif; ?>
                      <div class="col-md-7 mb-3">
                        <label for="validationCustom3">Nome</label>
                        <input type="text" class="form-control" id="validationCustom3" name="nome" value="<?=$nome?>" required>
                      </div>
                    </div>
                    <!-- email-tel -->
                    <div class="form-row">
                      <div class="col-md-8 mb-3">
                        <label for="exampleInputEmail2">Email</label>
                        <input type="email" class="form-control" id="exampleInputEmail2" name="mail" aria-describedby="emailHelp1" value="<?=$mail?>" required>
                        <div class="invalid-feedback"> Inserisci un indirizzo email valido </div>

                      </div>
                      <div class="col-md-4 mb-3">
                        <label for="custom-phone">Telefono</label>
                        <input class="form-control input-phoneus" id="custom-phone" maxlength="14" name="tel" value="<?=$tel?>" required>
                        <div class="invalid-feedback"> Inserisci un numero di telefono </div>
                      </div>
                    </div> 
                    <!-- indirizzo -->
                    <div class="form-row">
                      <div class="col-md-12 mb-3">
                        <label for="address-wpalaceholder">Indirizzo</label>
                        <input type="text" id="address-wpalaceholder" class="form-control" placeholder="Enter your address" name="indirizzo" value="<?=$indirizzo?>" >
                        <div class="invalid-feedback"> Bad address </div>
                      </div>
                    </div>
                    <!-- città-rete-provincia-cap -->
                    <div class="form-row">
            
                      <div class="col-md-6 mb-3">
                        <label for="validationCustom33">Città</label>
                        <input type="text" class="form-control" id="validationCustom33" name="citta" value="<?=$citta?>" required>
                        <div class="invalid-feedback"> Inserisci la città o la località </div>
                      </div>

                      <div class="col-md-3 mb-3">
                        <label for="rete_id">Rete</label>
                        <select class="form-control" name="rete_id" id="rete_id" >
                          <?php 
                          if(0==$rete_id){$selected = "selected";}
                          echo '<option value="0" '.$selected.'>TUTTE (admin)</option>';
                          foreach ($gruppi as $g) {
                            if($g['gr_nome']==''){continue;}
                            if($g['gr_id']==$rete_id){$selected = "selected";}else{$selected = "";}
                            echo '<option value="'.$g['gr_id'].'" '.$selected.'>'.strtoupper($g['gr_nome']).'</option>';
                          }?>
                        </select>
                      </div>

                      <div class="col-md-1 mb-1">
                        <label for="custom-zip">PROV</label>
                        <input class="form-control" id="custom-pro" maxlength="2" name="pro" value="<?=$pro?>" >
                        <div class="invalid-feedback"> Inserisci la provincia </div>
                      </div>

                      <div class="col-md-2 mb-2">
                        <label for="custom-zip">CAP</label>
                        <input class="form-control" id="custom-zip" autocomplete="off" maxlength="5" name="cap" value="<?=$cap?>" >
                        <div class="invalid-feedback"> Inserisci un CAP. </div>
                      </div>
                    </div>
                    <!-- Contractor -->
                    <div class="form-row">
                      <div class="col-md-4 mb-3">
                        <label for="contractor">Contractor</label>
                        <select class="form-control" name="contractor" id="contractor">
                          <?php
                          if(!empty($contractors)){
                            foreach($contractors as $c){
                              $selected = ($contractor == $c['id']) ? 'selected' : '';
                              echo '<option value="'.$c['id'].'" '.$selected.'>'.$c['name'].'</option>';
                            }
                          }
                          ?>
                        </select>
                      </div>
                      <div class="col-md-4 mb-3">
                        <label for="role">Role</label>
                        <select class="form-control" name="role" id="role">
                          <option value="0" <?=($role==0)?'selected':''?>>Utente</option>
                          <option value="1" <?=($role==1)?'selected':''?>>Capo-gruppo</option>
                          <option value="3" <?=($role==3)?'selected':''?>>Admin</option>
                        </select>
                      </div>
                      <div class="col-md-4 mb-3">
                        <label for="permesso">Permesso</label>
                        <input type="number" class="form-control" id="permesso" name="permesso" value="<?=$permesso?>" min="0">
                      </div>
                    </div>
                    <!-- 2FA -->
                    <div class="form-row">
                      <div class="col-md-6 mb-3">
                        <div class="custom-control custom-switch">
                          <input type="checkbox" class="custom-control-input" id="require_2fa" name="require_2fa" value="1" <?=($require_2fa==1)?'checked':''?>>
                          <label class="custom-control-label" for="require_2fa">Autenticazione a due fattori (2FA)</label>
                        </div>
                        
                      </div>
                      <div class="col-md-6 mb-3">
                        <label for="gruppi_monitor_select">Gruppi Monitor</label>
                        <div class="mb-2">
                          <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="tutti_gruppi_switch" name="tutti_gruppi_switch" <?php if(!empty($gruppi_monitor)) echo 'checked'; ?>>
                            <label class="custom-control-label" for="tutti_gruppi_switch" id="tutti_gruppi_label"><?php echo !empty($gruppi_monitor) ? 'Seleziona Gruppi' : 'Tutti i gruppi'; ?></label>
                          </div>
                        </div>
                        <select class="form-control" id="gruppi_monitor_select" name="gruppi_monitor_select[]" multiple size="5" <?php echo empty($gruppi_monitor) ? 'disabled' : ''; ?>>
                          <?php
                          if(!empty($gruppi)){
                            $gruppiSelected = !empty($gruppi_monitor) ? explode(',', $gruppi_monitor) : array();
                            foreach($gruppi as $g){
                              if($g['gr_nome'] == ''){continue;}
                              $selected = in_array($g['gr_id'], $gruppiSelected) ? 'selected' : '';
                              echo '<option value="'.$g['gr_id'].'" '.$selected.'>'.strtoupper($g['gr_nome']).'</option>';
                            }
                          }
                          ?>
                        </select>
                        <small class="form-text text-muted" id="gruppi_help_text">Tieni premuto Ctrl (o Cmd su Mac) per selezionare più gruppi</small>
                        <input type="hidden" id="gruppi_monitor" name="gruppi_monitor" value="<?=$gruppi_monitor?>">
                      </div>
                    </div>
                    <!-- ACTIVE -->
                    <div class="form-row">
                      <div class="mb-3">
                        <div class="custom-control custom-switch">
                          <input type="checkbox" class="custom-control-input" <?=$chbox_active?> id="active" name="active" value="1">
                          <label class="custom-control-label" for="active"><?=$chbox_active_lab?></label>
                        </div>
                      </div>
                    </div>
                    <!-- CREATED/LOGIN -->
                    <div class="form-row">
                      <input name="password" id="password" type="hidden" value="<?=$password?>" >
                      <input name="dataCreazione" id="dataCreazione" type="hidden" value="<?=$data_creazione?>" >
                      <input name="formAction" id="formAction" type="hidden" value="<?=isset($_POST["formAction"]) ? $_POST["formAction"] : ''?>" >
                      <input name="id" id="userId" type="hidden" value="<?=$id?>" >
                      <div class="col-md-12 mb-3">
                        <div class="card bg-light">
                          <div class="card-body">
                            <h6 class="card-title">Informazioni Sistema</h6>
                            <p class="card-text mb-1"><strong>ID:</strong> <span id="info-id"><?=$id?></span></p>
                            <p class="card-text mb-1"><strong>Data Creazione:</strong> <span id="info-data-creazione"><?=$data_creazione?></span></p>
                            <p class="card-text mb-1"><strong>Ultimo Accesso:</strong> <span id="info-ultimo-accesso"><?=$ultimo_accesso ? $ultimo_accesso : 'Mai'?></span></p>
                            <p class="card-text mb-0"><strong>Gruppo:</strong> <span id="info-gruppo"><?=$gruppo ? strtoupper($gruppo) : 'ADMIN'?></span></p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <!-- Button bar -->
                    <div class="button-bar">

                      <button title="salva" class="btn btn-outline-success" type="submit" id="update"><span class="fe fe-save fe-16"></span></button>
                      <button title="lista" class="btn btn-outline-success back-lista" ><span class="fe fe-list fe-16"></span></button>
                      <button <?=$disabled?>title="cancella" type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#verticalModal"><span class="fe fe-trash fe-16"></span></button>
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
                        <div class="modal-body">Eliminare definitivamente il profilo di <?=strtoupper($nome)?>?</div>
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
                              <input type="text" class="form-control" name="newPassword" id="newPassword">
                            </div>
                          </form>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
                          <button type="button" class="btn mb-2 btn-primary" data-dismiss="modal" id="changePassword">Salva password</button>
                        </div>
                      </div>
                    </div>
                  </div>




                </div> <!-- /.card-body -->
              </div> <!-- /.my-4 -->
            </div> <!-- /.my-4 -->
          </div> <!-- /.col-12 col-lg-10 col-xl-8 -->
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

  <script src='js/jquery.mask.min.js'></script>
  <script src='js/select2.min.js'></script>
  <script src='js/jquery.steps.min.js'></script>
  <script src='js/jquery.validate.min.js'></script>
  <script src='js/jquery.timepicker.js'></script>
  <script src='js/dropzone.min.js'></script>
  <script src='js/uppy.min.js'></script>
  <script src='js/quill.min.js'></script>
  <?=$script?>
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