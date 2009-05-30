<?php

# load tarstream
require '../tarstream.php';

# create tar stream
$ts = new TarStream('foo.tar.gz');

# add dynamically generated text file
$ts->add_file('foo/hello_world.txt', 'hello world!');

# add real file from disk
$ts->add_file_from_path('foo/from_disk.txt', 'data/tiny.txt');

# finish sending
$ts->finish();
exit;

?>
