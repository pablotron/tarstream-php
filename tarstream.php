<?php

##########################################################################
# TarStream-PHP - Streamed, dynamically generated tar archives.          #
# by Paul Duncan <pabs@pablotron.org>                                    #
#                                                                        #
# Copyright (C) 2009 Paul Duncan <pabs@pablotron.org>                    #
#                                                                        #
# Permission is hereby granted, free of charge, to any person obtaining  #
# a copy of this software and associated documentation files (the        #
# "Software"), to deal in the Software without restriction, including    #
# without limitation the rights to use, copy, modify, merge, publish,    #
# distribute, sublicense, and/or sell copies of the Software, and to     #
# permit persons to whom the Software is furnished to do so, subject to  #
# the following conditions:                                              #
#                                                                        #
# The above copyright notice and this permission notice shall be         #
# included in all copies or substantial portions of the of the Software. #
#                                                                        #
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,        #
# EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF     #
# MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. #
# IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR      #
# OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,  #
# ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR  #
# OTHER DEALINGS IN THE SOFTWARE.                                        #
##########################################################################

class TarStream_Error extends Exception {};

#
# TarStream - Streamed, dynamically generated tar archives.
# by Paul Duncan <pabs@pablotron.org>
#
# Requirements:
#
# * PHP version 5.1.2 or newer.
#
# Usage:
#
# Streaming tar archives is a simple, three-step process:
#
# 1.  Create the tar stream:
#
#     $tar = new TarStream('example.tar.gz');
#
# 2.  Add one or more files to the archive:
#
#     # add first file (dynamically generated)
#     $data = "I am a sample text file";
#     $tar->add_file('some_file.txt', $data);
#
#     # add second file (from existing file)
#     $tar->add_file_from_path('another_file.png', 'path/to/foo.png');
#
# 3.  Finish the tar stream:
#
#     $tar->finish();
#
# TarStream will automatically compress the generated tarball with gzip
# or bzip2 based on the output filename.  For example, a file named
# "example.tar.gz" will be compressed with gzip, while "example.tar.bz2"
# will be compressed with bzip2.
#
# You can also override the timestamp, owner, and type of files as you
# add them to the archive.  See the API documentation for each method
# below for additional information.
#
# Example:
#
#     # create a new TarStream object
#     $tar = new TarStream('some_files.tar.gz');
#
#     # add a dynamically generated text file
#     $data = "Hello from TarStream-PHP!";
#     $tar->add_file("some_files/hello.txt", $data);
#
#     # list of local files
#     $files = array('foo.txt', 'bar.jpg');
#
#     # read and add each file to the archive
#     foreach ($files as $path)
#       $tar->add_file_from_path("some_files/$path", $path);
#
#     # finish writing archive to output
#     $tar->finish();
#
class TarStream {
  #
  # Release version of TarStream-PHP.
  #
  static $VERSION = '0.1.0';

