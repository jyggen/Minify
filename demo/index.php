<?php
require '../Minify.class.php';
$js = new Minify('js', 'js/', 'scripts.min.js', array(), array('jquery-1.4.2.js', 'websocket.class.js'), true); 
?>
<!DOCTYPE html> 
<html lang="en"> 
	<head> 
		<meta charset="utf-8" />
		<title>Minify - Compress your files on the fly!</title>
	</head> 
	<body>
	</body>
</html>
