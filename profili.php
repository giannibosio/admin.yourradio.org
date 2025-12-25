<?php
// Start the session
session_start();
include_once('load.php');

if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}

include_once('inc/head.php');

$tableId = 1;
$tableName = 'tutti';

$tables = '';
$scripts = '';

$tables.='
<!-- table '.$tableId.' --> 
<div class="card shadow mb-6">
<div class="card-body">
<!-- table -->
<table class="table datatables display table-sm table-profili" id="dataTable-'.$tableId.'" style="width:100%">
<thead>
<tr>
<th>NOME</th>
<th>GRUPPO</th>
<th class="toHide">MAIL</th>
<th class="toHide">ULTIMO ACCESSO</th>
<th class="toHide">ATTIVO</th>

</tr>
</thead>
<tfoot>
<tr>
<th>NOME</th>
<th>GRUPPO</th>
<th class="toHide">MAIL</th>
<th class="toHide">ULTIMO ACCESSO</th>
<th class="toHide">ATTIVO</th>

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
  var activeColumn = 4;
  var table=$("#dataTable-'.$tableId.'").DataTable( {

    "ajax": {
      "url": "https://yourradio.org/api/utenti",
      "dataSrc": "data"
    },
    "columns": [
    { "data": "nome" },
    { "data": "gruppo" },
    { "data": "mail" },
    { "data": "ultimo_accesso" },
    { "data": "attivo" },

    ],
    "rowId": "id",
    "ordering": true,
    "columnDefs": [
    { "visible": false, "targets": activeColumn }
    ],

    "order": [[ groupColumn, "asc" ]],

    "drawCallback": function ( settings ) {
      var api = this.api();
      var rows = api.rows( {page:"current"} ).nodes();
      var last=null;


      },

      "rowCallback": function( row, data ) {

       $(row).addClass("rowPingMonitor");
       $("td:eq(0)",row).addClass("tdNome");
       $("td:eq(1)",row).addClass("toNeverHide");
       $("td:eq(2)",row).addClass("toHide");
       $("td:eq(3)",row).addClass("toHide");
       $("td:eq(4)",row).addClass("toHide");

       if(data.attivo==1){
        switch(data.gruppo) {
          case "ADMIN":
          $(row).addClass("pingMonitorRed");
          break;
          default:
          $(row).addClass("pingMonitorGreen");
        }
        }else{
          $(row).addClass("pingMonitorWhite");
        }


        },

        "paging":   false,
        "info":     true,
        "searching": true
        } );

        $("body").on("click", "#dataTable-1 tbody tr", function(){
          var id=$(this).attr("id");
          console.log("Apro scheda "+id);
          window.open("profilo-scheda.php?id="+id,"_self");
          });

          } );

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
                        <!-- Small table -->
                        <div class="col-md-12 my-4 monitor-table">
                          <div class="row align-items-center mb-4">
                            <div class="col">
                              <h2 class="mb-2 page-title">
                                <span class="avatar avatar-sm mt-2">
                                  <span class="fe fe-users fe-20"></span> Profili utenti
                                </span>
                              </h2>
                            </div>
                            <div class="col-auto">
                              <button class="btn btn-outline-secondary back-lista" onclick="window.open('profilo-scheda.php','_self');"><span class="fe fe-plus fe-16"></span> Nuovo Profilo</button>
                            </div>
                          </div>
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