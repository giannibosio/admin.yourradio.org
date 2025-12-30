<?php
// Start the session
session_start();
include_once('load.php');
if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}

//echo $_POST["formAction"];

DB::init();

// Carica il gruppo dall'API invece del database locale
$gruppoId = $_GET["id"] ?? '';
$gruppoData = null;

if(!empty($gruppoId) && $gruppoId != 'nuova') {
    // Chiama l'API per ottenere i dati del gruppo
    $apiUrl = "https://yourradio.org/api/gruppi/" . intval($gruppoId);
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if($httpCode == 200) {
        $apiResponse = json_decode($response, true);
        if(isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data'])) {
            $gruppoData = $apiResponse['data'];
        }
    }
}

// Estrai i dati del gruppo
if($gruppoData) {
    $active = isset($gruppoData['attivo']) ? $gruppoData['attivo'] : 0;
    $id = isset($gruppoData['id']) ? $gruppoData['id'] : '';
    $nome = isset($gruppoData['nome']) ? strtoupper($gruppoData['nome']) : '';
    $rss_id = isset($gruppoData['rss_id']) ? $gruppoData['rss_id'] : '';
    $data_creazione = isset($gruppoData['data_creazione']) ? $gruppoData['data_creazione'] : '';
    $tot_players = isset($gruppoData['tot_player']) ? $gruppoData['tot_player'] : 0;
    $note = isset($gruppoData['note']) ? $gruppoData['note'] : '';
} else {
    $active = '';
    $id = '';
    $nome = '';
    $rss_id = '';
    $data_creazione = '';
    $tot_players = 0;
    $note = '';
}

// Crea un array compatibile con il codice esistente
$g = array();
if($gruppoData) {
    $g[0] = array(
        'gr_active' => $active,
        'gr_id' => $id,
        'gr_nome' => $nome,
        'rss_id' => $rss_id,
        'gr_dataCreazione' => $data_creazione,
        'tot_player' => $tot_players,
        'gr_note' => $note
    );
}

if(!isset($id) || $id==0 || $id==''){
  $disabled=" disabled ";
  $id='nuova';
  $title='Nuovo Gruppo';
}else{
  $disabled="";
  $title=$nome;

  verifyGroupFolder($id,strtolower($nome));
}





if(isset($_POST["formAction"]) && $_POST["formAction"]!=''){

  if($_POST["formAction"]=="back"){
    header("location:gruppi.php");
  }
  // La cancellazione del gruppo è ora gestita tramite API
  if($_POST["formAction"]=="deleteSubGroup"){
    $_POST["formAction"]='';
    $res=Gruppi::deleteSottoGruppoByID($_POST['SubGroupSelId'] ?? '');
  }
  if($_POST["formAction"]=="addNewSubGroup"){
    $_POST["formAction"]='';
    $res=Gruppi::addSottoGruppoByName($_GET["id"] ?? '',$_POST['newSubGroupName'] ?? '');
  }
  if($_POST["formAction"]=="update"){
      $_GET["id"]=Gruppi::updateGruppo($_POST);
      $_POST["formAction"]='';
  }
  // L'upload del logo è ora gestito tramite API

}
include_once('inc/head.php');


//scarico sottogruppi di questo gruppo ed i player collegati
$sgs=Gruppi::selectSubGruppoById($_GET["id"] ?? '');
$tot= is_array($sgs) ? count($sgs) : 0;
$tableSubGruppi='
<div class="card shadow" style="border: 1px solid #666">
<div class="card-body">
<h5 class="card-title">Sottogruppi</h5>
  <table class="table table-hover table-sm">
    <thead>
      <tr>
      <th>Nome</th>
      <th>Players</th>
      <th></th>
      </tr>
    </thead>
    <tbody>';
    if($tot>0){
foreach($sgs as $sg){
  $plsgr=Gruppi::selectTotPlayersSottoGruppoById($sg['sgr_id']);
  $totPlayers = $plsgr[0]['tot_player'];
  $buttonClass = $totPlayers > 0 ? "btn-outline-success" : "btn-outline-danger";
  $tableSubGruppi.='
  <tr>
    <td>'.$sg['sgr_nome'].'</td>
    <td>
      <button type="button" class="btn '.$buttonClass.' btn-sm btn-show-players-subgroup" 
              data-toggle="modal" 
              data-target="#playersSubgruppoModal" 
              data-subgruppo-id="'.$sg['sgr_id'].'" 
              data-subgruppo-nome="'.htmlspecialchars($sg['sgr_nome'], ENT_QUOTES).'"
              title="Visualizza players"
              style="width: 40px; font-size: 16px;">
        '.$totPlayers.'
      </button>
    </td>
    <td>
<button title="cancella" type="button" class="btn btn-outline-danger badge-del-subgroup" data-toggle="modal" data-target="#verticalModalSottogruppo" namegroup="'.strtoupper($sg['sgr_nome']).'" idgroup='.$sg['sgr_id'].'><span class="fe fe-trash fe-16"></span></button>
  </tr>                      
  ';
}  
}else{
  $tableSubGruppi.='<tr><td>NESSUNO</td><td></td></tr>';
}
$tableSubGruppi.='</tbody>
                  </table>
