<?php 

class ReportRow {

	public $itemID = "";
	public $oldURL = "";
	public $newURL = "";
	public $transactionType = "";
    public $resourceID = "";
	public $currentWebAddressArray = null;
	public $currentOnlineResource = null;
	public $newWebAddressArray = null;
	public $actionMessage = "";
	public $failure = false;
	public $updated = false;
	private $resourceReports = [];


	private function getFields()
	{
		return [
			"Item ID" => $this->itemID,
			"Resource ID" => $this->resourceID,
			"Old URL" => $this->oldURL, 
			"New URL" => $this->newURL,
			"Transaction Type" => $this->transactionType,
			"Current Web Address Array" => $this->getArrayValueAsString($this->currentWebAddressArray, "", "Web address is empty"),
			"Current Online Resource" => $this->currentOnlineResource,
			"New Web Address Array" => $this->getArrayValueAsString($this->newWebAddressArray, "", "New Web address is empty"),
			"Message" => $this->actionMessage,
			"Status" => $this->getOverallStatus(),
		];
	}

	/**
	 * Will decide the text to report for the "Status" column.
	 * It will report a response if this is not a resource.
	 */
	private function getOverallStatus(){
		if($this->resourceID !== ""){
			return "";
		}
		else {
			return $this->getSuccess() ? "Success" : "Fail";
		}
	}

	/**
	 * This will examine itself and all attached resource reports.
	 *
	 * It will report success only if none of them have been marked
	 * as "failure", and at least one of them is marked a "updated".
	 *
	 * @return bool false if failure, true if success
	 */
	public function getSuccess(){
		if (!$this->getFailure() && $this->getUpdated()){
			return true;
		}
		return false;
	}

	public function getFailure(){
		if ($this->failure == true) {
			return true;
		}
		foreach($this->getResourceReports() as $r){
			if ($r->getFailure()){
				return true;
			}
		}
		return false;
	}

	public function getUpdated(){
		if ($this->updated == true) {
			return true;
		}
		foreach($this->getResourceReports() as $r){
			if ($r->getUpdated()){
				return true;
			}
		}
		return false;
    }
    
    /**
     * Format an array as a pretty string or return some default values if the array is empty
     */
    private function getArrayValueAsString($value, $valueIfNull, $valueIfEmpty){
        if(!is_null($value)) {
            if(is_array($value)){
                if(count($value) === 0) {
                    return $valueIfEmpty;
                }
                if(count($value) > 0) {
                    return join(' | ', $value);
                }
            } else {
                return $value;
            }
        }
        return $valueIfNull;
    }

	/**
	 * Get the headers as a CSV row
	 * @return string
	 */
	public function getCsvHeader(){
		$headerArr = array_keys($this->getFields());
		return join(",", $headerArr);
	}

	/**
	 * Get the values as a CSV row
	 * @return string
	 */
	public function getCsvRow(){
		$rowArr = array_values($this->getFields());
		return join(",", $rowArr);
	}

	/**
	 * Add a resource report
	 * @param $r ReportRow
	 */
	public function addResourceReport(ReportRow $r){
		$this->resourceReports[] = $r;
	}

	/**
	 * Get all resource reports
	 * @return array
	 */
	public function getResourceReports(){
		return $this->resourceReports;
	}
}

?>