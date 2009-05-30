<?php

require '../tarstream.php';

# create tar stream
$ts = new TarStream('foo.tar.gz');

# add simple text file
$ts->add_file('foo/simple.txt', 'some simple text file');

# add a second file with options
$ts->add_file('foo/run_me.sh', 'echo shame on you', array(
  # set creation time to two hours ago
  'time'  => time() - 2 * 3600,

  # mark file as executable
  'mode'  => 0750,
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
