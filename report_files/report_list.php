<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css?family=Raleway" rel="stylesheet">

        <style>
            html, body {
                margin: 5;
                padding: 0;
            }
            body, input, select { font-family: 'Raleway', sans-serif; } 
            select, input { font-size: 1em; }
            form { font-size: 1em; }
            #reportdir {
                display: inline-block;
                position: relative;
                width: 50%;
                box-sizing: border-box;
                padding: 44px;
                vertical-align: top;
            }
        </style>
    </head>

    <body>
        <div id="reportdir">

        </br><a href="../index.html">Back to Index</a></br>

            <?php
                $files = scandir('./');
                sort($files);

                echo nl2br ("\n\n");
                echo "Report Files. Click to download: ";
                echo nl2br ("\n\n");

                foreach($files as $file) {
                    echo "<a href='./" . $file . "'>" . $file . "</a";
                    echo nl2br ("\n\n");
                }
            ?>

        </div>
    </body>

</html>
