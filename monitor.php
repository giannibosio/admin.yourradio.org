<?php
// Start the session
session_start();
include_once('load.php');

if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}

include_once('inc/head.php');




//DB::init();
//$gruppi=Gruppi::selectAllActive();

//foreach ($gruppi as &$g) {
	$tableId = 1;
	$tableName = 'tutti';

	$tables = '';
	$scripts = '';

	$tables.='
	<!-- table '.$tableId.' --> 
		<div class="card shadow mb-6">
			<div class="card-body">
			<!-- table -->
				<table class="table datatables display table-sm" id="dataTable-'.$tableId.'" style="width:100%">
					<thead>
					<tr>
					<th>NOME</th>
					<th class="tdGruppo">GRUPPO</th>
					<th class="tdPing">PING</th>
					<th class="tdIp">IP</th>
					<th class="tdSD">SD</th>
					</tr>
					</thead>
					<tfoot>
					<tr>
					<th>NOME</th>
					<th class="tdGruppo">GRUPPO</th>
					<th class="tdPing">PING</th>
					<th class="tdIp">IP</th>
					<th class="tdSD">SD</th>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	<!-- //table '.$tableId.' --> 
    ';

    $scripts.='
    	$(document).ready(function() {
    		

    		var groupColumn = 1;
    		var table=$("#dataTable-'.$tableId.'").DataTable( {
    			
		        "ajax": {
		          "url": "https://yourradio.org/api/monitor?gruppo='.$tableName.'",
		          "dataSrc": "data"
		        },
		        "columns": [
		            { "data": "nome" },
		            { "data": "gruppo" },
		            { "data": "ping" },
		            { "data": "ip" },
		            { "data": "sd" }
		        ],
		        "rowId": "player_id",
		        "ordering": true,
		        "columnDefs": [
            		{ "visible": true, "targets": groupColumn }
        		],
        		"order": [[ groupColumn, "asc" ]],

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

		        "rowCallback": function( row, data ) {
		        	
		        	$(row).addClass("rowPingMonitor");
		        	$("td:eq(0)",row).addClass("tdNome");
		        	$("td:eq(1)",row).addClass("tdGruppo");
		        	$("td:eq(2)",row).addClass("tdPing");
		        	$("td:eq(3)",row).addClass("tdIp");
		        	$("td:eq(4)",row).addClass("tdSD");

		        	switch(data.status) {
					  case 1:
					    $(row).addClass("pingMonitorGreen");
					    break;
					  case 2:
					    $(row).addClass("pingMonitorRed");
					    break;
					  case 3:
					    $(row).addClass("pingMonitorWhite");
					    break;
					  case 4:
					    $(row).addClass("pingMonitorYellow");
					    break;
					  default:
					    $(row).addClass("pingMonitorWhite");
					}

					if(data.type=="RASPI"){
						$("td:eq(3)",row).addClass("typeRaspi");
					}else{
						$("td:eq(3)",row).addClass("typePc");
					}

					switch(data.sd_status) {
						case 0:
						    $("td:eq(4)",row).addClass("noSd");
						    break;
						case 1:
						    $("td:eq(4)",row).addClass("normalSd");
						    break;
						case 2:
						    $("td:eq(4)",row).addClass("alarmSd");
						    break;
						case 3:
						    $("td:eq(4)",row).addClass("alarmSd");
						    break;
						case 4:
						    $("td:eq(4)",row).addClass("ndSd");
						    break;
						default:
					    	
					}
				    
				  },

				
				"paging":   false,
        		"info":     true,
		        "searching": true
		    } );

		    var dt = new Date()+"";
		    var p = dt.indexOf("GMT");
		    var n = dt.substring(0, p);
    		$("#uploaded").html(n);

		    setInterval( function () {
    			table.ajax.reload();
    			var d = new Date();
    			$("#uploaded").html(d);
			}, 58000 );

			//history.replaceState({},"","http://yourradio.org");

			
			$("body").on("click", "#dataTable-1 tbody tr", function(){
				var id=$(this).attr("id");
  				console.log("Apro scheda "+id);
  				window.open("monitor-scheda.php?id="+id,"_self");
			});

			$( window ).resize(function() {
				if($( window ).width()<400){
					resizeNome();
				}
			});
    
      	} );
    	
	';
//}


?>

<body class="horizontaldark">
    <div class="wrapper">

    <?php include_once('inc/menu-h.php'); ?>

    <main role="main" class="main-content">
        <div class="container-fluid">
          <div class="row justify-content-center">
            <div class="col-12">
              	<div class="row">
                	<!-- Small table -->
                	<div class="col-md-12 my-4 monitor-table">
                		<h2 class="mb-2 page-title">Monitor</h2><div id="uploaded"></div>

                		<?=$tables?>
                  
              		</div>
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