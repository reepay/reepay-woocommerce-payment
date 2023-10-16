export HPOS_ENABLED="no"

echo 'Plugins: woo'
echo "HPOS enabled: $HPOS_ENABLED"
export PHPUNIT_PLUGINS="woo"
php ./vendor/bin/phpunit

echo 'Plugins: woo,woo_subs'
echo "HPOS enabled: $HPOS_ENABLED"
export PHPUNIT_PLUGINS="woo,woo_subs"
php ./vendor/bin/phpunit

echo 'Plugins: woo,rp_subs'
echo "HPOS enabled: $HPOS_ENABLED"
export PHPUNIT_PLUGINS="woo,rp_subs"
php ./vendor/bin/phpunit

echo 'Plugins: woo,woo_subs,rp_sub'
echo "HPOS enabled: $HPOS_ENABLED"
export PHPUNIT_PLUGINS="woo,woo_subs,rp_subs"
php ./vendor/bin/phpunit

export HPOS_ENABLED="yes"

echo 'Plugins: woo,woo_subs,rp_sub'
echo "HPOS enabled: $HPOS_ENABLED"
export PHPUNIT_PLUGINS="woo,woo_subs,rp_subs"
php ./vendor/bin/phpunit