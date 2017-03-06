# Hackheim Hackespace Member Managment

## Requirements

- Composer. Install Composer with ´curl -sS https://getcomposer.org/installer | php´ https://getcomposer.org/download/

## Getting Started

    - Some server requrements: ´apt install php-mysql php-gd php-curl php-xml php-mbstring php-intl´
    - run ´composer.phar install´ (or composer.phar update if your are upgrading)
    - copy ´.env.example´ to ´.env´ and edit the values
    - migrate the DB ´php vendor/bin/phinx migrate -c lib/phinx.php´
    - setup apache/nginx/etc or try it out with ´php -S localhost:8000 -t public/´

## TODO
- [ ] All purchases need to be stored
- [ ] Log all callbacks
- [ ] Stop subscription https://stripe.com/docs/api#delete_customer
- [ ] Renew payment subscription https://stripe.com/docs/recipes/updating-customer-cards

## Migrations

This is how a migration is created and executed:

    # Enter the vagrant box
    vagrant ssh
    cd /var/www/src
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

