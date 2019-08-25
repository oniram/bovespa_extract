INSTALLATION
------------

### About
Developed with Yii framework


### Install 
Configure and install Yii framework <br/>
Configure and install mysql instance. <br/> 
Copy config/db.php.example to config/db.php <br/>
Create a database and set your database info in config/db.php <br/>

Enter in root directory and run commands bellow
 ```
$ composer install
 ```

 ```
$ php yii migrate
 ```

 ```
$ php yii serve
 ```

Open browser and navigate to: http://localhost:8080


Route for import actions by date:
http://localhost:8080/index.php?r=site/import


The action for this route is actionImport and was defined in: controllers/SiteController.php
