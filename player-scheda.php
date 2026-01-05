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
if(!isset($_GET["id"]) || $_GET["id"]==0 || $_GET["id"]==''){
  $disabled=" disabled ";
  $id='nuova';
  $title='Nuovo Player';
  $p = [];
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
$userIdForPassword = isset($_GET['id']) ? intval($_GET['id']) : 0;
$script = '
<script>
$(document).ready(function() {
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
  
  $( "#changePassword" ).on("click", function() {
    $("#formActionPlayer").val("changepassword");
    var newPass=$("#newPassword").val();
    if(newPass==""){
      console.log("nessuna password inserita");
      return;
    }
    
    // Determina se usare il proxy (da localhost)
    var isLocalhost = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
    var apiUrl = "https://yourradio.org/api/utenti/__USER_ID_FOR_PASSWORD__/password";
    // Usa percorso assoluto per il proxy quando caricato via AJAX
    var proxyPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf(\'/\')) + \'/api-proxy.php\';
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
          data: JSON.stringify({newpass: newPass}),
          success: function(res){
            console.log("API RESPONSE - Change Password SUCCESS:", res);
            if(res.success) {
              $("#password").val(res.data.password_md5);
              console.log("nuova password cambiata");
              $(".alert").hide();
            }
          },
          error: function(xhr, status, error){
            console.error("API RESPONSE - Change Password ERROR:", {xhr: xhr, status: status, error: error});
            alert("Purtroppo non ho potuto cambiare la password... qualcosa è andato storto");
          }
        });
  });

  // Log del valore iniziale di pl_idGruppo al caricamento della pagina
  $(document).ready(function() {
    var serverPlIdGruppo = ' . (isset($p[0]['pl_idGruppo']) ? intval($p[0]['pl_idGruppo']) : 0) . ';
    var initialPlIdGruppo = $("#pl_idGruppo").val();
    console.log("VALORE INIZIALE pl_idGruppo (hidden) al caricamento pagina:", initialPlIdGruppo, "| tipo:", typeof initialPlIdGruppo);
    console.log("Valore pl_idGruppo dal server (PHP):", serverPlIdGruppo);
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
    
    var formData = {};
    var $form = $(this);
    
    // Raccogli tutti i dati del form
    $form.find("input, select, textarea").each(function() {
      var $field = $(this);
      var name = $field.attr("name");
      var type = $field.attr("type");
      
      if(name && name !== "formAction" && name !== "formActionPlayer" && name !== "password") {
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
    
    // Assicurati che pl_idGruppo sia sempre presente (anche se 0)
    if(!("pl_idGruppo" in formData)) {
      formData.pl_idGruppo = 0;
    }
    
    console.log("Dati form raccolti:", formData);
    console.log("VALORE pl_idGruppo da inviare all\'API:", formData.pl_idGruppo !== undefined ? formData.pl_idGruppo : "NON PRESENTE", "| Tipo:", typeof formData.pl_idGruppo);
    
    var playerId = formData.pl_id;
    if(!playerId) {
      playerId = __PLAYER_ID_FOR_JS__;
    }
    if(!playerId || playerId === "" || playerId === "nuova") {
      alert("ID player non trovato");
      console.error("ID player non trovato:", playerId);
      return false;
    }
    
    console.log("Invio dati a API per player ID:", playerId);
    
    // Determina se usare il proxy (da localhost)
    var isLocalhost = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
    var apiUrl = "https://yourradio.org/api/players/" + playerId;
    // Usa percorso assoluto per il proxy quando caricato via AJAX
    var proxyPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf(\'/\')) + \'/api-proxy.php\';
    var finalUrl = isLocalhost ? proxyPath + "?url=" + encodeURIComponent(apiUrl) : apiUrl;
    
    console.log("API CALL - Update Player");
    console.log("  Server: https://yourradio.org");
    console.log("  Endpoint: /api/players/" + playerId);
    console.log("  Method: PUT");
    console.log("  Using proxy: " + isLocalhost);
    console.log("  Final URL: " + finalUrl);
    console.log("  Data:", formData);
    
    var $submitBtn = $("#update");
    var originalText = $submitBtn.html();
    $submitBtn.prop("disabled", true).html("Salvataggio...");
    
    $.ajax({
      method: "PUT",
      url: finalUrl,
      contentType: "application/json",
      data: JSON.stringify(formData),
      success: function(res) {
        console.log("API RESPONSE - Update Player SUCCESS:", res);
        console.log("  Server: https://yourradio.org");
        console.log("  Response:", res);
        if(res.success) {
          alert("Player aggiornato con successo!");
          window.location.reload();
        } else {
          alert("Errore: " + (res.error ? res.error.message : "Errore sconosciuto"));
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
        alert(errorMsg);
        $submitBtn.prop("disabled", false).html(originalText);
      }
    });
    return false;
  });

  if($("#password").val()=="" && $("#login").val()!="" ){
    $("#msg_alert").html("Ricorda di creare la password!");
    $(".alert").show();
  }

  // Inizializza il grafico SDmem - deve essere eseguito dopo che gauge.min.js è caricato
  // e dopo che l elemento SDmem è nel DOM

}); // Fine document.ready
</script>
';
$script = str_replace('__PLAYER_ID_FOR_JS__', $playerIdForJs, $script);
$script = str_replace('__GRUPPO_ID_FOR_JS__', $gruppoIdForJs, $script);
$script = str_replace('__USER_ID_FOR_PASSWORD__', $userIdForPassword, $script);


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
                      <h2 class="mb-0 text-uppercase"><?=$p[0]['pl_nome'] ?? ''?></h2>
                      <h4 class="mb-0 text-uppercase"><?=$p[0]['gr_nome'] ?? ''?></h4>
                      <small class="text-muted text-uppercase">ID.</small> <?=$p[0]['pl_id'] ?? ''?>
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
                    <input type="hidden" id="pl_idGruppo" name="pl_idGruppo" value="<?=(!empty($p) && isset($p[0]['pl_idGruppo'])) ? intval($p[0]['pl_idGruppo']) : 0?>">
                    <!-- username-nome -->
                    <div class="form-row">
                      <div class="col-md-12 mb-3">
                        <div class="custom-control custom-switch">
                          <input type="checkbox" class="custom-control-input" <?=$chbox_active?> id="pl_active" name="pl_active" value="1">
                          <label class="custom-control-label" for="pl_active"><?=$chbox_active_lab?></label>
                        </div>
                      </div>
                      <div class="col-md-12 mb-3">
                        <label class="form-scheda-label">Nome</label>
                        <input type="text" class="form-control input-uppercase" id="pl_nome" name="pl_nome" value="<?=$p[0]['pl_nome'] ?? ''?>" required>
                      </div>


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
                          <a role="button" href="#collapse-innsk-4" data-toggle="collapse" data-target="#collapse-innsk-4" aria-expanded="false" aria-controls="collapse-innsk-4" class="title-tab ">
                            <span class="fe fe-monitor fe-20"></span><strong>Digital Signage</strong>
                          </a>
                        </div>
                        <div id="collapse-innsk-4" class="collapse" aria-labelledby="heading-innsk-4" data-parent="#accordion-<?php echo $isAjaxRequest ? 'player-inner-scheda' : '1'; ?>">
                          
                          <div class="card-body">
                            <div class="col-md-4 mb-3">
                            <?php echo buildCheckByDbField("Digital Signage Attivo","pl_ds_attivo",$p[0]['pl_ds_attivo'] ?? 0);?>
                            <?php echo buildCheckByDbField("Audio","pl_ds_audio",$p[0]['pl_ds_audio'] ?? 0);?>

                        


                            <?php echo buildCheckByDbField("Videospot","pl_ds_videospot",$p[0]['pl_ds_videospot'] ?? 0);?>
                            <?php echo buildCheckByDbField("Videoclip","pl_ds_videoclip_on",$p[0]['pl_ds_videoclip_on'] ?? 0);?>
                            <?php echo buildCheckByDbField("Infobox Oroscopo","pl_ds_oroscopo_on",$p[0]['pl_ds_oroscopo_on'] ?? 0);?>
                            <?php echo buildCheckByDbField("Infobox News","pl_ds_news_on",$p[0]['pl_ds_news_on'] ?? 0);?>
                            <?php echo buildCheckByDbField("Infobox Meteo","pl_ds_meteo_on",$p[0]['pl_ds_meteo_on'] ?? 0);?>
                            <?php echo buildCheckByDbField("Infobox ADV","pl_ds_adv_on",$p[0]['pl_ds_adv_on'] ?? 0);?>
                          </div>
                          <div class="col-md-4 mb-3">
<label class="form-scheda-label">Campagna</label>
                        <select class="form-control" name="pl_ds_campagna_id" id="pl_ds_campagna_id" >
                          <?php 
                          echo '<option value="" >NESSUNA</option>';
                          foreach ($campagne as $c) {
                            if($c['ds_camp_titolo']==''){continue;} ///cambiare con verifica scadenza

                                if($c['ds_camp_id']==($p[0]['pl_ds_campagna_id'] ?? '')){$selected = "selected";}else{$selected = "";}
                            echo '<option value="'.$c['ds_camp_id'].'" '.$selected.'>'.strtoupper($c['ds_camp_titolo']).'</option>';
                          }?>
                        </select>
                      </div>

                          </div>
                        </div>
                      </div>






                    <!-- CREATED/LOGIN -->
                    <div class="form-row">
                      <div class="col-md-12 mb-4 ">
                        <input name="password" id="password" type="hidden" value="<?=$p[0]['pl_keyword_md5'] ?? ''?>" >
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