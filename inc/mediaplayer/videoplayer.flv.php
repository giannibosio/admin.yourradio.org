<?php 
if (!isset($_SESSION)) { session_start(); }

include_once("../utils.inc.php");


if(!isUserLogged()){
	echo "user unknow!";
	include('logout.php');
	return;
}


$fileName=$_SESSION['mediaplayer']['file'];

$fileTitolo=$_SESSION['mediaplayer']['titolo'];

//if(!file_exists($serverPathFile)){
// echo $fileName.'<br/><div style="background-color:transparent;text-align:center;color:#fff;font-family: Verdana, Tahoma,sans-serif;font-size: 14px;"><br/><b>QUESTO FILE NON E\' DISPONIBILE !</b><br/>Effettuare l\'upolad<br/><br/></div>';
// exit;
// }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset=utf-8 />

<!-- Website Design By: www.happyworm.com -->
<title>Demo : jPlayer as a video player</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<link href="skin/pink.flag/jplayer.pink.flag.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery.jplayer.min.js"></script>
<script type="text/javascript">
//<![CDATA[
$(document).ready(function(){

	$("#jquery_jplayer_1").jPlayer({
		ready: function () {
			$(this).jPlayer("setMedia", {
				flv: "<?php echo $fileName ?>",
				poster: "videoplayer_cover_480x270.jpg"
			});
		},
		swfPath: "js",
		supplied: "flv",
		size: {
			width: "636px",
			height: "360px",
			cssClass: "jp-video-360p"
		},
		smoothPlayBar: true,
		keyEnabled: true
	});
});
//]]>
</script>
</head>
<body>
		<div id="jp_container_1" class="jp-video jp-video-360p">
			<div class="jp-type-single">
				<div id="jquery_jplayer_1" class="jp-jplayer"></div>
				<div class="jp-gui">
					
					<div class="jp-interface">
						<div class="jp-progress">
							<div class="jp-seek-bar">
								<div class="jp-play-bar"></div>
							</div>
						</div>
						<div class="jp-current-time"></div>
						<div class="jp-duration"></div>
						<div class="jp-title">
							<ul>
								<li><?= $fileTitolo?></li>
							</ul>
						</div>
						<div class="jp-controls-holder">
							<ul class="jp-controls">
								<li><a href="javascript:;" class="jp-play" tabindex="1">play</a></li>
								<li><a href="javascript:;" class="jp-pause" tabindex="1">pause</a></li>
								<li><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li>
								<li><a href="javascript:;" class="jp-mute" tabindex="1" title="mute">mute</a></li>
								<li><a href="javascript:;" class="jp-unmute" tabindex="1" title="unmute">unmute</a></li>
								<li><a href="javascript:;" class="jp-volume-max" tabindex="1" title="max volume">max volume</a></li>
							</ul>
							<div class="jp-volume-bar">
								<div class="jp-volume-bar-value"></div>
							</div>

							<ul class="jp-toggles">
								<li><a href="javascript:;" class="jp-full-screen" tabindex="1" title="full screen">full screen</a></li>
								<li><a href="javascript:;" class="jp-restore-screen" tabindex="1" title="restore screen">restore screen</a></li>
								<li><a href="javascript:;" class="jp-repeat" tabindex="1" title="repeat">repeat</a></li>
								<li><a href="javascript:;" class="jp-repeat-off" tabindex="1" title="repeat off">repeat off</a></li>
							</ul>
						</div>
					</div>
				</div>
				<div class="jp-no-solution">
					<span>ERROR</span>
					Questo device non &egrave; abilitato per vedere i video. (Necessario FlashPlayer)
				</div>
			</div>
		</div>
</body>
</html>