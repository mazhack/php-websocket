<?php
namespace WebSocket;

/**
 * Abstract Connection class
 *
 * @author Juan Enrique Escobar <neblipedia@gmail.com>
 */
abstract class Connection {

  const TYPE_WS = 1;

  const TYPE_AMI = 2;

  /**
   *
   * @var \WebSocket\Application\Application
   */
  protected $application = null;

  /**
   *
   * @var \WebSocket\Server
   */
  protected $server;

  /**
   *
   *
   */
  protected $socket;

  /**
   * debe ser escrita usando _send
   *
   * @var string
   */
  private $write_buffer='';

  /**
   *
   * @var string
   */
  protected $read_buffer='';

  /**
   * close after empty write_buffer
   *
   * @var boolean
   */
  protected $close_in_empty=false;

  protected $connected=false;

  protected $type=null;

  protected $ip=null;

  protected $port=null;

  protected $connectionId = null;

  public function __construct($server, $socket, $ip, $port){
    $this->server = $server;
    $this->socket = $socket;
    $this->ip = $ip;
    $this->port = $port;
    $this->connectionId = md5($this->ip . $this->port . spl_object_hash($this));
    $this->log('Connected');
  }

  public function getType(){
    return $this->type;
  }

  /**
   *
   *
   * @return
   */
  public function hasDataToWrite(){
    if(strlen($this->write_buffer) > 0){
      return $this->socket;
    }
    return false;
  }

  public function doWrite(){
    $length = strlen($this->write_buffer);
    $written = fwrite($this->socket, $this->write_buffer);

    if($written === false || $written == 0){
      // error on write close the socket
      $this->onDisconnect();
      return false;
    }elseif ($written < $length) {
      $this->write_buffer = substr($this->write_buffer, $written);
    } else {
      $this->write_buffer = '';
      if($this->close_in_empty){
        $this->onDisconnect();
        return false;
      }
    }
  }

  protected function _send($data) {
    $this->write_buffer.=$data;
    return true;
  }

  public abstract function send($data);

  public abstract function onData($data);

  public abstract function onDisconnect();

  public function getApplication(){
    return $this->application;
  }

  public function getSocket(){
    return $this->socket;
  }

  public function log($message, $type = 'info'){
    $this->server->log('[client ' . $this->ip . ':' . $this->port . '] ' . $message, $type);
  }

  public function getClientIp(){
    return $this->ip;
  }

  public function getClientPort(){
    return $this->port;
  }

  public function getClientId(){
    return $this->connectionId;
  }

}
