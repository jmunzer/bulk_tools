<?php

class Tool {
    private $columnConfig = [];
    private $validationMap = [
        "ITEM" => "validateIdentifierFromURLOrUUID"
    ];
    private $uploadedFileHandle = null;

    public function __construct(UploadedFile $uploadedFile, $columnConfig, $operation){
        
        $this->uploadedFileHandle = $uploadedFile->getFileHandler();

        // temporaraily hard coding
        $this->columnConfig = [
            1 => "ITEM",
            2 => ""
        ];

        // read the file
        $this->validateFile();
        // check each row
        // process the file
    }

    private function validateFile() {
        // length of file?
        // validate Rows
    }

    private function validateRow() {
        // use the column config with the validation map 
        // to decide which validations to run for each column
        // 
    }

    private function readRows($operation){
        try {
            while (($line = fgetcsv($this->uploadedFileHandle, 1000, ",")) !== FALSE) {
                $operation($line);
            }
        } catch(Exception $e){
            echo_message_to_screen(ERROR, $e->message);
            exit(1);
        }
    }

    private function resetFile(){
        rewind($this->uploadedFileHandle);
    }

    private function validateIdentifierFromURLOrUUID($value){
       // check whether we have a URL or Item ID 
       
       // clean up the input value
       $parts = explode(" ", $line_of_text);
       $item = filter_var(trim($parts[0]), FILTER_VALIDATE_URL);
   
       // regex pattern for a valid UUID
       $UUID_valid = '/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/';
   
       if(empty($item)){
           // this is not a URL
           $itemId = trim($parts[0]);
   
       } else {
           // this is a URL
           $itemLink = preg_split('/[\/\.]/', $item);
           $itemId = implode(" ",preg_grep($UUID_valid, $itemLink));
       }
   
       // validate the UUID
       if(preg_match($UUID_valid, $itemId)) {
           echo "Valid UUID: $itemId </br>";
       } else {
           echo "Error with reading a valid Item ID, please verify input file: $itemId </br>";
        //   continue;
       }
    }

    private function isUUID() {

    }

    private function isURL() {

    }

    private function isResourceID(){

    }

}       // length of file?
        // validate Rows
    }

    private function validateRow() {
        // use the column config with the validation map 
        // to decide which validations to run for each column
        // 
    }

    private function readRows($operation){
        try {
            while (($line = fgetcsv($this->uploadedFileHandle, 1000, ",")) !== FALSE) {
                $operation($line);
            }
        } catch(Exception $e){
            echo_message_to_screen(ERROR, $e->message);
            exit(1);
        }
    }

    private function resetFile(){
        rewind($this->uploadedFileHandle);
    }

    private function validateIdentifierFromURLOrUUID($value){
       // check whether we have a URL or Item ID 
       
       // clean up the input value
       $parts = explode(" ", $line_of_text);
       $item = filter_var(trim($parts[0]), FILTER_VALIDATE_URL);
   
       // regex pattern for a valid UUID
       $UUID_valid = '/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/';
   
       if(empty($item)){
           // this is not a URL
           $itemId = trim($parts[0]);
   
       } else {
           // this is a URL
           $itemLink = preg_split('/[\/\.]/', $item);
           $itemId = implode(" ",preg_grep($UUID_valid, $itemLink));
       }
   
       // validate the UUID
       if(preg_match($UUID_valid, $itemId)) {
           echo "Valid UUID: $itemId </br>";
       } else {
           echo "Error with reading a valid Item ID, please verify input file: $itemId </br>";
        //   continue;
       }
    }

    private function isUUID() {

    }

    private function isURL() {

    }

    private function isResourceID(){

    }

}