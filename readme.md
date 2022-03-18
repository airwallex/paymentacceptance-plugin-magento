# Airwallex Payments

## Compatibility:
This module was tested on:
* Magento 2.3 @ PHP 7.3

## Installing/Getting started 

```bash
composer require airwallex/payments-plugin-magento:^0.0
```

Enable the module
```bash
bin/magento module:enable Airwallex_Payments
```

Module install
```bash
bin/magento setup:upgrade
```

Clean Magento Cache
```bash
bin/magento module:enable cache:flush
```

