export HPOS_ENABLED="no"

echo 'PHPUNIT_PLUGINS=no'
echo 'HPOS_ENABLED=no'
export PHPUNIT_PLUGINS="no"
php ./vendor/bin/phpunit

echo 'PHPUNIT_PLUGINS=woo'
echo 'HPOS_ENABLED=no'
export PHPUNIT_PLUGINS="woo"
php ./vendor/bin/phpunit

echo 'PHPUNIT_PLUGINS=woo,woo_subs'
echo 'HPOS_ENABLED=no'
export PHPUNIT_PLUGINS="woo,woo_subs"
php ./vendor/bin/phpunit

echo 'PHPUNIT_PLUGINS=woo,rp_subs'
echo 'HPOS_ENABLED=no'
export PHPUNIT_PLUGINS="woo,rp_subs"
php ./vendor/bin/phpunit

echo 'PHPUNIT_PLUGINS=woo,woo_subs,rp_subs'
echo 'HPOS_ENABLED=no'
export PHPUNIT_PLUGINS="woo,woo_subs,rp_subs"
php ./vendor/bin/phpunit

export HPOS_ENABLED="yes"

echo 'PHPUNIT_PLUGINS=woo,woo_subs,rp_subs'
echo 'HPOS_ENABLED=yes'
export PHPUNIT_PLUGINS="woo,woo_subs,rp_subs"
php ./vendor/bin/phpunit