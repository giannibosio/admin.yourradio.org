<?php
// Start the session
session_start();
include_once('load.php');
if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}


DB::init();
$songId = $_GET["id"] ?? '';
// Se l'ID è 'nuova' o vuoto, non chiamare selectSongById
if($songId === '' || $songId === 'nuova' || $songId === '0') {
    $s = [['sg_file' => '', 'sg_filesize' => '', 'sg_titolo' => '', 'sg_artista' => '', 'sg_anno' => '', 'sg_artista2' => '', 'sg_artista3' => '', 'sg_diritti' => '', 'sg_autori' => '', 'sg_casaDiscografica' => '', 'sg_etichetta' => '', 'sg_umoreId' => '', 'sg_nazione' => '', 'sg_id' => '', 'sg_attivo' => 0]];
} else {
    $s = Songs::selectSongById($songId);
if(empty($s) || !isset($s[0])) {
    $s = [['sg_file' => '', 'sg_filesize' => '', 'sg_titolo' => '', 'sg_artista' => '', 'sg_anno' => '', 'sg_artista2' => '', 'sg_artista3' => '', 'sg_diritti' => '', 'sg_autori' => '', 'sg_casaDiscografica' => '', 'sg_etichetta' => '', 'sg_umoreId' => '', 'sg_nazione' => '', 'sg_id' => '', 'sg_attivo' => 0]];
    }
}

//print_r($s);

if(!empty($s) && isset($s[0])) {
    $_SESSION['mediaplayer']['file']=$s[0]['sg_file'] ?? '';
    $_SESSION['mediaplayer']['artista']=$s[0]['sg_artista'] ?? '';
    $_SESSION['mediaplayer']['titolo']=$s[0]['sg_titolo'] ?? '';
    $_SESSION['songs']['totalPathFile']=SONG_PATH.($s[0]['sg_file'] ?? '').".mp3";
    $_SESSION['songs']['schedaId']=$_GET["id"] ?? '';
    
    // Verifica se il file audio esiste: se sg_file non è vuoto, assumiamo che il file esista sul server remoto
    // Non possiamo usare file_exists() perché il file è su un server remoto
    $sg_file = $s[0]['sg_file'] ?? '';
    if(!empty($sg_file) && $sg_file !== '') {
        $file_audio_ok = true;
    } else {
        $file_audio_ok = false;
    }
    
    $id=$s[0]['sg_id'] ?? '';
    // Se l'ID è vuoto ma abbiamo un ID dall'URL, usalo
    if(empty($id) && !empty($songId) && $songId !== 'nuova' && $songId !== '0') {
        $id = $songId;
    }
    $active=$s[0]['sg_attivo'] ?? 0;
} else {
    $_SESSION['mediaplayer']['file']='';
    $_SESSION['mediaplayer']['artista']='';
    $_SESSION['mediaplayer']['titolo']='';
    $_SESSION['songs']['totalPathFile']='';
    $_SESSION['songs']['schedaId']='';
    $file_audio_ok=false;
    $id='';
    $active=0;
}

if(!isset($id) || $id==0 || $id==''){
  $disabled=" disabled ";
  $id='nuova';
  $title='Nuovo profilo';
}else{
  $disabled="";
  $title=" ".$id;
}
if($active==1){$chbox_active="checked";$chbox_active_lab="Attivo";}else{$chbox_active="";$chbox_active_lab="Disattivato";}


?>

<div class="row align-items-center mb-4">
  <div class="col">
    <h2 class="mb-2 page-title">
      <span class="avatar avatar-sm mt-2">
        <span class="fe fe-music fe-20"></span>
        <?=$title?>
      </span>
    </h2>
  </div>
  <div class="col-auto">
    
  </div>
</div>


