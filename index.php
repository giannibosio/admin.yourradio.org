<?php
// Start the session
session_start();

include_once('load.php');

if(!isset($_SESSION["nome"])){
  header("location:auth-login.php");
}

/// crea grafico player attivi in top - ora usa API endpoint
$scriptChartTop="

  <style>
  @media (max-width: 575.98px) {
    .hide-mobile{
      display:none;
    }
  }

  .apexcharts-menu {
    background: #333;
  }
  .apexcharts-menu-item {
    padding: 3px 3px;
    font-size: 12px;
    cursor: pointer;
    text-align: center;
  }

  .apexcharts-menu-item:hover {
    color:#fff;
    background-color: #666 !important;
  }

  .apexcharts-tooltip {
    background: #999;
    color: #fff;
  }
  .apexcharts-yaxis{
    margin-top:3px;
    border:1px solid #f00;
  }
  </style>
  <script>
  // Carica i dati dal nuovo endpoint API
  function loadChartData() {
    $.ajax({
      url: 'https://yourradio.org/api/dashboard/graph',
      method: 'GET',
      dataType: 'json',
      success: function(response) {
        if(response.success && response.data) {
          var data = response.data;
          
          // Prepara i dati per il grafico
          var gruppi = data.gruppi || [];
          var playersON = data.on || [];
          var playersOFF = data.off || [];
          var playersOFS = data.ofs || [];
          
          // Crea il grafico con i dati ricevuti
          createChart(gruppi, playersON, playersOFF, playersOFS, data.percentages);
        } else {
          console.error('Errore nel caricamento dati:', response);
          $('#topchart').html('<div style=\"text-align:center;padding:20px;color:#fff;\">Errore nel caricamento dei dati</div>');
        }
      },
      error: function(xhr, status, error) {
        console.error('Errore AJAX:', error);
        $('#topchart').html('<div style=\"text-align:center;padding:20px;color:#fff;\">Errore di connessione</div>');
      }
    });
  }
  
  function createChart(gruppi, playersON, playersOFF, playersOFS, percentages) {
    var options = {
      colors: ['#26cb26','#ef1a04','#ffa500'],
      series: [
        {
          name: 'ON',
          data: playersON
        },
        {
          name: 'OFF',
          data: playersOFF
        },
        {
          name: 'OUT OF SERVICE',
          data: playersOFS
        }
      ],
      chart: {
        type: 'bar',
        height: 550,
        stacked: true,
        toolbar: {
          show: true
        }
      },
      plotOptions: {
        bar: {
          horizontal: true,
          startingShape: 'flat',
          endingShape: 'flat',
          columnWidth: '60%',
          barHeight: '80%',
          distributed: false,
          rangeBarOverlap: true,
          rangeBarGroupRows: false,
          colors: {
              ranges: [{
                  from: 0,
                  to: 0,
                  color: undefined
              }],
              backgroundBarColors: [],
              backgroundBarOpacity: 1,
              backgroundBarRadius: 0
          },
          dataLabels: {
              position: 'top',
              maxItems: 100,
              hideOverflowingLabels: true,
              orientation: 'horizontal'
          }
        }
      },
      stroke: {
        width: 0,
        colors: ['#26cb26','#ef1a04','#ffa500']
      },
      title: {
        text: 'Players',
        style: {
          fontSize:  '20px',
          fontWeight:  'bold',
          fontFamily:  undefined,
          color:  '#FFFFFF'
        },
      },
      xaxis: {
        type: 'category',
        categories: gruppi,
        labels: {
          formatter: function (val) {
            return val
          },
          style: {
            colors: '#ddd'
          }
        },
      },
      yaxis: {
        title: {
          text: undefined,
          rotate: 90,
          offsetX: 0,
          offsetY: 0,
          style: {
            color: '#f00',
            fontSize: '12px',
            fontFamily: 'Helvetica, Arial, sans-serif',
            fontWeight: 600,
            cssClass: 'apexcharts-yaxis-title',
          },
        },
        labels: {
          style: {
            maxHeight: 120,
            fontSize:'12px',
            colors: '#fff'
          }
        }
      },
      tooltip: {
        y: {
          formatter: function (val) {
            return val + ' players'
          }
        },
        theme: true
      },
      fill: {
        opacity: 1
      },
      legend: {
        position: 'top',
        horizontalAlign: 'center',
        offsetX: 40,
        labels: {
          colors: '#fff',
          useSeriesColors: false
        },
        markers: {
          width: 12,
          height: 12,
          strokeWidth: 0,
          strokeColor: '#f00',
          fillColors: ['#26cb26','#ef1a04','#ffa500'],
          
          customHTML: undefined,
          onClick: undefined,
          offsetX: 0,
          offsetY: 0
        }
      },
      noData: {
        text: '...loading...',
        align: 'center',
        verticalAlign: 'middle',
        offsetX: 0,
        offsetY: 0,
        style: {
          color: '#fff',
          fontSize: '14px',
        }
      }
    };

    var chart = new ApexCharts(document.querySelector('#topchart'), options);
    chart.render();
    
    // Aggiorna i grafici radiali con le percentuali
    if(percentages) {
      updateRadialCharts(percentages);
    }
    
    // Debug: verifica i dati
    console.log('Dati grafico caricati:', {
      ON: playersON,
      OFF: playersOFF,
      OFS: playersOFS,
      Gruppi: gruppi,
      Percentages: percentages
    });
  }
  
  function updateRadialCharts(percentages) {
    // Aggiorna grafico ON
    if(document.querySelector('#radialbar-on')) {
      var optionsOn = {
        chart: {
          height: 350,
          type: 'radialBar',
        },
        series: [percentages.on || 0],
        colors: ['#26cb26'],
        plotOptions: {
          radialBar: {
            track: {
              background: '#444',
            },
            dataLabels: {
              name: {
                offsetY: -10,
                color: '#26cb26',
                fontSize: '23px'
              },
              value: {
                color: '#26cb26',
                fontSize: '30px',
                show: true
              }
            }
          }
        },
        labels: ['ON LINE']
      };
      var chartOn = new ApexCharts(document.querySelector('#radialbar-on'), optionsOn);
      chartOn.render();
    }
    
    // Aggiorna grafico OFF
    if(document.querySelector('#radialbar-off')) {
      var optionsOff = {
        chart: {
          height: 350,
          type: 'radialBar',
        },
        series: [percentages.off || 0],
        colors: ['#ef1a04'],
        plotOptions: {
          radialBar: {
            track: {
              background: '#444',
            },
            dataLabels: {
              name: {
                offsetY: -10,
                color: '#ef1a04',
                fontSize: '23px'
              },
              value: {
                color: '#ef1a04',
                fontSize: '30px',
                show: true
              }
            }
          }
        },
        labels: ['PAUSE']
      };
      var chartOff = new ApexCharts(document.querySelector('#radialbar-off'), optionsOff);
      chartOff.render();
    }
    
    // Aggiorna grafico OFS
    if(document.querySelector('#radialbar-ofs')) {
      var optionsOfs = {
        chart: {
          height: 350,
          type: 'radialBar',
        },
        series: [percentages.ofs || 0],
        colors: ['#ffa500'],
        plotOptions: {
          radialBar: {
            track: {
              background: '#444',
            },
            dataLabels: {
              name: {
                offsetY: -10,
                color: '#ffa500',
                fontSize: '23px'
              },
              value: {
                color: '#ffa500',
                fontSize: '30px',
                show: true
              }
            }
          }
        },
        labels: ['OFF']
      };
      var chartOfs = new ApexCharts(document.querySelector('#radialbar-ofs'), optionsOfs);
      chartOfs.render();
    }
  }
  
  // Carica i dati quando il documento è pronto
  $(document).ready(function() {
    loadChartData();
  });

        // I grafici radiali vengono creati dalla funzione updateRadialCharts() quando arrivano i dati dall'API
        // Non sono più necessari qui perché i dati vengono caricati dinamicamente


        $( '.refresh-page' ).click(function() {
          location.reload();
        });

        $( '.go-to-monitor' ).click(function() {
          window.open('monitor.php','_self');
        });


