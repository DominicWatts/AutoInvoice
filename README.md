# AutoInvoice #

Automatically invoice orders after checkout event

Config within admin determins which payment methods this Auto Invoice applies to

Stores > Configuration > Xigen > Auto Invoice

![Screenshot](https://i.snag.gy/UQrP9Y.jpg)

# Install instructions #

`composer require dominicwatts/faker`

`php bin/magento setup:upgrade`

# Usage instructions #

Console script to generate invoices by increment ID

`xigen:invoice-order [-o|--orderid ORDERID`

`php bin/magento xigen:invoice-order -o 000000006`