<div class="card-body">

  <form id="scheda-song" class="needs-validation" novalidate method="post" action="">
    <!-- hidden variables -->
    <input name="sg_id" id="sg_id" type="hidden" value="<?=$_GET["id"] ?? ''?>" >
    <input name="sg_file" id="sg_file" type="hidden" value="<?=($s[0]['sg_file'] ?? '')?>">
    <input name="sg_filesize" id="sg_filesize" type="hidden" value="<?=($s[0]['sg_filesize'] ?? '')?>">
    <div class="form-row">
      <!-- ACTIVE -->
      <div class="mb-3">
        <div class="custom-control custom-switch">
          <input type="checkbox" class="custom-control-input" <?=$chbox_active?> id="sg_attivo" name="sg_attivo" value="1">
          <label class="custom-control-label" for="sg_attivo"><?=$chbox_active_lab?></label>
        </div>
      </div>
    </div>
    <div class="form-row">
      <div class="col-md-5 mb-3">
        <label for="sg_titolo">Titolo</label>
        <input type="text" class="form-control toSanitize" id="sg_titolo" name="sg_titolo" value="<?=($s[0]['sg_titolo'] ?? '')?>" required>
      </div>
      <div class="col-md-5 mb-3">
        <label for="sg_artista">Artista</label>
        <input type="text" class="form-control toSanitize" id="sg_artista" name="sg_artista" value="<?=($s[0]['sg_artista'] ?? '')?>" required>
      </div>
      <div class="col-md-2 mb-3">
        <label for="sg_anno">Anno</label>
        <select class="form-control" name="sg_anno" id="sg_anno" >
          <?php 
          echo '<option value="0"></option>';
          for ($a=2021;$a>1945;$a=$a-1){
            if(isset($s[0]['sg_anno']) && $s[0]['sg_anno']==$a){$selected = "selected";}else{$selected = "";}
            echo '<option value="'.$a.'" '.$selected.'>'.$a.'</option>';
          }?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="col-md-5 mb-3">
        <label for="validationCustom3">Artista 2</label>
        <input type="text" class="form-control toSanitize" id="sg_artista2" name="sg_artista2" value="<?=($s[0]['sg_artista2'] ?? '')?>" required>
      </div>
      <div class="col-md-5 mb-3">
        <label for="validationCustom3">Artista 3</label>
        <input type="text" class="form-control toSanitize" id="sg_artista3" name="sg_artista3" value="<?=($s[0]['sg_artista3'] ?? '')?>" required>
      </div>
      <div class="col-md-2 mb-3">
        <?php echo createSelectForSchedaSong2("diritti","sg_diritti",$s[0]['sg_diritti'] ?? '');?>
      </div>
    </div>
    <div class="form-row">
      <div class="col-md-4 mb-3">
        <label for="validationCustom3">Autori</label>
        <input type="text" class="form-control toSanitize" id="sg_autori" name="sg_autori" value="<?=($s[0]['sg_autori'] ?? '')?>" required>
      </div>
      <div class="col-md-4 mb-3">
        <label for="validationCustom3">Casa Discografica</label>
        <input type="text" class="form-control toSanitize" id="sg_casaDiscografica" name="sg_casaDiscografica" value="<?=($s[0]['sg_casaDiscografica'] ?? '')?>" required>
      </div>
      <div class="col-md-4 mb-3">
        <label for="validationCustom3">Etichetta</label>
        <input type="text" class="form-control toSanitize" id="sg_etichetta" name="sg_etichetta" value="<?=($s[0]['sg_etichetta'] ?? '')?>" required>
      </div>
    </div>
    <div class="form-row">
      <div class="col-md-3 mb-3"> 
        <?php echo createSelectForSchedaSong2("umore","sg_umoreId",$s[0]['sg_umoreId'] ?? '');?>
      </div>
      <div class="col-md-3 mb-3"> 
        <?php echo createSelectForSchedaSong2("nazionalità","sg_nazione",$s[0]['sg_nazione'] ?? '');?>
      </div>
      <div class="col-md-3 mb-3"> 
        <?php echo createSelectForSchedaSong2("sesso","sg_sex",$s[0]['sg_sex'] ?? '');?>
      </div>
      <div class="col-md-3 mb-3"> 
        <?php echo createSelectForSchedaSong2("energia","sg_energia",$s[0]['sg_energia'] ?? '');?>
      </div>
    </div>
    <div class="form-row">
      <div class="col-md-3 mb-3"> 
        <?php echo createSelectForSchedaSong2("ritmo","sg_ritmoId",$s[0]['sg_ritmoId'] ?? '');?>
      </div>
      <div class="col-md-3 mb-3"> 
        <?php echo createSelectForSchedaSong2("periodo","sg_periodoId",$s[0]['sg_periodoId'] ?? '');?>
      </div>
      <div class="col-md-3 mb-3"> 
        <?php echo createSelectForSchedaSong2("genere","sg_genereId",$s[0]['sg_genereId'] ?? '');?>
      </div>
      <div class="col-md-3 mb-3"> 
        <?php echo createSelectForSchedaSong2("strategia","sg_strategia",$s[0]['sg_strategia'] ?? '');?>
      </div>
    </div>
    <div class="form-row">
      <div class="col-md-12 mb-3">
        <label for="formats_select">Format</label>
        <select class="form-control" id="formats_select" name="formats_select[]" multiple size="5">
          <!-- I format verranno caricati dinamicamente dall'API -->
        </select>
        <small class="form-text text-muted">Clicca per selezionare/deselezionare. Tieni premuto Ctrl (o Cmd su Mac) per aggiungere/togliere più format</small>
        <input type="hidden" id="formats" name="formats" value="">
      </div>
    </div>

    <div class="form-row" id="form-row-alert-file" style="<?php echo ($file_audio_ok) ? 'display:none;' : ''?>">
      <div class="col-md-12 mb-3"> 
        <div class="alert alert-danger" role="alert">
          <span class="fe fe-minus-circle fe-16 mr-2"></span> Manca il file audio! </div>
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
      <button type="button" title="torna alla lista" class="btn btn-outline-success chiudiSchedaSong"><span class="fe fe-list fe-16"></span></button>
      <span class="fe fe-tool fe-16"> </span>
      
      <button type="button" title="SALVA" class="btn btn-outline-danger" data-toggle="modal" data-target="#updateModal"><span class="fe fe-save fe-16"></span></button>
      
      <button title="CANCELLA" type="button" class="btn btn-outline-danger" id="btnDelete" data-toggle="modal" data-target="#verticalModal" style="display:none;"><span class="fe fe-trash fe-16"></span></button>
      
      <button title="UPLOAD" type="button" class="btn btn-outline-danger" id="btnUpload" data-toggle="modal" data-target="#uploadModal" style="display:none;"><span class="fe fe-upload fe-16"></span></button>

      
      <button style="<?php echo (!$file_audio_ok) ? 'display:none;' : ''?>" title="DOWNLOAD file" type="button" class="btn btn-outline-danger" id="downloadFile"><span class="fe fe-play fe-16"></span></button>


    </div>

  </form>

  <!-- modal Salva -->
  <div class="modal fade" id="updateModal" tabindex="-1" role="dialog" aria-labelledby="updateModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="verticalModalTitle">Salva modifiche</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">Vuoi salvare le modifiche ?</div>
        <div class="modal-footer">
          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
          <button class="btn mb-2 btn-danger" id="updateSong">Salva</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Cancella -->
  <div class="modal fade" id="verticalModal" tabindex="-1" role="dialog" aria-labelledby="verticalModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="verticalModalTitle">Cancella Song</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">Eliminare definitivamente <?=strtoupper($s[0]['sg_artista'] ?? '')?> - <?=strtoupper($s[0]['sg_titolo'] ?? '')?>?</div>
        <div class="modal-footer">
          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
          <button class="btn mb-2 btn-danger" id="deleteSong" data-dismiss="modal">Cancella</button>
        </div>
      </div>
    </div>
  </div>

  <!-- modal upload -->
  <div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="varyModalLabel" style="display: none;" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="varyModalLabel">Upload File</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <form id="uploadFileForm" enctype="multipart/form-data">
            <div class="form-group">
              <div class="custom-file">
                <input type="file" id="nameFileInput" name="nameFileInput" class="form-control-file">
              </div>
            </div>
          
            <div class="modal-footer">
              <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
              <button type="button" class="btn mb-2 btn-primary" data-dismiss="modal" id="uploadFile">Carica File</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>



