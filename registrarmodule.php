<?php 

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Module\Registrar\Registrarmodule\ApiClient;
//use Capsule
use WHMCS\Database\Capsule;


function registrarmodule_MetaData()
{
    return array(
        'DisplayName' => 'EPP Module for WHMCS',
        'APIVersion' => '1.1',
    );
}

function registrarmodule_getConfigArray() {
    return array(
        // Friendly display name for the module
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'EPP Module for WHMCS',
        ),
        // a text field type allows for single line text input
        'Email' => [
            'FriendlyName' => 'Email For WHMCS User',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter in megabytes',
        ],
        // a password field type allows for masked text input
        'Password' => [
            'FriendlyName' => 'Password For WHMCS User',
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter secret value here',
        ],
    );

}

/**
 * Check Domain Availability.
 *
 * Determine if a domain or group of domains are available for
 * registration or transfer.
 *
 * @param array $params common module parameters
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @see \WHMCS\Domains\DomainLookup\SearchResult
 * @see \WHMCS\Domains\DomainLookup\ResultsList
 *
 * @throws Exception Upon domain availability check failure.
 *
 * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
 */
function registrarmodule_CheckAvailability($params)
{

    // availability check parameters (passed from WHMCS)
    $searchTerm = $params['searchTerm'];
    $tldsToInclude = $params['tldsToInclude'];
    $tld = $params['tld'];
    $expanded_names = array_map(
        function($tld) use ($searchTerm) {
            return $searchTerm . $tld;
        },
        $params["tldsToInclude"]
    );

    try {

        $api = new ApiClient();//create an instance of the ApiClient class
        $api->checkAvailability($searchTerm . ".gr"); //pass to function searching domain with the tld
        $results = new ResultsList(); //create new object of ResultsList

        // Instantiate a new domain search result object
        $searchResult = new WHMCS\Domains\DomainLookup\SearchResult($searchTerm,".gr");

        // Determine the appropriate status to return
        if($api->getFromResponse('available') === true) {
            $searchResult->setStatus(SearchResult::STATUS_NOT_REGISTERED);
        } else {
            $searchResult->setStatus(SearchResult::STATUS_REGISTERED);
        }

        // Add the domain search result object to the results list
        $results->append($searchResult);

        logModuleCall('registrarmodule', __FUNCTION__,$searchTerm,$expanded_names);
        return $results;
       

    } catch (\Exception $e) {
        logModuleCall('registrarmodule', __FUNCTION__, $params, $e->getMessage());
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Register a domain.
 *
 * Attempt to register a domain with the domain registrar.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain registration order
 * * When a pending domain registration order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function registrarmodule_RegisterDomain($params)
{

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];

    /**
     * Nameservers.
     *
     * If purchased with web hosting, values will be taken from the
     * assigned web hosting server. Otherwise, uses the values specified
     * during the order process.
     */
    $nameserver1 = $params['ns1'];
    $nameserver2 = $params['ns2'];
    $nameserver3 = $params['ns3'];
    $nameserver4 = $params['ns4'];
    $nameserver5 = $params['ns5'];

    // registrant information
    $firstName = $params["firstname"];
    $lastName = $params["lastname"];
    $fullName = $params["fullname"]; // First name and last name combined
    $companyName = $params["companyname"];
    $email = $params["email"];
    $address1 = $params["address1"];
    $address2 = $params["address2"];
    $city = $params["city"];
    $state = $params["state"]; // eg. TX
    $stateFullName = $params["fullstate"]; // eg. Texas
    $postcode = $params["postcode"]; // Postcode/Zip code
    $countryCode = $params["countrycode"]; // eg. GB
    $countryName = $params["countryname"]; // eg. United Kingdom
    $phoneNumber = $params["phonenumber"]; // Phone number as the user provided it
    $phoneCountryCode = $params["phonecc"]; // Country code determined based on country
    $phoneNumberFormatted = $params["fullphonenumber"]; // Format: +CC.xxxxxxxxxxxx

    /**
     * Admin contact information.
     *
     * Defaults to the same as the client information. Can be configured
     * to use the web hosts details if the `Use Clients Details` option
     * is disabled in Setup > General Settings > Domains.
     */
    $adminFirstName = $params["adminfirstname"];
    $adminLastName = $params["adminlastname"];
    $adminCompanyName = $params["admincompanyname"];
    $adminEmail = $params["adminemail"];
    $adminAddress1 = $params["adminaddress1"];
    $adminAddress2 = $params["adminaddress2"];
    $adminCity = $params["admincity"];
    $adminState = $params["adminstate"]; // eg. TX
    $adminStateFull = $params["adminfullstate"]; // eg. Texas
    $adminPostcode = $params["adminpostcode"]; // Postcode/Zip code
    $adminCountry = $params["admincountry"]; // eg. GB
    $adminPhoneNumber = $params["adminphonenumber"]; // Phone number as the user provided it
    $adminPhoneNumberFormatted = $params["adminfullphonenumber"]; // Format: +CC.xxxxxxxxxxxx

    // domain addon purchase status
    $enableDnsManagement = (bool) $params['dnsmanagement'];
    $enableEmailForwarding = (bool) $params['emailforwarding'];
    $enableIdProtection = (bool) $params['idprotection'];

    /**
     * Premium domain parameters.
     *
     * Premium domains enabled informs you if the admin user has enabled
     * the selling of premium domain names. If this domain is a premium name,
     * `premiumCost` will contain the cost price retrieved at the time of
     * the order being placed. The premium order should only be processed
     * if the cost price now matches the previously fetched amount.
     */
    $premiumDomainsEnabled = (bool) $params['premiumEnabled'];
    $premiumDomainsCost = $params['premiumCost'];

    $domain1 = $sld . '.' . $tld;
    $code_contact = "b68_";

//    //take from API suggested contactId to use
//    try {
//        $api = new ApiClient();
//        $contactId = $api->suggestContactId();
//    } catch (\Exception $e) {
//        //log error
//        logModuleCall('Registrarmodule', 'Get ContactId Errors',$e->getMessage(),$e->getMessage());
//        return array(
//            'Error Taking Suggested ContactId for creating contact' => $e->getMessage(),
//        );
//        return;
//
//    }

//    //Contact Details
//    $contact = array (
//        "id" => $contactId['contactId'],
//        "localizedName" => $firstName,
//        "localizedOrganization" => $lastName,
//        "localizedStreet" => $address1,
//        "localizedCity" => $city,
//        "localizedState" => $state,
//        "localizedPostalCode" => $postcode,
//        "localizedCountry" => $countryCode,
//        "internationalOrganization" => "Playsystems",
//        "internationalName" => $lastName,
//        "internationalStreet" => $address1,
//        "internationalCity" => $city,
//        "internationalState" => $state,
//        "internationalPostalCode" => $postcode,
//        "internationalCountry" => $countryCode,
//        "voicePhone" => "+30.2122148888",
//        "faxPhone" => "+30.2122148888",
//        "email" => $email,
//        "password" => generatePassword(10),
//        "discloseFlag" => "1"
//    );
//
//    $postfields = array( //Create postfields array, this is the data we are posting to the API
//        'domainName' => $domain1,
//        'registrant' => $contactId['contactId'],
//        'admin' => $contactId['contactId'],
//        'tech' => $contactId['contactId'],
//        'billing' => $contactId['contactId'],
//        'password' => generatePassword(9),
//        'period' => $registrationPeriod,
//        );

    try {
        $api = new ApiClient(); // Create a new API client instance
        $contactId = $api->suggestContactId();
        $contact = array (
            "id" => $contactId['contactId'],
            "localizedName" => $firstName,
            "localizedOrganization" => $lastName,
            "localizedStreet" => $address1,
            "localizedCity" => $city,
            "localizedState" => $state,
            "localizedPostalCode" => $postcode,
            "localizedCountry" => $countryCode,
            "internationalOrganization" => "Playsystems",
            "internationalName" => $lastName,
            "internationalStreet" => $address1,
            "internationalCity" => $city,
            "internationalState" => $state,
            "internationalPostalCode" => $postcode,
            "internationalCountry" => $countryCode,
            "voicePhone" => "+30.2122148888",
            "faxPhone" => "+30.2122148888",
            "email" => $email,
            "password" => generatePassword(10),
            "discloseFlag" => "1"
        );

        $postfields = array( //Create postfields array, this is the data we are posting to the API
            'domainName' => $domain1,
            'registrant' => $contactId['contactId'],
            'admin' => $contactId['contactId'],
            'tech' => $contactId['contactId'],
            'billing' => $contactId['contactId'],
            'password' => generatePassword(9),
            'period' => $registrationPeriod,
        );
        $api->createContact($contact); // Create the contact
        $api->registerDomain($postfields); // Call Function to register domain and making the contact for registrant

        return array(
            'success' => 'Your Domain has been registered successfully',
        );

    } catch (\Exception $e) {
        //log error
        logModuleCall('registrarmodule', 'RegisterDomain Errors',__FUNCTION__,$e->getMessage());
        return array(
            'error' => $e->getMessage(),
        );
    }
}



/**
 * Initiate domain transfer.
 *
 * Attempt to create a domain transfer request for a given domain.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain transfer order
 * * When a pending domain transfer order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function registrarmodule_TransferDomain($params) {

    $sld = $params['sld']; // eg. domain
    $tld = $params['tld']; // eg. gr
    $regperiod = $params['regperiod']; // Registration period in years
    $eppcode = $params['eppcode']; // EPP Code for the domain
    $domain1 = $sld . '.' . $tld; // Domain name we want to transfer

    // registrant information
    $firstName = $params["firstname"];
    $lastName = $params["lastname"];
    $fullName = $params["fullname"]; // First name and last name combined
    $companyName = $params["companyname"];
    $email = $params["email"];
    $address1 = $params["address1"];
    $address2 = $params["address2"];
    $city = $params["city"];
    $state = $params["state"]; // eg. TX
    $stateFullName = $params["fullstate"]; // eg. Texas
    $postcode = $params["postcode"]; // Postcode/Zip code
    $countryCode = $params["countrycode"]; // eg. GB
    $countryName = $params["countryname"]; // eg. United Kingdom
    $phoneNumber = $params["phonenumber"]; // Phone number as the user provided it
    $phoneCountryCode = $params["phonecc"]; // Country code determined based on country
    $phoneNumberFormatted = $params["fullphonenumber"]; // Format: +CC.xxxxxxxxxxxx
    //take from API suggested contactId to use
    try {
        $api = new ApiClient();
        $contactId = $api->suggestContactId();
    } catch (\Exception $e) {
        //log error
        logModuleCall('Registrarmodule', 'Get ContactId Errors',__FUNCTION__,$e->getMessage());

    }

    //Create Contact with the same details as the registrant
    $contact = array (
        "id" => $contactId['contactId'],
        "localizedName" => $firstName,
        "localizedOrganization" => $lastName,
        "localizedStreet" => $address1,
        "localizedCity" => $city,
        "localizedState" => $state,
        "localizedPostalCode" => $postcode,
        "localizedCountry" => $countryCode,
        "internationalOrganization" => "Playsystems",
        "internationalName" => $lastName,
        "internationalStreet" => $address1,
        "internationalCity" => $city,
        "internationalState" => $state,
        "internationalPostalCode" => $postcode,
        "internationalCountry" => $countryCode,
        "voicePhone" => "+30.2122148888",
        "faxPhone" => "+30.2122148888",
        "email" => $email,
        "password" => generatePassword(10),
        "discloseFlag" => "1"
    );

    $postfields = array( //Create postfields array, this is the data we are posting to the API to transfer Domain
        "domainName" => $domain1,
        "password" => $eppcode,
        "contactId" => $contactId['contactId'],
        "newPassword" => generatePassword(10)
    );

//
    try {
        $api = new ApiClient(); // Create a new API client instance
        $api->createContact($contact); // Create the contact
        $api->transferDomain($postfields); // Call Function to transfer domain

        return array(
            'success' => 'Your Domain has been transferred successfully',
        );

    } catch (\Exception $e) {
        //log error
        logModuleCall('registrarmodule', 'TransferDomain Errors',__FUNCTION__,$e->getMessage());
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Renew a domain.
 *
 * Attempt to renew/extend a domain for a given number of years.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain renewal order
 * * When a pending domain renewal order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function registrarmodule_RenewDomain($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];

    // domain addon purchase status
    $enableDnsManagement = (bool) $params['dnsmanagement'];
    $enableEmailForwarding = (bool) $params['emailforwarding'];
    $enableIdProtection = (bool) $params['idprotection'];

    /**
     * Premium domain parameters.
     *
     * Premium domains enabled informs you if the admin user has enabled
     * the selling of premium domain names. If this domain is a premium name,
     * `premiumCost` will contain the cost price retrieved at the time of
     * the order being placed. A premium renewal should only be processed
     * if the cost price now matches that previously fetched amount.
     */
    $premiumDomainsEnabled = (bool) $params['premiumEnabled'];
    $premiumDomainsCost = $params['premiumCost'];


    // Build post data for renewDomain Endpoint
    $postfields = array(
        "domainName" => $sld . '.' . $tld,
        "years" => $registrationPeriod,
    );

    try {
        $api = new ApiClient(); // Create a new API client instance
        $api->renewDomain($postfields); // Call Function to renew domain

        //log success
        logModuleCall('registrarmodule', 'RenewDomain Success',__FUNCTION__,$api);

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        //log error
        logModuleCall('registrarmodule', 'RenewDomain Errors',__FUNCTION__,$e->getMessage());
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Save nameserver changes.
 *
 * This function should submit a change of nameservers request to the
 * domain registrar.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function registrarmodule_SaveNameservers($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // submitted nameserver values
    $nameserver1 = $params['ns1'];
    $nameserver2 = $params['ns2'];
    $nameserver3 = $params['ns3'];
    $nameserver4 = $params['ns4'];
    $nameserver5 = $params['ns5'];
    //check nameservers that are not empty and add it to array
    $nameservers = array();
    if (!empty($nameserver1)) {
        $nameservers[] = $nameserver1;
    }
    if (!empty($nameserver2)) {
        $nameservers[] = $nameserver2;
    }
    if (!empty($nameserver3)) {
        $nameservers[] = $nameserver3;
    }
    if (!empty($nameserver4)) {
        $nameservers[] = $nameserver4;
    }
    if (!empty($nameserver5)) {
        $nameservers[] = $nameserver5;
    }
        $postfields = array(
            "nameservers" => $nameservers,
            "domain" => $sld . '.' . $tld,
        );

        try {
            $api = new ApiClient(); // Create a new API client instance
            $api->addNameserver($postfields); // Call Function to update nameserver

            //log success
            logModuleCall('registrarmodule', 'SaveNameservers Success',__FUNCTION__,$postfields);

            return array(
                'success' => true,
            );

        } catch (\Exception $e) {
            //log error
            logModuleCall('registrarmodule', 'SaveNameservers Errors',__FUNCTION__,$postfields,$e->getMessage());
            return array(
                'error' => $e->getMessage(),
            );
        }

    return array(
        'error' => 'No Nameservers to update',
    );
}
/**
 * Fetch current nameservers.
 *
 * This function should return an array of nameservers for a given domain.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function registrarmodule_GetNameservers($params)
{
    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    try {
        $api = new ApiClient();
        $api->getDomainInfo($sld . '.' . $tld); //pass domain to get info
        $nameservers = $api->getFromResponse('nameservers'); //get nameservers from response
       //for each name server of nameservers array build an array with ns1,ns2,ns3,ns4,ns5
        //log module
        logModuleCall('registrarmodule', 'GetNameservers Success',__FUNCTION__,$api);
        $i = 1;
        foreach ($nameservers as $nameserver) {
            $nameserverArray['ns' . $i] = $nameserver;
            $i++;
        }

        return $nameserverArray;

    } catch (\Exception $e) {
        logModuleCall('registrarmodule', 'GetNameservers Errors',__FUNCTION__,$e->getMessage());
        return array(
            'error' => $e->getMessage(),
        );
    }
}


/**
 * Generate a password for domain password
 * Generates a strong password of N length containing at least one lower case letter,
 * one uppercase letter, one digit, and one special character. The remaining characters
 * in the password are chosen at random from those four sets.
 return $password;
 */
function generatePassword($length = 9,$available_sets = 'luds')
{
    $sets = array();
    if(strpos($available_sets, 'l') !== false)
        $sets[] = 'abcdefghjkmnpqrstuvwxyz'; //
    if(strpos($available_sets, 'u') !== false)
        $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    if(strpos($available_sets, 'd') !== false)
        $sets[] = '0123456789';
    if(strpos($available_sets, 's') !== false)
        $sets[] = '~!@#$%^&*(){}:;-_+=\/?[].';

    $all = '';
    $password = '';
    foreach($sets as $set) //create a string with one character from each set
    {
        $password .= $set[array_rand(str_split($set))];
        $all .= $set;
    }

    $all = str_split($all);
    for($i = 0; $i < $length - count($sets); $i++)
        $password .= $all[array_rand($all)]; //add random characters from the $all string to fill up the length

    $password = str_shuffle($password); //shuffle the password string before returning

    return $password;
}


/**
 * Client Area Custom Button Array.
 *
 * Allows you to define additional actions your module supports.
 * In this example, we register a Get EPP Code action which triggers
 * the `registrarmodule_geteppcode` function when invoked.
 *
 * @return array
 */
function registrarmodule_ClientAreaCustomButtonArray()
{
    return array(
        'Get EPP Code' => 'geteppcode',
        'Forward Domain' => 'forwarddomain',
    );
}


/**
 * Example Custom Module Function: Get EPP Code
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function registrarmodule_geteppcode($params)
{
    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $email = $params['email'];
    $domainid = $params['domainid'];
    $eppcode = '';

    try {
        $api = new ApiClient();
        $api->getDomainInfo($sld . '.' . $tld); //pass domain to get info
        $eppcode = $api->getFromResponse('authInfoPassword'); //get nameservers from response
        $domain = $sld . '.' . $tld;
        //check is submit button clicked and password is not empty

        if(isset($_POST['submit']) && !empty($_POST['password'])) { //if we submit form we want to check if password is correct
            $password = $_POST['password'];
            //query capsule tbl clients to get email of current user
            $email= Capsule::table('tblclients')->where('id', $_SESSION['uid'])->value('email');
            //Check if password is same as the login user password
            $command = 'ValidateLogin';
            $postData = array(
                'email' => $email,
                'password2' => $password,
            );
            //call the api to validate the password
            $resultsAuth = localAPI($command, $postData);
            if($resultsAuth['result'] == 'success') {

                $command = 'SendEmail';
                //send email to user with eppcode
                $postData = array(
                    '//example1' => 'example',
                    'messagename' => 'Get EPP Code Email',
                    'id' => $_SESSION['uid'],
                    '//example2' => 'example',
                    'customtype' => 'general',
                    'customsubject' => 'Get EPP Code Email',
                    'custommessage' => '<p>Thank you for choosing us</p><p>Your EPP Code for domain {$domain} is: {$eppcode}</p>
                    <p>{$signature}</p>
                    <p style="padding-left: 90px;"><img src="https://playcloudservices.com/index.php?rp=/images/em/7_SupportBanner_cropped2.png" alt="SupportBanner.png" width="2555" height="852" /></p>
                    <center>
                    <a href="{$company_domain}">visit our website</a> <span class="hide-mobile"> </span> <a href="{$whmcs_url}">log in to your account</a> <span class="hide-mobile"> | </span> <a href="https://support.playsystems.gr/open.php?lang=en_US">get support</a> <br />Copyright © {$company_name}, All rights reserved.
                    </center>
                    ',
                    'customvars' => base64_encode(serialize(array("eppcode"=>$eppcode,"domain"=>$domain))),
                );
                //call the api to send email
                $resultsEmail = localAPI($command, $postData);
            }
        }
        return array(
            'templatefile' => 'geteppcode', // Template File to render
           //pass arguments to template
            'vars' => array(
                'domaindid' => $domainid,
                'temp' => $resultsAuth['result'],
                'errorMail' => $resultsEmail['result'],
            ),

        );

    } catch (\Exception $e) {
        logModuleCall('registrarmodule', 'GetEPP CODE Errors',__FUNCTION__,$e->getMessage());
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Example Custom Module Function: Forward Domain
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function registrarmodule_forwarddomain($params) {
    //check if submit button is clicked and url is not empty
    if(isset($_POST['submit']) && !empty($_POST['url'])) {
        $url = $_POST['url']; //take url from form
        //we must check if url is valid
        if (!preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $url)) {
            $urlErr = "Invalid URL";
        } else {
            //we must forward domain to url
        }
    }
    return array(
        'templatefile' => 'forwarddomain', // Template File to render
        'vars' => array(
            'domainid' => $params['domainid'],
            'url' => $url,
            'urlErr' => $urlErr,
        ),
    );
}


?>