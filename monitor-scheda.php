<?php
// Start the session
session_start();
include_once('load.php');

if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}
if(!isset($_GET["id"])){
  header("location:monitor.php");
}

include_once('inc/head.php');

DB::init();
$p=Monitor::selectPlayerByID($_GET["id"]);

//print_r($p);



if(substr(strtoupper($p[0]['pl_player_pc']),0,4)=="RSPI"){
			$logo="raspi_logo_200.png";
			$type="RASPI";
			$ipDevice=substr($p[0]['pl_player_pc'],5);

			$external_url;
			if($p[0]['gr_nat_port']){
				$external_url="http://".$p[0]['pl_player_ip'].":".$p[0]['gr_nat_port'];
			}
			
		}else{
			$logo="pc_logo_200.png";
			$type="PC";
		}


/// prepara table ping
$tables.='
	<!-- table Ping --> 
		<div class="card shadow mb-6">
			<div class="card-body">
			<!-- table -->
				<table class="table datatables display table-sm" id="dataTable-ping" style="width:100%">
					<thead>
					<tr>
					<th class="tdSkTimestamp">Timestamp</th>
					<th class="tdSkGiorno">Giorno</th>
					<th class="tdSkPcName">PcName</th>
					<th class="tdSkIpExternal">IP External</th>
					<th class="tdSkNote">Note</th>
					<th class="tdSkStatus">Status</th>
					</tr>
					</thead>
					<tfoot>
					<tr>
					<th class="tdSkTimestamp">Timestamp</th>
					<th class="tdSkGiorno">Giorno</th>
					<th class="tdSkPcName">PcName</th>
					<th class="tdSkIpExternal">IP External</th>
					<th class="tdSkNote">Note</th>
					<th class="tdSkStatus">Status</th>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	<!-- //table Ping --> 
    ';
$scripts.='
    	$(document).ready(function() {
    		var groupColumn = 1;
    		var table=$("#dataTable-ping").DataTable( {
    			
		        "ajax": {
		          "url": "https://yourradio.org/api/monitor/ping/'.$_GET["id"].'",
		          "dataSrc": "data"
		        },
		        "columns": [
		            { "data": "TimeStamp" },
		            { "data": "Giorno" },
		            { "data": "PcName" },
		            { "data": "IpExternal" },
		            { "data": "Note" },
		            { "data": "Status" }
		        ],
		        
		        "ordering": true,
		        "columnDefs": [
            		{ "visible": false, "targets": groupColumn },
            		{ "visible": false, "targets": 5 }
        		],
        		"order": [[ groupColumn, "desc" ]],

        		"rowCallback": function( row, data ) {

		        	$("td:eq(0)",row).addClass("tdSkTimestamp");
		        	$("td:eq(1)",row).addClass("tdSkPcName");
		        	$("td:eq(2)",row).addClass("tdSkIpExternal");
		        	$("td:eq(3)",row).addClass("tdSkNote");

		        
		        },

        		"drawCallback": function ( settings ) {
		            var api = this.api();
		            var rows = api.rows( {page:"current"} ).nodes();
		            var last=null;
		 
		            api.column(groupColumn, {page:"current"} ).data().each( function ( group, i ) {
		                if ( last !== group ) {
		                    $(rows).eq( i ).before("<tr class=\"bg-light\"><td colspan=\"5\">"+group+"</td></tr>");
		                    last = group;
		                }
		            });
		        },

				
				"paging":   false,
        		"info":     false,
		        "searching": false
		    } );

      	} );
    
      
	';
?>


<body class="horizontal dark">
    <div class="wrapper">

    	<?php include_once('inc/menu-h.php'); ?>

    	<main role="main" class="main-content">
        <div class="container-fluid">
          <div class="row justify-content-center">
            <div class="col-12 col-lg-10 col-xl-8">
              <div class="row align-items-center mb-4">
                <div class="col">
                  
                </div>
                <div class="col-auto">
                <?php if($external_url!=''){?>
                	<button type="button" class="btn btn-primary" onclick="window.open('<?=$external_url?>','_blank');">Player on-line</button>
                <?php }?>
                  	<button type="button" class="btn btn-secondary" onclick="window.open('monitor.php','_self');"> Monitor</button>
                </div>
              </div>
              <div class="card shadow">
                <div class="card-body p-5">
                  <div class="row mb-5">
                    <div class="col-12 text-left mb-4">
                      <img src="./assets/images/<?=$logo?>" class="navbar-brand-img brand-sm mx-auto mb-4" alt="...">
                      <h2 class="mb-0 text-uppercase"><?=$p[0]['pl_nome']?></h2>
                      <h4 class="mb-0 text-uppercase"><?=$p[0]['gr_nome']?></h4>
                      <small class="text-muted text-uppercase">ID.</small> <?=$p[0]['pl_id']?>
                    </div>
                    <div class="col-md-7">
                      <p class="mb-4">
                      	Indirizzo: <span class="text-muted text-uppercase"><?=$p[0]['pl_indirizzo']?>, <?=$p[0]['pl_citta']?> [<?=$p[0]['pl_pro']?>]</span><br>
                        Riferimento: <span class="text-muted text-uppercase"><?=$p[0]['pl_riferimento']?></span><br>
                        Telefono: <span class="text-muted text-uppercase"><?=$p[0]['pl_telefono']?></span><br>
                        Mail: <span class="text-muted text-lowercase"><?=$p[0]['pl_mail']?></span><br>
                      </p>
                      <p>
                        Orari: dalle <span class="text-muted text-uppercase"><?=sprintf('%02d', $p[0]['pl_client_ora_on_ora'])?>:<?=sprintf('%02d', $p[0]['pl_client_ora_on_min'])?> alle <?=sprintf('%02d', $p[0]['pl_client_ora_off_ora'])?>:<?=sprintf('%02d', $p[0]['pl_client_ora_off_min'])?></span><br>
                        Type device: <span class="text-muted text-uppercase"><?=$type?></span><br>
                        IP PUBBLICO: <span class="text-muted text-uppercase"><?=$p[0]['pl_player_ip']?></span><br>
                        IP DEVICE: <span class="text-muted text-uppercase"><?=$ipDevice?></span><br>
                        Memoria SD: <span class="text-muted"><?=$p[0]['pl_mem_size']?> (usata al <?=$p[0]['pl_mem_percent']?>%) </span><br>
                        Memoria SD status: <span class="text-muted"> usata:<?=$p[0]['pl_mem_used']?> - disponibile: <?=$p[0]['pl_mem_available']?></span><br>

                      </p>
                    </div>
                    <div class="col-md-5">
                      
                    </div>
                  </div> <!-- /.row -->
                  


                  <?=$tables?>



                  


                </div> <!-- /.card-body -->
              </div> <!-- /.card -->
            </div> <!-- /.col-12 -->
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