</div> <!-- /.card-body -->

<script>

// Definisci immediatamente loadFormats come funzione globale per essere disponibile quando la pagina viene caricata dinamicamente
window.loadFormats = function() {
  console.log("[loadFormats] Funzione chiamata");
  
  // Verifica che l'elemento esista
  var $formatSelect = $("#formats_select");
  console.log("[loadFormats] Elemento formats_select trovato:", $formatSelect.length);
  
  if ($formatSelect.length === 0) {
    console.log("[loadFormats] Elemento non trovato, riprovo tra 100ms");
    // Se l'elemento non esiste, riprova dopo un breve delay
    setTimeout(function() {
      window.loadFormats();
    }, 100);
    return;
  }
  
  console.log("[loadFormats] Chiamata API formats");
  // Carica i format dall'API e popola la multiselect
  $.ajax({
    url: "https://yourradio.org/api/formats?t=" + new Date().getTime(),
    method: "GET",
    dataType: "json",
    cache: false,
    success: function(response) {
      console.log("[loadFormats] Risposta API formats:", response);
      if(response.success && response.data && response.data.length > 0) {
        console.log("[loadFormats] Trovati", response.data.length, "format");
        $formatSelect.empty();
        
        response.data.forEach(function(format) {
          var frmtId = format.frmt_id || '';
          var frmtNome = (format.frmt_nome && format.frmt_nome !== '') ? format.frmt_nome : 'Format #' + frmtId;
          $formatSelect.append('<option value="' + frmtId + '">' + frmtNome + '</option>');
        });
        
        console.log("[loadFormats] Format aggiunti alla select, totale opzioni:", $formatSelect.find("option").length);
        
        // Carica i format esistenti della song se è una song esistente
        // Leggi l'ID dal campo del form o dall'URL
        var songId = $("#sg_id").val();
        if (!songId || songId === '' || songId === 'nuova') {
          var urlParams = new URLSearchParams(window.location.search);
          songId = urlParams.get('id');
        }
        console.log("[loadFormats] Song ID:", songId);
        if (songId && songId !== '' && songId !== 'nuova') {
          console.log("[loadFormats] Chiamata API song per recuperare format esistenti");
          $.ajax({
            url: "https://yourradio.org/api/songs/" + songId + "?t=" + new Date().getTime(),
            method: "GET",
            dataType: "json",
            cache: false,
            success: function(songResponse) {
              console.log("[loadFormats] Risposta API song:", songResponse);
              if(songResponse.success && songResponse.data && songResponse.data.formats && Array.isArray(songResponse.data.formats)) {
                var existingFormats = songResponse.data.formats;
                console.log("[loadFormats] Format esistenti della song:", existingFormats);
                var selectedCount = 0;
                $formatSelect.find("option").each(function() {
                  var optionId = parseInt($(this).val());
                  if (existingFormats.indexOf(optionId) !== -1) {
                    $(this).prop("selected", true);
                    selectedCount++;
                  }
                });
                console.log("[loadFormats] Format selezionati:", selectedCount);
                updateFormats();
              } else {
                console.log("[loadFormats] Nessun format trovato nella risposta song o formato non valido");
              }
            },
            error: function(xhr, status, error) {
              console.error("[loadFormats] Errore nel caricamento song:", error, xhr);
            }
          });
        } else {
          console.log("[loadFormats] Nuova song, nessun format da selezionare");
        }
      } else {
        console.warn("[loadFormats] Nessun format nella risposta API o risposta non valida");
      }
    },
    error: function(xhr, status, error) {
      console.error("[loadFormats] Errore nel caricamento format:", error, xhr);
    }
  });
};

