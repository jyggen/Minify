<?php
error_reporting(E_ALL | E_STRICT);

require '../Minify.class.php';

$minify = new Minify();

$minify->set('debug', true);
$minify->set('type', 'css');
$minify->set('directory', 'css/');
$minify->set('merge', false);

$minify->run();
$minify->reset();

$minify->set('debug', true);
$minify->set('type', 'js');
$minify->set('directory', 'js/');
$minify->set('merge', false);

$minify->run();
?>
<!DOCTYPE html> 
<html lang="en"> 
	<head> 
		<meta charset="utf-8" />
		<title>Minify - Compress your files on the fly!</title>
	</head> 
	<body>
	<?php
	foreach($minify->links as $link) {
		echo htmlentities($link) . '<br>';
	}
	?>
	</body>
</html>
