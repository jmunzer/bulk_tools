# TARL Bulk Tools

## Running with XAMPP

This guide will instruct you how to get these bulk tools working on a personal/work computer providing you have administrative rights enabled.

## Installer

- First download & run XAMPP.

        This can be found at https://www.apachefriends.org/
         - Download the latest version for your operating system of choice.

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

- Download the script from: https://github.com/jmunzer/bulk_tools/archive/master.zip
- Extract the downloaded ZIP file to the following location: c:\xampp\htdocs

## Running the tool

- click on: http://localhost/bulk_tools-master/index.html
- Follow steps on webform.
- An example upload file is included as list_id.txt and looks like this (one list ID per line):

        5AF6B766-325D-A542-5837-7AD6DC4F703E
        5D6EA0C2-45F4-268B-878B-0BEBF1C234E9
        CB98F155-576C-553D-7C37-88D1A28CAF49
        45146B68-9C56-92B0-AE28-0AB0D5A5239D
        EAFDDB7B-B580-6FDD-8808-AE63781EB866
        C3FECB4D-1509-60A3-BCB3-6E4142917C3A
        B6E658B0-5DAE-CF98-5FAA-5DB345685320
        C2D6D98F-5523-1A8D-BA00-5ED7114C3E9A
        BC011E42-A395-9248-E147-1C869BFECF02
        185251D1-23F3-F0CF-00C7-286D4645F07C

List IDs can be acquired from the TARL 'All Lists' report by grabbing the unique ID from the end of each List Link:

    http://yorksj.rl.talis.com/lists/5AF6B766-325D-A542-5837-7AD6DC4F703E

Item IDs can be acquired from the TARL 'All List Items' report by grabbing the unique ID from the end of each Item Link:

    http://yorksj.rl.talis.com/items/CED33BC7-309A-3306-47EB-0FB9CB9D9136
    
Paragraph Formatting. If you want to add formatting to your paragraph text, you can use a number of html tags. An example of some functionality can be seen with the below example - if pasted into the 'Input the desired text:' field - 

        <h1>This <u>paragraph</u> is<strong> in bold</strong> and can be <mark background-color: yellow>highlighted</mark>.</h1>

## __IMPORTANT__ Publishing lists

Every edit you make to a list is only made to the draft version of the list. to be visible to end users you need to publish your changes.
Publishing lists is a 'computing expensive' operation and Talis Aspire uses a queuing mechanism to do this in the background so that you can do other things while waiting for the changes to be published.

In the future all API list publishing changes will be on a queue that won't affect real human list editors. Right now, if you put thousands of list publish requests through it will delay publish events for other users. You will always want to make sure that you only make a list publish request when needed.

We will update this readme when this issue is resolved (it is being worked on now).

There are two strategies for publishing the changes that you are making to lists.

### Strategy 1: Edit now, publish later (preferred)

This strategy is about not using the API to publish lists. In the future (for reasons given above) this will not be an issue, but right now this is the preferred method.

1. Go to the all lists report and select lists with a status of `Published with unpublished changes`. These will be lists that may be affected be the last step in this process, you need to decide if it is important to get to a point where there are no lists wuth outstanding changes before you proceed.
2. Edit the list using the scripts included in this repo.
3. __Important__ Leave the list publish option set to false so that the scripts do not publish the lists.
4. Complete all the edits you require. This might mean running the item or paragraph additions multiple times.
5. Once all updates are complete. Go to the all lists report and select lists with a status of `Published with unpublished changes`. These will be lists which you have edited.
6. Use the bulk actions tools within the all lists report to queue them for publishing. These publish events will __not__ impact end users editing lists.

### Strategy 2: Edit now, publish now on last edit

This strategy limits the publish events to being __once__ per list. It uses the API to publish the lists. Right now, this causes end user edits to be queued behind your changes. This may mean that you cause a delay to other Talis Aspire tenants who are editing their lists.

1. Use the scripts to add items, paragraphs or delete items. You may have multiple operations to perform.
2. On each operation, make sure the publish list option is set to false.
3. On the __last__ operation, make sure the publish list option is set to true. This will publish the lists via the API.

If you forget to make the change on the last operation, don't worry, as you can still use the all lists report to find lists with changes and bulk publish those lists.

## Report Files

- Report files are under the root folder of ./report_files and are separated by function.
- If you extracted the tool to the suggestioned location in the above steps, this will be: c:\xampp\htdocs\bulk_tools-master\report_files\
