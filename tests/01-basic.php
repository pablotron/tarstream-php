<?php

require '../tarstream.php';

$name = preg_replace('/^[.\/]+|\//', '', $_SERVER['PATH_INFO']);
if (!$name)
  $name = 'foo.tar.gz';

# create tar stream
$ts = new TarStream($name);

# add simple text file
$ts->add_file('foo/simple.txt', 'some simple text file');

# add file with options
$ts->add_file('foo/test.txt', 'This is a sample text file.', array(
  'user'  => 'pabs',
  'uid'   => 1000,
  'gid'   => 1000,
  'mode'  => 0640,
));

$ts->add_file_from_path('tiny.txt', 'tiny.txt');
$ts->add_file_from_path('rand.blob', 'rand.blob');
$ts->add_file_from_path('rand.symlink', 'rand.symlink');
$ts->add_file_from_path('rand.hardlink', 'rand.hardlink');
$ts->add_file_from_path('zero.blob', 'zero.blob');
$ts->add_file_from_path('zero2.blob', 'zero.blob');

$ts->finish();

exit;

?>
