<?php
error_reporting(E_ALL | E_STRICT);

require '../Minify.class.php';

for($i=1; $i<=7; $i++) {

	if($i < 10)
		$i = '00'.$i;
	elseif($i < 100)
		$i = '0'.$i;

	if(in_array($i, array(108, 129, 183)))
		break;

	$css_files[] = 'http://www.csszengarden.com/' . $i . '/' . $i . '.css';

}

Minify::add($css_files);
Minify::add('https://ajax.googleapis.com/ajax/libs/chrome-frame/1.0.2/CFInstall.js');
Minify::add('https://ajax.googleapis.com/ajax/libs/dojo/1.6.1/dojo/dojo.xd.js.uncompressed.js');
Minify::add('https://ajax.googleapis.com/ajax/libs/ext-core/3.1.0/ext-core-debug.js');
Minify::add('js/jquery.js');
Minify::add('css/styles.css');
Minify::add('css/test.css');
Minify::run();
Minify::debug();
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<title>Minify - Compress your files on the fly!</title>
	</head>
	<body>
	<?php echo '<pre>' . htmlentities(Minify::getLinks()) . '</pre>'; ?>
	</body>
</html>