<?php

# finish stream and exit
require '../tarstream.php';

# load filename from request
# if the user enters .tar.gz or .tar.bz2 as a suffix then the
# archive will be automagically compressed
$name = preg_replace('/^[.\/]+|\//', '', $_SERVER['PATH_INFO']);
if (!$name)
  $name = 'foo.tar.gz';

# create tar stream
$ts = new TarStream($name);

# add simple text file
$ts->add_file('foo/simple.txt', 'some simple text file');

# add file from disk that compresses well
$ts->add_file_from_path('foo/zero.blob', 'data/zero.blob');

# finish stream and exit
$ts->finish();
exit;

?>
