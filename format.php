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
// Funzione globale per aprire il modale di modifica
function openEditFormatModal(formatId) {
  // Carica i dati del format
  $.ajax({
    url: "https://yourradio.org/api/formats?all=1&t=" + new Date().getTime(),
    method: "GET",
    dataType: "json",
    cache: false,
    success: function(response) {
      if (response.success && response.data) {
        var format = response.data.find(function(f) {
          return f.frmt_id == formatId;
        });
        
        if (format) {
          // Popola il form
          $("#editFormatId").val(format.frmt_id || \'\');
          $("#editFormatIdDisplay").val(format.frmt_id || \'\');
          $("#editFormatNome").val(format.frmt_nome || \'\');
          $("#editFormatDescrizione").val(format.frmt_descrizione || \'\');
          $("#editDescrizioneCounter").text((format.frmt_descrizione || \'\').length);
          
          // Imposta gli switch
          $("#editFormatActive").prop("checked", format.frmt_active == 1);
          $("#editFormatPermettiRipetizione").prop("checked", format.frmt_permettiRipetizioneArtista == 1);
          
          // Imposta il numero di songs collegate
          var songsCount = format.songs_count || 0;
          $("#editFormatSongsCount").text(songsCount);
          
          // Mostra il modale
          $("#editFormatModal").modal("show");
        } else {
          alert("Format non trovato!");
        }
      }
    },
    error: function(xhr, status, error) {
      console.error("Errore nel caricamento format:", error);
      alert("Errore nel caricamento dei dati del format");
    }
  });
}

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
            \'<div class="card-body" style="cursor:pointer;" onclick="openEditFormatModal(\' + formatId + \');">\' +
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
  
  // Funzione per ricaricare la lista dei format
  function reloadFormatList() {
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
              \'<div class="card-body" style="cursor:pointer;" onclick="openEditFormatModal(\' + formatId + \');">\' +
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
        console.error("Errore nel ricaricamento format:", error, xhr);
      }
    });
  }
  
  // Gestione contatore caratteri nome
  $("#formatNome").on("input", function() {
    var value = $(this).val();
    var length = value.length;
    $("#nomeCounter").text(length);
    
    // Rimuovi caratteri non validi (solo lettere, numeri e spazi)
    var cleaned = value.replace(/[^A-Za-z0-9\\s]/g, \'\');
    if (cleaned !== value) {
      $(this).val(cleaned);
      length = cleaned.length;
      $("#nomeCounter").text(length);
      $("#formatNomeError").text("Caratteri non validi rimossi. Sono ammessi solo lettere, numeri e spazi.").show();
      setTimeout(function() {
        $("#formatNomeError").fadeOut();
      }, 3000);
    } else {
      $("#formatNomeError").hide();
    }
    
    // Verifica limite 16 caratteri
    if (length > 16) {
      $("#formatNomeError").text("Il nome non può superare i 16 caratteri!").show();
      $(this).val($(this).val().substring(0, 16));
      $("#nomeCounter").text(16);
    }
  });
  
  // Gestione contatore caratteri descrizione
  $("#formatDescrizione").on("input", function() {
    var length = $(this).val().length;
    $("#descrizioneCounter").text(length);
    if (length > 25) {
      $("#formatDescrizioneError").text("La descrizione non può superare i 25 caratteri!").show();
      $(this).val($(this).val().substring(0, 25));
      $("#descrizioneCounter").text(25);
    } else {
      $("#formatDescrizioneError").hide();
    }
  });
  
  
  // Gestione click su Conferma
  $("#formatConfirmBtn").on("click", function() {
    var nome = $("#formatNome").val().trim().toUpperCase();
    var descrizione = $("#formatDescrizione").val().trim();
    
    // Validazione
    if (!nome || nome === \'\') {
      alert("Il nome del format è obbligatorio!");
      return;
    }
    
    if (nome.length > 16) {
      alert("Il nome non può superare i 16 caratteri!");
      $("#formatNome").focus();
      return;
    }
    
    if (!descrizione || descrizione === \'\') {
      alert("La descrizione è obbligatoria!");
      return;
    }
    
    if (descrizione.length > 25) {
      alert("La descrizione non può superare i 25 caratteri!");
      $("#formatDescrizione").focus();
      return;
    }
    
    // Rimuovi caratteri non validi dal nome
    nome = nome.replace(/[^A-Z0-9\\s]/g, \'\');
    
    // Limita a 16 caratteri
    if (nome.length > 16) {
      nome = nome.substring(0, 16);
    }
    
    // Disabilita i pulsanti e mostra lo spinner
    $("#formatConfirmBtn").prop("disabled", true);
    $("#formatCancelBtn").prop("disabled", true);
    $("#formatLoadingSpinner").show();
    $("#formatErrorAlert").hide();
    $("#newFormatForm").hide();
    
    // Prepara i dati da inviare
    var requestData = {
      frmt_nome: nome,
      frmt_descrizione: descrizione
    };
    
    var jsonData = JSON.stringify(requestData);
    console.log("[format] Invio dati:", requestData);
    console.log("[format] JSON string:", jsonData);
    
    // Invia i dati all\'API
    $.ajax({
      url: "https://yourradio.org/api/formats",
      type: "POST",
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      processData: false,
      data: jsonData,
      success: function(response) {
        if (response.success) {
          // Chiudi il modal
          $("#newFormatModal").modal("hide");
          // Ricarica la lista dei format
          reloadFormatList();
          // Reset del form
          $("#newFormatForm")[0].reset();
          $("#descrizioneCounter").text(0);
        } else {
          // Mostra errore
          $("#formatErrorText").text(response.error ? response.error.message : "Errore durante il salvataggio");
          $("#formatErrorAlert").show();
          $("#formatLoadingSpinner").hide();
          $("#newFormatForm").show();
          $("#formatConfirmBtn").prop("disabled", false);
          $("#formatCancelBtn").prop("disabled", false);
        }
      },
      error: function(xhr, status, error) {
        var errorMsg = "Errore durante il salvataggio";
        if (xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
          errorMsg = xhr.responseJSON.error.message;
        }
        $("#formatErrorText").text(errorMsg);
        $("#formatErrorAlert").show();
        $("#formatLoadingSpinner").hide();
        $("#newFormatForm").show();
        $("#formatConfirmBtn").prop("disabled", false);
        $("#formatCancelBtn").prop("disabled", false);
      }
    });
  });
  
  // Reset del form quando il modal viene chiuso
  $("#newFormatModal").on("hidden.bs.modal", function() {
    $("#newFormatForm")[0].reset();
    $("#nomeCounter").text(0);
    $("#descrizioneCounter").text(0);
    $("#formatNomeError").hide();
    $("#formatDescrizioneError").hide();
    $("#formatErrorAlert").hide();
    $("#formatLoadingSpinner").hide();
    $("#newFormatForm").show();
    $("#formatConfirmBtn").prop("disabled", false);
    $("#formatCancelBtn").prop("disabled", false);
  });
  
  // Gestione contatore caratteri descrizione nel modale di modifica
  $("#editFormatDescrizione").on("input", function() {
    var length = $(this).val().length;
    $("#editDescrizioneCounter").text(length);
    if (length > 25) {
      $("#editFormatDescrizioneError").text("La descrizione non può superare i 25 caratteri!").show();
      $(this).val($(this).val().substring(0, 25));
      $("#editDescrizioneCounter").text(25);
    } else {
      $("#editFormatDescrizioneError").hide();
    }
  });
  
  // Gestione click su Aggiorna
  $("#editFormatUpdateBtn").on("click", function() {
    var formatId = $("#editFormatId").val();
    var descrizione = $("#editFormatDescrizione").val().trim();
    var active = $("#editFormatActive").is(":checked") ? 1 : 0;
    var permettiRipetizione = $("#editFormatPermettiRipetizione").is(":checked") ? 1 : 0;
    
    // Validazione
    if (!descrizione || descrizione === \'\') {
      alert("La descrizione è obbligatoria!");
      return;
    }
    
    if (descrizione.length > 25) {
      alert("La descrizione non può superare i 25 caratteri!");
      $("#editFormatDescrizione").focus();
      return;
    }
    
    // Disabilita i pulsanti e mostra lo spinner
    $("#editFormatUpdateBtn").prop("disabled", true);
    $("#editFormatCancelBtn").prop("disabled", true);
    $("#editFormatDeleteBtn").prop("disabled", true);
    $("#editFormatLoadingSpinner").show();
    $("#editFormatErrorAlert").hide();
    $("#editFormatForm").hide();
    
    // Invia i dati all\'API
    $.ajax({
      url: "https://yourradio.org/api/formats/" + formatId,
      type: "PUT",
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      processData: false,
      data: JSON.stringify({
        frmt_active: active,
        frmt_descrizione: descrizione,
        frmt_permettiRipetizioneArtista: permettiRipetizione
      }),
      success: function(response) {
        if (response.success) {
          // Chiudi il modal
          $("#editFormatModal").modal("hide");
          // Ricarica la lista dei format
          reloadFormatList();
        } else {
          // Mostra errore
          $("#editFormatErrorText").text(response.error ? response.error.message : "Errore durante l\'aggiornamento");
          $("#editFormatErrorAlert").show();
          $("#editFormatLoadingSpinner").hide();
          $("#editFormatForm").show();
          $("#editFormatUpdateBtn").prop("disabled", false);
          $("#editFormatCancelBtn").prop("disabled", false);
          $("#editFormatDeleteBtn").prop("disabled", false);
        }
      },
      error: function(xhr, status, error) {
        var errorMsg = "Errore durante l\'aggiornamento";
        if (xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
          errorMsg = xhr.responseJSON.error.message;
        }
        $("#editFormatErrorText").text(errorMsg);
        $("#editFormatErrorAlert").show();
        $("#editFormatLoadingSpinner").hide();
        $("#editFormatForm").show();
        $("#editFormatUpdateBtn").prop("disabled", false);
        $("#editFormatCancelBtn").prop("disabled", false);
        $("#editFormatDeleteBtn").prop("disabled", false);
      }
    });
  });
  
  // Variabile globale per salvare i dati durante la cancellazione
  var pendingDeleteSongsCount = 0;
  var pendingDeleteFormatName = \'\';
  
  // Gestione click su Cancella
  $("#editFormatDeleteBtn").on("click", function() {
    // Salva il numero di songs e il nome del format PRIMA di chiudere il modale
    pendingDeleteSongsCount = parseInt($("#editFormatSongsCount").text()) || 0;
    pendingDeleteFormatName = $("#editFormatNome").val() || \'Format\';
    
    // Chiudi il modale di edit prima di mostrare quello di conferma
    $("#editFormatModal").modal("hide");
    // Mostra il modale di conferma dopo un breve delay per permettere la chiusura del primo
    setTimeout(function() {
      // Prepara il messaggio di conferma usando i valori salvati
      var message = "Attenzione - Operazione Irreversibile<br>";
      message += "Cancellando il format <strong>" + pendingDeleteFormatName + "</strong> verranno aggiornate anche tutte le song abbinate (tot.: " + pendingDeleteSongsCount + ").<br><br>";
      message += "Vuoi procedere?";
      $("#deleteFormatMessage").html(message);
      
      $("#deleteFormatConfirmModal").modal("show");
    }, 300);
  });
  
  // Gestione click su SI nel modale di conferma cancellazione
  $("#deleteFormatYesBtn").on("click", function() {
    var formatId = $("#editFormatId").val();
    
    // Disabilita i pulsanti e mostra lo spinner
    $("#deleteFormatYesBtn").prop("disabled", true);
    $("#deleteFormatNoBtn").prop("disabled", true);
    $("#deleteFormatLoadingSpinner").show();
    
    // Invia la richiesta di cancellazione
    $.ajax({
      url: "https://yourradio.org/api/formats/" + formatId,
      type: "DELETE",
      dataType: "json",
      success: function(response) {
        if (response.success) {
          // Chiudi entrambi i modali
          $("#deleteFormatConfirmModal").modal("hide");
          $("#editFormatModal").modal("hide");
          // Ricarica la lista dei format
          reloadFormatList();
        } else {
          alert("Errore durante la cancellazione: " + (response.error ? response.error.message : "Errore sconosciuto"));
          $("#deleteFormatYesBtn").prop("disabled", false);
          $("#deleteFormatNoBtn").prop("disabled", false);
          $("#deleteFormatLoadingSpinner").hide();
        }
      },
      error: function(xhr, status, error) {
        var errorMsg = "Errore durante la cancellazione";
        if (xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
          errorMsg = xhr.responseJSON.error.message;
        }
        alert(errorMsg);
        $("#deleteFormatYesBtn").prop("disabled", false);
        $("#deleteFormatNoBtn").prop("disabled", false);
        $("#deleteFormatLoadingSpinner").hide();
      }
    });
  });
  
  // Reset del form quando il modale di modifica viene chiuso
  $("#editFormatModal").on("hidden.bs.modal", function() {
    $("#editFormatForm")[0].reset();
    $("#editDescrizioneCounter").text(0);
    $("#editFormatErrorAlert").hide();
    $("#editFormatLoadingSpinner").hide();
    $("#editFormatForm").show();
    $("#editFormatUpdateBtn").prop("disabled", false);
    $("#editFormatCancelBtn").prop("disabled", false);
    $("#editFormatDeleteBtn").prop("disabled", false);
  });
  
  // Reset del modale di conferma cancellazione quando viene chiuso
  $("#deleteFormatConfirmModal").on("hidden.bs.modal", function() {
    $("#deleteFormatLoadingSpinner").hide();
    $("#deleteFormatYesBtn").prop("disabled", false);
    $("#deleteFormatNoBtn").prop("disabled", false);
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
                              <button class="btn btn-outline-secondary back-lista" data-toggle="modal" data-target="#newFormatModal"><span class="fe fe-plus fe-16"></span> Nuovo</button>
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

<!-- Modal modifica format -->
<div class="modal fade" id="editFormatModal" tabindex="-1" role="dialog" aria-labelledby="editFormatModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editFormatModalLabel">Modifica Format</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="editFormatForm">
          <input type="hidden" id="editFormatId" name="frmt_id">
          
          <div class="form-group">
            <label for="editFormatIdDisplay" class="col-form-label">ID:</label>
            <input type="text" class="form-control" id="editFormatIdDisplay" readonly style="max-width: 100px;" maxlength="5">
          </div>
          
          <div class="form-group">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="editFormatActive" name="frmt_active" value="1">
              <label class="custom-control-label" for="editFormatActive">Attivo</label>
            </div>
          </div>
          
          <div class="form-group">
            <label for="editFormatNome" class="col-form-label">Nome Format:</label>
            <input type="text" class="form-control" id="editFormatNome" name="frmt_nome" readonly >
            <small class="form-text text-muted">Il nome non può essere modificato</small>
          </div>
          
          <div class="form-group">
            <label for="editFormatDescrizione" class="col-form-label">Breve Descrizione (max 25 caratteri):</label>
            <input type="text" class="form-control" id="editFormatDescrizione" name="frmt_descrizione" maxlength="25" required>
            <small class="form-text text-muted"><span id="editDescrizioneCounter">0</span>/25 caratteri</small>
            <div id="editFormatDescrizioneError" class="alert alert-danger mt-2" style="display:none;"></div>
          </div>
          
          <div class="form-group">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="editFormatPermettiRipetizione" name="frmt_permettiRipetizioneArtista" value="1">
              <label class="custom-control-label" for="editFormatPermettiRipetizione">Permetti Ripetizione Artista</label>
            </div>
            <p class="mt-2 mb-0"><small class="text-muted">Song collegate: <span id="editFormatSongsCount">0</span></small></p>
          </div>
        </form>
        <div id="editFormatLoadingSpinner" class="text-center" style="display:none;">
          <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Aggiornamento in corso...</span>
          </div>
          <p class="mt-2">Aggiornamento in corso...</p>
        </div>
        <div id="editFormatErrorAlert" class="alert alert-danger" style="display:none;">
          <span id="editFormatErrorText"></span>
          <button type="button" class="close" onclick="$(\'#editFormatErrorAlert\').hide();">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn mb-2 btn-danger" id="editFormatDeleteBtn">Cancella</button>
        <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal" id="editFormatCancelBtn">Annulla</button>
        <button type="button" class="btn mb-2 btn-primary" id="editFormatUpdateBtn">Aggiorna</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal conferma cancellazione format -->
<div class="modal fade" id="deleteFormatConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteFormatConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteFormatConfirmModalLabel">Conferma Cancellazione</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p id="deleteFormatMessage">Vuoi cancellare questo format?</p>
        <div id="deleteFormatLoadingSpinner" class="text-center" style="display:none;">
          <div class="spinner-border text-danger" role="status">
            <span class="sr-only">Cancellazione in corso...</span>
          </div>
          <p class="mt-2">Cancellazione in corso...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal" id="deleteFormatNoBtn">NO</button>
        <button type="button" class="btn mb-2 btn-danger" id="deleteFormatYesBtn">SI</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal nuovo format -->
<div class="modal fade" id="newFormatModal" tabindex="-1" role="dialog" aria-labelledby="newFormatModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newFormatModalLabel">Nuovo Format</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="newFormatForm">
          <div class="form-group">
            <label for="formatNome" class="col-form-label">Nome Format (max 16 caratteri):</label>
            <input type="text" class="form-control" id="formatNome" name="formatNome" style="text-transform: uppercase" maxlength="16" required>
            <small class="form-text text-muted"><span id="nomeCounter">0</span>/16 caratteri. Solo lettere, numeri e spazi. Non sono ammessi apostrofi o caratteri speciali.</small>
            <div id="formatNomeError" class="alert alert-danger mt-2" style="display:none;"></div>
          </div>
          <div class="form-group">
            <label for="formatDescrizione" class="col-form-label">Breve Descrizione (max 25 caratteri):</label>
            <input type="text" class="form-control" id="formatDescrizione" name="formatDescrizione" maxlength="25" required>
            <small class="form-text text-muted"><span id="descrizioneCounter">0</span>/25 caratteri</small>
            <div id="formatDescrizioneError" class="alert alert-danger mt-2" style="display:none;"></div>
          </div>
        </form>
        <div id="formatLoadingSpinner" class="text-center" style="display:none;">
          <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Salvataggio in corso...</span>
          </div>
          <p class="mt-2">Salvataggio in corso...</p>
        </div>
        <div id="formatErrorAlert" class="alert alert-danger" style="display:none;">
          <span id="formatErrorText"></span>
          <button type="button" class="close" onclick="$(\'#formatErrorAlert\').hide();">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal" id="formatCancelBtn">Annulla</button>
        <button type="button" class="btn mb-2 btn-primary" id="formatConfirmBtn">Conferma</button>
      </div>
    </div>
  </div>
</div>


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
