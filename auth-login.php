<?php
session_start();

include_once('load.php');

if (isset($_COOKIE['lastpage'])){$startpage=$_COOKIE['lastpage'];}else{$startpage="index.php";}

if(isset($_SESSION["login"])){header("location:".$startpage); }
if(isset($_GET['t']) && $_GET['t']=='logout'){
  session_unset();
}

$msg='';
if(isset($_POST['inputLogin']) && isset($_POST['inputPassword'])){
  // Usa l'API per l'autenticazione invece della connessione diretta al database
  $apiUrl = "https://yourradio.org/api/auth/login";
  $postData = json_encode([
    'login' => $_POST['inputLogin'],
    'password' => $_POST['inputPassword']
  ]);
  
  $ch = curl_init($apiUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($postData)
  ]);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if($httpCode == 200) {
    $apiResponse = json_decode($response, true);
    if(isset($apiResponse['success']) && $apiResponse['success'] && isset($apiResponse['data'])) {
      $user = $apiResponse['data'];
      $_SESSION["login"] = $user['login'];
      $_SESSION["password"] = $_POST['inputPassword'];
      $_SESSION["nome"] = $user['nome'];
      $_SESSION["userID"] = $user['id'];
      header("location:".$startpage);
      exit;
    } else {
      $msg = isset($apiResponse['error']['message']) ? $apiResponse['error']['message'] : "Errore durante il login";
    }
  } else {
    $apiResponse = json_decode($response, true);
    if(isset($apiResponse['error']['message'])) {
      $msg = $apiResponse['error']['message'];
    } else {
      $msg = "Errore durante la connessione al server di autenticazione";
    }
  }
}
include_once('inc/head.php');
?>
  <body class="dark ">
    <div class="wrapper vh-100">
      <div class="row align-items-center h-100">
        <form class="col-lg-3 col-md-4 col-10 mx-auto text-center" method="post" action="<?=$_SERVER['PHP_SELF']?>">
          <a class="navbar-brand mx-auto mt-2 flex-fill text-center" href="./index.php">
            <img src="./assets/images/logo-yourradio-maxi.png" alt="Yourradio"> 
          </a>
          <div class="form-group">
            <label for="inputEmail" class="sr-only">Email address</label>
            <!--<input type="email" name="inputEmail" id="inputEmail" class="form-control form-control-lg" placeholder="Email address" required="" autofocus="">-->
            <input type="text" name="inputLogin" id="inputLogin" class="form-control form-control-lg" placeholder="Login utente" required="" autofocus="">
          </div>
          <div class="form-group">
            <label for="inputPassword" class="sr-only">Password</label>
            <input type="password" name="inputPassword" id="inputPassword" class="form-control form-control-lg" placeholder="Password" required="">
          </div>
          <div class="login-alert">
            <?=$msg?>
          </div>
          <!--<div class="checkbox mb-3">
            <label>
              <input type="checkbox" value="remember-me"> Stay logged in </label>
          </div>-->
          <button class="btn btn-lg btn-primary btn-block" type="submit" >Entra</button>
          <p class="mt-5 mb-3 text-muted">YourRadio© 2020</p>
        </form>
      </div>
    </div>
    <script src="js/jquery.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/moment.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/simplebar.min.js"></script>
    <script src='js/daterangepicker.js'></script>
    <script src='js/jquery.stickOnScroll.js'></script>
    <script src="js/tinycolor-min.js"></script>
    <script src="js/config.js"></script>
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