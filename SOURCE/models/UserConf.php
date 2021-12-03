<?php

require('../../user.config.php');

class UserConf {
    /**
     * @param string shortCode The shortcode for your Talis Aspire tenancy
     */
    public $shortCode = '';

    /**
     * @param string clientID The Client ID given to you by Talis Support
     */
    public $clientID = '';

    /**
     * @param string secret The Client Secret given to you by Talis Support
     */
    public $secret = '';

    /**
     * @param string TalisGUID A talis_guid to use for any requests which require the X-Effective-User header to be set.
     * You will find this in the all users report CSV export.
     */
    public $TalisGUID = '';

    /**
     * @param string importanceID Used with the New Acquisitions tool, this sets a default item importance to assign to newly created items
     * See documentation within new_acq folder for more details
     * for example: 'http://yorksj.rl.talis.com/config/importance5ab0e620d975a'
     */
    public $importanceID = '';

    /**
    * @param string alma_lookup Used with the New Acquisitions tool, this is the API url for the Alma report. A feed of resources from your LMS.
    * for example: "https://api-eu.hosted.exlibrisgroup.com/almaws/v1/analytics/reports?path=%2Fshared%2FUniversity%20of%20Westminster%2FReports%2FContent%20and%20Digital%20Services%2FNew%20Acquisitions%20-%20TARL%20API&limit=25&col_names=true&apikey=l7************************"
    * Please see new_acq documentation for more details.
    */
    public $alma_lookup = '';
    
    /**
     * Constructor
     */
    public function __construct() { 
        $this->shortCode = $shortCode;
        $this->clientID = $clientID;
        $this->secret = $secret;
        $this->TalisGUID = $TalisGUID;

        // optionals
        $this->importanceID = $importanceID;
        $this->alma_looup = $alma_lookup;

        $this->errorIfInvalid();
        echo $this->printValues();
    }

    /**
     * Check the parameters are valid
     * @return boolean
     */
    public function isValid() {
        if (empty($this->shortcode)) {
            return false;
        }
        if (empty($this->clientId)) {
            return false;
        }
        if (empty($this->secret)) {
            return false;
        }
        return true;
    }

    public function errorIfInvalid() {
        if (!$this->isValid()) {
            $msg = "User config is invalid";
            $msg .= "</br>";
            $msg .= $this->printValues();
            throw new Exception("Could not open csv file - Process Stopped.");
        }
    }

    public function printValues() {
        return `Tenancy Shortcode set: $shortCode</br>
                Client ID set:  $clientID </br>
                User GUID to use: $TalisGUID </br>`;       
    }

}