  #
  # Default options for a new TarStream object.  You can override any of
  # these options by passing a second parameter to the TarStream
  # constructor, like so:
  #
  #     # create new archive and override the 'preserve_symlinks` and
  #     # 'allow_absolute_path' options
  #     $tar = new TarStream('example.tar.tz', array(
  #       'preserve_symlinks'   => false,
  #       'allow_absolute_path' => true,
  #     ));
  #
  static $DEFAULT_OPTIONS = array(
    # Send HTTP headers?
    'send_http_headers'     => true,

    # Allow leading slash in file name?
    'allow_absolute_path'   => false,

    #
    # Preserve symbolic links?
    #
    # If enabled, TarStream will honor symbolic links; that is, if you
    # pass a symbolic link to add_file_from_path(), then TarStream will
    # add a symbolic link to the archive.  If this option is set to
    # false, then TarStream will dereference symbolic links and add them
    # as regular files.
    #
    'preserve_symlinks'     => true,

    #
    # Preserve hard links?
    #
    # If enabled, TarStream will keep an intelligent cache of hard
    # links and attempt to preserve then within the generated archive.
    #
    # For example:
    #
    #     # create a sample text file named "a.txt"
    #     $ echo "it really tied the room together" > a.txt
    #
    #     # hard link "b.txt" to "a.txt"
    #     $ ln a.txt b.txt
    #
    #     # ... later on in PHP:
    #
    #     # create new streamed archive
    #     $tar = new TarStream('example.tar.gz');
    #
    #     # add a.txt to the archive
    #     $tar->add_file_from_path('example/a.txt', 'a.txt');
    #
    #     #
    #     # add b.txt to the archive
    #     #
    #     # Note: At this point TarStream will notice that b.txt is a
    #     # hard link to a file that already exists in the archive,
    #     # and add it as such.
    #     #
    #     $tar->add_file_from_path('example/b.txt', 'b.txt');
    #
    #     # ... continue adding files
    #
    # It's probably best to leave this option enabled.
    #
    'preserve_links'        => true,

    #
    # Automatically determine compression type based on filename?
    #
    # If this is true then files with a suffix of '.tar.gz', '.tgz',
    # '.tar.bz2', '.tbz2', '.tb2', '.tbz', and '.tbz2' will be
    # automatically compressed with the appropriate compression
    # algorithm.
    #
    'auto_compress'         => true,

    #
    # Default compression level (number).
    #
    # If set to 'auto' (the default), then TarStream uses the default
    # compression level for the compression algorithm in use.
    #
    # It's probably best to leave this option at the default setting.
    #
    # Note that the exact meaning of this property varies depending on
    # the compression algorithm used; see TarStream::$COMPRESSION_TYPES
    # below for additional information.
    #
    'compress_level'        => 'auto',

    #
    # Input buffer size, in bytes.
    #
    # A larger buffer size means more memory (RAM) use, but better file
    # compression.  Conversely, a smaller buffer size will lower server
    # memory use, but files will not compress as well.
    #
    'buffer_size'           => 16384,
  );

  #
  # Hash of compression types supported by TarStream.  You can add your
  # own compression types here if you'd like.  For example:
  #
  #     # Add support for a new compression type "foobar" to TarStream
  #     # with a mime type of "application/x-foobar", using the
  #     # compression function "foobar_compress()", and file extensions
  #     # ".tar.foobar" and ".tfb":
  #     TarStreawm::$COMPRESSION_TYPES['foobar'] = array(
  #       'compress_fn'   => 'foobar_compress',
  #       'mime'          => 'application/x-foobar',
  #       'default_level' => -1,
  #       'extension_re'  => '/\.(tfb|tar\.foobar)$/',
  #     );
  #
  #     # ... later in code
  #
  #     # create new tar stream object using custom compression
  #     $tar = new TarStream('example.tar.foobar');
  #
  # Note that your compression algorithm must be supported on the
  # client-side; otherwise users will be unable to extract files from
  # the archive.
  #
  static $COMPRESSION_TYPES = array(
    # gzip compressed tar archives
    'gzip' => array(
      # gzip compression function
      'compress_fn'   => 'gzencode',

      # mime type for gzipped tar files
      'mime'          => 'application/x-gzip',

      # default compression level
      'default_level' => -1,

      # regular expression specifying matching filename suffixes
      'extension_re'  => '/\.(tgz|tar\.gz)$/',
    ),

    # bzip2 compressed tar archives
    'bzip2' => array(
      # bzip2 compression function
      'compress_fn'   => 'bzcompress',

      # mime type for bzipped tar files
      'mime'          => 'application/x-bzip2',

      # default compression level
      'default_level' => 4,

      # regular expression specifying matching filename suffixes
      'extension_re'  => '/\.(tb2|tbz2?|tar\.bz2)$/',
    ),
  );

  # declare private instance variables
  private $name, $opt, $compress, $bytes_sent,
          $inode_cache, $http_headers_sent = false;

  #
  # Create a new TarStream object.
  #
  function __construct($name = null, $opt = array()) {
    $this->name       = $name;
    $this->opt        = array_merge(self::$DEFAULT_OPTIONS, $opt);
    $this->compress   = $this->should_compress($this->name, $this->opt);
    $this->bytes_sent = 0;
  }