function clean_input(string) {
  const map = {
      '&': 'AND',
      '"': "`",
      "'": "`",
  };
  const reg = /[&'"/]/ig;
  return string.replace(reg, (match)=>(map[match]));
}

$( ".toSanitize" ).change(function() {
  $(this).val(clean_input($(this).val()));
});

$("#downloadFile").on("click", function () {  
  var filename = $("#sg_file").val() || "<?=($s[0]['sg_file'] ?? '')?>";
  if (filename) {
    var audioUrl = "https://yourradio.org/player/song/" + filename + ".mp3";
    // Mostra il player audio nella pagina
    var $audioPlayer = $("#audioPlayer");
    var $playerRow = $("#form-row-audio-player");
    
    $audioPlayer.attr("src", audioUrl);
    $playerRow.fadeIn("fast");
    // Avvia la riproduzione
    $audioPlayer[0].play().catch(function(error) {
      console.log("Errore nella riproduzione:", error);
    });
  }
});

// Chiudi il player audio
$("#closeAudioPlayer").on("click", function() {
  var $audioPlayer = $("#audioPlayer");
  $audioPlayer[0].pause();
  $audioPlayer.attr("src", "");
  $("#form-row-audio-player").fadeOut("fast");
});

$("#uploadFile").on("click", function (e) {
    e.preventDefault();
    // Leggi l'ID dal campo del form o dall'URL
    var songId = $("#sg_id").val();
    if (!songId || songId === '' || songId === 'nuova') {
      var urlParams = new URLSearchParams(window.location.search);
      songId = urlParams.get('id');
      if (songId && songId !== 'nuova' && songId !== '') {
        $("#sg_id").val(songId); // Aggiorna il campo nascosto
      }
    }
    
    if (!songId || songId === '' || songId === 'nuova') {
      alert("Devi prima salvare la song prima di caricare il file!");
      return false;
    }
    
    var fileInput = $('#nameFileInput')[0];
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
      alert("Seleziona un file da caricare!");
      return false;
    }
    
    var fdata = new FormData();
    fdata.append('file', fileInput.files[0]);
    
    // Disabilita il bottone durante il caricamento
    $("#uploadFile").prop("disabled", true).text("Caricamento...");
    
    $.ajax({
        url: "https://yourradio.org/api/songs/" + songId + "/upload",
        type: "POST",
        data: fdata,
        processData: false,
        contentType: false,
        success: function (response, status, jqxhr) {
          console.log("[uploadFile] Risposta:", response);
          if(response.success) {
            // Aggiorna il campo sg_file nel form
            if(response.data && response.data.filename) {
              var filename = response.data.filename.replace(/\.[^/.]+$/, ""); // Rimuovi estensione
              $("#sg_file").val(filename);
            }
            // Mostra il bottone download e nascondi l'alert
          $("#downloadFile").css("display","");
          $("#form-row-alert-file").css("display","none");
            // Chiudi il modal
            $("#uploadModal").modal("hide");
            alert("File caricato con successo!");
          } else {
            alert("Errore: " + (response.message || "Errore sconosciuto"));
          }
          // Riabilita il bottone
          $("#uploadFile").prop("disabled", false).text("Carica File");
        },
        error: function (jqxhr, status, errorMessage) {
          console.error("[uploadFile] Errore:", errorMessage, jqxhr);
          var errorMsg = "Errore durante il caricamento del file.";
          if (jqxhr.responseJSON && jqxhr.responseJSON.error && jqxhr.responseJSON.error.message) {
            errorMsg = jqxhr.responseJSON.error.message;
          } else if (jqxhr.responseText) {
            try {
              var errorData = JSON.parse(jqxhr.responseText);
              if (errorData.error && errorData.error.message) {
                errorMsg = errorData.error.message;
              }
            } catch (e) {
              // Se non è JSON, usa il testo della risposta
              errorMsg = jqxhr.responseText || errorMessage;
            }
          }
          alert("Errore: " + errorMsg);
          // Riabilita il bottone
          $("#uploadFile").prop("disabled", false).text("Carica File");
        }
    });
    
    return false;
});
  
  $(".chiudiSchedaSong").on("click", function(){
    // Ferma l'audio quando si esce dalla pagina
    var $audioPlayer = $("#audioPlayer");
    if ($audioPlayer.length > 0 && $audioPlayer[0]) {
      $audioPlayer[0].pause();
      $audioPlayer[0].currentTime = 0;
      $audioPlayer.attr("src", "");
    }
    // Nascondi il player
    $("#form-row-audio-player").hide();
    
    $(".songs-table").fadeIn( "fast", function() {
      $(".song-scheda").fadeOut( "fast");
    });  
  });
  $( "#deleteSong" ).click(function() {
    $("#formAction").val("deleteSong");
    $.ajax( {
      url: 'https://yourradio.org/api/songs/<?=($s[0]['sg_id'] ?? '')?>',
      method: 'DELETE',
      success: function (response, status, jqxhr) {
        console.log(response);
        if(response.success) {
          $("#<?=($s[0]['sg_id'] ?? '')?>").css("display", "none");
          $(".chiudiSchedaSong").click();
        }
      },
      error: function (jqxhr, status, errorMessage) {
        console.log("ERROR "+errorMessage);
      }
    });
  });
  
  // Funzione per aggiornare l'input hidden con i format selezionati
  function updateFormats() {
    var selectedIds = [];
    $("#formats_select option:selected").each(function() {
      selectedIds.push(parseInt($(this).val()));
    });
    $("#formats").val(JSON.stringify(selectedIds));
    console.log("[updateFormats] Format selezionati aggiornati:", selectedIds);
  }
  
  // Carica i format quando il documento è pronto o quando la pagina viene caricata dinamicamente
  (function() {
    console.log("[song-scheda] Inizializzazione caricamento format");
    console.log("[song-scheda] jQuery disponibile:", typeof jQuery !== 'undefined');
    
    if (typeof jQuery !== 'undefined') {
      $(document).ready(function() {
        console.log("[song-scheda] Document ready, chiamo loadFormats");
        if (typeof window.loadFormats === 'function') {
          window.loadFormats();
        } else {
          console.error("[song-scheda] loadFormats non è una funzione!");
        }
      });
    } else {
      console.log("[song-scheda] jQuery non disponibile, aspetto...");
      // Se jQuery non è ancora caricato, aspetta
      var checkJQuery = setInterval(function() {
        if (typeof jQuery !== 'undefined') {
          console.log("[song-scheda] jQuery ora disponibile");
          clearInterval(checkJQuery);
          $(document).ready(function() {
            console.log("[song-scheda] Document ready (dopo attesa jQuery), chiamo loadFormats");
            if (typeof window.loadFormats === 'function') {
              window.loadFormats();
            } else {
              console.error("[song-scheda] loadFormats non è una funzione!");
            }
          });
        }
      }, 50);
      // Timeout di sicurezza dopo 2 secondi
      setTimeout(function() {
        clearInterval(checkJQuery);
        if (typeof jQuery !== 'undefined') {
          console.log("[song-scheda] Timeout scaduto, jQuery disponibile, chiamo loadFormats");
          $(document).ready(function() {
            if (typeof window.loadFormats === 'function') {
              window.loadFormats();
            } else {
              console.error("[song-scheda] loadFormats non è una funzione!");
            }
          });
        } else {
          console.error("[song-scheda] Timeout scaduto, jQuery ancora non disponibile!");
        }
      }, 2000);
    }
  })();
  
  // Aggiorna l'input hidden quando cambiano le selezioni
  $(document).on("change", "#formats_select", function() {
    updateFormats();
  });
  
  // Gestione deselezione con click
  $(document).on("mousedown", "#formats_select", function(e) {
    var option = e.target;
    if (option.tagName === 'OPTION' && option.selected) {
      e.preventDefault();
      option.selected = false;
      updateFormats();
      $(this).trigger('change');
    }
  });
  
  // Funzione per caricare i dati della song dall'API e aggiornare il form
  function loadSongData() {
    // Leggi l'ID dal campo del form o dall'URL
    var songId = $("#sg_id").val();
    if (!songId || songId === '' || songId === 'nuova') {
      // Prova a leggere dall'URL
      var urlParams = new URLSearchParams(window.location.search);
      songId = urlParams.get('id');
      if (songId && songId !== 'nuova' && songId !== '') {
        $("#sg_id").val(songId); // Aggiorna il campo nascosto
      } else {
        console.log("[loadSongData] Nuova song, nessun dato da caricare");
        return;
      }
    }
    
    console.log("[loadSongData] Caricamento dati song ID:", songId);
    $.ajax({
      url: "https://yourradio.org/api/songs/" + songId + "?t=" + new Date().getTime(),
      method: "GET",
      dataType: "json",
      cache: false,
      success: function(response) {
        console.log("[loadSongData] Dati ricevuti:", response);
        if(response.success && response.data) {
          var data = response.data;
          // Aggiorna l'ID nel campo nascosto se non è già impostato
          if(data.sg_id) {
            $("#sg_id").val(data.sg_id);
            // Aggiorna la visibilità dei pulsanti ora che abbiamo un ID valido
            $("#btnDelete").show();
            $("#btnUpload").show();
          }
          // Aggiorna tutti i campi del form con i dati aggiornati
          if(data.sg_artista !== undefined) $("#sg_artista").val(data.sg_artista);
          if(data.sg_titolo !== undefined) $("#sg_titolo").val(data.sg_titolo);
          if(data.sg_anno !== undefined) $("#sg_anno").val(data.sg_anno);
          if(data.sg_artista2 !== undefined) $("#sg_artista2").val(data.sg_artista2);
          if(data.sg_artista3 !== undefined) $("#sg_artista3").val(data.sg_artista3);
          if(data.sg_diritti !== undefined) $("#sg_diritti").val(data.sg_diritti);
          if(data.sg_autori !== undefined) $("#sg_autori").val(data.sg_autori);
          if(data.sg_casaDiscografica !== undefined) $("#sg_casaDiscografica").val(data.sg_casaDiscografica);
          if(data.sg_etichetta !== undefined) $("#sg_etichetta").val(data.sg_etichetta);
          if(data.sg_umoreId !== undefined) $("#sg_umoreId").val(data.sg_umoreId);
          if(data.sg_nazione !== undefined) $("#sg_nazione").val(data.sg_nazione);
          if(data.sg_file !== undefined) {
            $("#sg_file").val(data.sg_file);
            // Aggiorna lo stato del file audio
            if(data.sg_file && data.sg_file !== '' && data.sg_file !== '0') {
              // File audio presente
              $("#form-row-alert-file").hide();
              $("#downloadFile").show();
            } else {
              // File audio mancante
              $("#form-row-alert-file").show();
              $("#downloadFile").hide();
            }
          }
          if(data.sg_filesize !== undefined) $("#sg_filesize").val(data.sg_filesize);
          if(data.sg_attivo !== undefined) {
            if(data.sg_attivo == 1) {
              $("#sg_attivo").prop("checked", true);
            } else {
              $("#sg_attivo").prop("checked", false);
            }
          }
          // Aggiorna il titolo della pagina
          if(data.sg_id) {
            var pageTitle = " " + data.sg_id + ".mp3";
            $(".page-title").html('<span class="avatar avatar-sm mt-2"><span class="fe fe-music fe-20"></span>' + pageTitle + '</span>');
            document.title = pageTitle;
          }
          // Aggiorna anche i format se presenti
          if(data.formats && Array.isArray(data.formats)) {
            $("#formats_select option").each(function() {
              var optionId = parseInt($(this).val());
              if (data.formats.indexOf(optionId) !== -1) {
                $(this).prop("selected", true);
              } else {
                $(this).prop("selected", false);
              }
            });
            updateFormats();
          }
          console.log("[loadSongData] Form aggiornato con i dati più recenti");
        }
      },
      error: function(xhr, status, error) {
        console.error("[loadSongData] Errore nel caricamento dati:", error, xhr);
      }
    });
  }
  
  // Test immediato per verificare che jQuery sia disponibile
  console.log("[song-scheda] jQuery disponibile:", typeof jQuery !== 'undefined');
  console.log("[song-scheda] Document ready state:", document.readyState);
  
  // Funzione per aggiornare la visibilità dei pulsanti in base all'ID
  function updateButtonVisibility() {
    var songId = $("#sg_id").val();
    if (!songId || songId === '' || songId === 'nuova') {
      // Prova a leggere dall'URL
      var urlParams = new URLSearchParams(window.location.search);
      songId = urlParams.get('id');
    }
    
    if (songId && songId !== '' && songId !== 'nuova') {
      // Mostra i pulsanti CANCELLA e UPLOAD
      $("#btnDelete").show();
      $("#btnUpload").show();
    } else {
      // Nascondi i pulsanti CANCELLA e UPLOAD
      $("#btnDelete").hide();
      $("#btnUpload").hide();
    }
  }
  
  // Carica i dati quando il documento è pronto
  // Se la pagina viene caricata dinamicamente, i dati PHP potrebbero essere in cache
  // Quindi ricarichiamo sempre i dati dall'API per assicurarci di avere i dati più recenti
  if (typeof jQuery !== 'undefined') {
    $(document).ready(function() {
      // Assicurati che il campo sg_id sia popolato correttamente dall'URL
      var urlParams = new URLSearchParams(window.location.search);
      var urlId = urlParams.get('id');
      if (urlId && urlId !== 'nuova' && urlId !== '') {
        var currentId = $("#sg_id").val();
        if (!currentId || currentId === '' || currentId === 'nuova') {
          $("#sg_id").val(urlId);
        }
      }
      
      // Aggiorna la visibilità dei pulsanti all'apertura della pagina
      updateButtonVisibility();
      
      // Carica sempre i dati dall'API se c'è un ID valido
      var songId = $("#sg_id").val();
      if (songId && songId !== '' && songId !== 'nuova') {
        console.log("[song-scheda] Caricamento dati all'apertura della pagina, ID:", songId);
        setTimeout(function() {
          if (typeof loadSongData === 'function') {
            loadSongData();
            // Aggiorna la visibilità dei pulsanti dopo il caricamento dei dati
            setTimeout(updateButtonVisibility, 200);
          }
        }, 100);
      }
    });
  }
  
  // Handler diretto come fallback
  function handleUpdateSong(e) {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    console.log("[updateSong] Handler chiamato - Bottone cliccato");
    
    // Leggi l'ID dal campo del form o dall'URL
    var songId = $("#sg_id").val();
    if (!songId || songId === '' || songId === 'nuova') {
      var urlParams = new URLSearchParams(window.location.search);
      songId = urlParams.get('id');
      if (songId && songId !== 'nuova' && songId !== '') {
        $("#sg_id").val(songId); // Aggiorna il campo nascosto
      }
    }
    var isNewSong = (!songId || songId === '' || songId === 'nuova');
    console.log("[updateSong] Song ID:", songId, "Is New:", isNewSong);
    
    $("#formAction").val(isNewSong ? "createSong" : "updateSong");
    var formData = {};
    var $form = $("#scheda-song");
    
    if ($form.length === 0) {
      console.error("[updateSong] Form scheda-song non trovato");
      alert("Errore: Form non trovato");
      return false;
    }
    
    console.log("[updateSong] Serializzazione form...");
    $form.serializeArray().forEach(function(item) {
      // Salta i format dalla serializzazione standard, li gestiamo dopo
      if (item.name === 'formats_select[]' || item.name === 'sg_id') {
        return;
      }
      formData[item.name] = item.value;
    });
    
    // Aggiungi i format selezionati come array (sempre, anche se vuoto)
    var formatsArray = [];
    $("#formats_select option:selected").each(function() {
      var formatId = parseInt($(this).val());
      if (formatId > 0) {
        formatsArray.push(formatId);
      }
    });
    formData.formats = formatsArray;
    
    console.log("[updateSong] Dati da inviare:", formData);
    console.log("[updateSong] Format selezionati:", formatsArray);
    
    // Disabilita il bottone durante la richiesta
    $("#updateSong").prop("disabled", true).text("Salvataggio...");
    
    var apiUrl = isNewSong ? 'https://yourradio.org/api/songs' : 'https://yourradio.org/api/songs/' + songId;
    var apiMethod = isNewSong ? 'POST' : 'PUT';
    console.log("[updateSong] URL API:", apiUrl, "Method:", apiMethod);
    
    $.ajax( {
      url: apiUrl + '?t=' + new Date().getTime(),
      type: apiMethod,
      contentType: 'application/json',
      cache: false,
      data: JSON.stringify(formData),
      success: function (response, status, jqxhr) {
        console.log("[updateSong] Risposta API:", response);
        if(response.success) {
          var finalSongId = songId;
          var wasNewSong = isNewSong;
          
          if(isNewSong && response.data) {
            // Nuova song creata, aggiorna l'ID
            var newId = null;
            if (response.data.sg_id) {
              newId = response.data.sg_id;
            } else if (response.data.id) {
              newId = response.data.id;
            }
            
            if (newId) {
              console.log("[updateSong] Nuova song creata con ID:", newId);
              $("#sg_id").val(newId);
              // Mostra i pulsanti CANCELLA e UPLOAD ora che abbiamo un ID valido
              $("#btnDelete").show();
              $("#btnUpload").show();
              // Aggiorna l'URL e ricarica la pagina con il nuovo ID per mostrare tutti i dati
              window.location.href = "song-scheda.php?id=" + newId + "&t=" + new Date().getTime();
              return; // Esci dalla funzione perché la pagina verrà ricaricata
            }
          }
          
          if(!wasNewSong && finalSongId) {
            $( "#" + finalSongId + "-artista" ).html($( "#sg_artista" ).val());
            $( "#" + finalSongId + "-titolo" ).html($( "#sg_titolo" ).val());
          }
          
          console.log("[updateSong] Song " + (wasNewSong ? "creata" : "aggiornata") + " con successo");
          // Chiudi il modal dopo il successo
          $("#updateModal").modal("hide");
          
          // Ricarica i dati della song dall'API per assicurarsi che il form mostri i dati aggiornati
          if(finalSongId && !wasNewSong) {
            console.log("[updateSong] Ricarico i dati della song dall'API...");
            $.ajax({
              url: "https://yourradio.org/api/songs/" + finalSongId + "?t=" + new Date().getTime(),
              method: "GET",
              dataType: "json",
              cache: false,
              success: function(songResponse) {
                console.log("[updateSong] Dati ricaricati:", songResponse);
                if(songResponse.success && songResponse.data) {
                  var data = songResponse.data;
                  // Aggiorna tutti i campi del form con i dati aggiornati
                  if(data.sg_artista !== undefined) $("#sg_artista").val(data.sg_artista);
                  if(data.sg_titolo !== undefined) $("#sg_titolo").val(data.sg_titolo);
                  if(data.sg_anno !== undefined) $("#sg_anno").val(data.sg_anno);
                  if(data.sg_artista2 !== undefined) $("#sg_artista2").val(data.sg_artista2);
                  if(data.sg_artista3 !== undefined) $("#sg_artista3").val(data.sg_artista3);
                  if(data.sg_diritti !== undefined) $("#sg_diritti").val(data.sg_diritti);
                  if(data.sg_autori !== undefined) $("#sg_autori").val(data.sg_autori);
                  if(data.sg_casaDiscografica !== undefined) $("#sg_casaDiscografica").val(data.sg_casaDiscografica);
                  if(data.sg_etichetta !== undefined) $("#sg_etichetta").val(data.sg_etichetta);
                  if(data.sg_umoreId !== undefined) $("#sg_umoreId").val(data.sg_umoreId);
                  if(data.sg_nazione !== undefined) $("#sg_nazione").val(data.sg_nazione);
                  if(data.sg_attivo !== undefined) {
                    if(data.sg_attivo == 1) {
                      $("#sg_attivo").prop("checked", true);
                    } else {
                      $("#sg_attivo").prop("checked", false);
                    }
                  }
                  // Aggiorna anche i format se presenti
                  if(data.formats && Array.isArray(data.formats)) {
                    $("#formats_select option").each(function() {
                      var optionId = parseInt($(this).val());
                      if (data.formats.indexOf(optionId) !== -1) {
                        $(this).prop("selected", true);
                      } else {
                        $(this).prop("selected", false);
                      }
                    });
                    updateFormats();
                  }
                  console.log("[updateSong] Form aggiornato con i dati ricaricati");
                }
              },
              error: function(xhr, status, error) {
                console.error("[updateSong] Errore nel ricaricamento dati:", error);
              }
            });
          }
        } else {
          console.error("[updateSong] Risposta non di successo:", response);
          alert("Errore durante il salvataggio: " + (response.message || "Errore sconosciuto"));
        }
        // Riabilita il bottone
        $("#updateSong").prop("disabled", false).text("Salva");
      },
      error: function (jqxhr, status, errorMessage) {
        console.error("[updateSong] Errore:", errorMessage, jqxhr);
        console.error("[updateSong] Dettagli errore:", jqxhr.responseText);
        alert("Errore durante il salvataggio: " + errorMessage);
        // In caso di errore, chiudi comunque il modal
        $("#updateModal").modal("hide");
        // Riabilita il bottone
        $("#updateSong").prop("disabled", false).text("Salva");
      }
    });
    
    return false;
  }
  
  // Usa event delegation per gestire il click sul bottone anche se viene caricato dinamicamente
  $(document).on("click", "#updateSong", handleUpdateSong);
  
  // Aggiungi anche un handler diretto quando il documento è pronto
  $(document).ready(function() {
    console.log("[song-scheda] Document ready - Registro handler diretto per updateSong");
    $("#updateSong").off("click").on("click", handleUpdateSong);
    
    // Test: verifica che il bottone esista
    if ($("#updateSong").length > 0) {
      console.log("[song-scheda] Bottone updateSong trovato");
    } else {
      console.warn("[song-scheda] Bottone updateSong NON trovato al document ready");
    }
  });
  


