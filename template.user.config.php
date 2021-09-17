<?php
/**
 * This file contains user config. you can use this to set key's and secrets so that you don't have to set them elsewhere.
 * 
 * Make a copy of this template file and call it user.config.php.
 * 
 * Keep this copied file safe. 
 * 
 */

/**
 * The shortcode for your Talis Aspire tenancy
 */
$shortCode = '';

/**
 * The Client ID given to you by Talis Support
 */
$clientID = '';

/**
 * The Client Secret given to you by Talis Support
 */
$secret = '';

/**
 * A talis_guid to use for any requests which require the X-Effective-User header to be set.
 * You will find this in the all users report CSV export.
 */
$TalisGUID = '';

/**
 * Used with the New Acquisitions tool, this sets a default item importance to assign to newly created items
 * See documentation within new_acq folder for more details
 * for example: 'http://yorksj.rl.talis.com/config/importance5ab0e620d975a'
 */
$importanceID = '';

/**
* Used with the New Acquisitions tool, this is the API url for the Alma report. A feed of resources from your LMS.
* for example: "https://api-eu.hosted.exlibrisgroup.com/almaws/v1/analytics/reports?path=%2Fshared%2FUniversity%20of%20Westminster%2FReports%2FContent%20and%20Digital%20Services%2FNew%20Acquisitions%20-%20TARL%20API&limit=25&col_names=true&apikey=l7************************"
* Please see new_acq documentation for more details.
*/
$alma_lookup = '';
