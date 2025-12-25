<?php
// Start the session
session_start();
include_once('load.php');
if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}


DB::init();


$id=$_GET["id"];
if(!isset($_GET["id"]) || $_GET["id"]=0 || $_GET["id"]=''){
  $disabled=" disabled ";
  $id='nuova';
  $title='Nuovo Jingle';
}else{
  $p=Jingles::selectJingleByID($id);
  $disabled="";
  $title=$p[0]['jingle_nome'];
}

///seleziona sottogruppi
$sg=Gruppi::selectSubGruppoByIdPlayer($p[0]['jingle_gr_id']);

print_r($p);

$chbox_active = ($p[0]['jingle_attivo']==1)?"checked":"";
$chbox_programmato = ($p[0]['jingle_programmato']==1)?"checked":"";

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

  $( "#jingle-skinn-chiudi" ).click(function() {
    closeChildTab("tab-jingles");
    console.log("torna al tab lista jingles del gruppo");
  });
</script>
';
?>

           <div class="col-12 col-lg-10 col-xl-12">
            <div class="my-4">

              <div class="alert alert-primary" style="display:none" role="alert">
                <span class="fe fe-alert-circle fe-16 mr-2"></span>
                <span id="msg_alert"></span>
              </div>

              <div class="my-4">

                <div class="row mt-2 align-items-center">
                  <div class="col-md-12 text-center mb-0">
                     Jingle
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
                          <input type="checkbox" class="custom-control-input" <?=$chbox_active?> id="pl_active" name="jingle_attivo" value="1">
                          <label class="custom-control-label" for="jingle_attivo">Player id.<?=$p[0]['jingle_id']?> attivo</label>
                        </div>
                        
                      </div>
                    </div>
                    <div class="form-row  mb-2">
                      <div class="col-md-7 mb-0">
                        <label class="form-scheda-label">Nome</label>
                        <input type="text" class="form-control input-uppercase" id="jingle_nome" name="jingle_nome" value="<?=$p[0]['jingle_nome']?>" required>
                      </div>
                      <div class="col-md-5 mb-0">
                        <label class="form-scheda-label">Gruppo</label>
                        <input type="text" class="form-control input-uppercase" id="gruppo_nome" name="gruppo_nome" value="<?=$p[0]['gr_nome']?>">
                      </div>
                    </div>
                    <div class="form-row mt-5 mb-2">
                      <div class="custom-control custom-checkbox mb-3">
                          <input type="checkbox" class="custom-control-input" <?=$chbox_programmato?> id="jingle_programmato" name="jingle_programmato" value="1">
                          <label class="custom-control-label" for="jingle_programmato">Programmato</label>
                        </div>

                    </div>

                   
                    <div class="accordion mt-3 w-100 inner-scheda" id="accordion-jingle-inner-scheda">

                      <div class="card shadow">
                        <div class="card-header" id="heading-innsk-3">
                          <a role="button" href="#collapse-innsk-3" data-toggle="collapse" data-target="#collapse-innsk-3" aria-expanded="false" aria-controls="collapse-innsk-3" class="title-tab ">
                            <span class="fe fe-layers fe-20"></span><strong>Sottogruppi</strong>
                          </a>
                        </div>
                        <div id="collapse-innsk-3" class="collapse show" aria-labelledby="heading-innsk-3" data-parent="#accordion-player-inner-scheda">
                          <div class="card-body">
                            <?php echo buildCheckSubGroupByIdPlayer($p[0]['jingle_gr_id']);?>
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