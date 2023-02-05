<?php

namespace WHMCS\Module\Registrar\Registrarmodule;

use WHMCS\Database\Capsule;

/**
 * Class to login to API and get token
 */

class Authentication {

    /**
     * @var string The URL to the service. You should not need to change this.
     */
    public static $serviceURL = 'https://api.playsystems.io/auth/user/login';
    /**

    /**
     * @var array The Response from the API.
     */
    protected $results = array();
    protected $config = array();
    //constructor
    public function __construct() {
        $query = \Illuminate\Database\Capsule\Manager::table('tblregistrars')
            ->where("registrar", "=", "registrarmodule");
        foreach($query->get() as $field) {
            $this->config[$field->setting] = Decrypt($field->value);
        }
    }
    /**
     * @desc Login to API and get token
     * @return token
     */

   public function login() {

       $ch = curl_init(); //init curl
       //credentials of the user
       $loginUser = array (
           'email' => $this->config['Email'],
           'password' => $this->config['Password']
       );
       $payload = json_encode($loginUser); //encode the data to json
       curl_setopt($ch, CURLOPT_URL,self::$serviceURL); //set the url
       // Attach encoded JSON string to the POST fields
       curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
       // Set the content type to application/json
       curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
       // Return response instead of outputting
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       //SetTimeOut
       curl_setopt($ch, CURLOPT_TIMEOUT, 100);

       // Execute the POST request
       $response = curl_exec($ch);

       // Get Http response code
       $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

       //Check for errors
       if (curl_errno($ch)) {
           throw new \Exception('Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
       }
       //check http response code
       if ($httpCode != 200 && $httpCode != 201 && $httpCode != 202 && $httpCode != 203 && $httpCode != 204) {
           throw new \Exception('HTTP Error: ' . $httpCode . ' - ' . $response . '- from GettingToken action');
       }

       curl_close($ch); // Close cURL session handle
       $this->results = json_decode($response, true); //decode the response to array
       //Create Log For Authentication
       logModuleCall(
           'Registrarmodule',
           'Get Token',
           $response, // Reponse from API
           $this->results //decode the response from API
       );

       return $this->results['token']; //return token

   }
}