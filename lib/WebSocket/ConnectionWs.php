<?php
namespace WebSocket;

/**
 * WebSocket Connection class
 *
 * @author Juan Enrique Escobar <neblipedia@gmail.com>
 */
class ConnectionWs extends ConnectionWsBase {

  public function __construct($server, $socket){

    $this->type=self::TYPE_WS;
    // set some client-information:
    $socketName = stream_socket_get_name($socket, true);
    $tmp = explode(':', $socketName);
    parent::__construct($server, $socket, $tmp[0], $tmp[1]);
  }

  protected function handshake($data){
    $this->log('Performing handshake');
    $lines = preg_split("/\r\n/", $data);

    // check for valid http-header:
    if(!preg_match('/\AGET (\S+) HTTP\/1.1\z/', $lines[0], $matches))
    {
      $this->log('Invalid request: ' . $lines[0]);
      $this->sendHttpResponse(400);
      $this->close_in_empty=true;
      return false;
    }

    // check for valid application:
    $path = $matches[1];
    $this->application = $this->server->getApplication(substr($path, 1));
    if(!$this->application)
    {
      $this->log('Invalid application: ' . $path);
      $this->sendHttpResponse(404);
      $this->close_in_empty=true;
      return false;
    }

    // generate headers array:
    $headers = array();
    foreach($lines as $line)
    {
      $line = chop($line);
      if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
      {
        $headers[$matches[1]] = $matches[2];
      }
    }

    // check for supported websocket version:
    if(!isset($headers['Sec-WebSocket-Version']) || $headers['Sec-WebSocket-Version'] < 6)
    {
      $this->log('Unsupported websocket version.');
      $this->sendHttpResponse(501);
      $this->close_in_empty=true;
      return false;
    }

    // check origin:
    if($this->server->getCheckOrigin() === true)
    {
      $origin = (isset($headers['Sec-WebSocket-Origin'])) ? $headers['Sec-WebSocket-Origin'] : false;
      $origin = (isset($headers['Origin'])) ? $headers['Origin'] : $origin;
      if($origin === false)
      {
        $this->log('No origin provided.');
        $this->sendHttpResponse(401);
        $this->close_in_empty=true;
        return false;
      }

      if(empty($origin))
      {
        $this->log('Empty origin provided.');
        $this->sendHttpResponse(401);
        $this->close_in_empty=true;
        return false;
      }

      if($this->server->checkOrigin($origin) === false)
      {
        $this->log('Invalid origin provided.');
        $this->sendHttpResponse(401);
        $this->close_in_empty=true;
        return false;
      }
    }

    // do handyshake: (hybi-10)
    $secKey = $headers['Sec-WebSocket-Key'];
    $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    $response = "HTTP/1.1 101 Switching Protocols\r\n";
    $response.= "Upgrade: websocket\r\n";
    $response.= "Connection: Upgrade\r\n";
    $response.= "Sec-WebSocket-Accept: " . $secAccept . "\r\n";
    if(isset($headers['Sec-WebSocket-Protocol']) && !empty($headers['Sec-WebSocket-Protocol']))
    {
      $response.= "Sec-WebSocket-Protocol: " . substr($path, 1) . "\r\n";
    }
    $response.= "\r\n";

    //$this->write_buffer.=$response;
    $this->_send($response);

    $this->connected = true;
    $this->log('Handshake sent');
    $this->application->onConnect($this);

    return true;
  }

  protected function sendHttpResponse($httpStatusCode = 400)
  {
    $httpHeader = 'HTTP/1.1 ';
    switch($httpStatusCode)
    {
      case 400:
        $httpHeader .= '400 Bad Request';
        break;

      case 401:
        $httpHeader .= '401 Unauthorized';
        break;

      case 403:
        $httpHeader .= '403 Forbidden';
        break;

      case 404:
        $httpHeader .= '404 Not Found';
        break;

      case 501:
        $httpHeader .= '501 Not Implemented';
        break;
    }
    $httpHeader .= "\r\n";

    $this->send($httpHeader);
  }

  protected function processData($data) {
    do{
      $decodedData=$this->hybi10Decode();

      if($decodedData == false){
        break;
      }

      if(!isset($decodedData['type']))
      {
        $this->sendHttpResponse(401);
        $this->close_in_empty=true;
        return false;
      }

      switch($decodedData['type'])
      {
        case 'text':
          $this->application->onData($decodedData['payload'], $this);
          break;

        case 'binary':
          if(method_exists($this->application, 'onBinaryData'))
          {
            $this->application->onBinaryData($decodedData['payload'], $this);
          }
          else
          {
            $this->close(1003);
          }
          break;

        case 'ping':
          $this->send($decodedData['payload'], 'pong', false);
          $this->log('Ping? Pong!');
          break;

        case 'pong':
          // server currently not sending pings, so no pong should be received.
          break;

        case 'close':
          $this->close();
          $this->log('Disconnected');
          break;
      }
    }while(true);

    return true;
  }

  public function send($payload, $type = 'text', $masked = false){
    return parent::send($payload, $type, $masked);
  }

  /**
   * envia la señal de desconexion a la aplicacion y luego llama a onClose para que cierre el socket
   */
  public function onDisconnect()
  {
    if($this->connected){
      $this->log('onDisconnected', 'info');

      $this->close(1000);
      $this->connected=false;

      if($this->application){
        $this->application->onDisconnect($this);
      }
    }
    // lo removemos del listado de clientes
    $this->server->removeClientOnClose($this);
  }

}
