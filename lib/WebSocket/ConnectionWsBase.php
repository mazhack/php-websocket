<?php
namespace WebSocket;

/**
 * WebSocket Connection class
 *
 * @author Juan Enrique Escobar <neblipedia@gmail.com>
 */
abstract class ConnectionWsBase extends Connection {

  protected $close_on_unmasked=true;

  /**
   * manipula los datos del handshake para establecer la conexion
   *
   * @param unknown $data
   */
  protected abstract function handshake($data);

  public function onData($data){
    if($this->connected){
      $this->read_buffer.=$data;
      return $this->processData($data);
    } else {
      $this->handshake($data);
    }
  }

  public function send($payload, $type = 'text', $masked = false){
    $encodedData = $this->hybi10Encode($payload, $type, $masked);
    return parent::_send($encodedData);
  }

  public function close($statusCode = 1000)
  {
    $payload = str_split(sprintf('%016b', $statusCode), 8);
    $payload[0] = chr(bindec($payload[0]));
    $payload[1] = chr(bindec($payload[1]));
    $payload = implode('', $payload);

    switch($statusCode)
    {
      case 1000:
        $payload .= 'normal closure';
        break;

      case 1001:
        $payload .= 'going away';
        break;

      case 1002:
        $payload .= 'protocol error';
        break;

      case 1003:
        $payload .= 'unknown data (opcode)';
        break;

      case 1004:
        $payload .= 'frame too large';
        break;

      case 1007:
        $payload .= 'utf8 expected';
        break;

      case 1008:
        $payload .= 'message violates server policy';
        break;
    }

    $this->send($payload, 'close', false);
    $this->close_in_empty=true;

  }

  protected function hybi10Encode($payload, $type = 'text', $masked = true)
  {
    $frameHead = array();
    $frame = '';
    $payloadLength = strlen($payload);

    switch($type)
    {
      case 'text':
        // first byte indicates FIN, Text-Frame (10000001):
        $frameHead[0] = 129;
        break;

      case 'close':
        // first byte indicates FIN, Close Frame(10001000):
        $frameHead[0] = 136;
        break;

      case 'ping':
        // first byte indicates FIN, Ping frame (10001001):
        $frameHead[0] = 137;
        break;

      case 'pong':
        // first byte indicates FIN, Pong frame (10001010):
        $frameHead[0] = 138;
        break;
    }

    // set mask and payload length (using 1, 3 or 9 bytes)
    if($payloadLength > 65535)
    {
      $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
      $frameHead[1] = ($masked === true) ? 255 : 127;
      for($i = 0; $i < 8; $i++)
      {
        $frameHead[$i+2] = bindec($payloadLengthBin[$i]);
      }
      // most significant bit MUST be 0 (close connection if frame too big)
      if($frameHead[2] > 127)
      {
        $this->close(1004);
        return false;
      }
    }
    elseif($payloadLength > 125)
    {
      $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
      $frameHead[1] = ($masked === true) ? 254 : 126;
      $frameHead[2] = bindec($payloadLengthBin[0]);
      $frameHead[3] = bindec($payloadLengthBin[1]);
    }
    else
    {
      $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
    }

    // convert frame-head to string:
    foreach(array_keys($frameHead) as $i)
    {
      $frameHead[$i] = chr($frameHead[$i]);
    }
    if($masked === true)
    {
      // generate a random mask:
      $mask = array();
      for($i = 0; $i < 4; $i++)
      {
        $mask[$i] = chr(rand(0, 255));
      }

      $frameHead = array_merge($frameHead, $mask);
    }
    $frame = implode('', $frameHead);

    // append payload to frame:
    $framePayload = array();
    for($i = 0; $i < $payloadLength; $i++)
    {
      $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
    }

    return $frame;
  }

  protected function hybi10Decode(){
    $data=$this->read_buffer;
    $data_len=strlen($data);
    if($data_len == 0){
      return false;
    }

    $payloadLength = '';
    $mask = '';
    $unmaskedPayload = '';
    $decodedData = array();

    // estimate frame type:
    $firstByteBinary = sprintf('%08b', ord($data[0]));
    $secondByteBinary = sprintf('%08b', ord($data[1]));
    $opcode = bindec(substr($firstByteBinary, 4, 4));
    $isMasked = ($secondByteBinary[0] == '1') ? true : false;
    $payloadLength = ord($data[1]) & 127;

    // close connection if unmasked frame is received:
    if($this->close_on_unmasked && $isMasked == false)
    {
      $this->close(1002);
    }

    if($payloadLength === 126)
    {
      if($isMasked){
        $mask = substr($data, 4, 4);
        $payloadOffset = 8;
      }else{
        $payloadOffset=4;
      }
      $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
    }
    elseif($payloadLength === 127)
    {
      if($isMasked){
        $mask = substr($data, 10, 4);
        $payloadOffset = 14;
      }else{
        $payloadOffset=10;
      }
      $tmp = '';
      for($i = 0; $i < 8; $i++)
      {
        $tmp .= sprintf('%08b', ord($data[$i+2]));
      }
      $dataLength = bindec($tmp) + $payloadOffset;
      unset($tmp);
    }
    else
    {
      if($isMasked){
        $mask = substr($data, 2, 4);
        $payloadOffset = 6;
      }else{
        $payloadOffset = 2;
      }
      $dataLength = $payloadLength + $payloadOffset;
    }

    if($data_len < $dataLength){
      return false;
    }

    if($isMasked === true){
      for($i = $payloadOffset; $i < $dataLength; $i++)
      {
        $j = $i - $payloadOffset;
        if(isset($data[$i]))
        {
          $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
        }
      }
      $decodedData['payload'] = $unmaskedPayload;
    }else{
      $decodedData['payload'] = substr($data, $payloadOffset, $dataLength-$payloadOffset);
    }

    $this->read_buffer=substr($data, $dataLength);

    switch($opcode) {
      // text frame:
      case 1:
        $decodedData['type'] = 'text';
        break;

      case 2:
        $decodedData['type'] = 'binary';
        break;

        // connection close frame:
      case 8:
        $decodedData['type'] = 'close';
        break;

        // ping frame:
      case 9:
        $decodedData['type'] = 'ping';
        break;

        // pong frame:
      case 10:
        $decodedData['type'] = 'pong';
        break;

      default:
        // frame incompleto
        return false;
        break;
    }

    return $decodedData;
  }

}