</script>





<?php
exit;

/////////////////////////////////////////////////////////////////////////


if(isset($_POST["formAction"]) && $_POST["formAction"]!=''){

  if($_POST["formAction"]=="back"){
    header("location:songs.php");
  }

  if($_POST["formAction"]=="update"){
    if(isset($_POST["login"]) && $_POST["login"]!=''){
      $_GET["id"]=Utenti::updateUtente($_POST);
      $_POST["formAction"]='';
    }else{
      echo "Login non valido !";
    }

  }
  if($_POST["formAction"]=="delete"){
    $_POST["formAction"]='';
    $res=Utenti::deleteUtente($_GET["id"]);
    header("location:profili.php");
  }

}
include_once('inc/head.php');

$u=Song::selectSongByID($_GET["id"]);

$active=$u[0]['active'];
$id=$u[0]['id'];
$login=$u[0]['login'];
$nome=$u[0]['nome'];
$indirizzo=$u[0]['indirizzo'];
$citta=$u[0]['citta'];
$pro=$u[0]['pro'];
$cap=$u[0]['cap'];
$tel=$u[0]['tel'];
$mail=$u[0]['mail'];
$password=$u[0]['password'];
$permesso=$u[0]['permesso'];
$gruppo=$u[0]['gr_nome'];
$rete_id=$u[0]['rete_id']; if(!isset($rete_id)){$rete_id=0;}
$data_creazione=$u[0]['dataCreazione'];
$ultimo_accesso=$u[0]['ultimoAccesso'];
$note=$u[0]['note'];