  #
  # Add dynamic file to TarStream object.
  #
  # Parameters:
  # 
  #   * path: path and name of file inside archive (string, required).
  #   * data: file contents (string, required).
  #   * opt:  optional hash of file attributes (hash, optional).  See
  #     the "File Options" section below for a list of available
  #     options.
  # 
  # Examples:
  # 
  #   * Add a simple text file to the archive:
  #
  #     # file contents
  #     $data = 'This is the contents of hello.txt.';
  #     
  #     # add file
  #     $tar->add_file('foo/hello.txt', $data);
  # 
  #   * Add a text file and set the timestamp to one hour ago:
  # 
  #     # file contents
  #     $data = 'This is the contents of hello.txt.';
  #     
  #     # add file with options
  #     $tar->add_file('foo/hello.txt', $data, array(
  #       'time' => time() - 3600, # one hour ago
  #     ));
  # 
  function add_file($path, $data, $opt = array()) {
    # build file header
    $header = $this->file_header($path, strlen($data), $opt);

    # pad data to 512-byte boundary
    if (($pad = (512 - (strlen($data) % 512))) != 512)
      $data .= pack("x$pad");

    # send file data
    return $this->send($header . $data);
  }

  private static $STAT_OPT_MAP = array(
    'mode'  => 'mode',
    'uid'   => 'uid',
    'gid'   => 'gid',
    'ctime' => 'time',
  );

  #
  # Add existing file to TarStream object.
  #
  function add_file_from_path($name, $path, $src_opt = array()) {
    $st = $this->wrap_stat($path);

    # derive default options from stat output
    $opt = array();
    foreach (self::$STAT_OPT_MAP as $st_key => $opt_key)
      $opt[$opt_key] = $st[$st_key];

    # extract symlink path
    if ($this->opt['preserve_symlinks'] && is_link($path)) {
      $opt['type'] = '2';
      $opt['link'] = readlink($path);
    }

    # add additional settings from user options
    $opt = array_merge($opt, $src_opt);

    # check for hard links
    if ($this->opt['preserve_links'] && !is_dir($path) && $st['nlink'] > 1) {
      # lazy-load inode cache
      if (!$this->inode_cache)
        $this->inode_cache = array();

      # build key for this file
      $key = join('-', array(
        $st['dev'],
        $st['ino'],
      ));

      if ($this->inode_cache[$key]) {
        # use previous link
        $opt['type'] = '1';
        $opt['link'] = $this->inode_cache[$key];
      } else {
        # add file to inode cache
        $this->inode_cache[$key] = $name;
      }
    }

    # get file size
    $size = ($opt['type'] == 2) ? 0 : $st['size'];

    # build and send file header
    $ret = $this->send($this->file_header($name, $size, $opt));

    # send file contents
    if (!$opt['link']) {
      if ($this->compress) {
        if (($fh = @fopen($path, 'rb')) === false)
          throw new TarStream_Error("fopen() failed for '$path'");

        # read input file
        $file_len = 0;
        while (!feof($fh)) {
          # read chunk
          $buf = fread($fh, $this->opt['buffer_size']);

          # send file chunk
          if ($buf !== false && ($len = strlen($buf)) > 0) {
            $this->send($buf);
            $file_len += $len;
          }
        }

        # make sure the file size hasn't changed between the call to
        # stat() and the calls to fread()
        if ($file_len != $size)
          throw new TarStream_Error("file sizes differ: fread() = $file_len, stat() = $size");

        # close input file
        @fclose($fh);
      } else {
        # compression is disabled, so we can use readfile()
        if (($sent = @readfile($path)) === false)
          throw new TarStream_Error("readfile() failed for '$path'");

        # make sure the file size hasn't changed between the call to
        # stat() and the call to readfile()
        if ($sent != $size)
          throw new TarStream_Error("file sizes differ: readfile() = $sent, stat() = $size");

        # add file size to output count
        $this->bytes_sent += $size;
      }

      # send file padding
      if (($pad = 512 - ($size % 512)) != 512)
        $this->send(pack("x$pad"));
    }

    # return total number of bytes sent
    return $this->bytes_sent;
  }

