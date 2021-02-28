<?php

/**
 * PHP library version: 1.8.0
 */

class Worldpay
{

    /**
     * Library variables
     * */

    private $service_key = "";
    private $timeout = 150;
    private $disable_ssl = false;
    private $endpoint = 'https://api.worldpay.com/v1/';
    private $order_types = array('ECOM', 'MOTO', 'RECURRING');
    private $pluginName = false;
    private $pluginVersion = false;
    private $shopperSessionId = false;

    private static $errors = array(
        "ip"        => "Invalid parameters",
        "cine"      => "php_curl was not found",
        "to"        => "Request timed out",
        "nf"        => "Not found",
        "apierror"  => "API Error",
        "uanv"      => "Worldpay is currently unavailable, please try again later",
        "contact"   => "Error contacting Worldpay, please try again later",
        'ssl'       => 'You must enable SSL check in production mode',
        'verify'    => 'Worldpay not verifiying SSL connection',
        'orderInput'=> array(
            'token'             => 'No token found',
            'orderCode'         => 'No order_code entered',
            'orderDescription'  => 'No order_description found',
            'amount'            => 'No amount found, or it is not a whole number',
            'currencyCode'      => 'No currency_code found',
            'name'              => 'No name found',
            'billingAddress'    => 'No billing_address found'
        ),
        'notificationPost'      => 'Notification Error: Not a post',
        'notificationUnknown'   => 'Notification Error: Cannot be processed',
        'refund'    =>  array(
            'ordercode'         => 'No order code entered'
        ),
        'capture'    =>  array(
            'ordercode'         => 'No order code entered'
        ),
        'json'      => 'JSON could not be decoded',
        'key'       => 'Please enter your service key',
        'sslerror'  => 'Worldpay SSL certificate could not be validated',
        'timeouterror'=> 'Gateway timeout - possible order failure. Please review the order in the portal to confirm success.'
    );

    /**
     * Library constructor
     * @param string $service_key
     *  Your worldpay service key
     * @param int $timeout
     *  Connection timeout length
     * */
    public function __construct($service_key = false, $timeout = false)
    {
        if ($service_key == false) {
            self::onError("key");
        }
        $this->service_key = $service_key;

        if ($timeout !== false) {
            $this->timeout = $timeout;
        }

        if (!function_exists("curl_init")) {
            self::onError("cine");
        }

        //
    }

    /**
     * Set api endpoint
     * @param string
     * */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * Set plugin data
     * @param string
     * @param string
     * */
    public function setPluginData($name, $version)
    {
        $this->pluginName = $name;
        $this->pluginVersion = $version;
    }