if(!isset($id) || $id=0 || $id=''){
  $disabled=" disabled ";
  $id='nuova';
  $title='Nuovo profilo';
}else{
  $disabled="";
  $title=$nome;
  if($rete_id>0){$type="Account";}else{$type="Admin";}
}


if($active==1){$chbox_active="checked";$chbox_active_lab="Attivo";}else{$chbox_active="";$chbox_active_lab="Disattivato";}

$script='
<script>
$( "#update" ).click(function() {
    $("#formAction").val("update");
  });
  $( "#delete" ).click(function() {
    $("#formAction").val("delete");
    console.log("cancella scheda ");
    $( "#scheda-profilo" ).submit();
  });
  $( ".back-lista" ).click(function() {
    $("#formAction").val("back");
    console.log("torna alla lista");
    window.open("profili.php","_self");
  });
  $( "#changePassword" ).click(function() {
    $("#formAction").val("changepassword");
    var newPass=$("#newPassword").val();
    if(newPass==""){
      console.log("nessuna password inserita");
      return;
    }
    $.ajax({
          method: "PUT",
          url: "https://yourradio.org/api/utenti/'.$_GET["id"].'/password",
          contentType: "application/json",
          data: JSON.stringify({newpass: newPass}),
          success: function(res){
            if(res.success) {
              $("#password").val(res.data.password_md5);
              console.log("nuova password cambiata");
              $(".alert").hide();
            }
          },
          error: function(stato){
            alert("Purtroppo non ho potuto cambiare la password... qualcosa è andato storto");
          }
        });
  });

  if($("#password").val()=="" && $("#login").val()!="" ){
    $("#msg_alert").html("Ricorda di creare la password!");
    $(".alert").show();
  }

