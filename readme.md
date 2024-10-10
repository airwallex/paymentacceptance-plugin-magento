# Airwallex Payments

## Compatibility:

This module was tested on:
* Magento 2.4.2 @ PHP 7.4
* Magento 2.4.3 @ PHP 7.4
* Magento 2.4.6 @ PHP 8.1
* Magento 2.4.7 @ PHP 8.3

## Installing/Getting started 

```bash
composer require airwallex/payments-plugin-magento
```

Enable the module
```bash
bin/magento module:enable Airwallex_Payments
```

Module install
```bash
bin/magento setup:upgrade
```

Reindex customer_grid
```bash
bin/magento indexer:reindex customer_grid
```

Clean Magento Cache
```bash
bin/magento cache:clean
bin/magento cache:flush
```

## Upgrade

```bash
composer update airwallex/payments-plugin-magento
bin/magento setup:upgrade
bin/magento indexer:reindex customer_grid  # This command is required only during the first upgrade.
bin/magento cache:clean
bin/magento cache:flush
```