  #
  # Add empty directory to TarStream object.
  #
  # Note: this method is not strictly necessary; decompression programs
  # will create directories as necessary, so you really only need to use
  # this method if you want to create empty directories.
  #
  # Example:
  #
  #     # add empty directory named 'foo/bar' to tar file
  #     $tar->add_dir('foo/bar');
  #
  function add_dir($path, $opt = array()) {
    # append slash to file name
    if (substr($path, -1) != '/')
      $path .= '/';

    # set file type
    $opt['type'] = '5';

    return $this->add_file($path, '', $opt);
  }

  #
  # Finish sending a TarStream object.
  #
  # Note: this method exists for compatability with ZipStream-PHP and
  # currently does nothing, although that may change in the future.
  #
  # Example:
  #
  #     # finish tar stream
  #     $tar->finish();
  #
  function finish() {
    # does nothing, added for compatability with zipstream-php
  }

  private function send($data) {
    if ($this->opt['send_http_headers'] && !$this->http_headers_sent)
      $this->send_http_headers();

    # if compression is enabled, then compress header
    if ($this->compress) {
      # get compression info
      $info = self::$COMPRESSION_TYPES[$this->compress];

      # get compression function
      $fn = $info['compress_fn'];

      # get compression level
      $level = $this->opt['compress_level'];
      if ($level == 'auto')
        $level = $info['default_level'];

      # compress data
      $data = $fn($data, $level);
    }

    # was a callback specified?
    if ($cb = $this->opt['callback']) {
      # we have a callback function, so
      # pass our data to it
      if (is_array($cb)) {
        list($obj, $fn) = $cb;
        $obj->$fn($data);
      } else {
        $cb($data);
      }
    } else {
      # echo data to output stream
      echo $data;
    }

    # increment byte count
    $this->bytes_sent += strlen($data);

    # return total number of bytes sent
    return $this->bytes_sent;
  }

  private static $HTTP_HEADERS = array(
    'Pragma'                    => 'public',
    'Cache-Control'             => 'public, must-revalidate',
    'Content-Transfer-Encoding' => 'binary',
  );

  #
  # Get the suggested content-type for this stream
  # (can be overridden by user)
  #
  private function get_content_type() {
    $ret = 'application/x-tar';

    if ($this->compress)
      $ret = self::$COMPRESSION_TYPES[$this->compress]['mime'];

    return "$ret; charset=binary";
  }

  #
  # Send HTTP headers for this stream (private).
  #
  private function send_http_headers() {
    # grab options
    $opt = $this->opt;

    # set content type
    $content_type = $this->get_content_type();
    if ($opt['content_type'])
      $content_type = $this->opt['content_type'];

    # set content disposition
    $disposition = 'attachment';
    if ($opt['content_disposition'])
      $disposition = $opt['content_disposition'];

    # add filename to disposition (if specified)
    if ($this->name)
      $disposition .= "; filename=\"{$this->name}\"";

    # build http headers
    $headers = array_merge(self::$HTTP_HEADERS, array(
      'Content-Type'        => $content_type,
      'Content-Disposition' => $disposition,
    ));

    # send http headers
    foreach ($headers as $key => $val)
      header("$key: $val");

    # mark headers as sent
    $this->http_headers_sent = true;
  }

  private static $FIELD_LIMITS = array(
    'mode'    => 8,
    'uid'     => 8,
    'gid'     => 8,
    'time'    => 12,
  );

  private static $DEFAULT_HEADERS = array(
    # regular file
    'type'  => '0',
  );

  private function check_path($path) {
    foreach (split('/', $path) as $part)
      if ($part == '..')
        throw new TarStream_Error("invalida path: cannot contain '..'");
  }