<button title="Aggiungi nuovo sottogruppo" type="button"  class="btn btn-outline-secondary" data-toggle="modal" data-target="#subGroupAddModal" ><span class="fe fe-plus fe-16"></span></button>

                  </div></div>';


$rssList=Gruppi::selectAllRss();

// Inizializzazione variabili per le tabelle e gli script
$players_table = '';
$scripts = '';
$jingles_table = '';
$spot_net_table = '';
$spot_loc_table = '';
$rubriche_table = '';

$tableId = 'gruppo-players';

$players_table.='
<!-- table '.$tableId.' -->
<div class="card shadow mb-6" style="border: 1px solid #666">
<div class="card-body">
<h5 class="card-title">Players di '.strtoupper($g[0]['gr_nome'] ?? '').'</h5>
<!-- table -->
<table class="table datatables display table-sm table-'.$tableId.'" id="dataTable-'.$tableId.'" style="width:100%">
<thead>
<tr>
<th>NOME</th>
<th>STATUS</th>

</tr>
</thead>
<tfoot>
<tr>
<th>NOME</th>
<th>STATUS</th>

</tr>
</tfoot>
</table>
</div>
</div>
<!-- //table '.$tableId.' --> 
';



$scripts.='
$(document).ready(function() {
  var activeColumn = 2;
  var badgeOn="<span class=\"badge badge-success\">ATTIVO</span>";
  var badgeOff="<span class=\"badge badge-danger\">DISABILITATO</span>";


  var table=$("#dataTable-'.$tableId.'").DataTable( {

    "ajax": {
      "url": "https://yourradio.org/api/gruppi/'.($_GET["id"] ?? '').'/players",
      "dataSrc": "data"
    },

    "columns": [
    { "data": "nome" },
    { "data": "attivo" },
    { "data": "attivo" },
    ],

    "rowId": "id",

    "ordering": true,
    "columnDefs": [
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
      $("td:eq(1)",row).addClass("toNeverHide");
      if(data.attivo=="ON"){
        $(row).addClass("pingMonitorGreen");
        
        $("td:eq(1)",row).html(badgeOn);
      }else{
        $(row).addClass("pingMonitorGreen");
        
        $("td:eq(1)",row).html(badgeOff);
      }
    },
    "paging":   false,
    "info":     true,
    "searching": false
  });

  $("body").on("click", "#dataTable-'.$tableId.' tbody tr", function(){
    var id=$(this).attr("id");
    openSchedaOnTab("player-inner-scheda.php?id="+id,"tab-players");
  });

});

';

$tableId = 'gruppo-jingles';
$jingles_table.='
<!-- table '.$tableId.' -->
<div class="card shadow mb-6" style="border: 1px solid #666">
<div class="card-body">
<h5 class="card-title">Jingles di '.strtoupper($g[0]['gr_nome'] ?? '').'</h5>
<!-- table -->
<table class="table datatables display table-sm table-'.$tableId.'" id="dataTable-'.$tableId.'" style="width:100%">
<thead>
<tr>
<th>ID</th>
<th>NOME</th>
<th>STATUS</th>
<th>ACTIVE</th>

</tr>
</thead>
<tfoot>
<tr>
<th>ID</th>
<th>NOME</th>
<th>STATUS</th>
<th>ACTIVE</th>

</tr>
</tfoot>
</table>
</div>
</div>
<!-- //table '.$tableId.' --> 
';

$scripts.='
$(document).ready(function() {
  var idColumn = 0;
  var activeColumn = 3;
  var badgeOn="<span class=\"badge badge-success\">ON</span>";
  var badgeOff="<span class=\"badge badge-danger\">OFF</span>";


  var table=$("#dataTable-'.$tableId.'").DataTable( {

    "ajax": {
      "url": "https://yourradio.org/api/jingles?gruppo_id='.($_GET["id"] ?? '').'",
      "dataSrc": "data"
    },

    "columns": [
    { "data": "id" },
    { "data": "nome" },
    { "data": "status" },
    { "data": "attivo" },
    ],

    "rowId": "id",
    "order": [[ 3, "desc" ],[ 2, "desc" ]],

    "ordering": true,
    "columnDefs": [
    { "visible": false, "targets": activeColumn },
    { "visible": false, "targets": idColumn }
    ],

    "drawCallback": function ( settings ) {
      var api = this.api();
      var rows = api.rows( {page:"current"} ).nodes();
      var last=null;
      },

    "rowCallback": function( row, data ) {
      
      $(row).addClass("rowPingMonitor");
      $("td:eq(0)",row).addClass("toNeverHide");
      $("td:eq(1)",row).addClass("toNeverHide");
      
      if(data.attivo=="1"){
        $(row).addClass("pingMonitorGreen");
        
        //$("td:eq(2)",row).html(badgeOn);
      }else{
        $(row).addClass("pingMonitorWhite");
        
        //$("td:eq(2)",row).html(badgeOff);
      }
    },
    "paging":   false,
    "info":     true,
    "searching": false
  });

  $("body").on("click", "#dataTable-'.$tableId.' tbody tr", function(){
    var id=$(this).attr("id");
    openSchedaOnTab("jingle-inner-scheda.php?id="+id,"tab-jingles");
  });

});

';

