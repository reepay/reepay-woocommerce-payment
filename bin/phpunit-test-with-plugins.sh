echo 'Woo'
export PHPUNIT_PLUGINS="woo"
npm run phpunit

echo 'woo,woo_subs'
export PHPUNIT_PLUGINS="woo,woo_subs"
npm run phpunit

echo 'woo,rp_subs'
export PHPUNIT_PLUGINS="woo,rp_subs"
npm run phpunit

echo 'woo,woo_subs,rp_sub'
export PHPUNIT_PLUGINS="woo,woo_subs,rp_subs"
npm run phpunit