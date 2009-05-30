<?php

class TarStream {
  static $VERSION = '0.1.0';

  static $DEFAULT_OPTIONS = array(
    'send_http_headers'     => true,

    # allow leading slash in file name?
    'allow_absolute_path'   => false,

    # preserve symlinks?
    # FIXME: do we really want this enabled by default?
    'preserve_symlinks'     => true,

    # preserve hard links?
    'preserve_links'        => true,

    'force_gzip'            => false,
    'auto_gzip'             => true,
    'gzip_level'            => -1,
    'gzip_buf_size'         => 16000,
  );

  function __construct($name, $opt = array()) {
    $this->name = $name;
    $this->opt  = array_merge(self::$DEFAULT_OPTIONS, $opt);
    $this->gzip = $this->needs_gzip($this->name, $this->opt);
    $this->bytes_sent = 0;
  }

  function add_file($path, $data, $opt = array()) {
    # build file header
    $header = $this->file_header($path, strlen($data), $opt);

    # pad data to 512-byte boundary
    if (($pad = (512 - (strlen($data) % 512))) != 512)
      $data .= pack("x$pad");

    # send file data
    return $this->send($header . $data);
  }

  static $STAT_OPT_MAP = array(
    'mode'  => 'mode',
    'uid'   => 'uid',
    'gid'   => 'gid',
    'ctime' => 'time',
  );

  function add_file_from_path($name, $path, $src_opt = array()) {
    $st = $this->stat($path);

    # derive default options from stat output
    $opt = array();
    foreach (self::$STAT_OPT_MAP as $src_key => $dst_key)
      $opt[$dst_key] = $st[$src_key];

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
    $size = $opt['link'] ? $st['size'] : 0;

    # build and send file header
    $ret = $this->send($this->file_header($name, $size, $opt));

    # send file contents
    if (!$opt['link']) {
      if ($this->gzip) {
        if (($fh = @fopen($path, 'rb')) === false)
          throw new Exception("fopen() failed for '$path'");

        # read input file
        while (!feof($fh)) {
          # read block
          $buf = fread($fh, $this->opt['gzip_buf_size']);
          $this->send($buf);
        }

        # close input file
        @fclose($fh);
      } else {
        if (@readfile($path) === false)
          throw new Exception("readfile() failed for '$path'");
      }

      # send file padding
      if (($pad = 512 - ($st['size'] % 512)) != 512)
        $this->send(pack("x$pad"));
    }

    # return total number of bytes sent
    return $this->bytes_sent;
  }

  function add_dir($path, $opt = array()) {
    # append slash to file name
    if (substr($path, -1) != '/')
      $path .= '/';

    # set file type
    $opt['type'] = '5';

    return $this->add_file($path, '', $opt);
  }

  function finish() {
    # does nothing, added for compatability with zipstream-php
  }

  private function send($data) {
    if ($this->opt['send_http_headers'] && !$this->http_headers_sent)
      $this->send_http_headers();

    # if gzip is enabled, then compress header
    if ($this->gzip)
      $data = gzencode($data);

    # send data, increment bytes sent
    echo $data;
    $this->bytes_sent += strlen($data);

    # return total number of bytes sent
    return $this->bytes_sent;
  }

  static $HTTP_HEADERS = array(
    'Pragma'                    => 'public',
    'Cache-Control'             => 'public, must-revalidate',
    'Content-Transfer-Encoding' => 'binary',
  );

  #
  # Send HTTP headers for this stream.
  #
  private function send_http_headers() {
    # grab options
    $opt = $this->opt;
    
    # build content type
    $content_type = join('', array(
      'application/',
      $this->gzip ? 'x-gzip' : 'x-tar',
      '; charset=binary',
    ));

    # grab content type from options
    if ($opt['content_type'])
      $content_type = $this->opt['content_type'];

    # grab content disposition 
    $disposition = 'attachment';
    if ($opt['content_disposition'])
      $disposition = $opt['content_disposition'];

    # add filename to disposition
    if ($this->name) 
      $disposition .= "; filename=\"{$this->name}\"";

    # build headers
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


  static $FIELD_LIMITS = array(
    'mode'    => 8,
    'uid'     => 8,
    'gid'     => 8,
    'time'    => 12,
  );

  static $DEFAULT_HEADERS = array(
    # regular file
    'type'  => '0',
  );

  private function check_path($path) {
    foreach (split('/', $path) as $part)
      if ($part == '..')
        throw new Exception("invalida path: cannot contain '..'");
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
      throw new Exception("file path too long ($len > 253)");

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
        throw new Exception("invalid $key value ($val < 0 or $val > $max)");
    }

    # verify type field
    if (strchr('0123456', $opt['type']) === false)
      throw new Exception("invalid type value: {$opt['type']}");

    # check link path length
    $link_len = strlen($opt['link']);
    if ($link_len > 99) 
      throw new Exception("link path too long ($link_len > 99");

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

  private function needs_gzip($name, $opt) {
    if ($opt['force_gzip'])
      return true;

    if ($opt['auto_gzip'])
      return preg_match('/\.t?gz$/', $name);

    return false;
  }

  private function stat($path) {
    # stat file
    $st = ($this->opt['preserve_symlinks']) ? @lstat($path) : @stat($path);

    # check for error
    if ($st === false)
      throw new Exception("stat() failed for '$path'");

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
