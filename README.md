Magento 2 State Wise Shipping
================================

## How to install?


Install with Composer!

	```
	composer require mageali/magento2-customstatewiseshipping
	php bin/magento module:enable MageAli_CustomStateWiseShipping
	php bin/magento setup:upgrade
	php bin/magento setup:di:compile
	php bin/magento setup:static-content:deploy
	php bin/magento cache:flush
 
	```


Install manually!

  * Download
  * Extract files
  * In your Magento 2 root directory create folder app/code/MageAli\CustomStateWiseShipping
  * Copy files and folders from the archive to that folder
  * In the command line, using "cd", navigate to your Magento 2 root directory
  * Run the commands:
	```
	php bin/magento setup:upgrade
	php bin/magento setup:di:compile
	php bin/magento setup:static-content:deploy
	php bin/magento cache:flush
	```



## How to use this?

	After installing this module you will see a shipping method in the admin panel named "Custom State Wise Shipping"  (Admin panel->stores->configuration->sales->shipping method)

	You can see the "custom shipping method" on the Shopping Cart page and Checkout page.


## License
	* it is free