    /**
     * Gets the client IP by checking $_SERVER
     * @return string
     * */
    private function getClientIp()
    {
        $ipaddress = '';

        if( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif ( isset($_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( isset($_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( isset($_SERVER['HTTP_X_FORWARDED'] ) ) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif ( isset($_SERVER['HTTP_FORWARDED_FOR'] ) ) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif ( isset($_SERVER['HTTP_FORWARDED'] ) ) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif ( isset($_SERVER['REMOTE_ADDR'] ) ) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }
        return $this->isValidIP( $ipaddress );
    }

    private function isValidIP( $ipaddress )
    {
        // No need to check this one
        if( $ipaddress === 'UNKNOWN' ) {
            return $ipaddress;
        }

        // If the IP address is valid send it back
        if( filter_var( $ipaddress, FILTER_VALIDATE_IP ) ) {
            return $ipaddress;
        }

        // Clean up the IP6 address
        if ( strpos( $ipaddress, ':' ) !== false ) {

            // Make an array of the chunks
            $ip = explode( ":", $ipaddress );

            // Only the first 8 chunks count
            $ip = array_slice( $ip, 0, 8 );

            // Make sure each chunk is 4 characters long and only contains letters and numbers
            foreach( $ip as &$value ) {
                $value = substr( $value, 0, 4 );
                $value = preg_replace( '/\W/', '', $value );
            }

            unset( $value );

            // Combine the chunks and return the IP6 address
            return implode( ":", $ip );

        }

        // Clean up the IP4 address
        if ( strpos( $ipaddress, '.' ) !== false ) {

            // Make an array of the chunks
            $ip = explode( ".", $ipaddress );

            // Only the first 4 chunks count
            $ip = array_slice( $ip, 0, 4 );

            // Make sure each chunk is 3 characters long and only contains numbers
            foreach( $ip as &$value ) {
                $value = substr( $value, 0, 3 );
                $value = preg_replace( '/\D/', '', $value );
            }

            unset( $value );

            // Combine the chunks and return the IP4 address
            return implode( ".", $ip );

        }

        // Fallback
        return $ipaddress;
    }    

    /**
     * Checks if variable is a float
     * @param float $number
     * @return bool
     * */
    private function isFloat($number)
    {
        return !!strpos($number, '.');
    }

    /**
     * Checks order input array for validity
     * @param array $order
     * */
    private function checkOrderInput($order)
    {
        $errors = array();
        if (empty($order) || !is_array($order)) {
            self::onError('ip');
        }
        if (!isset($order['token'])) {
            $errors[] = self::$errors['orderInput']['token'];
        }
        if (!isset($order['orderDescription'])) {
            $errors[] = self::$errors['orderInput']['orderDescription'];
        }
        if (!isset($order['amount']) || ($order['amount'] > 0 && $this->isFloat($order['amount']))) {
            $errors[] = self::$errors['orderInput']['amount'];
        }
        if (!isset($order['currencyCode'])) {
            $errors[] = self::$errors['orderInput']['currencyCode'];
        }
        if (!isset($order['name'])) {
            $errors[] = self::$errors['orderInput']['name'];
        }
        if (!isset($order['billingAddress'])) {
            $errors[] = self::$errors['orderInput']['billingAddress'];
        }

        if (count($errors) > 0) {
            self::onError('ip', implode(', ', $errors));
        }
    }

    /**
     * Sends request to Worldpay API
     * @param string $action
     * @param string $json
     * @param bool $expectResponse
     * @param string $method
     * @return string JSON string from Worldpay
     * */
    private function sendRequest( $action, $json = false, $expectResponse = false, $method = 'POST' ) {

        $arch = (bool)((1<<32)-1) ? 'x64' : 'x86';

        $clientUserAgent = 'os.name=' . php_uname('s') . ';os.version=' . php_uname('r') . ';os.arch=' .
                            $arch . ';lang.version='. phpversion() . ';lib.version=1.8.0;' . 'api.version=v1;lang=php;owner=worldpay';

        if ($this->pluginName) {
             $clientUserAgent .= ';plugin.name=' . $this->pluginName;
        }

        if ($this->pluginVersion) {
             $clientUserAgent .= ';plugin.version=' . $this->pluginVersion;
        }

        $remote_post = array(
                            'method'        => $method,
                            'timeout'       => $this->timeout,
                            'redirection'   => 5,
                            'httpversion'   => '1.0',
                            'blocking'      => true,
                            'headers'       => array(
                                                "Authorization"             => $this->service_key,
                                                "Content-Type"              => "application/json",
                                                "X-wp-client-user-agent"    => $clientUserAgent,
                                                "Content-Length"            => strlen( $json )
                                            ),
                            'body'          => $json,
                            'cookies'       => array()
                        );

        $worldpay_response = wp_remote_post( $this->endpoint.$action, $remote_post );

        if( !is_wp_error( $worldpay_response ) ) {

            return self::handleResponse( $worldpay_response['body'] );

        } else {

            // Error code
            $error_code = $worldpay_response->get_error_code();

            // Error message
            $error_message = $worldpay_response->get_error_message();

            if ( $error_code === 60 ) {
                self::onError( 'sslerror', false, $error_code, null, $error_message );
            } elseif ( $error_code === 28 ) {
                self::onError( 'timeouterror', false, $error_code, null, $error_message );
            } else {
                self::onError( 'uanv', false, $error_code, null, $error_message );
            }

        }

        return self::handleResponse( $worldpay_response['body'] );

    }


    /**
     * Create Worldpay APM order
     * @param array $order
     * @return array Worldpay order response
     * */
    public function createApmOrder($order = array())
    {

        $this->checkOrderInput($order);

        $defaults = array(
            'deliveryAddress' => null,
            'billingAddress' => null,
            'successUrl' => null,
            'pendingUrl' => null,
            'failureUrl' => null,
            'cancelUrl' => null,
            'shopperEmailAddress' => null
        );

        $order = array_merge($defaults, $order);

        $obj = array(
            "token" => $order['token'],
            "orderDescription" => $order['orderDescription'],
            "amount" => $order['amount'],
            "currencyCode" => $order['currencyCode'],
            "name" => $order['name'],
            "shopperEmailAddress" => $order['shopperEmailAddress'],
            "billingAddress" => $order['billingAddress'],
            "deliveryAddress" => $order['deliveryAddress'],
            "customerOrderCode" => $order['customerOrderCode'],
            "successUrl" => $order['successUrl'],
            "pendingUrl" => $order['pendingUrl'],
            "failureUrl" => $order['failureUrl'],
            "cancelUrl" => $order['cancelUrl']
        );
        
        if (isset($order['statementNarrative'])) {
            $obj['statementNarrative'] = $order['statementNarrative'];
        }
        if (!empty($order['settlementCurrency'])) {
            $obj['settlementCurrency'] = $order['settlementCurrency'];
        }
        if (!empty($order['customerIdentifiers'])) {
            $obj['customerIdentifiers'] = $order['customerIdentifiers'];
        }

        $json = json_encode($obj);

        $response = $this->sendRequest('orders', $json, true);

        if (isset($response["orderCode"])) {
            //success
            return $response;
        } else {
            self::onError("apierror");
        }
    }


    /**
     * Create Worldpay order
     * @param array $order
     * @return array Worldpay order response
     * */
    public function createOrder($order = array())
    {

        $this->checkOrderInput($order);

        $defaults = array(
            'orderType' => 'ECOM',
            'billingAddress' => null,
            'deliveryAddress' => null,
            'is3DSOrder' => false,
            'authoriseOnly' => false,
            'redirectURL' => false,
            'shopperEmailAddress' => null
        );

        $order = array_merge($defaults, $order);

        $obj = array(
            "token" => $order['token'],
            "orderDescription" => $order['orderDescription'],
            "amount" => round($order['amount']),
            "is3DSOrder" => ($order['is3DSOrder']) ? true : false,
            "currencyCode" => $order['currencyCode'],
            "name" => $order['name'],
            "shopperEmailAddress" => $order['shopperEmailAddress'],
            "orderType" => (in_array($order['orderType'], $this->order_types)) ? $order['orderType'] : 'ECOM',
            "authorizeOnly" => ($order['authoriseOnly']) ? true : false,
            "billingAddress" => $order['billingAddress'],
            "deliveryAddress" => $order['deliveryAddress'],
            "customerOrderCode" => $order['customerOrderCode']
        );

        if ($obj['is3DSOrder']) {
            $obj['shopperIpAddress'] = $this->getClientIp();
            $obj['shopperSessionId'] = $this->getSessionId();
            $obj['shopperUserAgent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            $obj['shopperAcceptHeader'] = $this->getShopperAcceptHeader();
        }

        if (isset($order['statementNarrative'])) {
            $obj['statementNarrative'] = $order['statementNarrative'];
        }
        if (!empty($order['settlementCurrency'])) {
            $obj['settlementCurrency'] = $order['settlementCurrency'];
        }
        if (!empty($order['customerIdentifiers'])) {
            $obj['customerIdentifiers'] = $order['customerIdentifiers'];
        }

        $json = json_encode($obj);
        $response = $this->sendRequest('orders', $json, true);

        if (isset($response["orderCode"])) {
            //success
            return $response;
        } else {
            self::onError("apierror");
        }
    }

    /**
     * Authorise Worldpay 3DS Order
     * @param string $orderCode
     * @param string $responseCode
     * */
    public function authorise3DSOrder( $orderCode, $request )
    {
        $json = json_encode( $request );

        return $this->sendRequest('orders/' . $orderCode, $json, true, 'PUT');
    }

    /**
     * Capture Authorized Worldpay Order
     * @param string $orderCode
     * @param string $amount
     * */
    public function captureAuthorisedOrder($orderCode = false, $amount = null)
    {
        if (empty($orderCode) || !is_string($orderCode)) {
            self::onError('ip', self::$errors['capture']['ordercode']);
        }

        if (!empty($amount) && is_numeric($amount)) {
            $json = json_encode(array('captureAmount'=>"{$amount}"));
        } else {
            $json = false;
        }

        $this->sendRequest('orders/' . $orderCode . '/capture', $json, !!$json);
    }

    /**
     * Cancel Authorized Worldpay Order
     * @param string $orderCode
     * */
    public function cancelAuthorisedOrder($orderCode = false)
    {
        if (empty($orderCode) || !is_string($orderCode)) {
            self::onError('ip', self::$errors['capture']['ordercode']);
        }

        $this->sendRequest('orders/' . $orderCode, false, false, 'DELETE');
    }

    /**
     * Refund Worldpay order
     * @param bool $orderCode
     * @param null $amount
     */
    public function refundOrder($orderCode = false, $amount = null)
    {
        if (empty($orderCode) || !is_string($orderCode)) {
            self::onError('ip', self::$errors['refund']['ordercode']);
        }

        if (!empty($amount) && is_numeric($amount)) {
            $json = json_encode( array( 'refundAmount' => "{$amount}" ) );
        } else {
            $json = false;
        }

        return $this->sendRequest('orders/' . $orderCode . '/refund', $json, false);
    }

    /**
     * Get a Worldpay order
     * @param string $orderCode
     * @return array Worldpay order response
     * */
    public function getOrder($orderCode = false)
    {
        if (empty($orderCode) || !is_string($orderCode)) {
            self::onError('ip', self::$errors['orderInput']['orderCode']);
        }
        $response = $this->sendRequest('orders/' . $orderCode, false, true, 'GET');

        if (!isset($response["orderCode"])) {
            self::onError("apierror");
        }
        return $response;
    }

    /**
     * Get card details from Worldpay token
     * @param string $token
     * @return array card details
     * */
    public function getStoredCardDetails($token = false)
    {
        if (empty($token) || !is_string($token)) {
            self::onError('ip', self::$errors['orderInput']['token']);
        }
        $response = $this->sendRequest('tokens/' . $token, false, true, 'GET');

        if (!isset($response['paymentMethod'])) {
            self::onError("apierror");
        }

        return $response['paymentMethod'];
    }

    /**
     * Disable SSL Check ~ Use only for testing!
     * @param bool $disable
     * */
    public function disableSSLCheck($disable = false)
    {
        $this->disable_ssl = $disable;
    }


    /**
     * Set timeout
     * @param int $timeout
     * */
    public function setTimeout($timeout = 3)
    {
        $this->timeout = $timeout;
    }

    /**
     * get sessionId for 3ds
     * @return string $shopperSessionId
     * */
    public function getSessionId()
    {
        if ($this->shopperSessionId) {
            return $this->shopperSessionId;
        } else {
            if (!$_SESSION['worldpay_sessionid']) {
               $_SESSION['worldpay_sessionid'] = uniqid();
            }
            return $_SESSION['worldpay_sessionid'];
        }
    }

    /**
     * set sessionId for 3ds
     * @param string $timeout
     * */
    public function setSessionId($shopperSessionId)
    {
        $this->shopperSessionId = $shopperSessionId;
    }

    /**
     * get shopper accept header for 3ds
     * @return string $acceptHeader
     * */
    public function getShopperAcceptHeader()
    {
       return isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '*/*';
    }

    /**
     * Handle errors
     * @param string-error_key $error
     * @param string $message
     * @param string $code
     * @param string $httpStatusCode
     * @param string $description
     * @param string $customCode
     * */
    public static function onError(
        $error = false,
        $message = false,
        $code = null,
        $httpStatusCode = null,
        $description = null,
        $customCode = null
    ) {

        $error_message = ($message) ? $message : '';
        if ($error) {
            $error_message = self::$errors[$error];
            if ($message) {
                $error_message .=  ' - '. $message;
            }
        }
        throw new WorldpayException(
            $error_message,
            $code,
            null,
            $httpStatusCode,
            $description,
            $customCode
        );
    }

    /**
     * Handle response object
     * @param string $response
     * */
    public static function handleResponse( $response ) {
        return json_decode($response, true);
    }
}

class WorldpayException extends Exception
{
    private $httpStatusCode;
    private $description;
    private $customCode;


    public function __construct(
        $message = null,
        $code = 0,
        Exception $previous = null,
        $httpStatusCode = null,
        $description = null,
        $customCode = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpStatusCode = $httpStatusCode;
        $this->description = $description;
        $this->customCode = $customCode;
    }

    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    public function getCustomCode()
    {
        return $this->customCode;
    }

    public function getDescription()
    {
        return $this->description;
    }
}
