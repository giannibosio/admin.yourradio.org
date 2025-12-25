<?php 
if (!isset($_SESSION)) { session_start(); }

set_include_path("../");
include_once("utils.inc.php");

if(!isUserLogged()){
	include('adm-login.php');
	return;
}
$fileName=$_SESSION['mediaplayer']['file'];
if(strlen(@$_GET['filename'])>5){$fileName=@$_GET['filename'];}

$fileTitolo=$_SESSION['mediaplayer']['titolo'];
//echo $fileName;
if(!file_exists($fileName)){

 echo '<br/><div style="background-color:transparent;text-align:center;color:#fff;font-family: Verdana, Tahoma,sans-serif;font-size: 14px;"><br/><b>QUESTO FILE NON E\' DISPONIBILE !</b><br/>Effettuare l\'upolad<br/><br/></div>';
 exit;
 }

if(strlen($fileTitolo)>35){$fileTitolo=substr($fileTitolo,0,35)."...";}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset=utf-8 />

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<link href="skin/yourradio/jplayer.yourradio.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery.jplayer.min.js"></script>
<script type="text/javascript">
//<![CDATA[
$(document).ready(function(){

	$("#jquery_jplayer_1").jPlayer({
		ready: function () {
			$(this).jPlayer("setMedia", {
				mp3:"<?php echo $fileName ?>"
			});
		},
		swfPath: "js",
		supplied: "mp3",
		wmode: "window",
		smoothPlayBar: true,
		keyEnabled: true
	});
});
//]]>
</script>
</head>
<body style="margin:0px;background-color:#eee">
<center><br/>
		<div id="jquery_jplayer_1" class="jp-jplayer" ></div>

		<div id="jp_container_1" class="jp-audio" style="border:none">
			<div class="jp-type-single">
				<div class="jp-gui jp-interface">
					<ul class="jp-controls">
						<li><a href="javascript:;" class="jp-play" tabindex="1">play</a></li>
						<li><a href="javascript:;" class="jp-pause" tabindex="1">pause</a></li>
						<li><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li>
						
					</ul>
					<div class="jp-progress">
						<div class="jp-seek-bar">
							<div class="jp-play-bar"></div>
						</div>
					</div>
					<div class="jp-time-holder">
						<div class="jp-current-time"></div>
						<div class="jp-duration"></div>

					</div>
				</div>
			
			</div>
		</div>
</body>

</html>
