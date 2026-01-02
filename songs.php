<?php
// Start the session
session_start();
include_once('load.php');

if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}

// Gestione salvataggio/recupero filtri nella sessione PHP
if (isset($_GET['action'])) {
  if ($_GET['action'] === 'saveFilters') {
    // Salva i filtri nella sessione
    $rawInput = file_get_contents('php://input');
    $filters = json_decode($rawInput, true);
    if (is_array($filters)) {
      $_SESSION['songs_filters'] = $filters;
      header('Content-Type: application/json');
      echo json_encode(array('success' => true, 'data' => $filters, 'message' => 'Filtri salvati con successo'));
      exit;
    } else {
      header('Content-Type: application/json');
      echo json_encode(array('success' => false, 'error' => 'Dati non validi'));
      exit;
    }
  } elseif ($_GET['action'] === 'getFilters') {
    // Recupera i filtri dalla sessione
    $filters = isset($_SESSION['songs_filters']) ? $_SESSION['songs_filters'] : array();
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'data' => $filters));
    exit;
  } elseif ($_GET['action'] === 'clearFilters') {
    // Rimuovi i filtri dalla sessione
    unset($_SESSION['songs_filters']);
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'data' => array(), 'message' => 'Filtri rimossi con successo'));
    exit;
  }
}

$filters=Utils::createAllSongsFilters(); 

include_once('inc/head.php');

$tableId = 'songs';

$tables = '';
$scripts = '';

$tables.='
<!-- table '.$tableId.' --> 
<div class="card shadow mb-6">
  <div class="card-body col-md-12">
    <!-- table -->
    <table class="table datatables display table-sm table-'.$tableId.'" id="dataTable-'.$tableId.'" style="width:100%">
      <thead>
        <tr>
          <th>Artista</th>
          <th>Titolo</th>
          <th>Anno</th>
        </tr>
      </thead>
      <tfoot>
        <tr>
          <th>Artista</th>
          <th>Titolo</th>
          <th>Anno</th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<!-- //table '.$tableId.' --> 
';

$scripts.='
// Funzione per salvare i filtri nella sessione PHP lato applicazione
function saveFiltersToSession() {
  // Non salvare se i filtri non sono ancora stati caricati
  if (!filtersLoaded) {
    console.log("[songs] Filtri non ancora caricati, non salvo");
    return;
  }
  
  var filters = {};
  var hasNonDefaultFilters = false;
  
  // Salva tutti i filtri
  $(".songFilter_select").each(function() {
    var id = $(this).attr("id");
    if (!id || id === \'undefined\') {
      return; // Salta elementi senza ID valido
    }
    if (id === "f_format") {
      // Per la multiselect format, salva come array
      var formatValues = [];
      $(this).find("option:selected").each(function() {
        var val = $(this).val();
        if (val && val !== \'\' && val !== \'0\') {
          formatValues.push(val);
          hasNonDefaultFilters = true;
        }
      });
      filters[id] = formatValues;
    } else {
      var val = $(this).val() || \'0\';
      filters[id] = val;
      // Considera non-default se il valore non è \'0\' o il primo valore di default
      if (val !== \'0\' && val !== \'\') {
        // Verifica se non è il valore di default (primo option)
        var firstOption = $(this).find("option:first").val();
        if (val !== firstOption) {
          hasNonDefaultFilters = true;
        }
      }
    }
  });
  
  // Se non ci sono filtri non-default, non salvare (evita di sovrascrivere con valori vuoti)
  if (!hasNonDefaultFilters) {
    console.log("[songs] Filtri vuoti o di default, non salvo in sessione");
    return;
  }
  
  // Salva nella sessione PHP lato applicazione (non tramite API)
  console.log("[songs] Invio filtri da salvare:", filters);
  $.ajax({
    url: "songs.php?action=saveFilters",
    method: "POST",
    contentType: "application/json; charset=utf-8",
    dataType: "json",
    processData: false,
    data: JSON.stringify(filters),
    success: function(response) {
      console.log("[songs] Filtri salvati in sessione - Risposta:", response);
    },
    error: function(xhr, status, error) {
      console.error("[songs] Errore nel salvataggio filtri:", error, "Status:", status, "Response:", xhr.responseText);
    }
  });
}

// Variabile globale per indicare se i filtri sono stati caricati
var filtersLoaded = false;

