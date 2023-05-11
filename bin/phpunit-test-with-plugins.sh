echo 'Plugins: woo'
export PHPUNIT_PLUGINS="woo"
php ./vendor/bin/phpunit

echo 'Plugins: woo,woo_subs'
export PHPUNIT_PLUGINS="woo,woo_subs"
php ./vendor/bin/phpunit

echo 'Plugins: woo,rp_subs'
export PHPUNIT_PLUGINS="woo,rp_subs"
php ./vendor/bin/phpunit

echo 'Plugins: woo,woo_subs,rp_sub'
export PHPUNIT_PLUGINS="woo,woo_subs,rp_subs"
php ./vendor/bin/phpunit