<?php
error_reporting(E_ALL | E_STRICT);

require '../Minify.class.php';

$minify = new Minify();
$minify->setType('css');
$minify->setDirectory('css/');
$minify->set('debug', true);

for($i=1; $i<=10; $i++) {
	
	if($i < 10)
		$i = '00'.$i;
	elseif($i < 100)
		$i = '0'.$i;
	
	if(in_array($i, array(108, 129, 183)))
		break;
	
	$minify->addFile('http://www.csszengarden.com/' . $i . '/' . $i . '.css');

}

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
		echo '<pre>' . htmlentities($link) . '</pre>';
	}
	?>
	</body>
</html>
