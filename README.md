INSTALLATION
------------

### About
Developed with Yii framework


### Install 
Configure and install Yii framework
Configure and install mysql instance. 
Copy config/db.php.example to config/db.php
Create a database and set your database info in config/db.php

Enter in root directory and run commands above
$ composer install
$ php yii migrate
$ php yii serve

Open browser and navigate to: http://localhost:8080


Route for import actions by date:
http://localhost:8080/index.php?r=site/import