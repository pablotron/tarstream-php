<?php

require '../tarstream.php';

$ts = new TarStream('foo.tar');

$ts->add_file('foo/simple.txt', 'some simple text file');

$ts->add_file('foo/test.txt', 'This is a sample text file.', array(
  'user'  => 'pabs',
  'uid'   => 1000,
  'gid'   => 1000,
  'mode'  => 0640,
));

$ts->finish();

exit;

?>
