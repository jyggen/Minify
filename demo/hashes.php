<?php /* check what hash algorithms your system supports */ ?>

<pre>

<?php

echo 'Building random data ...' . PHP_EOL;
@ob_flush();flush();

$data = '';
for ($i = 0; $i < 64000; $i++)
    $data .= hash('md5', rand(), true);

echo strlen($data) . ' bytes of random data built !' . PHP_EOL . PHP_EOL . 'Testing hash algorithms ...' . PHP_EOL;
@ob_flush();flush();

$results = array();
foreach (hash_algos() as $v) {
    echo $v . PHP_EOL;
    @ob_flush();flush();
    $time = microtime(true);
    $hash = hash($v, $data, false);
    $time = microtime(true) - $time;
    $results[$time * 1000000000] = array('name' => "$v (hex)", 'hash' => $hash);
    $time = microtime(true);
    $hash = hash($v, $data, true);
    $time = microtime(true) - $time;
    $results[$time * 1000000000] = array('name' => "$v (raw)", 'hash' => $hash);
}

ksort($results);

echo PHP_EOL . PHP_EOL . 'Results: ' . PHP_EOL;

$i = 1;
echo '<table><tr><td>Hash:</td><td>Name:</td><td>Time:</td></tr>';
foreach ($results as $k => $v)
        echo '<tr><td>' . $v['hash'] . '</td><td>' . $v['name'] . '</td><td>' . ($k / 1000) . ' microseconds' . '</td></tr>';

?>

</pre>