</script>
";

include_once('inc/head.php');
?>


  <body class="horizontal  dark  ">
    <div class="wrapper">

      <?php include_once('inc/menu-h.php'); ?>

      <main role="main" class="main-content">
        <div class="container-fluid">
          <div class="row justify-content-center">
            <div class="col-12">
              <!-- content -->
              
              <div class="row align-items-center mb-2">
                <div class="col">
                  <h2 class="h5 page-title">Ciao <?=$_SESSION["nome"]?>!</h2>
                </div>
                <div class="col-auto">
                  <form class="form-inline">
                    <div class="form-group d-none d-lg-inline">
                      <div class="px-2 py-2 text-muted">
                        <span class="small"><?=dateByNow()?></span>
                      </div>
                    </div>
                    <div class="form-group">
                      <button type="button" class="btn btn-sm refresh-page"><span class="fe fe-refresh-ccw fe-16 text-muted"></span></button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="mb-2 align-items-center hide-mobile">

                <div class="card shadow mb-4">
                  <div class="card-body">
                    <div class="chartbox mr-4">
                      <div id="topchart"></div>
                      <div style="text-align:center;width:100%"><button type="button" class="btn mb-2 btn-secondary btn-sm go-to-monitor">Monitor</button></div>
                    </div>
                    
                  </div> <!-- .card-body -->

                </div> <!-- .card -->

              </div>


              <div class="row items-align-baseline">
                


                <div class="col-md-12 col-lg-4">
                  <div class="card shadow eq-card mb-4">
                    <div class="card-body">
                      <div class="chart-widget mb-2">
                        <div id="radialbar-on"></div>
                      </div>
                    </div> <!-- .card-body -->
                  </div> <!-- .card -->
                </div> <!-- .col -->


                <div class="col-md-12 col-lg-4">
                  <div class="card shadow eq-card mb-4">
                    <div class="card-body">
                      <div class="chart-widget mb-2">
                        <div id="radialbar-off"></div>
                      </div>
                    </div> <!-- .card-body -->
                  </div> <!-- .card -->
                </div> <!-- .col -->



                <div class="col-md-12 col-lg-4">
                  <div class="card shadow eq-card mb-4">
                    <div class="card-body">
                      <div class="chart-widget mb-2">
                        <div id="radialbar-ofs"></div>
                      </div>
                    </div> <!-- .card-body -->
                  </div> <!-- .card -->
                </div> <!-- .col -->


              </div> <!-- .row -->

             


            </div> <!-- .col-12 -->
          </div> <!-- .row -->
        </div> <!-- .container-fluid -->
        <div class="modal fade modal-notif modal-slide" tabindex="-1" role="dialog" aria-labelledby="defaultModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="defaultModalLabel">Notifications</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body">
                <div class="list-group list-group-flush my-n3">
                  <div class="list-group-item bg-transparent">
                    <div class="row align-items-center">
                      <div class="col-auto">
                        <span class="fe fe-box fe-24"></span>
                      </div>
                      <div class="col">
                        <small><strong>Package has uploaded successfull</strong></small>
                        <div class="my-0 text-muted small">Package is zipped and uploaded</div>
                        <small class="badge badge-pill badge-light text-muted">1m ago</small>
                      </div>
                    </div>
                  </div>
                  <div class="list-group-item bg-transparent">
                    <div class="row align-items-center">
                      <div class="col-auto">
                        <span class="fe fe-download fe-24"></span>
                      </div>
                      <div class="col">
                        <small><strong>Widgets are updated successfull</strong></small>
                        <div class="my-0 text-muted small">Just create new layout Index, form, table</div>
                        <small class="badge badge-pill badge-light text-muted">2m ago</small>
                      </div>
                    </div>
                  </div>
                  <div class="list-group-item bg-transparent">
                    <div class="row align-items-center">
                      <div class="col-auto">
                        <span class="fe fe-inbox fe-24"></span>
                      </div>
                      <div class="col">
                        <small><strong>Notifications have been sent</strong></small>
                        <div class="my-0 text-muted small">Fusce dapibus, tellus ac cursus commodo</div>
                        <small class="badge badge-pill badge-light text-muted">30m ago</small>
                      </div>
                    </div> <!-- / .row -->
                  </div>
                  <div class="list-group-item bg-transparent">
                    <div class="row align-items-center">
                      <div class="col-auto">
                        <span class="fe fe-link fe-24"></span>
                      </div>
                      <div class="col">
                        <small><strong>Link was attached to menu</strong></small>
                        <div class="my-0 text-muted small">New layout has been attached to the menu</div>
                        <small class="badge badge-pill badge-light text-muted">1h ago</small>
                      </div>
                    </div>
                  </div> <!-- / .row -->
                </div> <!-- / .list-group -->
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-block" data-dismiss="modal">Clear All</button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal fade modal-shortcut modal-slide" tabindex="-1" role="dialog" aria-labelledby="defaultModalLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="defaultModalLabel">Shortcuts</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body px-5">
                <div class="row align-items-center">
                  <div class="col-6 text-center">
                    <div class="squircle bg-success justify-content-center">
                      <i class="fe fe-cpu fe-32 align-self-center text-white"></i>
                    </div>
                    <p>Control area</p>
                  </div>
                  <div class="col-6 text-center">
                    <div class="squircle bg-primary justify-content-center">
                      <i class="fe fe-activity fe-32 align-self-center text-white"></i>
                    </div>
                    <p>Activity</p>
                  </div>
                </div>
                <div class="row align-items-center">
                  <div class="col-6 text-center">
                    <div class="squircle bg-primary justify-content-center">
                      <i class="fe fe-droplet fe-32 align-self-center text-white"></i>
                    </div>
                    <p>Droplet</p>
                  </div>
                  <div class="col-6 text-center">
                    <div class="squircle bg-primary justify-content-center">
                      <i class="fe fe-upload-cloud fe-32 align-self-center text-white"></i>
                    </div>
                    <p>Upload</p>
                  </div>
                </div>
                <div class="row align-items-center">
                  <div class="col-6 text-center">
                    <div class="squircle bg-primary justify-content-center">
                      <i class="fe fe-users fe-32 align-self-center text-white"></i>
                    </div>
                    <p>Users</p>
                  </div>
                  <div class="col-6 text-center">
                    <div class="squircle bg-primary justify-content-center">
                      <i class="fe fe-settings fe-32 align-self-center text-white"></i>
                    </div>
                    <p>Settings</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
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
    <script src="js/d3.min.js"></script>
    <script src="js/topojson.min.js"></script>
    <script src="js/datamaps.all.min.js"></script>
    <script src="js/datamaps-zoomto.js"></script>
    <script src="js/datamaps.custom.js"></script>
    <script src="js/Chart.min.js"></script>
    <script>
      /* defind global options */
      Chart.defaults.global.defaultFontFamily = base.defaultFontFamily;
      Chart.defaults.global.defaultFontColor = colors.mutedColor;
    </script>
    <script src="js/gauge.min.js"></script>
    <script src="js/jquery.sparkline.min.js"></script>
    <script src="js/apexcharts.min.js"></script>
    <script src="js/apexcharts.custom.js"></script>
    <script src='js/jquery.mask.min.js'></script>
    <script src='js/select2.min.js'></script>
    <script src='js/jquery.steps.min.js'></script>
    <script src='js/jquery.validate.min.js'></script>
    <script src='js/jquery.timepicker.js'></script>
    <script src='js/dropzone.min.js'></script>
    <script src='js/uppy.min.js'></script>
    <script src='js/quill.min.js'></script>


<?=$scriptChartTop?>






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