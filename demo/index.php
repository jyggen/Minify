<?php
require '../Minify.class.php';
$js = new Minify('js', 'js/', array(), array(), true); 
?>
<!DOCTYPE html> 
<html lang="en"> 
	<head> 
		<meta charset="utf-8" />
		<title>Minify - Compress your files on the fly!</title>
		<?php echo $js->link; ?>
	</head> 
	<body>
	</body>
</html>
