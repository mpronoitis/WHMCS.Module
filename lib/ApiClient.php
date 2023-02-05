<?php
namespace WHMCS\Module\Registrar\Registrarmodule;

class ApiClient {
    const API_URL = 'https://api.playsystems.io/epp/'; // API URL to check availability of domain
    protected $results = array(); // array to store results from API responses

    /**
     * function to fetch token
     * @return token
     */

    public function getToken() {
        //use Authentication class to get token

     if(is_null($this->bearerToken) || json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $this->bearerToken)[1]))))->exp < time()) {
            $auth = new Authentication();
            $this->bearerToken = $auth->login();
        }

        return $this->bearerToken;
    }

    //protected bearer token
    protected $bearerToken;

    /**
     * Check Availability for a  domain
     *
     * @var array $action, searchTerm for domain
     * @return array $results, decoded response from API
     */

    public function checkAvailability($action)
    {

       //Create Headers with token
      $headers = array();
        try {
            $headers[] = 'Authorization: Bearer ' . $this->getToken();
        } catch (\Exception $e) {
            logModuleCall(
                'Registrarmodule',
                'Check Availability error when getting token for checkAvailability Endpoint',
                $headers,
                $e->getMessage()
            );

        } //add token to the header
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init(); // Initiate cURL
        curl_setopt($ch, CURLOPT_URL,self::API_URL . 'domain/check' . '/' .  $action ); // setting properly url/endpoint for the request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); //add headers to the request
        $response = curl_exec($ch); // Execute the request
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);// Get the HTTP response code
        if (curl_errno($ch)) {
            throw new \Exception('Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
        }
        //check http response code
        if ($httpCode != 200) {
            throw new \Exception('HTTP Error: ' . $httpCode);
        }
        curl_close($ch);

        $this->results = $this->processResponse($response); //decode response returned by the API

        logModuleCall( // logModuleCall is a WHMCS function, creating Logs At
            'Registrarmodule',
            $action, //searhing domain
            $response, //response from the API
            $this->results, //decoded response from the API
        );

        return $this->results; //if all is ok, return the results
    }

    /**
     * Register a domain
     *
     * @var array $postfields, array of data to be sent to the API
     * @var array $contact, array of contact data to be sent to the API, so we create the registrant contact
     * @return array {
     *     @var bool|null $success
     *     @var string|null $error
     * }
     */
    public function registerDomain($postfields)
    {
        //make HttpHeaders
        $headers = array();
        try {
            $headers[] = 'Authorization: Bearer ' .$this->getToken();
        } catch (\Exception $e) {
            logModuleCall(
                'Registrarmodule',
                'Register Domain error when getting token for registerDomain Endpoint',
                $headers,
                $e->getMessage()
            );
            throw new \Exception('Register Domain error when getting token for registerDomain Endpoint');
        } //add token to the header
        $headers[] = 'Content-Type: application/json';

        // If contact is created, register domain
        $ch = curl_init(self::API_URL . 'domain/register'); // Initiate cURL

        $payload = json_encode($postfields);
        // Attach encoded JSON string to the POST fields
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        // Set the content type to application/json
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Return response instead of outputting
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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
            throw new \Exception('HTTP Error: ' . $httpCode . ' - ' . $response . '- from registerDomain action');
        }

        curl_close($ch); // Close cURL session handle
        $this->results = $this->processResponse($response);
        //Create Log For Register Domain
        logModuleCall(
            'Registrarmodule',
            'Register Domain',
            $postfields,
            $response,
            $this->results,
        );

        return $this->results;
    }

    public function createContact($contact) {

        //make HttpHeaders
        $headers = array();
        try {
            $headers[] = 'Authorization: Bearer ' . $this->getToken();
        } catch (\Exception $e) {
            logModuleCall(
                'Registrarmodule',
                'Error Taking Token when called createContact',
                $headers,
                $e->getMessage(),
            );

            throw new \Exception('Register Domain error when getting token for createContact Endpoint');
        } //add token to the header
        $headers[] = 'Content-Type: application/json';
        $ch = curl_init(self::API_URL . 'contact/create'); // Initiate cURL

        $payload = json_encode($contact);
           // Attach encoded JSON string to the POST fields
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        // Set the content type to application/json
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Return response instead of outputting
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //SetTimeOut
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);

        // Execute the POST request
        $response = curl_exec($ch);
        // Get the HTTP response code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);// Get the HTTP response code

        if (curl_errno($ch)) {
            throw new \Exception('Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
        }
        //check http response code
        if ($httpCode != 200 && $httpCode != 201 && $httpCode != 202 && $httpCode != 203 && $httpCode != 204) {
            throw new \Exception('HTTP Error: ' . $httpCode . ' - ' . $response . ' - from create contact action');
        }

        curl_close($ch); //close curl connection
        $this->results = $this->processResponse($response);

        //Creating Log for creating the contact
        logModuleCall(
            'Registrarmodule',
            'Create Contact',
            $contact, //contact to be created
            $response, //response from API
            $this->results //decoded response from API
        );

        return $this->results;
    }
    /**
     * Suggest a contactId
     *     @var string $contactId
     */
    public function suggestContactId()
    {
        //create headers
        $headers = array();
        try {
            $headers[] = 'Authorization: Bearer ' . $this->getToken();
        } catch (\Exception $e) {
            logModuleCall(
                'Registrarmodule',
                'Error when getting token for suggestContactId Endpoint',
                $headers,
                $e->getMessage()
            );
            throw new \Exception('Error when getting token for suggestContactId Endpoint');
        } //add token to the header
        $ch = curl_init(); // Initiate cURL
        curl_setopt($ch, CURLOPT_URL,self::API_URL . 'contact/suggest'); // setting properly url/endpoint for the request
//        curl_setopt($ch, CURLOPT_GET, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); //setting headers
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);

        $response = curl_exec($ch); // Execute the request
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);// Get the HTTP response code
        if (curl_errno($ch)) {
            throw new \Exception('Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
        }
        //check http response code
        if ($httpCode != 200) {
            throw new \Exception('HTTP Error: ' . $httpCode . ' - from suggest contact action');
        }
        curl_close($ch);

        $this->results = $this->processResponse($response); //decode response returned by the API

        logModuleCall( // logModuleCall is a WHMCS function, creating Logs At
            'Registrarmodule',
            'Suggest Contactid', //searhing domain
            $response, //response from the API
            $this->results, //decoded response from the API
        );

        return $this->results; //if all is ok, return the results

    }
    /**
     * Renew Domain
     *
     */
    function renewDomain($postfields) {
        //make HttpHeaders
        $headers = array();
        //try get token
        try {
            $headers[] = 'Authorization: Bearer ' . $this->getToken();
        } catch (\Exception $e) {
            logModuleCall(
                'Registrarmodule',
                'Error Taking Token when called renewDomain',
                $headers,
                $e->getMessage(),
            );

            throw new \Exception('Register Domain error when getting token for renewDomain Endpoint');
        } //add token to the header
        $headers[] = 'Content-Type: application/json';
        $ch = curl_init(self::API_URL . 'domain/renew'); // Initiate cURL

        $payload = json_encode($postfields);
        // Attach encoded JSON string to the POST fields
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        // Set the content type to application/json
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Return response instead of outputting
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //SetTimeOut
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);

        // Execute the POST request
        $response = curl_exec($ch);
        // Get the HTTP response code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);// Get the HTTP response code

        if (curl_errno($ch)) {
            throw new \Exception('Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
        }
        //check http response code
        if ($httpCode != 200 && $httpCode != 201 && $httpCode != 202 && $httpCode != 203 && $httpCode != 204) {
            throw new \Exception('HTTP Error: ' . $httpCode . ' - ' . $response . ' - from renew domain action');
        }

        curl_close($ch); //close curl connection
        $this->results = $this->processResponse($response);

        //Creating Log for creating the contact
        logModuleCall(
            'Registrarmodule',
            'Renew Domain',
            $postfields, //contact to be created
            $response, //response from API
            $this->results //decoded response from API
        );

        return $this->results;
    }

    /**
     * Transfer Domain
     * @var array $postfields contains the domain name and the epp code
     * @return array
     */
    function transferDomain(array $postfields) {
        //make HttpHeaders
        $headers = array();
        try {
            $headers[] = 'Authorization: Bearer ' .$this->getToken();
        } catch (\Exception $e) {
            logModuleCall(
                'Registrarmodule',
                'Error Taking Token when called transferDomain',
                $headers,
                $e->getMessage(),
            );
           throw new \Exception('Register Domain error when getting token for transferDomain Endpoint');
        } //add token to the header
        $headers[] = 'Content-Type: application/json';

        // If contact is created, transfer domain
        $ch = curl_init(self::API_URL . 'domain/transfer'); // Initiate cURL

        $payload = json_encode($postfields); //
        // Attach encoded JSON string to the POST fields
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        // Set HTTP Header for POST request
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Return response instead of outputting
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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
            throw new \Exception('HTTP Error: ' . $httpCode . ' - ' . $response . '- from transferDomain action');
        }

        curl_close($ch); // Close cURL session handle
        $this->results = $this->processResponse($response);
        //Create Log For Register Domain
        logModuleCall(
            'Registrarmodule',
            'Transfer Domain',
            $postfields,
            $response,
            $this->results,
        );

        return $this->results;
    }

    /**
     * Add Nameserver to a domain
     * @var array $postfields contains the domain name,the nameserver
     * @return array
     */
    function addNameserver(array $postfields) {
        //make HttpHeaders
        $headers = array();
        try {
            $headers[] = 'Authorization: Bearer ' .$this->getToken();
        } catch (\Exception $e) {
            logModuleCall(
                'Registrarmodule',
                'Error Taking Token when called addNameserver',
                $headers,
                $e->getMessage(),
            );
            throw new \Exception('Register Domain error when getting token for addNameserver Endpoint');
        } //add token to the header
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init(self::API_URL . 'domain/nameservers/add'); // Initiate cURL

        $payload = json_encode($postfields); //
        // Attach encoded JSON string to the POST fields
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        // Set HTTP Header for POST request
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Return response instead of outputting
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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
            throw new \Exception('HTTP Error: ' . $httpCode . ' - ' . $response . '- from addNameserver action');
        }

        curl_close($ch); // Close cURL session handle
        $this->results = $this->processResponse($response);
        //Create Log For Register Domain
        logModuleCall(
            'Registrarmodule',
            'Add Nameserver',
            $postfields,
            $response,
            $this->results,
        );

        return $this->results;
    }
    /**
     * Fetch Nameserver of a domain
     * @var string $action contains the domain name
     * @return array
     */
    function getDomainInfo($action) {
        //Create Headers with token
        $headers = array();
        try {
            $headers[] = 'Authorization: Bearer ' . $this->getToken();
        } catch (\Exception $e) {
            logModuleCall(
                'Registrarmodule',
                'Getting Nameservers error when getting token for getDomainInfo Endpoint',
                $headers,
                $e->getMessage()
            );

        } //add token to the header
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init(); // Initiate cURL
        curl_setopt($ch, CURLOPT_URL,self::API_URL . 'domain/info' . '/' .  $action ); // setting properly url/endpoint for the request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); //add headers to the request
        $response = curl_exec($ch); // Execute the request
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);// Get the HTTP response code
        if (curl_errno($ch)) {
            throw new \Exception('Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
        }
        //check http response code
        if ($httpCode != 200) {
            throw new \Exception('HTTP Error: ' . $httpCode);
        }
        curl_close($ch);

        $this->results = $this->processResponse($response); //decode response returned by the API

        logModuleCall( // logModuleCall is a WHMCS function, creating Logs At
            'Registrarmodule',
            $action, //searhing domain
            $response, //response from the API
            $this->results, //decoded response from the API
        );

        return $this->results; //if all is ok, return the results
    }
    /**
     * Forwards a domain to another url
     */
    public function forwardDomain() {

    }

    /**
     * Process the response from the API
     *
     * @var string $response, response from the API
     * @return array $results, decoded response from the API
     */
    public function processResponse($response)
    {
        return json_decode($response, true);
    }

    /**
     * Get the results from the API
     *
     * @return array $results, decoded response from the API
     */
    public function getFromResponse($key)
    {
        return isset($this->results[$key]) ? $this->results[$key] : '';
    }
}