$tableId = 'gruppo-spot-net';
$spot_net_table.='
<!-- table '.$tableId.' -->
<div class="card shadow mb-6" style="border: 1px solid #666">
<div class="card-body">
<h5 class="card-title">Spot Network di '.strtoupper($g[0]['gr_nome'] ?? '').'</h5>
<!-- table -->
<table class="table datatables display table-sm table-'.$tableId.'" id="dataTable-'.$tableId.'" style="width:100%">
<thead>
<tr>
<th>ID</th>
<th>NOME</th>
<th>STATUS</th>
<th>ACTIVE</th>

</tr>
</thead>
<tfoot>
<tr>
<th>ID</th>
<th>NOME</th>
<th>STATUS</th>
<th>ACTIVE</th>

</tr>
</tfoot>
</table>
</div>
</div>
<!-- //table '.$tableId.' --> 
';

$scripts.='
$(document).ready(function() {
  var idColumn = 0;
  var activeColumn = 3;
  var badgeOn="<span class=\"badge badge-success\">ON</span>";
  var badgeOff="<span class=\"badge badge-danger\">OFF</span>";


  var table=$("#dataTable-'.$tableId.'").DataTable( {

    "ajax": {
      "url": "https://yourradio.org/api/spot/net?gruppo_id='.($_GET["id"] ?? '').'",
      "dataSrc": "data"
    },

    "columns": [
    { "data": "id" },
    { "data": "nome" },
    { "data": "status" },
    { "data": "attivo" },
    ],

    "rowId": "id",
    "order": [[ 3, "desc" ],[ 2, "desc" ]],

    "ordering": true,
    "columnDefs": [
    { "visible": false, "targets": activeColumn },
    { "visible": false, "targets": idColumn }
    ],

    "drawCallback": function ( settings ) {
      var api = this.api();
      var rows = api.rows( {page:"current"} ).nodes();
      var last=null;
      },

    "rowCallback": function( row, data ) {
      
      $(row).addClass("rowPingMonitor");
      $("td:eq(0)",row).addClass("toNeverHide");
      $("td:eq(1)",row).addClass("toNeverHide");
      
      if(data.attivo=="1"){
        $(row).addClass("pingMonitorGreen");
        
        //$("td:eq(2)",row).html(badgeOn);
      }else{
        $(row).addClass("pingMonitorWhite");
        
        //$("td:eq(2)",row).html(badgeOff);
      }
    },
    "paging":   false,
    "info":     true,
    "searching": false
  });

  $("body").on("click", "#dataTable-'.$tableId.' tbody tr", function(){
    var id=$(this).attr("id");
    window.open("player-scheda.php?id="+id,"_self");
  });

});

';

$tableId = 'gruppo-spot-loc';
$spot_loc_table.='
<!-- table '.$tableId.' -->
<div class="card shadow mb-6" style="border: 1px solid #666">
<div class="card-body">
<h5 class="card-title">Spot Locali di '.strtoupper($g[0]['gr_nome'] ?? '').'</h5>
<!-- table -->
<table class="table datatables display table-sm table-'.$tableId.'" id="dataTable-'.$tableId.'" style="width:100%">
<thead>
<tr>
<th>ID</th>
<th>NOME</th>
<th>STATUS</th>
<th>ACTIVE</th>

</tr>
</thead>
<tfoot>
<tr>
<th>ID</th>
<th>NOME</th>
<th>STATUS</th>
<th>ACTIVE</th>

</tr>
</tfoot>
</table>
</div>
</div>
<!-- //table '.$tableId.' --> 
';

$scripts.='
$(document).ready(function() {
  var idColumn = 0;
  var activeColumn = 3;
  var badgeOn="<span class=\"badge badge-success\">ON</span>";
  var badgeOff="<span class=\"badge badge-danger\">OFF</span>";


  var table=$("#dataTable-'.$tableId.'").DataTable( {

    "ajax": {
      "url": "https://yourradio.org/api/spot/loc?gruppo_id='.($_GET["id"] ?? '').'",
      "dataSrc": "data"
    },

    "columns": [
    { "data": "id" },
    { "data": "nome" },
    { "data": "status" },
    { "data": "attivo" },
    ],

    "rowId": "id",
    "order": [[ 3, "desc" ],[ 2, "desc" ]],

    "ordering": true,
    "columnDefs": [
    { "visible": false, "targets": activeColumn },
    { "visible": false, "targets": idColumn }
    ],

    "drawCallback": function ( settings ) {
      var api = this.api();
      var rows = api.rows( {page:"current"} ).nodes();
      var last=null;
      },

    "rowCallback": function( row, data ) {
      
      $(row).addClass("rowPingMonitor");
      $("td:eq(0)",row).addClass("toNeverHide");
      $("td:eq(1)",row).addClass("toNeverHide");
      
      if(data.attivo=="1"){
        $(row).addClass("pingMonitorGreen");
        
        //$("td:eq(2)",row).html(badgeOn);
      }else{
        $(row).addClass("pingMonitorWhite");
        
        //$("td:eq(2)",row).html(badgeOff);
      }
    },
    "paging":   false,
    "info":     true,
    "searching": false
  });

  $("body").on("click", "#dataTable-'.$tableId.' tbody tr", function(){
    var id=$(this).attr("id");
    window.open("player-scheda.php?id="+id,"_self");
  });

});

';