  #
  # create ustar tar header for file
  #
  private function file_header($path, $size, $opt = array()) {
    # strip leading slashes from path
    if (!$this->opt['allow_absolute_path'])
      $path = preg_replace('/^\/+/', '', $path);
    $this->check_path($path);

    # populate default options
    $opt = array_merge(self::$DEFAULT_HEADERS, $opt);

    # set time and mode
    if (!$opt['time'])
      $opt['time'] = time();
    if (!$opt['mode'])
      $opt['mode'] = octdec('0644');

    # check path length
    $len = strlen($path);
    if ($len > 253)
      throw new TarStream_Error("file path too long ($len > 253)");

    # check file prefix
    $prefix = '';
    if ($len > 99) {
      $prefix = substr($path, 0, $len - 100);
      $path = substr($path, $len - 100);
      $len = strlen($path);
    }

    # verify numeric field values
    foreach (self::$FIELD_LIMITS as $key => $max) {
      $val = $opt[$key];
      $max = pow(8, $max);
      if ($val && ($val < 0 || $val > $max))
        throw new TarStream_Error("invalid $key value ($val < 0 or $val > $max)");
    }

    # verify type field
    if (strchr('0123456', $opt['type']) === false)
      throw new TarStream_Error("invalid type value: {$opt['type']}");

    # check link path length
    $link_len = strlen($opt['link']);
    if ($link_len > 99)
      throw new TarStream_Error("link path too long ($link_len > 99");

    # generate header
    $ret = $this->pack_fields(array(
      array('a100',         $path),                           # name
      array('@100a8',       $this->octal($opt['mode'],  8)),  # mode
      array('@108a8',       $this->octal($opt['uid'],   8)),  # uid
      array('@116a8',       $this->octal($opt['gid'],   8)),  # gid
      array('@124a12',      $this->octal($size,         12)), # size
      array('@136a12',      $this->octal($opt['time'],  12)), # time
      array('@149a8',       '        '),                      # checksum
      array('@157a1',       $opt['type']),                    # type
      array('@158a100',     $opt['link']),                    # link
      array('@258a6',       'ustar'),                         # ustar tag
      array('@263a2',       '  '),                            # ustar version
      array('@266a32',      $opt['user']),                    # user name
      array('@298a32',      $opt['group']),                   # user name
      array('@330a8',       $opt['major']),                   # dev major
      array('@338a8',       $opt['minor']),                   # dev minor
      array('@346a155',     $prefix),                         # prefix
      array('@513')                                           # padding
    ));

    # calculate header checksum
    $sum = 0;
    for ($i = 0; $i < 512; $i++)
      $sum += ord($ret[$i]);

    # create checksum string
    $checksum = pack('a7a1', str_pad(decoct($sum), 6, '0', STR_PAD_LEFT), ' ');

    # apply checksum and return result
    return substr_replace($ret, $checksum, 148, 9);
  }

  private function should_compress($name, $opt) {
    if ($opt['compress'])
      return $opt['compress'];

    # if name is defined and auto_compress is enabled (and it is by
    # default), then test to see if the user-specified name matches any
    # known compression types
    if ($name && $opt['auto_compress'])
      foreach (self::$COMPRESSION_TYPES as $key => $data)
        if (preg_match($data['extension_re'], $name))
          return $key;

    # no compression
    return false;
  }

  private function wrap_stat($path) {
    # stat file
    $st = ($this->opt['preserve_symlinks']) ? @lstat($path) : @stat($path);

    # check for error
    if ($st === false)
      throw new TarStream_Error("stat() failed for '$path'");

    # return result
    return $st;
  }

  #
  # Create a format string and argument list for pack(), then call
  # pack() and return the result.
  #
  private function pack_fields($fields) {
    list ($fmt, $args) = array('', array());

    # populate format string and argument list
    foreach ($fields as $field) {
      $fmt .= $field[0];

      if (count($field) > 1)
        $args[] = $field[1];
    }

    # prepend format string to argument list
    array_unshift($args, $fmt);

    # build output string from header and compressed data
    return call_user_func_array('pack', $args);
  }

  #
  # convert a numeric value to a octal, left-padded with zeros
  #
  private function octal($num, $size) {
    # convert number octal string, left-padded with zeros
    return str_pad(decoct($num), $size - 1, '0', STR_PAD_LEFT);
  }
};

?>
