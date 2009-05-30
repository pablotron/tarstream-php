<?php

# load tarstream
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

# finish stream and exit
$ts->finish();
exit;

?>