$tableId = 'gruppo-rubriche';
$rubriche_table.='
<!-- table '.$tableId.' -->
<div class="card shadow mb-6" style="border: 1px solid #666">
<div class="card-body">
<h5 class="card-title">Rubriche disponibili </h5>
<!-- table -->
<table class="table datatables display table-sm table-'.$tableId.'" id="dataTable-'.$tableId.'" style="width:100%">
<thead>
<tr>
<th>ID</th>
<th>NOME</th>
<th>FILES</th>
<th>STATUS</th>

</tr>
</thead>
<tfoot>
<tr>
<th>ID</th>
<th>NOME</th>
<th>FILES</th>
<th>STATUS</th>

</tr>
</tfoot>
</table>
</div>
</div>
<!-- //table '.$tableId.' --> 
';

$scripts.='
$(document).ready(function() {
  var idColumn = 0;
  var activeColumn = 3;
  var badgeOn="<span class=\"badge badge-success\">ON</span>";
  var badgeOff="<span class=\"badge badge-danger\">OFF</span>";


  var table=$("#dataTable-'.$tableId.'").DataTable( {

    "ajax": {
      "url": "https://yourradio.org/api/rubriche?gruppo_id='.($_GET["id"] ?? '').'",
      "dataSrc": "data"
    },

    "columns": [
    { "data": "id" },
    { "data": "nome" },
    { "data": "files" },
    { "data": "status" },
    ],

    "rowId": "id",
    "order": [[ 3, "desc" ],[ 2, "desc" ]],

    "ordering": true,
    "columnDefs": [
    { "visible": true, "targets": activeColumn },
    { "visible": false, "targets": idColumn }
    ],

    "drawCallback": function ( settings ) {
      var api = this.api();
      var rows = api.rows( {page:"current"} ).nodes();
      var last=null;
      },

    "rowCallback": function( row, data ) {
      
      $(row).addClass("rowPingMonitor");
      $("td:eq(0)",row).addClass("toNeverHide");
      $("td:eq(1)",row).addClass("toNeverHide");
      
      if(data.files>0){
        $(row).addClass("pingMonitorGreen");
        
        //$("td:eq(2)",row).html(badgeOn);
      }else{
        $(row).addClass("pingMonitorWhite");
        
        //$("td:eq(2)",row).html(badgeOff);
      }
    },
    "paging":   false,
    "info":     true,
    "searching": false
  });

  $("body").on("click", "#dataTable-'.$tableId.' tbody tr", function(){
    var id=$(this).attr("id");
    window.open("player-scheda.php?id="+id,"_self");
  });

});

';


$gruppi=Gruppi::selectAllActive();




if($active==1){$chbox_active="checked";$chbox_active_lab="Attivo";}else{$chbox_active="";$chbox_active_lab="Disattivato";}

