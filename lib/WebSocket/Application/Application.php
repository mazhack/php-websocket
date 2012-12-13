<?php

namespace WebSocket\Application;

/**
 * WebSocket Server Application
 *
 * @author Nico Kaiser <nico@kaiser.me>
 * @author Juan Enrique Escobar <neblipedia@gmail.com>
 */
abstract class Application
{
    protected static $instances = array();

    /**
     * Singleton
     */
    protected function __construct() { }

    final private function __clone() { }

    final public static function getInstance()
    {
        $calledClassName = get_called_class();
        if (!isset(self::$instances[$calledClassName])) {
            self::$instances[$calledClassName] = new $calledClassName();
        }

        return self::$instances[$calledClassName];
    }

    abstract public function onConnect(\WebSocket\Connection $connection);

	abstract public function onDisconnect(\WebSocket\Connection $connection);

	/**
	 * process data of websocket client
	 *
	 * @param $data
	 * @param $client
	 */
	abstract public function onData($data, \WebSocket\Connection $client);

	// Common methods:

	protected function _decodeData($data)
	{
		$decodedData = json_decode($data, true);
		if($decodedData === null)
		{
			return false;
		}

		if(isset($decodedData['action'], $decodedData['data']) === false)
		{
			return false;
		}

		return $decodedData;
	}

	protected function _encodeData($action, $data){
		$payload = array(
			'action' => $action,
			'data' => $data
		);

		return json_encode($payload);
	}
}
