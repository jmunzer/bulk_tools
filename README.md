# PARA_UPD

## Running with XAMPP

This guide will instruct you how to get this paragraph updater working on a personal/work computer providing you have administrative rights enabled.

## Installer

- First download & run XAMPP.

    This can be found at https://www.apachefriends.org/ - Download the latest version for your operating system of choice.

- Once in the installer you will be prompted to 'Select Components'.

    Untick everything besides the following:

            Apache
            PHP

- Install Location

    Install XAMPP to "C:\xampp" if on Windows.

- Windows Firewall

    Depending on your Windows security settings, you may receive a Windows Security Alert from Windows Defender Firewall asking for Apache HTTP Server to communicate on certain networks. If you do, ensure that the following network is ticked:

        # Private networks, such as my home or work network

## Running the XAMPP environment

- Once XAMPP has successfully installed, please run this service by doing the following:

    1) Click on the start menu
    2) Type 'xampp'
    3) Select 'XAMPP Control Panel'

- Once the XAMPP Control Panel is running, start the Apache service by clicking 'Start' under Actions. If successful, you should see the following:

    10:49:53  [main] 	Control Panel Ready
    10:49:56  [Apache] 	Attempting to start Apache app...
    10:49:56  [Apache] 	Status change detected: running

    ...and the Apache module will be highlighted in green.

## Putting the tool in the right place

- Download the script from: https://github.com/jmunzer/para_upd/archive/master.zip
- Extract the downloaded ZIP file to the following location: c:\xampp\htdocs

## Running the tool

- click on: http://localhost/para_upd-master/src/index.html
- Follow step on webform.

## Report Files

- Report files are under the name and root folder of ./output.log
- If you extracted the tool to the suggestioned location in the above steps, this will be: c:\xampp\htdocs\para_upd-master\src\output.log