$script='
<script>
  $( ".badge-del-subgroup" ).click(function() {
    $("#SubGroupSelId").val($(this).attr("idgroup"));
    $("#SubGroupSelName").val($(this).attr("namegroup"));
    var namen=$("#SubGroupSelName").val();
    $("#modal-del-subgroup-text").html("Vuoi cancellare il sottogruppo "+namen+" e tutti i collegamenti ai player ?");
  });

  $( "#update" ).click(function() {
    $("#formAction").val("update");
  });
  // La cancellazione del gruppo è ora gestita tramite API (vedi sotto)
  $( ".back-lista" ).click(function() {
    $("#formAction").val("back");
    console.log("torna alla lista");
    window.open("gruppi.php","_self");
  });
  $( "#deleteSubGroup" ).click(function() {
    $("#formAction").val("deleteSubGroup");
    console.log("cancella sottogruppo "+$("#SubGroupSelName").val()+ " - id."+$("#SubGroupSelId").val());
    $( "#scheda-gruppo" ).submit();
  });
  $( "#addNewSubGroup" ).click(function() {
    $("#formAction").val("addNewSubGroup");
    $("#newSubGroupName").val($("#newName").val());
    console.log("aggiungi sottogruppo "+$("#newName").val());

    $( "#scheda-gruppo" ).submit();
  });

  // Gestione modale players sottogruppo
  $(document).on("click", ".btn-show-players-subgroup", function() {
    var subgruppoId = $(this).data("subgruppo-id");
    var subgruppoNome = $(this).data("subgruppo-nome");
    var $modal = $("#playersSubgruppoModal");
    var $content = $("#playersSubgruppoContent");
    
    // Aggiorna il titolo della modale
    $("#playersSubgruppoModalLabel").text("Players del Sottogruppo: " + subgruppoNome);
    
    // Mostra spinner
    $content.html("<div class=\"text-center\"><div class=\"spinner-border text-primary\" role=\"status\"><span class=\"sr-only\">Caricamento...</span></div></div>");
    
    // Carica i players
    $.ajax({
      url: "https://yourradio.org/api/subgruppi/" + subgruppoId + "/players",
      type: "GET",
      dataType: "json",
      success: function(response) {
        if (response.success && response.data && response.data.length > 0) {
          var html = "<table class=\"table table-hover table-sm\">";
          html += "<thead><tr><th>Nome</th></tr></thead>";
          html += "<tbody>";
          $.each(response.data, function(index, player) {
            var textColor = player.attivo === "ON" ? "text-success" : "text-danger";
            html += "<tr class=\"player-row-clickable\" style=\"cursor: pointer;\" data-player-id=\"" + player.id + "\">";
            html += "<td><span class=\"" + textColor + "\">" + player.nome + "</span></td>";
            html += "</tr>";
          });
          html += "</tbody></table>";
          $content.html(html);
        } else {
          $content.html("<div class=\"alert alert-info\">Nessun player associato a questo sottogruppo.</div>");
        }
      },
      error: function(xhr, status, error) {
        $content.html("<div class=\"alert alert-danger\">Errore nel caricamento dei players: " + error + "</div>");
      }
    });
  });

  // Gestione click sui players nella modale sottogruppo
  $(document).on("click", ".player-row-clickable", function() {
    var playerId = $(this).data("player-id");
    if (playerId) {
      // Chiudi la modale
      $("#playersSubgruppoModal").modal("hide");
      // Attiva il tab Players
      $("#gruppo-tab-players").click();
      // Apri la scheda del player nella tab players
      setTimeout(function() {
        openSchedaOnTab("player-inner-scheda.php?id=" + playerId, "tab-players");
      }, 100);
    }
  });

  // Gestione upload logo tramite API
  $("#uploadLogoForm").on("submit", function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    var $btn = $("#uploadLogoBtn");
    var $spinner = $btn.find(".spinner-border");
    var $modal = $("#uploadLogoModal");
    var gruppoId = $("#uploadLogoForm input[name=\'groupId\']").val();
    
    // Mostra spinner
    $spinner.removeClass("d-none");
    $btn.prop("disabled", true);
    
    // Rimuovi eventuali messaggi di errore precedenti
    $modal.find(".alert").remove();
    
    $.ajax({
      url: "https://yourradio.org/api/gruppi/" + gruppoId + "/logo",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        if (response.success) {
          // Mostra messaggio di successo
          var successMsg = \'<div class="alert alert-success" role="alert">Logo caricato con successo!</div>\';
          $modal.find(".modal-body").prepend(successMsg);
          
          // Ricarica la pagina dopo 1 secondo per mostrare il nuovo logo
          setTimeout(function() {
            window.location.reload();
          }, 1000);
        } else {
          var errorMsg = \'<div class="alert alert-danger" role="alert">\' + (response.error ? response.error.message : "Errore sconosciuto") + \'</div>\';
          $modal.find(".modal-body").prepend(errorMsg);
          $spinner.addClass("d-none");
          $btn.prop("disabled", false);
        }
      },
      error: function(xhr, status, error) {
        var errorResponse = xhr.responseJSON;
        var errorMsg = \'<div class="alert alert-danger" role="alert">\' + 
          (errorResponse && errorResponse.error ? errorResponse.error.message : "Errore durante il caricamento del logo: " + error) + 
          \'</div>\';
        $modal.find(".modal-body").prepend(errorMsg);
        $spinner.addClass("d-none");
        $btn.prop("disabled", false);
      }
    });
  });

  // Gestione cancellazione gruppo tramite API
  $("#confirmDeleteGruppo").on("click", function() {
    var gruppoId = '.json_encode($id != "nuova" ? $id : null).';
    var $btn = $(this);
    var $spinner = $btn.find(".spinner-border");
    var $modal = $("#deleteGruppoModal");
    var $cancelBtn = $("#cancelDeleteBtn");
    var $closeBtn = $("#closeDeleteModal");
    
    // Disabilita i pulsanti e mostra lo spinner
    $btn.prop("disabled", true);
    $cancelBtn.prop("disabled", true);
    $closeBtn.prop("disabled", true);
    $spinner.removeClass("d-none");
    
    // Mostra spinner anche nel body della modale
    $modal.find(".modal-body").html(\'<div class="text-center"><div class="spinner-border text-danger" role="status"><span class="sr-only">Eliminazione in corso...</span></div><p class="mt-3">Eliminazione del gruppo in corso...</p></div>\');
    
    $.ajax({
      url: "https://yourradio.org/api/gruppi/" + gruppoId,
      type: "DELETE",
      dataType: "json",
      success: function(response) {
        if (response.success) {
          // Mostra messaggio di successo
          $modal.find(".modal-body").html(\'<div class="alert alert-success" role="alert"><strong>Gruppo eliminato con successo!</strong></div>\');
          // Reindirizza a gruppi.php dopo 1 secondo
          setTimeout(function() {
            window.location.href = "gruppi.php";
          }, 1000);
        } else {
          var errorMsg = \'<div class="alert alert-danger" role="alert"><strong>Errore:</strong> \' + (response.error ? response.error.message : "Errore sconosciuto") + \'</div>\';
          $modal.find(".modal-body").html(errorMsg);
          $btn.prop("disabled", false);
          $cancelBtn.prop("disabled", false);
          $closeBtn.prop("disabled", false);
          $spinner.addClass("d-none");
        }
      },
      error: function(xhr, status, error) {
        var errorResponse = xhr.responseJSON;
        var errorMsg = \'<div class="alert alert-danger" role="alert"><strong>Errore:</strong> \' + 
          (errorResponse && errorResponse.error ? errorResponse.error.message : "Errore durante l\'eliminazione del gruppo: " + error) + 
          \'</div>\';
        $modal.find(".modal-body").html(errorMsg);
        $btn.prop("disabled", false);
        $cancelBtn.prop("disabled", false);
        $closeBtn.prop("disabled", false);
        $spinner.addClass("d-none");
      }
    });
  });

  $(".tabs-scheda-gruppo ").hide();
  $("#gruppo-tab-players").click();
  $(".tab-players").fadeIn("slow");

  

  if(getCookie("gruppo-tab")=="players"){
    $("#gruppo-tab-players").click();
    $(".tabs-scheda-gruppo ").hide();
    $(".tab-players").fadeIn("slow");
  }
  if(getCookie("gruppo-tab")=="sottogruppi"){
    $("#gruppo-tab-sottogruppi").click();
    $(".tabs-scheda-gruppo ").hide();
    $(".tab-sottogruppi").fadeIn("slow");
  }

  $( "#gruppo-tab-scheda" ).click(function() {
    $(".tabs-scheda-gruppo ").hide();
    $(".tab-scheda").fadeIn("slow");
    document.cookie="gruppo-tab=scheda";
  });

  $( "#gruppo-tab-sottogruppi" ).click(function() {
    $(".tabs-scheda-gruppo ").hide();
    $(".tab-sottogruppi").fadeIn("slow");
    document.cookie="gruppo-tab=sottogruppi";
  });

  $( "#gruppo-tab-players" ).click(function() {
    $(".tabs-scheda-gruppo ").hide();
    $(".tab-players").fadeIn("slow");
    document.cookie="gruppo-tab=players";
  });

  $( "#gruppo-tab-jingles" ).click(function() {
    $(".tabs-scheda-gruppo ").hide();
    $(".tab-jingles").fadeIn("slow");
    document.cookie="gruppo-tab=jingles";
  });

  $( "#gruppo-tab-spot-net" ).click(function() {
    $(".tabs-scheda-gruppo ").hide();
    $(".tab-spot-net").fadeIn("slow");
    document.cookie="gruppo-tab=spot-net";
  });
  $( "#gruppo-tab-spot-loc" ).click(function() {
    $(".tabs-scheda-gruppo ").hide();
    $(".tab-spot-loc").fadeIn("slow");
    document.cookie="gruppo-tab=spot-loc";
  });
  $( "#gruppo-tab-rubriche" ).click(function() {
    $(".tabs-scheda-gruppo ").hide();
    $(".tab-rubriche").fadeIn("slow");
    document.cookie="gruppo-tab=rubriche";
  });
  $( "#gruppo-tab-selector" ).click(function() {
    $(".tabs-scheda-gruppo ").hide();
    $(".tab-selector").fadeIn("slow");
    document.cookie="gruppo-tab=selector";
  });

  function openSchedaOnTab(url,tab){
    $(".tabs-scheda-gruppo."+tab+">.mb-3>.child-tab").html("<center>...loading...</center>");
    $(".tabs-scheda-gruppo."+tab+">.mb-3>.child-tab").load(url, function(responseTxt, statusTxt, xhr){
      if(statusTxt == "success")
      $(".tabs-scheda-gruppo."+tab+">.mb-3>.primary-tab").slideUp(); 
        $(".tabs-scheda-gruppo."+tab+">.mb-3>.child-tab").fadeIn("slow")
         
      if(statusTxt == "error")
        alert("Error: " + xhr.status + ": " + xhr.statusText);
    });
  }
  
  function closeChildTab(tab){
    $(".tabs-scheda-gruppo."+tab+">.mb-3>.child-tab").html(""); 
    $(".tabs-scheda-gruppo."+tab+">.mb-3>.primary-tab").fadeIn( "slow");
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
            
            <div class="mb-4">

              <div class="alert alert-primary" style="display:none" role="alert">
                <span class="fe fe-alert-circle fe-16 mr-2"></span>
                <span id="msg_alert"></span>
              </div>


              <div class="mb-4">

                <div class="card-header">
                  
                  <div class="logo-scheda-gruppo mt-0">
                    <h4>Gruppo</h4>
                    <h2 class="page-title"><?=$title?></h2>

                    <img src="https://yourradio.org/player/<?=strtolower($g[0]['gr_nome'])?>/images/logo_gruppo.png" >
                    <div class="mt-2">
                      <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#uploadLogoModal">
                        CAMBIO LOGO
                      </button>
                    </div>
                    </div>
                </div>

                <div class="card-body gruppo-scheda">
                  

                  <!--<ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                    <li class="nav-item">
                      <a class="nav-link active" id="gruppo-tab-scheda" data-toggle="tab" role="tab" aria-controls="scheda" aria-selected="true">Scheda</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" id="gruppo-tab-sottogruppi" data-toggle="tab" role="tab" aria-controls="sottogruppi" aria-selected="false">SottoGruppi</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" id="gruppo-tab-players" data-toggle="tab" role="tab" aria-controls="players" aria-selected="false">Players</a>
                    </li>
                  </ul>-->

                  <form id="scheda-gruppo" class="needs-validation" novalidate method="post">
                    
                    <!-- ACTIVE -->
                    <div class="form-row tab-scheda ">
                      <div class="mb-3">
                        <div class="custom-control custom-switch">
                          <input type="checkbox" class="custom-control-input" <?=$chbox_active?> id="active" name="active" value="1">
                          <label class="custom-control-label" for="active"><?=$chbox_active_lab?></label>
                        </div>
                      </div>
                    
                      <div class="col-md-12 mb-3">
                        <label for="validationCustom3">Nome <i>(non modificabile)</i></label>
                        <input disabled type="text" class="form-control" id="validationCustom3" value="<?=$nome?>" required>
                      </div>

                    </div>
                    

                    <div class="card shadow">
                      <div id="collapse1">
                        <div class="card-body">
                            
                            <ul class="nav nav-pills" id="pills-tab2" role="tablist">
                              <li class="nav-item">
                                <a title="Players" class="nav-link px-3 gruppo-scheda" id="gruppo-tab-players" data-toggle="tab" href="#pills-contact2" role="tab" aria-controls="players" aria-selected="false">Players</a>
                              </li>
                              <li class="nav-item">
                                <a title="Sottogruppi" class="nav-link px-3 gruppo-scheda" id="gruppo-tab-sottogruppi" data-toggle="tab" href="#pills-profile2" role="tab" aria-controls="sottogruppi" aria-selected="false">SottoGruppi</a>
                              </li>
                              <li class="nav-item">
                                <a title="Jingles" class="nav-link px-3 gruppo-scheda" id="gruppo-tab-jingles" data-toggle="tab" href="#pills-contact2" role="tab" aria-controls="jingles" aria-selected="false">Jingles</a>
                              </li>
                              <li class="nav-item">
                                <a title="Spot Network" class="nav-link px-3 gruppo-scheda" id="gruppo-tab-spot-net" data-toggle="tab" href="#pills-contact2" role="tab" aria-controls="spot-net" aria-selected="false">Spot Net</a>
                              </li>
                              <li class="nav-item">
                                <a title="Spot Locali" class="nav-link px-3 gruppo-scheda" id="gruppo-tab-spot-loc" data-toggle="tab" href="#pills-contact2" role="tab" aria-controls="spot-loc" aria-selected="false">Spot Loc</a>
                              </li>
                              <li class="nav-item">
                                <a title="Spot Locali" class="nav-link px-3 gruppo-scheda" id="gruppo-tab-rubriche" data-toggle="tab" href="#pills-contact2" role="tab" aria-controls="rubriche" aria-selected="false">Rubriche</a>
                              </li>
                              <li class="nav-item">
                                <a title="Spot Locali" class="nav-link px-3 gruppo-scheda" id="gruppo-tab-selector" data-toggle="tab" href="#pills-contact2" role="tab" aria-controls="selector" aria-selected="false">Selector</a>
                              </li>
                            </ul>

                            <div class="form-row tabs-scheda-gruppo tab-sottogruppi">
                              <div class="col-md-12 mb-3">
                                <div class="primary-tab"><?=$tableSubGruppi?></div>
                                <div class="child-tab"></div>
                              </div>
                            </div>
                            <div class="form-row tabs-scheda-gruppo tab-players">
                              <div class="col-md-12 mb-3">
                                <div class="primary-tab"><?=$players_table?></div>
                                <div class="child-tab"></div>
                              </div>
                            </div>
                            <div class="form-row tabs-scheda-gruppo tab-jingles">
                              <div class="col-md-12 mb-3">
                                <div class="primary-tab"><?=$jingles_table?></div>
                                <div class="child-tab"></div>
                              </div>
                            </div>
                            <div class="form-row tabs-scheda-gruppo tab-spot-net">
                              <div class="col-md-12 mb-3">
                                <div class="primary-tab"><?=$spot_net_table?></div>
                                <div class="child-tab"></div>
                              </div>
                            </div>
                            <div class="form-row tabs-scheda-gruppo tab-spot-loc">
                              <div class="col-md-12 mb-3">
                                <div class="primary-tab"><?=$spot_loc_table?></div>
                                <div class="child-tab"></div>
                              </div>
                            </div>
                            <div class="form-row tabs-scheda-gruppo tab-rubriche">
                              <div class="col-md-12 mb-3">
                                <div class="primary-tab"><?=$rubriche_table?></div>
                                <div class="child-tab"></div>
                              </div>
                            </div>
                            <div class="form-row tabs-scheda-gruppo tab-selector">
                              <div class="col-md-12 mb-3">
                                <div class="primary-tab">WORK IN PROGRESS</div>
                                <div class="child-tab"></div>
                              </div>
                            </div>



                        </div>
                      </div>
                    </div> <!-- // card shadow -->



                    

                    <!-- CREATED/LOGIN -->
                    <div class="form-row">
                      <input name="groupId" id="groupId" type="hidden" value="<?=$_GET["id"] ?? ''?>" >
                      <input name="nome" id="nome" type="hidden" value="<?=$nome?>" >
                      <input name="SubGroupSelId" id="SubGroupSelId" type="hidden" value="" >
                      <input name="SubGroupSelName" id="SubGroupSelName" type="hidden" value="" >
                      <input name="newSubGroupName" id="newSubGroupName" type="hidden" value="" >
                      <input name="dataCreazione" id="dataCreazione" type="hidden" value="<?=$data_creazione?>" >
                      <input name="formAction" id="formAction" type="hidden" value="<?=$_POST["formAction"] ?? ''?>" >
                      <i><h10>gruppo creato il <?=$data_creazione?></h10></i>

                    </div>
                    <!-- Button bar -->
                    <div class="button-bar">

                      <button title="salva" class="btn btn-outline-success" type="submit" id="update"><span class="fe fe-save fe-16"></span></button>
                      <button title="lista" class="btn btn-outline-success back-lista" ><span class="fe fe-list fe-16"></span></button>
                      <button <?=$disabled?>title="cancella" type="button" class="btn btn-outline-danger" id="btnDeleteGruppo" data-toggle="modal" data-target="#deleteGruppoModal"><span class="fe fe-trash fe-16"></span></button>

                    </div>

                  </form>



                  <!-- Modal Cancella Gruppo -->
                  <div class="modal fade" id="deleteGruppoModal" tabindex="-1" role="dialog" aria-labelledby="deleteGruppoModalLabel" aria-hidden="true" data-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="deleteGruppoModalLabel">Cancella Gruppo</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close" id="closeDeleteModal">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>
                        <div class="modal-body">
                          <div class="alert alert-danger" role="alert">
                            <strong>ATTENZIONE!</strong> Stai per eliminare definitivamente il gruppo <strong><?=strtoupper($nome)?></strong>.
                          </div>
                          <p>Questa operazione eliminerà:</p>
                          <ul>
                            <li>Tutti i <strong>player</strong> di questo gruppo</li>
                            <li>Tutti i <strong>sottogruppi</strong></li>
                            <li>Tutti i <strong>jingles</strong></li>
                            <li>Tutti gli <strong>Spot Net</strong></li>
                            <li>Tutti gli <strong>Spot Loc</strong></li>
                            <li>L\'<strong>abbinamento alle rubriche</strong></li>
                            <li>La <strong>cartella</strong> del gruppo e tutti i suoi contenuti</li>
                          </ul>
                          <p class="text-danger"><strong>Questa operazione è irreversibile!</strong></p>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal" id="cancelDeleteBtn">Annulla</button>
                          <button type="button" class="btn mb-2 btn-danger" id="confirmDeleteGruppo">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Elimina Gruppo
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Modal Cancella SottoGruppo -->
                  <div class="modal fade" id="verticalModalSottogruppo" tabindex="-1" role="dialog" aria-labelledby="verticalModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="verticalModalTitle">Cancella SottoGruppo </h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>
                        <div id="modal-del-subgroup-text" class="modal-body"></div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
                          <button class="btn mb-2 btn-danger" id="deleteSubGroup">Cancella</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- modal nuovo sottogruppo -->
                  <div class="modal fade" id="subGroupAddModal" tabindex="-1" role="dialog" aria-labelledby="varyModalLabel" style="display: none;" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="varyModalLabel">Nuovo SottoGruppo</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                          </button>
                        </div>
                        <div class="modal-body">
                          <form>
                            <div class="form-group">
                              <label for="recipient-name" class="col-form-label">Nome del SottoGruppo:</label>
                              <input type="text" style="text-transform: uppercase" class="form-control" name="newName" id="newName">
                            </div>
                          </form>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
                          <button type="button" class="btn mb-2 btn-primary" data-dismiss="modal" id="addNewSubGroup">Aggiungi</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Modal Players Sottogruppo -->
                  <div class="modal fade" id="playersSubgruppoModal" tabindex="-1" role="dialog" aria-labelledby="playersSubgruppoModalLabel" aria-hidden="true" data-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="playersSubgruppoModalLabel">Players del Sottogruppo</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>
                        <div class="modal-body">
                          <div id="playersSubgruppoContent">
                            <div class="text-center">
                              <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Caricamento...</span>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Chiudi</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Modal Upload Logo -->
                  <div class="modal fade" id="uploadLogoModal" tabindex="-1" role="dialog" aria-labelledby="uploadLogoModalLabel" aria-hidden="true" data-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="uploadLogoModalLabel">Carica Logo Gruppo</h5>
                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>
                        <form id="uploadLogoForm" method="post" enctype="multipart/form-data">
                          <div class="modal-body">
                            <div class="form-group">
                              <label for="logo_file">Seleziona un file immagine (PNG, JPG, GIF)</label>
                              <input type="file" class="form-control-file" id="logo_file" name="logo_file" accept="image/png,image/jpeg,image/jpg,image/gif" required>
                              <small class="form-text text-muted">Il file verrà salvato come logo_gruppo.png</small>
                            </div>
                            <input type="hidden" name="formAction" value="uploadLogo">
                            <input type="hidden" name="groupId" value="<?=$id?>">
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn mb-2 btn-secondary" data-dismiss="modal">Annulla</button>
                            <button type="submit" class="btn mb-2 btn-primary" id="uploadLogoBtn">
                              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                              Carica Logo
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  




                </div> <!-- /.card-body -->
              </div> <!-- /.card shadow mb-4 -->
            </div> <!-- /.card shadow -->
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

  <script src='js/jquery.dataTables.min.js'></script>
            <script src='js/dataTables.bootstrap4.min.js'></script>
            <script src='https://cdn.datatables.net/buttons/1.6.5/js/dataTables.buttons.min.js'></script>
            <script src='https://cdn.datatables.net/buttons/1.6.5/js/buttons.colVis.min.js'></script>

  <script>
    <?=$scripts?>
  </script>            
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