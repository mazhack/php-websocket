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
   * @var \Application\Application
   */
  protected $application = null;

  /**
   *
   * @var \WebSocket\Socket
   */
  protected $server;

  /**
   *
   *
   */
  protected $socket;

  /**
   *
   * @var string
   */
  protected $write_buffer='';

  /**
   * close after empty write_buffer
   *
   * @var boolean
   */
  protected $close_in_empty=false;

  /**
   *
   * @var boolean
   */
  protected $waitingForData = false;

  protected $type=null;

  public function __construct($server, $socket){
    $this->server = $server;
    $this->socket = $socket;
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
    $written = @fwrite($this->socket, $this->write_buffer, $length);

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

  public function send($data) {
    $this->write_buffer.=$data;
    return true;
  }

  public abstract function close();

  public abstract function onData($data);

  public abstract function onDisconnect();

  public function getApplication(){
    return $this->application;
  }

  public function getSocket(){
    return $this->socket;
  }

}