</script>
';
?>

<body class="horizontal dark">
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

              <div class="my-4">

                <div class="row align-items-center mb-4">
                  <div class="col">
                    <h2 class="mb-2 page-title">
                      <span class="avatar avatar-sm mt-2">
                        <span class="fe fe-music fe-20"></span>
                        Song
                      </span>
                    </h2>
                  </div>
                  <div class="col-auto">
                    <button title="" type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#newGroupAddModal"><span class="fe fe-plus fe-16"></span> Crea nuovo gruppo</button>
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
                        <input class="form-control input-phoneus" id="custom-phone" maxlength="14" name="tel"value="<?=$tel?>" required>
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
                    <!-- Note -->
                    <div class="form-row">
                      <div class="col-md-12 mb-3">
                        <label for="note">Note</label>
                        <textarea class="form-control" id="note" name="note" placeholder="Scrivi nota" ="" rows="3"><?=$note?></textarea>
                        <div class="invalid-feedback"> Please enter a message in the textarea. </div>
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
                      <input name="permesso" id="permesso" type="hidden" value="<?=$permesso?>" >
                      <input name="dataCreazione" id="dataCreazione" type="hidden" value="<?=$data_creazione?>" >
                      <input name="formAction" id="formAction" type="hidden" value="<?=isset($_POST["formAction"]) ? $_POST["formAction"] : ''?>" >
                      <i><h10>scheda creata il <?=$data_creazione?><br>
                        ultimo accesso il <?=$ultimo_accesso?></h10></i>
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