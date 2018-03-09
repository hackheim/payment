# A Minimal Recurring Payment Solution 

Using stripe as payment gateway

## Requirements

- Composer. Install Composer with ´curl -sS https://getcomposer.org/installer | php´ https://getcomposer.org/download/
- stripe account
- mailgun account

## Getting Started

    - Some server requrements: ´apt install php-mysql php-gd php-curl php-xml php-mbstring php-intl´
    - run ´composer.phar install´ (or composer.phar update if your are upgrading)
    - copy ´.env.example´ to ´.env´ and edit the values
    - migrate the DB ´php vendor/bin/phinx migrate -c lib/phinx.php´
    - setup apache/nginx/etc or try it out with ´php -S localhost:8000 -t public/´

## TODO

- [ ] Maybe handle stripe "charge.refunded" event?
- [ ] Add DB and receipts backup solution
- [ ] Avvoid that payment errors create multiple customers when registering

## Fiken setup

- Create account 1578  for Stripe transit account and a Stripe supplier contact
- FIKEN_COMPANY env setting should be the same as your name used in urls at fiken "your-name-as"
- Password and email is a regular user login

## Migrations

This is how a migration is created and executed:

    # In source root folder:
    php vendor/bin/phinx create SomeChangeYouWantToDo
    # Edit it
    # Run it:
    php vendor/bin/phinx migrate -c phinx.php


## Documentation
- https://stripe.com/docs/subscriptions/quickstart
- https://fatfreeframework.com/3.6/views-and-templates
- https://stripe.com/docs/testing
- https://github.com/Wixel/GUMP
- https://packagist.org/packages/oasis/logging
- http://docs.phinx.org/en/latest/migrations.html
- https://www.linode.com/docs/websites/nginx/nginx-ssl-and-tls-deployment-best-practices
- https://cipherli.st/

## Checklist when moving from test to production
- Update stripe callback. Remove test and add production callback
- Check that test coupons exists in prod
- Check that test plans exists in prod
- Delete receipt files
- Clear logs
- Empty DB and/or fill prefill with legacy data
- Switch to stripe production keys
