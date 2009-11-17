<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2009, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace lithium\http;

use \lithium\core\Libraries;

/**
 * Basic Http Service
 *
 */
class Service extends \lithium\core\Object {

	/**
	 * Request Object
	 *
	 * @var object
	 */
	public $request =  null;

	/**
	 * Holds all parameters of the request
	 * Cast to object in the constructor
	 *
	 * @var object
	 */
	public $response = null;

	/**
	 * Holds the request and response used by send
	 *
	 * @var object
	 */
	public $last = null;

	/**
	 * auto config
	 *
	 * @var array
	 */
	protected $_autoConfig = array('classes' => 'merge');

	/**
	 * The `Socket` instance used to send `Service` calls
	 *
	 * @var \lithium\util\Socket
	 */
	protected $_connection = null;

	/**
	 * Indicates whether `Service` can connect to the HTTP endpoint for which it is configured.
	 * Defaults to true until a connection attempt fails.
	 *
	 * @var boolean
	 */
	protected $_isConnected = false;

	/**
	 * Fully-namespaced class references to `Service` class dependencies.
	 *
	 * @var array
	 */
	protected $_classes = array(
		'media'    => 'lithium\http\Media',
		'request'  => '\lithium\http\Request',
		'response' => '\lithium\http\Response',
		'socket'   => 'lithium\util\socket\Stream'
	);

	/**
	 * Initializes a new `Service` instance with the default HTTP request settings and
	 * transport- and format-handling classes.
	 *
	 * @param array $config
	 * @return void
	 */
	public function __construct($config = array()) {
		$defaults = array(
			'persistent' => false,
			'protocol'   => 'tcp',
			'host'       => 'localhost',
			'version'    => '1.1',
			'auth'       => 'Basic',
			'login'      => 'root',
			'password'   => '',
			'port'       => 80,
			'timeout'    => 1,
			'encoding'   => 'UTF-8',
			'classes'    => array()
		);
		$config = (array)$config + $defaults;

		$config['auth'] = array(
			'method'   => $config['auth'],
			'username' => $config['login'],
			'password' => $config['password']
		);
		parent::__construct($config);
	}

	/**
	 * Connect to datasource
	 *
	 * @return boolean
	 */
	public function connect() {
		if (!$this->_connection) {
			$socket = Libraries::locate('sockets.util', $this->_classes['socket']);
			$this->_connection = new $socket($this->_config);
		}
		if (!$this->_isConnected && $this->_connection->open()) {
			$this->_isConnected = true;
		}
		return $this->_isConnected;
	}

	/**
	 * Disconnect from socket
	 *
	 * @return boolean
	 */
	public function disconnect() {
		if ($this->_isConnected) {
			$this->_isConnected = !$this->_connection->close();
		}
		return !$this->_isConnected;
	}

	/**
	 * Send GET request
	 *
	 * @param string $path
	 * @param array $data
	 * @return string
	 */
	public function get($path = null, $data = array(), $options = array()) {
		return $this->send(__FUNCTION__, $path, $data, $options);
	}

	/**
	 * Send POST request
	 *
	 * @param string path
	 * @param array data
	 * @return string
	 */
	public function post($path = null, $data = array(), $options = array()) {
		return $this->send(__FUNCTION__, $path, $data, $options);
	}

	/**
	 * Send PUT request
	 *
	 * @param string path
	 * @param array data
	 * @return string
	 */
	public function put($path = null, $data = array(), $options = array()) {
		return $this->send(__FUNCTION__, $path, $data, $options);
	}

	/**
	 * Send DELETE request
	 *
	 * @param string path
	 * @param array params
	 * @return string
	 */
	public function delete($path = null, $data = array(), $options = array()) {
		return $this->send(__FUNCTION__, $path, $data, $options);
	}

	/**
	 * Send request and return response data
	 *
	 * @param string path
	 * @return string
	 */
	public function send($method, $path = null, $data = null, $options = array()) {
		$defaults = array('type' => 'form', 'return' => 'body');
		$options += $defaults;

		if ($this->connect() === false) {
			return false;
		}
		$request = $this->_request($method, $path, $data, $options);

		if ($this->_connection->write((string)$request)) {
			$message = $this->_connection->read();
			$response = new $this->_classes['response'](compact('message'));

			$this->last = (object)compact('request', 'response');
			$this->disconnect();

			return ($options['return'] == 'body') ? $response->body() : $response;
		}
	}

	/**
	 * Instantiates a request object (usually an instance of `http\Request`) and tests its
	 * properties based on the request type and data to be sent.
	 *
	 * @param string $method The HTTP method of the request, i.e. `'GET'`, `'HEAD'`, `'OPTIONS'`,
	 *               etc. Can be passed in upper- or lowercase.
	 * @param string $path The
	 * @param string $data
	 * @param string $options
	 * @return object Returns an instance of `http\Request`, configured with an HTTP method, query
	 *         string or POST/PUT data, and URL.
	 */
	protected function _request($method, $path, $data, $options) {
		$request = new $this->_classes['request']($this->_config + $options);
		$request->path = str_replace('//', '/', "/{$path}");
		$request->method = $method = strtoupper($method);
		$media = $this->_classes['media'];

		if ($data && !is_string($data) && in_array($options['type'], $media::types())) {
			$type = $media::type($options['type']);
			$contentType = (array)$type['content'];

			$request->headers(array('Content-Type' => current($contentType)));
			$data = Media::encode($options['type'], $data, $options);
		}
		in_array($method, array('POST', 'PUT')) ? $request->body($data) : $request->params = $data;
		return $request;
	}
}

?>