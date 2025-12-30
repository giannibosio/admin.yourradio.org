<?php
// Start the session
session_start();
if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}

include_once('load.php');

// Inizializza il database solo se necessario (per operazioni che non possono essere fatte via API)
// Su admin.yourradio.org il database potrebbe non essere accessibile direttamente
try {
    DB::init();
    $dbAvailable = true;
} catch (Exception $e) {
    // Database non disponibile, useremo solo le API
    $dbAvailable = false;
    error_log("Database non disponibile, utilizzo solo API: " . $e->getMessage());
}

$id=$_GET["id"] ?? '';
if(!isset($_GET["id"]) || $_GET["id"]==0 || $_GET["id"]==''){
  $disabled=" disabled ";
  $id='nuova';
  $title='Nuovo Player';
  $p = [];
}else{
  // Prova prima con il database, poi con l'API
  $p = array();
  if ($dbAvailable) {
    try {
      $p = Player::selectPlayerByID($id);
    } catch (Exception $e) {
      error_log("Errore nel caricamento player dal database: " . $e->getMessage());
      // Fallback all'API
      $dbAvailable = false;
    }
  }
  
  // Se il database non è disponibile o la query è fallita, usa l'API
  if (!$dbAvailable || empty($p) || !isset($p[0])) {
    $apiUrl = "https://yourradio.org/api/players/" . intval($id);
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if($httpCode == 200) {
      $apiResponse = json_decode($response, true);
      if(isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data'])) {
        // Converti il formato API al formato atteso dal codice esistente
        $playerData = $apiResponse['data'];
        $p = [[
          'pl_id' => $playerData['pl_id'] ?? $id,
          'pl_nome' => $playerData['pl_nome'] ?? '',
          'pl_active' => $playerData['pl_active'] ?? 0,
          'pl_player_pc' => $playerData['pl_player_pc'] ?? '',
          'pl_player_ip' => $playerData['pl_player_ip'] ?? '',
          'pl_riferimento' => $playerData['pl_riferimento'] ?? '',
          'pl_mail' => $playerData['pl_mail'] ?? '',
          'pl_telefono' => $playerData['pl_telefono'] ?? '',
          'pl_citta' => $playerData['pl_citta'] ?? '',
          'pl_indirizzo' => $playerData['pl_indirizzo'] ?? '',
          'pl_pro' => $playerData['pl_pro'] ?? '',
          'pl_cap' => $playerData['pl_cap'] ?? '',
          'pl_note' => $playerData['pl_note'] ?? '',
          'pl_idGruppo' => $playerData['pl_idGruppo'] ?? 0,
          'gr_nome' => $playerData['gr_nome'] ?? '',
          'gr_nat_port' => $playerData['gr_nat_port'] ?? '',
          'pl_mem_percent' => $playerData['pl_mem_percent'] ?? 0
        ]];
      }
    }
  }
  
  if(!empty($p) && isset($p[0])) {
    $disabled="";
    $title=$p[0]['pl_nome'] ?? '';
  } else {
    $disabled=" disabled ";
    $title='Player non trovato';
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
  $( "#player-skinn-chiudi" ).click(function() {
    
    closeChildTab("tab-players");
    console.log("torna al tab lista player del gruppo");
    
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

  var cpuGauge = Gauge(document.getElementById("SDmem"), {
    //dialStartAngle:1,
    max: 100,
    // custom label renderer
    label: function(value) {
      return Math.round(value) + "%";
    },
    // Custom dial colors (Optional)
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
  cpuGauge.setValueAnimated('.((!empty($p) && isset($p[0]['pl_mem_percent'])) ? $p[0]['pl_mem_percent'] : 0).', 2);


</script>
';


// Carica gruppi per la select - usa API se database non disponibile
$gruppi = array();
$rete_id = isset($p[0]['pl_idGruppo']) ? $p[0]['pl_idGruppo'] : 0;
if ($dbAvailable) {
  try {
    $gruppi = Gruppi::selectAllActive();
  } catch (Exception $e) {
    error_log("Errore nel caricamento gruppi: " . $e->getMessage());
  }
}
if (empty($gruppi)) {
  // Carica via API
  $apiUrl = "https://yourradio.org/api/gruppi";
  $ch = curl_init($apiUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($httpCode == 200) {
    $apiResponse = json_decode($response, true);
    if (isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data'])) {
      foreach ($apiResponse['data'] as $g) {
        $gruppi[] = [
          'gr_id' => $g['id'],
          'gr_nome' => $g['nome']
        ];
      }
    }
  }
}

///seleziona sottogruppi
$sg = array();
if (!empty($p) && isset($p[0]['pl_id'])) {
  if ($dbAvailable) {
    try {
      $sg = Gruppi::selectSubGruppoByIdPlayer($p[0]['pl_id']);
    } catch (Exception $e) {
      error_log("Errore nel caricamento sottogruppi: " . $e->getMessage());
    }
  }
}

///seleziona tutte le campagne by gruppoid
$campagne = array();
if (!empty($p) && isset($p[0]['pl_idGruppo'])) {
  if ($dbAvailable) {
    try {
      $campagne = Gruppi::selectCampagneByIdGroup($p[0]['pl_idGruppo']);
    } catch (Exception $e) {
      error_log("Errore nel caricamento campagne: " . $e->getMessage());
    }
  }
}



?>




           <div class="col-12 col-lg-10 col-xl-12">
            <div class="my-4">

              <div class="alert alert-primary" style="display:none" role="alert">
                <span class="fe fe-alert-circle fe-16 mr-2"></span>
                <span id="msg_alert"></span>
              </div>

              <div class="my-4">

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
              </div>

            <input type="hidden" class="form-control" id="pl_id" name="pl_id" value="<?=$p[0]['pl_id']?>" required>

                <div class="card-body">

                  <form id="scheda-profilo" class="needs-validation" novalidate method="post">
                    <!-- username-nome -->
                    <div class="form-row">
                      
                      <div class="col-md-12 mb-0">
                        <div class="custom-control custom-checkbox mb-3">
                          <input type="checkbox" class="custom-control-input" <?=$chbox_active?> id="pl_active" name="pl_active" value="1">
                          <label class="custom-control-label" for="pl_active">Player id.<?=$p[0]['pl_id']?> attivo</label>
                        </div>
                      </div>

                      <div class="col-md-6 mb-0">
                        <label class="form-scheda-label">Nome</label>
                        <input type="text" class="form-control input-uppercase" id="pl_nome" name="pl_nome" value="<?=$p[0]['pl_nome']?>" required>
                      </div>

                      <div class="col-md-6 mb-0">
                        <label class="form-scheda-label">Gruppo</label>
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


                    </div>

                    <!-- Riferimento -->
                    <div class="form-row">
                      <div class="col-md-5 mb-0">
                        <label class="form-scheda-label">Riferimento</label>
                        <input type="email" class="form-control input-uppercase" id="pl_riferimento" name="pl_riferimento" aria-describedby="emailHelp1" value="<?=strtolower($p[0]['pl_riferimento'])?>" required>
                        <div class="invalid-feedback"> Inserisci il nome del riferimento </div>
                      </div>
                      <div class="col-md-4 mb-0">
                        <label class="form-scheda-label">Email</label>
                        <input type="email" class="form-control" id="pl_mail" name="pl_mail" aria-describedby="emailHelp1" value="<?=strtolower($p[0]['pl_mail'])?>" required>
                        <div class="invalid-feedback"> Inserisci un indirizzo email valido </div>
                      </div>
                      <div class="col-md-3 mb-0">
                        <label class="form-scheda-label">Telefono</label>
                        <input class="form-control input-phoneus" id="custom-phone" maxlength="14" name="tel"value="<?=$p[0]['pl_telefono']?>" required>
                        <div class="invalid-feedback"> Inserisci un numero di telefono </div>
                      </div>
                    </div> 

                    <!-- città-rete-provincia-cap -->
                    <div class="form-row">
                      <div class="col-md-5 mb-0">
                        <label class="form-scheda-label">Indirizzo</label>
                        <input type="text" id="pl_indirizzo" class="form-control input-uppercase" placeholder="Enter your address" name="pl_indirizzo" value="<?=$p[0]['pl_indirizzo']?>" >
                        <div class="invalid-feedback"> Bad address </div>
                      </div>

                      <div class="col-md-4 mb-0">
                        <label class="form-scheda-label">Città</label>
                        <input type="text" class="form-control input-uppercase" id="pl_citta" name="pl_citta" value="<?=$p[0]['pl_citta']?>" required>
                        <div class="invalid-feedback"> Inserisci la città o la località </div>
                      </div>
                      <div class="col-md-1 mb-0">
                        <label class="form-scheda-label">PROV</label>
                        <input class="form-control input-uppercase" id="pl_pro" maxlength="2" name="pl_pro" value="<?=$p[0]['pl_pro']?>" >
                        <div class="invalid-feedback"> Inserisci la provincia </div>
                      </div>
                      <div class="col-md-2 mb-0">
                        <label class="form-scheda-label">CAP</label>
                        <input class="form-control" id="pl_cap" autocomplete="off" maxlength="5" name="pl_cap" value="<?=$p[0]['pl_cap']?>" >
                        <div class="invalid-feedback"> Inserisci un CAP. </div>
                      </div>
                    </div>
                    
                    <!-- Note -->
                    <div class="form-row">
                      <div class="col-md-12 mb-4">
                        <label class="form-scheda-label">Note</label>
                        <textarea class="form-control" id="pl_note" name="pl_note" placeholder="Scrivi nota" rows="3"><?=$p[0]['pl_note']?></textarea>
                        <div class="invalid-feedback"> Please enter a message in the textarea. </div>
                      </div>
                    </div>
                   
                    <div class="accordion w-100 inner-scheda" id="accordion-player-inner-scheda">

                      <div class="card shadow">
                        <div class="card-header" id="heading-innsk-1">
                          <a role="button" href="#collapse-innsk-1" data-toggle="collapse" data-target="#collapse1-innsk-" aria-expanded="true" aria-controls="collapse-innsk-1" class="title-tab">
                            <span class="fe fe-activity fe-20"></span><strong>Ping<?php echo $m=($type=="RASPI")? ' e SD Memory' : '';;?> </strong>
                          </a>
                        </div>
                        <div id="collapse1-innsk-" class="collapse show" aria-labelledby="heading-innsk-1" data-parent="#accordion-player-inner-scheda" style="">
                          <div class="card-body">
                            <i><h10>
                            ultimo ping: <?=$p[0]['pl_player_ultimaDataEstesa']?><br>
                            PC/IP player: <?=$p[0]['pl_player_pc']?><br>
                            IP esterno: <?=$p[0]['pl_player_ip']?>
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
                                    size: <?=$p[0]['pl_mem_size']?><br>
                                    used: <?=$p[0]['pl_mem_used']?><br>
                                    spazio disponibile: <?=$p[0]['pl_mem_available']?><br>
                                    percentuale occupata: <?=$p[0]['pl_mem_percent']?>%<br>
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
                        <div id="collapse-innsk-2" class="collapse" aria-labelledby="heading-innsk-2" data-parent="#accordion-player-inner-scheda" style="">
                          <div class="card-body">
                            <?php echo buildCheckByDbField("Ora esatta","pl_time",$p[0]['pl_time']);?>
                            <?php echo buildCheckByDbField("Multiutente","pl_player_freeaccess",$p[0]['pl_player_freeaccess']);?>
                            <?php echo buildCheckByDbField("Monitor","pl_monitor",$p[0]['pl_monitor']);?>
                            <?php echo buildCheckByDbField("Test","pl_test",$p[0]['pl_test']);?>
                            <?php echo buildCheckByDbField("Send Mail","pl_sendmail",$p[0]['pl_sendmail']);?>
                            <?php echo buildCheckByDbField("Edit selector","pl_client_edit_selector",$p[0]['pl_client_edit_selector']);?>
                            <?php echo buildCheckByDbField("Edit Spot","pl_client_edit_selector",$p[0]['pl_client_edit_selector']);?>
                            <?php echo buildCheckByDbField("Edit Rubriche","pl_client_edit_rubriche",$p[0]['pl_client_edit_rubriche']);?>
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
                            
                            <input class="form-control" id="pl_client_ora_on_ora" name="pl_client_ora_on_ora" type="hidden" value="<?=$p[0]['pl_client_ora_on_ora']?>">
                            <input class="form-control" id="pl_client_ora_on_min" name="pl_client_ora_on_min" type="hidden" value="<?=$p[0]['pl_client_ora_on_min']?>">
                            <input class="form-control" id="pl_oraOnCalcolata" name="pl_oraOnCalcolata" type="hidden" value="<?=$p[0]['pl_oraOnCalcolata']?>">
                            <input class="form-control" id="pl_client_ora_off_ora" name="pl_client_ora_off_ora" type="hidden" value="<?=$p[0]['pl_client_ora_off_ora']?>">
                            <input class="form-control" id="pl_client_ora_off_min" name="pl_client_ora_off_min" type="hidden" value="<?=$p[0]['pl_client_ora_off_min']?>">
                            <input class="form-control" id="pl_oraOffCalcolata" name="pl_oraOffCalcolata" type="hidden" value="<?=$p[0]['pl_oraOffCalcolata']?>">
                          </div>
                        </div>
                      </div>

                      <div class="card shadow">
                        <div class="card-header" id="heading-innsk-3">
                          <a role="button" href="#collapse-innsk-3" data-toggle="collapse" data-target="#collapse-innsk-3" aria-expanded="false" aria-controls="collapse-innsk-3" class="title-tab ">
                            <span class="fe fe-layers fe-20"></span><strong>Sottogruppi</strong>
                          </a>
                        </div>
                        <div id="collapse-innsk-3" class="collapse" aria-labelledby="heading-innsk-3" data-parent="#accordion-player-inner-scheda">
                          <div class="card-body">
                            <?php echo buildCheckSubGroupByIdPlayer($p[0]['pl_id']);?>
                          </div>
                        </div>
                      </div>

                      <div class="card shadow">
                        <div class="card-header" id="heading-innsk-4">
                          <a role="button" href="#collapse-innsk-4" data-toggle="collapse" data-target="#collapse-innsk-4" aria-expanded="false" aria-controls="collapse-innsk-4" class="title-tab ">
                            <span class="fe fe-monitor fe-20"></span><strong>Digital Signage</strong>
                          </a>
                        </div>
                        <div id="collapse-innsk-4" class="collapse" aria-labelledby="heading-innsk-4" data-parent="#accordion-player-inner-scheda">
                          
                          <div class="card-body">
                            <div class="col-md-4 mb-3">
                            <?php echo buildCheckByDbField("Digital Signage Attivo","pl_ds_attivo",$p[0]['pl_ds_attivo']);?>
                            <?php echo buildCheckByDbField("Audio","pl_ds_audio",$p[0]['pl_ds_audio']);?>

                        


                            <?php echo buildCheckByDbField("Videospot","pl_ds_videospot",$p[0]['pl_ds_videospot']);?>
                            <?php echo buildCheckByDbField("Videoclip","pl_ds_videoclip_on",$p[0]['pl_ds_videoclip_on']);?>
                            <?php echo buildCheckByDbField("Infobox Oroscopo","pl_ds_oroscopo_on",$p[0]['pl_ds_oroscopo_on']);?>
                            <?php echo buildCheckByDbField("Infobox News","pl_ds_news_on",$p[0]['pl_ds_news_on']);?>
                            <?php echo buildCheckByDbField("Infobox Meteo","pl_ds_meteo_on",$p[0]['pl_ds_meteo_on']);?>
                            <?php echo buildCheckByDbField("Infobox ADV","pl_ds_adv_on",$p[0]['pl_ds_adv_on']);?>
                          </div>
                          <div class="col-md-4 mb-3">
                            <label class="form-scheda-label">Campagna</label>
                            <select class="form-control" name="pl_ds_campagna_id" id="pl_ds_campagna_id" >
                              <?php 
                              echo '<option value="" >NESSUNA</option>';
                              foreach ($campagne as $c) {
                                if($c['ds_camp_titolo']==''){continue;} ///cambiare con verifica scadenza

                                if($c['ds_camp_id']==$p[0]['pl_ds_campagna_id']){$selected = "selected";}else{$selected = "";}
                                echo '<option value="'.$c['ds_camp_id'].'" '.$selected.'>'.strtoupper($c['ds_camp_titolo']).'</option>';
                              }?>
                            </select>
                          </div>

                              </div>
                        </div>
                      </div>

                    <!-- CREATED -->
                    <div class="form-row">
                      <div class="col-md-12 mb-4 ">
                        <input name="password" id="password" type="hidden" value="<?=$p[0]['pl_keyword_md5']?>" >
                        <input name="formAction" id="formAction" type="hidden" value="<?=$_POST["formAction"]?>" >
                        scheda creata il <?=$p[0]['pl_dataCreazione']?></h10></i>
                      </div>
                    </div>


                    <!-- Button bar -->
                    <div class="button-bar skinn">
                      <button title="Salva" class="btn btn-success" type="submit" id="player-skinn-update">Salva</button>
                      <button title="Chiudi" class="btn btn-success" id="player-skinn-chiudi" >Chiudi</button>
                      <button <?=$disabled?>title="cancella" type="button" class="btn btn-danger" data-toggle="modal" data-target="#modalCancellaPlayerSkinn">Cancella</button>
                    </div>
                  </form>

                  <!-- Modal Cancella -->
                  <div class="modal fade" id="modalCancellaPlayerSkinn" tabindex="-1" role="dialog" aria-labelledby="verticalModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="verticalModalTitle">Cancella player</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>
                        <div class="modal-body">Eliminare definitivamente il player <?=strtoupper($p[0]['pl_nome'])?>?</div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
                          <button class="btn mb-2 btn-danger" id="player-skinn-delete">Cancella</button>
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
        



  <script src="js/jquery.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/moment.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/simplebar.min.js"></script>
  <script src='js/daterangepicker.js'></script>
  <script src='js/jquery.stickOnScroll.js'></script>
  <script src="js/tinycolor-min.js"></script>
  <script>
    // Fix per config.js: assicurati che modeSwitcher esista prima che config.js venga caricato
    if (!document.querySelector('#modeSwitcher')) {
      var switcher = document.createElement('a');
      switcher.id = 'modeSwitcher';
      switcher.className = 'nav-link text-muted my-2';
      switcher.href = '#';
      switcher.setAttribute('data-mode', 'dark');
      switcher.style.display = 'none';
      if (document.body) {
        document.body.appendChild(switcher);
      } else {
        document.addEventListener('DOMContentLoaded', function() {
          document.body.appendChild(switcher);
        });
      }
    }
  </script>
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
  
  <?=$script?>


  <script>
    //i numeri sono formattati per l'utilizzo dell'ora nella scheda player
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
  </script>  


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