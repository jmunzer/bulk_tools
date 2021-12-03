<?php

class UploadedFile {

    private $filePath = null;
    private $fileHandler = null;

    public function getFileHandler(){
        if ($fileHandler === null) {
            openFileHandler();
        }
        return $this->fileHandler;
    }

    private function openFileHandler() {
        if ($this->filePath === null) {
            moveFile();
        }
        $this->fileHandler = fopen($this->filePath, "r");
        if ($file_handle == FALSE) {
            throw new Exception("Could not open csv file - Process Stopped.");
        }
    }

    private function moveFile() {
        $uploaddir = '../uploads/';
        $this->filePath = $uploaddir . basename($_FILES['userfile']['name']);
        
        echo '<pre>';
        if (move_uploaded_file($_FILES['userfile']['tmp_name'], $file)) {
            echo "File is valid, and was successfully uploaded.\n";
        } else {
            echo "File is invalid, and failed to upload - Please try again. -\n";
        }
        
        echo "</br>";
        print_r($file);
        echo "</br>";
        echo "</br>";
    }
    
}