<?php 
session_start();

//include_once('../load.php');

$_SESSION['songs']['totalPathFile'];
$fileName="../../player/song/".$_SESSION['mediaplayer']['file'].".mp3";
$fileTitolo=$_SESSION['mediaplayer']['artista']." - ".$_SESSION['mediaplayer']['titolo'];

// echo $_SESSION['songs']['totalPathFile'];
if(!file_exists($_SESSION['songs']['totalPathFile'])){
 echo '<br/><div style="background-color:transparent;text-align:center;color:#fff;font-family: Verdana, Tahoma,sans-serif;font-size: 14px;"><b>FILE NON DISPONIBILE !</b><br/>Effettuare l\'upolad<br/><br/></div>';
 exit;
 }

//require_once ('class/class.Id3v1.php'); 
//$id3v1 = new Id3v1($_SESSION['songs']['totalPathFile']); 
//$id3v1->setTitle($_SESSION['mediaplayer']['titolo']);
//$id3v1->setArtist($_SESSION['mediaplayer']['artista']); 
//$id3v1->setAlbum('---');
//$id3v1->setComment('www.yourradio.org');
//$id3v1->setYear(intval($_SESSION['mediaplayer']['anno']));
//$id3v1->save();

if(strlen($fileTitolo)>35){$fileTitolo=substr($fileTitolo,0,35)."...";}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset=utf-8 />

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<link href="skin/blue.monday/jplayer.blue.monday.css" rel="stylesheet" type="text/css" />
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
<body style="margin:0px;">

		<div id="jquery_jplayer_1" class="jp-jplayer" ></div>

		<div id="jp_container_1" class="jp-audio" style="border:none">
			<div class="jp-type-single">
				<div class="jp-gui jp-interface">
					<ul class="jp-controls">
						<li><a href="javascript:;" class="jp-play" tabindex="1">play</a></li>
						<li><a href="javascript:;" class="jp-pause" tabindex="1">pause</a></li>
						<li><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li>
						<li><a href="javascript:;" class="jp-mute" tabindex="1" title="mute">mute</a></li>
						<li><a href="javascript:;" class="jp-unmute" tabindex="1" title="unmute">unmute</a></li>
						<li><a href="javascript:;" class="jp-volume-max" tabindex="1" title="max volume">max volume</a></li>
					</ul>
					<div class="jp-progress">
						<div class="jp-seek-bar">
							<div class="jp-play-bar"></div>
						</div>
					</div>
					<div class="jp-volume-bar">
						<div class="jp-volume-bar-value"></div>
					</div>
					<div class="jp-time-holder">
						<div class="jp-current-time"></div>
						<div class="jp-duration"></div>

						<ul class="jp-toggles">
							<li><a href="javascript:;" class="jp-repeat" tabindex="1" title="repeat">repeat</a></li>
							<li><a href="javascript:;" class="jp-repeat-off" tabindex="1" title="repeat off">repeat off</a></li>
						</ul>
					</div>
				</div>
				<!--
				<div class="jp-title">
					<ul>
						<li><?php 
							//echo $id3v1->getArtist()." - ".$id3v1->getTitle();
							echo $fileTitolo;
							?></li>
					</ul>
				</div>-->
				<div class="jp-no-solution">
					<span>Update Required</span>
					To play the media you will need to either update your browser to a recent version or update your <a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>.
				</div>
			</div>
		</div>
</body>

</html>