// Funzione per ripristinare i filtri dalla sessione
function loadFiltersFromSession(callback) {
  // Carica i filtri dalla sessione PHP lato applicazione (non tramite API)
  $.ajax({
    url: "songs.php?action=getFilters&t=" + new Date().getTime(),
    method: "GET",
    dataType: "json",
    cache: false,
    success: function(response) {
      console.log("[songs] Risposta filtri dalla sessione:", response);
      if(response && response.success && response.data && Object.keys(response.data).length > 0) {
        var filters = response.data;
        console.log("[songs] Filtri caricati dalla sessione:", filters);
        
        // Applica i filtri ai select
        var filtersApplied = false;
        var appliedFilters = []; // Traccia quali filtri sono stati applicati
        
        for (var id in filters) {
          if (filters.hasOwnProperty(id) && id !== \'undefined\') {
            var $element = $("#" + id);
            console.log("[songs] Applico filtro", id, "=", filters[id], "Elemento trovato:", $element.length);
            if ($element.length > 0) {
              if (id === "f_format") {
                // Per la multiselect format, imposta i valori come array
                if (Array.isArray(filters[id]) && filters[id].length > 0) {
                  console.log("[songs] Applico format selezionati:", filters[id]);
                  $element.find("option").each(function() {
                    var val = $(this).val();
                    if (filters[id].indexOf(val) !== -1) {
                      $(this).prop("selected", true);
                      filtersApplied = true;
                    }
                  });
                  appliedFilters.push($element);
                }
              } else {
                var filterValue = filters[id];
                console.log("[songs] Tentativo di applicare filtro", id, "valore:", filterValue, "tipo:", typeof filterValue);
                if (filterValue !== undefined && filterValue !== null && filterValue !== \'\') {
                  // Converti a stringa se necessario per il confronto
                  var filterValueStr = String(filterValue);
                  if (filterValueStr !== \'0\' && filterValueStr !== \'\') {
                    $element.val(filterValueStr);
                    filtersApplied = true;
                    appliedFilters.push($element);
                    console.log("[songs] Filtro", id, "impostato a", filterValueStr, "valore attuale:", $element.val());
                  } else {
                    console.log("[songs] Filtro", id, "ignorato (valore 0 o vuoto)");
                  }
                } else {
                  console.log("[songs] Filtro", id, "ignorato (valore undefined/null/vuoto)");
                }
              }
            } else {
              console.warn("[songs] Elemento", id, "non trovato");
            }
          }
        }
        
        if (filtersApplied) {
          // Aggiorna il display dei format dopo il caricamento
          setTimeout(function() {
            if (typeof updateFormatDisplay === \'function\') {
              updateFormatDisplay();
            }
            // Forza un trigger di change sui select per assicurarsi che reloadTable() venga chiamato
            // Questo garantisce che reloadTable() legga i valori corretti dai select
            appliedFilters.forEach(function($el) {
              $el.trigger(\'change\');
            });
            // Se non ci sono filtri con event handler, chiama direttamente reloadTable
            setTimeout(function() {
              if (typeof reloadTable === \'function\') {
                console.log("[songs] Chiamata diretta reloadTable() dopo ripristino filtri");
                reloadTable();
              }
            }, 100);
          }, 500); // Aumentato il delay per assicurarsi che i select siano aggiornati
        }
        
        filtersLoaded = true;
        filtersRestoredFromSession = true; // Segna che i filtri sono stati ripristinati
        if (typeof callback === \'function\') {
          callback(true);
        }
        return true;
      } else {
        console.log("[songs] Nessun filtro salvato nella sessione");
        filtersLoaded = true;
        filtersRestoredFromSession = false; // Nessun filtro da ripristinare
        if (typeof callback === \'function\') {
          callback(false);
        }
        return false;
      }
    },
    error: function(xhr, status, error) {
      console.error("[songs] Errore nel caricamento filtri:", error, xhr);
      filtersLoaded = true;
      if (typeof callback === \'function\') {
        callback(false);
      }
      return false;
    }
  });
}

$(document).ready(function() {
  // Carica e popola i format dall\'API nella multiselect
  $.ajax({
    url: "https://yourradio.org/api/formats?t=" + new Date().getTime(),
    method: "GET",
    dataType: "json",
    cache: false,
    success: function(response) {
      if(response.success && response.data) {
        // Popola la multiselect con tutti i format
        var $formatSelect = $("#f_format");
        $formatSelect.empty(); // Svuota la select
        
        response.data.forEach(function(format) {
          var frmtId = format.frmt_id || \'\';
          var frmtNome = (format.frmt_nome && format.frmt_nome !== \'\') ? format.frmt_nome : \'Format #\' + frmtId;
          $formatSelect.append(\'<option value="\' + frmtId + \'">\' + frmtNome + \'</option>\');
        });
        
        // Dopo aver popolato i format, carica i filtri dalla sessione
        // Usa un piccolo delay per assicurarsi che gli elementi siano nel DOM
        setTimeout(function() {
          loadFiltersFromSession(function(success) {
            if (success) {
              console.log("[songs] Filtri ripristinati con successo");
            } else {
              console.log("[songs] Nessun filtro da ripristinare o errore nel ripristino");
            }
          });
        }, 150);
      }
    },
    error: function(xhr, status, error) {
      // Errore silenzioso nel caricamento format
      // Prova comunque a caricare i filtri dalla sessione
      loadFiltersFromSession();
    }
  });
  
  var artistaColumn = 0;
  var activeColumn = 3;
  var table=$("#dataTable-'.$tableId.'").DataTable( {

    "dom": "frtip",
    
    "ajax": {
      "url": "https://yourradio.org/api/songs",
      "dataSrc": "data"
    },

    "columns": [
    { "data": "artista" },
    { "data": "titolo" },
    { "data": "anno" },
    { "data": "attivo" },
    ],

    "rowId": "id",
    "iDisplayLength": 25,
    "ordering": true,
    "columnDefs": [
    { "visible": true, "targets": artistaColumn },
    { "visible": false, "targets": activeColumn }
    ],

    "drawCallback": function ( settings ) {
      var api = this.api();
      var rows = api.rows( {page:"current"} ).nodes();
      var last=null;
      },

    "rowCallback": function( row, data ) {
      $(row).addClass("rowPingMonitor");
      $("td:eq(0)",row).addClass("toNeverHide");
      $("td:eq(0)",row).attr("id",$(row).attr("id")+"-artista");
      $("td:eq(1)",row).addClass("toNeverHide");
      $("td:eq(1)",row).attr("id",$(row).attr("id")+"-titolo");
      $("td:eq(2)",row).addClass("toNeverHide");

      if(data.attivo==1){
          $(row).addClass("pingMonitorGreen");
        }else{
          $(row).addClass("pingMonitorWhite");
        }
      
    },

    "paging":   true,

    "info":     true,

    "searching": true

  });

  $("body").on("click", "#dataTable-'.$tableId.' tbody tr", function(){
    var id=$(this).attr("id");
    $(".rowPingMonitor").css("background-color","transparent");
    $(this).css("background-color","#9e9e9e5e");
    console.log("Apro scheda "+id);
    //window.open("song-scheda.php?id="+id,"_self");
    openSong(id);
  });
  
  // Handler per il pulsante "Nuova Song"
  $("#nuovaSongBtn").on("click", function(){
    console.log("Apro nuova song");
    openSong("nuova");
  });

  // Funzione per aggiornare la visualizzazione dei format selezionati
  function updateFormatDisplay() {
    var selectedFormats = [];
    $("#f_format option:selected").each(function() {
      var formatText = $(this).text();
      if (formatText && formatText !== \'\') {
        selectedFormats.push(formatText);
      }
    });
    
    var $formatDisplay = $("#format-selected-display");
    if ($formatDisplay.length === 0) {
      // Crea il div se non esiste, posizionandolo sulla stessa riga dell\'input search
      var $filterDiv = $("#dataTable-'.$tableId.'_filter");
      if ($filterDiv.length > 0) {
        // Modifica il layout del filtro per avere il div a sinistra e l\'input a destra
        $filterDiv.css(\'display\', \'flex\');
        $filterDiv.css(\'justify-content\', \'space-between\');
        $filterDiv.css(\'align-items\', \'center\');
        $filterDiv.prepend(\'<div id="format-selected-display" style="font-weight: bold; color: white; margin-right: 10px;"></div>\');
        $formatDisplay = $("#format-selected-display");
      }
    }
    
    if (selectedFormats.length > 0) {
      $formatDisplay.text(selectedFormats.join(\', \'));
    } else {
      $formatDisplay.text(\'TUTTI I FORMAT\');
    }
  }
  
  // Inizializza il display dei format dopo che la tabella è stata creata
  setTimeout(function() {
    updateFormatDisplay();
  }, 100);
  
  $(".songFilter_select").on("change", function(){
    if ($(this).attr(\'id\') === \'f_format\') {
      updateFormatDisplay();
    }
    // Salva i filtri nella sessione quando cambiano
    // Usa un delay per evitare di salvare durante il ripristino iniziale
    setTimeout(function() {
      saveFiltersToSession();
    }, 300);
    reloadTable()
  });
  
  // Gestione specifica per la multiselect format per permettere la deselezione
  $("#f_format").on("mousedown", function(e) {
    var option = e.target;
    // Se si clicca su un\'opzione già selezionata, la deseleziona
    if (option.tagName === \'OPTION\' && option.selected) {
      // Previeni il comportamento di default e deseleziona manualmente
      e.preventDefault();
      option.selected = false;
      // Aggiorna il display
      updateFormatDisplay();
      // Triggera l\'evento change per aggiornare la tabella
      $(this).trigger(\'change\');
    }
  });

  $("#songFilter_reset").on("click", function(){
    // Reset tutti i select normali
    $(".songFilter_select").not("#f_format").prop("selectedIndex",0);
    // Forza esplicitamente la select diritti a "*" (Tutti)
    $("#f_diritti").val("*");
    // Reset la multiselect format
    $("#f_format option").prop("selected", false);
    updateFormatDisplay();
    // Rimuovi i filtri dalla sessione PHP lato applicazione
    $.ajax({
      url: "songs.php?action=clearFilters",
      method: "GET",
      dataType: "json",
      success: function(response) {
        console.log("[songs] Filtri rimossi dalla sessione");
      }
    });
    reloadTable()
  });

  function reloadTable(){
    // Gestisci i format multipli
    var formatValues = [];
    $("#f_format option:selected").each(function() {
      var val = $(this).val();
      if (val && val !== \'\' && val !== \'0\') {
        formatValues.push(val);
      }
    });
    // Se ci sono format selezionati, inviali come lista separata da virgole
    // Se non ci sono format selezionati, non inviare il parametro format (mostra tutte le songs)
    var formatParam = formatValues.length > 0 ? formatValues.join(\',\') : \'\';
    var formatQuery = formatParam !== \'\' ? \'format=\'+encodeURIComponent(formatParam)+\'&\' : \'\';

    // Gestisci diritti: se è "*" (Tutti), non inviare il parametro
    var dirittiVal = $("#f_diritti").val();
    var dirittiQuery = (dirittiVal && dirittiVal !== \'*\' && dirittiVal !== \'\') ? "&diritti="+encodeURIComponent(dirittiVal) : "";
    
    var reloadTable="https://yourradio.org/api/songs?"+formatQuery+"attivo="+$("#f_abilitate").val()+"&nazionalita="+$("#f_nazionalita").val()+"&strategia="+$("#f_strategia").val()+"&sex="+$("#f_sex").val()+"&umore="+$("#f_umore").val()+"&ritmo="+$("#f_ritmo").val()+"&energia="+$("#f_energia").val()+"&anno="+$("#f_anno").val()+"&periodo="+$("#f_periodo").val()+"&genere="+$("#f_genere").val()+dirittiQuery;

    table.ajax.url( reloadTable ).load();
  }

  
  function openSong(id){
    $(".songs-table").fadeOut( "fast", function() {
      $(".song-scheda").html("<center>...loading "+id+"...</center>");
      $(".song-scheda").fadeIn( "fast", function() {

        // Aggiungi timestamp per evitare cache
        var cacheBuster = "&t=" + new Date().getTime();
        $(".song-scheda").load("song-scheda.php?id="+id+cacheBuster, function(responseTxt, statusTxt, xhr){
          if(statusTxt == "success") {
            console.log("[songs.php] Song caricata con successo, ID:", id);
            // Verifica che il contenuto sia stato caricato correttamente
            if ($(".song-scheda").html().indexOf("scheda-song") === -1) {
              console.error("[songs.php] Il contenuto non sembra essere stato caricato correttamente");
            }
            // Forza il caricamento dei dati aggiornati dall API dopo che la pagina è stata caricata
            setTimeout(function() {
              if (typeof loadSongData === \'function\') {
                console.log("[songs.php] Chiamo loadSongData per aggiornare i dati");
                loadSongData();
              } else {
                console.log("[songs.php] loadSongData non disponibile, riprovo tra 200ms");
                setTimeout(function() {
                  if (typeof loadSongData === \'function\') {
                    loadSongData();
                  }
                }, 200);
              }
            }, 150);
            // Forza il caricamento dei format dopo che la pagina è stata caricata
            // Prova più volte perché il codice potrebbe non essere ancora eseguito
            var retryCount = 0;
            var maxRetries = 10;
            var tryLoadFormats = function() {
              retryCount++;
              console.log("[songs.php] Tentativo", retryCount, "- Verifico se loadFormats è disponibile:", typeof window.loadFormats);
              if (typeof window.loadFormats === \'function\') {
                console.log("[songs.php] loadFormats disponibile, chiamo");
                window.loadFormats();
              } else if (retryCount < maxRetries) {
                console.log("[songs.php] loadFormats non ancora disponibile, riprovo tra 100ms");
                setTimeout(tryLoadFormats, 100);
              } else {
                console.error("[songs.php] loadFormats non disponibile dopo", maxRetries, "tentativi. Provo a caricare direttamente i format.");
                // Fallback: carica direttamente i format se la funzione non è disponibile
                loadFormatsDirectly(id);
              }
            };
            setTimeout(tryLoadFormats, 50);
          }
          if(statusTxt == "error")
            alert("Error: " + xhr.status + ": " + xhr.statusText);
        });
      });
    });
  }
  
  // Funzione fallback per caricare direttamente i format se loadFormats non è disponibile
  function loadFormatsDirectly(songId) {
    console.log("[songs.php] loadFormatsDirectly chiamata per song ID:", songId);
    var $formatSelect = $("#formats_select");
    if ($formatSelect.length === 0) {
      console.log("[songs.php] Elemento formats_select non trovato, riprovo tra 100ms");
      setTimeout(function() { loadFormatsDirectly(songId); }, 100);
      return;
    }
    
    console.log("[songs.php] Chiamata API formats");
    $.ajax({
      url: "https://yourradio.org/api/formats?t=" + new Date().getTime(),
      method: "GET",
      dataType: "json",
      cache: false,
      success: function(response) {
        console.log("[songs.php] Risposta API formats:", response);
        if(response.success && response.data && response.data.length > 0) {
          console.log("[songs.php] Trovati", response.data.length, "format");
          $formatSelect.empty();
          
          response.data.forEach(function(format) {
            var frmtId = format.frmt_id || \'\';
            var frmtNome = (format.frmt_nome && format.frmt_nome !== \'\') ? format.frmt_nome : \'Format #\' + frmtId;
            $formatSelect.append(\'<option value="\' + frmtId + \'">\' + frmtNome + \'</option>\');
          });
          
          console.log("[songs.php] Format aggiunti, totale opzioni:", $formatSelect.find("option").length);
          
          // Carica i format esistenti della song
          if (songId && songId !== \'\' && songId !== \'nuova\') {
            console.log("[songs.php] Chiamata API song per recuperare format esistenti");
            $.ajax({
              url: "https://yourradio.org/api/songs/" + songId + "?t=" + new Date().getTime(),
              method: "GET",
              dataType: "json",
              cache: false,
              success: function(songResponse) {
                console.log("[songs.php] Risposta API song:", songResponse);
                if(songResponse.success && songResponse.data && songResponse.data.formats && Array.isArray(songResponse.data.formats)) {
                  var existingFormats = songResponse.data.formats;
                  console.log("[songs.php] Format esistenti della song:", existingFormats);
                  var selectedCount = 0;
                  $formatSelect.find("option").each(function() {
                    var optionId = parseInt($(this).val());
                    if (existingFormats.indexOf(optionId) !== -1) {
                      $(this).prop("selected", true);
                      selectedCount++;
                    }
                  });
                  console.log("[songs.php] Format selezionati:", selectedCount);
                  // Aggiorna input hidden
                  var selectedIds = [];
                  $formatSelect.find("option:selected").each(function() {
                    selectedIds.push(parseInt($(this).val()));
                  });
                  $("#formats").val(JSON.stringify(selectedIds));
                  console.log("[songs.php] Input hidden aggiornato:", selectedIds);
                }
              },
              error: function(xhr, status, error) {
                console.error("[songs.php] Errore nel caricamento song:", error, xhr);
              }
            });
          }
        }
      },
      error: function(xhr, status, error) {
        console.error("[songs.php] Errore nel caricamento format:", error, xhr);
      }
    });
  }
});  
';
?>
          <body class="horizontal dark">
            <div class="wrapper">
              <?php include_once('inc/menu-h.php'); ?>
              <main role="main" class="main-content">
                <div class="container-fluid">
                  <div class="row justify-content-center">
                    <div class="col-12 col-lg-10 col-xl-8">
                        <!-- Small table -->
                        <div class="col-md-12 my-4 <?=$tableId?>-table">
                          <div class="row align-items-center mb-4">
                            <div class="col">
                              <h2 class="mb-2 page-title">
                                <span class="avatar avatar-sm mt-2">
                                  <span class="fe fe-music fe-20"></span>
                                    Songs
                                </span>
                              </h2>
                            </div>
                            <div class="col-auto">
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-md-8">
                              <?=$tables?>
                            </div>
                            <div class="col-md-4">
                              <?=$filters?>

                              
                            </div>
                          </div>
                        </div>

                        <div class="col-md-12 my-4 song-scheda">
                          
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