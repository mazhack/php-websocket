<?php

namespace WebSocket;

/**
 * Socket class
 *
 * @author Moritz Wutz <moritzwutz@gmail.com>
 * @author Nico Kaiser <nico@kaiser.me>
 * @author Juan Enrique Escobar <neblipedia@gmail.com>
 * @version 0.2
 */

/**
 * This is the main socket class
 */
class Socket {
  /**
   * @var Socket Holds the master socket
   */
  protected $master;

  /**
   * @var array Holds all connected sockets
   */
  protected $allsockets = array();
  protected $context = null;
  protected $ssl = false;

  public function __construct($host = 'localhost', $port = 8000, $ssl = false, $pem=null)
  {
    ob_implicit_flush(true);
    $this->ssl = $ssl;
    $this->pem=$pem;
    $this->createSocket($host, $port);
  }

  /**
   * Create a socket on given host/port
   *
   * @param string $host The host/bind address to use
   * @param int $port The actual port to bind on
   */
  private function createSocket($host, $port) {
    $protocol = 'tcp://';
    $this->context = stream_context_create();
    if($this->ssl === true){
      $protocol = 'tls://';
      $this->applySSLContext();
    }
    $url = $protocol.$host.':'.$port;
    if(!$this->master = stream_socket_server($url, $errno, $err, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $this->context))
    {
      die('Error creating socket: ' . $err);
    }
    stream_set_timeout($this->master, 2);
    stream_set_blocking($this->master, 1);
    $this->allsockets[] = $this->master;
  }

  private function applySSLContext() {
    //$pem_file = './server.pem';

    // Generate PEM file
    /*
    //
    if(!file_exists($pem_file))
    {
    $dn = array(
        "countryName" => "CO",
        "stateOrProvinceName" => "Santander",
        "localityName" => "Bucaramanga",
        "organizationName" => "Radio Taxis Libres S.A.",
        "organizationalUnitName" => "RTLSA",
        "commonName" => "lostaxis.com",
        "emailAddress" => "neblipedia@gmail.com"
    );
    $privkey = openssl_pkey_new();
    $cert    = openssl_csr_new($dn, $privkey);
    $cert    = openssl_csr_sign($cert, null, $privkey, 365);
    $pem = array();
    openssl_x509_export($cert, $pem[0]);
    openssl_pkey_export($privkey, $pem[1]);
    $pem = implode($pem);
    file_put_contents($pem_file, $pem);
    }
    */

    // apply ssl context:
    stream_context_set_option($this->context, 'ssl', 'local_cert', $this->pem);
    stream_context_set_option($this->context, 'ssl', 'allow_self_signed', true);
    stream_context_set_option($this->context, 'ssl', 'verify_peer', false);
  }

  // method originally found in phpws project:
  protected function readBuffer($resource){
      $buffer = '';
      $buffsize = 8192;
      do
      {
        if(feof($resource))
        {
          return false;
        }
        $result = fread($resource, $buffsize);
        if($result === false || feof($resource))
        {
          return false;
        }
        if(strlen($result) === 1 && $this->ssl){
          $result .= fread($resource, $buffsize);
        }
        $buffer .= $result;
        $metadata = stream_get_meta_data($resource);
        $unread=$metadata['unread_bytes'];
        $buffsize = ( $unread > $buffsize) ? $buffsize : $metadata['unread_bytes'];
      } while($unread > 0);

      return $buffer;
  }
}
