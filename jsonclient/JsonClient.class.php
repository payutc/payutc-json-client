<?php namespace JsonClient;


use \JsonClient\JsonException;
use \JsonClient\JsonClient;

class JsonException extends \Exception
{
	protected $type;
	protected $http_code;
	
	public function __construct($type, $code, $msg = null, $http_code = null)
	{
		parent::__construct($msg, $code);
		$this->type = $type;
		$this->http_code = $http_code;
	}
	
	public function getType()
	{
		return $this->type;
	}
	
	public function getHttpCode()
	{
		return $this->http_code;
	}
	
	public function __tostring()
	{
		return "JsonException(type={$this->type}, code={$this->getCode()}, msg={$this->getMessage()}, http_code={$this->http_code})";
	}
}

/**
 * Basic client to do POST and GET
 */
class JsonClient
{
	protected $url;
	protected $useragent;
	protected $curl_settings;
	
	public function __construct($url, $service, $curl_settings = array(), $useragent = "Payutc Json PHP Client")
	{
		$this->url = $url . '/' . $service . '/';
		$this->useragent = $useragent;
		$this->curl_settings = $curl_settings + 
			array(
				CURLOPT_USERAGENT => $this->useragent,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_CAINFO => __DIR__."/TERENA_SSL_CA.pem",
			);
	}
	
	/**
	 * @param string $func la fonction du service à appeller
	 * @param array $params un array key=>value des paramètres. default: array()
	 * @param string $method le méthode à utiliser (GET ou POST). default: 'POST'
	 */
	public function apiCall($func, array $params = array(), $method = 'POST')
	{
		// Construction de la chaîne de paramètres
		$paramstring = "";
		if (!empty($params)) {
			foreach ($params as $key => $param) {
				$paramstring .= $key . "=" . $param . "&";
			}
			// On supprimer le dernier &
			$paramstring = substr($paramstring, 0, -1);
		}
		
		// Réglages de cURL
		$settings = $this->curl_settings;
		$settings[CURLOPT_CUSTOMREQUEST] = $method;
		
		// Construction de l'URL et des postfields
		if($method == "GET"){
			$url = $this->url . $func . "?" . $paramstring;
		}
		else {
			$url = $this->url . $func;
			$settings[CURLOPT_POSTFIELDS] = $params;
		}
		
		// Initialisation de cURL
		$ch = curl_init($url);
		curl_setopt_array($ch, $settings);

		// Éxécution de la requête
		$result_encoded = curl_exec($ch);
		
		$result = json_decode($result_encoded);

		// Si erreur d'appel de cron
		if (curl_errno($ch) != 0) {
			throw new JsonException("Unknown", 503, "Erreur d'appel de cron");
		}
		// Si erreur on throw une exception
		if (isset($result->error)) {
			$err = $result->error;
			$type = isset($err->type) ? $err->type : "UnknownType";
			$code = isset($err->code) ? $err->code : "42";
			$message = isset($err->message) ? $err->message : "";
			throw new JsonException($type, $code, $message, curl_getinfo($ch, CURLINFO_HTTP_CODE));
		}
		// Sinon, on renvoie le résultat
		else {
			return $result;
		}
	}
}


/**
 * Auto generate calls client:
 * 
 * $client->myFunc(array("a"=>42)) will remotly call method "myFunc" with 
 * param a=42.
 * 
 */
class AutoJsonClient extends JsonClient
{
	public function __call($name, $arguments)
	{
		if (count($arguments) < 1) {
			return $this->apiCall($name);
		}
		else {
			return $this->apiCall($name, $arguments[0]);
		}
	}
}





