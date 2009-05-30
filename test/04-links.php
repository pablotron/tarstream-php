<?php

# load tarstream
require '../tarstream.php';

# create tar stream
$ts = new TarStream('foo.tar.gz');

$ts->add_file_from_path('foo/rand.blob', 'data/rand.blob');

# add symlink to rand.blob
$ts->add_file_from_path('foo/rand.symlink', 'data/rand.symlink');

# add hardlink to rand.blob
$ts->add_file_from_path('foo/rand.hardlink', 'data/rand.hardlink');

# finish stream and exit
$ts->finish();
exit;

?>
