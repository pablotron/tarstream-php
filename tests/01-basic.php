<?php

require '../tarstream.php';

$ts = new TarStream('foo.tar.gz');

$ts->add_file('foo/test.txt', 'This is a sample text file.');
$ts->finish();

exit;

?>
