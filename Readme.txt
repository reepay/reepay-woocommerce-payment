=== Billwerk+ Pay ===
Contributors: reepaydenmark,aaitse
Tags: billwerk+, visa, mastercard, dankort, mobilepay
Requires at least: 4.0
Tested up to: 6.6.1
Requires PHP: 7.4
Stable tag: 1.7.10
License: GPL
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html

Accept Visa, MasterCard, Dankort, MobilePay, American Express, Diners Club and more directly on your store with the Billwerk+ Pay Gateway.

== Description ==
Accept Visa, MasterCard, Dankort, MobilePay, American Express, Diners Club and more directly on your store with the Billwerk+ Pay Gateway for WooCommerce.
The Billwerk+ Pay plugin extends WooCommerce allowing you to take payments on your store via Billwerk+ Pay\'s API.

== Installation ==
See installation guide right here: https://docu.billwerk.plus/help/en/apps/woocommerce/setup-woocommerce-plugin.html

== Changelog ==
v 1.7.10
- [Fix] - Discount coupons for regular products couldn't be used if a Billwerk subscription product was also in the cart.
- [Fix] - The order confirmation page now shows a list of split orders (for regular and subscription products) with correct total amounts.
- [Improvement] - Remove Resurs Bank payment method.

v 1.7.9.3
- [Fix] - Vipps Recurring could not save card.

v 1.7.9.2
- [Fix] - Webhook URL for plain permalink structure.
- [Fix] - Removed debug log notice message on cart page.
- [Fix] - Since v1.7.9.1, setting "skip order lines" caused the authorized amount to be multiplied by 100 one more time. Only when using a saved card to pay for a non-subscription product.

v 1.7.9.1
- [Fix] - The payment method 'Vipps MobilePay Recurring' did not save the token.

v 1.7.9
- [Improvement] - New payment method added: "Vipps MobilePay".
- [Improvement] - Warning messages added for "Mobilepay" to encourage switch to "Vipps Mobilepay".
- [Improvement] - Name change payment method "Vipps Recurring" to "Vipps MobilePay Recurring".
- [Improvement] - Warning message added for "MobilePay Subscription" to encourage switch to using "Vipps MobilePay - Recurring" instead.

v 1.7.8.1 - 
* [Fix] - Fixed total calculation missing multiplication with number of items when using setting "Skip order lines".

v 1.7.8 -
* [Fix] - Bug WP warning message "The use statement with non-compound name WC_Reepay_Renewals has no effect." (hotfix 1.7.7.1).
* [Fix] - Bug double amount calculated when using setting "skip order lines" (hotfix 1.7.7.2).
* [Fix] - Bug fix WC discount codes on mixed orders.
* [Improvement] - Extra checkbox in WC standard checkout for subscription conditions.

v 1.7.7.2 -
* [Fix] - Setting skip order lines make calculate amount double.

v 1.7.7.1 -
* [Fix] - WP warning message The use statement with non-compound name WC_Reepay_Renewals has no effect.

v 1.7.7 -
* [Fix] - Missing payment_method_reference data in the Billwerk+ customer_payment_method_added webhook could cause PHP fatal error.
* [Fix] - WooCommerce Subscriptions had issues with change of payment method where orders got payment authorized but were not automatically captured and set to complete.
* [Fix] - Instant capture didn't work for orders with discount.
* [Fix] - Amounts in order notes were wrong for "Failed to settle" notes and some captures.
* [Improvement] - A WordPress notice appears when the module starts to use another API key. This is because the subscriptions are defined in the Billwerk+ account, and the notice is only showed if the subscription module "Optimize" is installed.
* [Compatibility] - Billwerk+ Optimize version 1.2.7

v 1.7.6 -
* [Fix] - Allow the activation of Santander and enforce a redirect for this payment.
* [Compatibility] - Billwerk+ Optimize version 1.2.6

v 1.7.5 - 
* [Improvement] - Product name change to "Billwerk+ Pay".
* [Fix] - Including card fee in the order line prevented the order from being auto-captured.
* [Fix] - WooCommerce subscription renewal orders were not auto-captured and their status remained incomplete.

v 1.7.4 - 
* Added link to install or activate WP Rollback plugin.

v 1.7.3 - Logo fixes, WP last support, Order text clear
v 1.7.2 - Fixes, refactor, tests
v 1.7.1 - New methods add, WPML detect language support, change order calculation
v 1.7.0 - Emoji in title clear, Payment method delete fixes, Fatal and warnings fixes
v 1.6.4 - Fix settle string fatal error
v 1.6.3 - Woocommerce zero payment fixes
v 1.6.2 - Fix user handle generate
v 1.6.1 - Card saving fixes, user creation fixes
v 1.6.0 - Lots of updates and fixes
v 1.5.0 - Billwerk+ version update and thankyou changes
v 1.4.73 - Billwerk+ naming changes
v 1.4.72 - MS tokens saving and HPOS support
v 1.4.71 - Container fixes, manual add token
v 1.4.70 - Fatal fixes, cards live loading, fix multisite webhooks
v 1.4.69 - MS method fixes, add cards logo, fix account settings
v 1.4.68 - Change order handle generation
v 1.4.67 - Fix bugs on checkout and thank you pages
v 1.4.66 - Choose send order lines or not
v 1.4.65 - Fix instant settle to full amount
v 1.4.64 - Fix subscription hook
v 1.4.63 - Global code refactors
v 1.4.62 - Instant settle full order lines
v 1.4.61 - Code refactor, Woo blocks fixes fatal
v 1.4.60 - Fix subscriptions coupons, Add Woo blocks
v 1.4.59 - Fix WC 7.5.0 checkout bug
v 1.4.58 - Fix multilingual webhook endpoints
v 1.4.57 - Only recurring button text
v 1.4.56 - Fix renewals error
v 1.4.55 - Fix fatal webhooks error
v 1.4.54 - Fix webhook configure
v 1.4.53 - Add anyday, fix pending payment
v 1.4.52 - Card type method call fix
v 1.4.51 - Fix invoice email settle
v 1.4.50 - Fix capture exception
v 1.4.49 - Keys info feature
v 1.4.48 - Subscriptions widget
v 1.4.47 - Enable sync default
v 1.4.46 - Fix status gateway
v 1.4.45 - Add settings separator
v 1.4.44 - Capture extra checking
v 1.4.43 - Sync statuses disable
v 1.4.42 - Fix order duplication
v 1.4.41 - Surcharge sync
v 1.4.40 - Manually invoices sync
v 1.4.39 - Subscriptions checkout fix
v 1.4.38 - Subscriptions checkout fix, visual admin features
v 1.4.37 - Card on file when using existing cards
v 1.4.36 - Fix checkout subscriptions
v 1.4.35 - Checkout fix
v 1.4.34 - New card subscriptions fix
v 1.4.33 - Settle items in one request, fix complete order settle
v 1.4.32 - Complete order settle fix
v 1.4.31 - WC subscription fixing
v 1.4.30 - Fix capture
v 1.4.29 - Mobile conditions subscriptions, allow any webhooks, subscriptions recurring
v 1.4.28 - Settle fee on complete
v 1.4.27 - Update customer after reorder
v 1.4.26 - Fix reorder after cancel
v 1.4.25 - Fix duplicated handle, remove settle button for already settled items
v 1.4.24 - Bugfix
v 1.4.23 - Bugfix for webhooks
v 1.4.22 - Settle price and all lines settle dynamically
v 1.4.21 - Fix shipping settle
v 1.4.20 - Change status message, add card token in order custom fields, fix display price refund
v 1.4.19 - Bugfixing
v 1.4.18 - improvements;
v 1.4.15 - improvements, bugfixes;
v 1.4.14 - added order_lines for capturing process;
v 1.4.13 - Bugfixes; improvements
v 1.4.12 - Notice fix, remove not used class, change curl to wp_remote_request
v 1.4.11 - Bugfix, improvements
v 1.4.10 - Bugfix
v 1.4.9 - Bugfix
v 1.4.8 - Fixes for partial settle. Added improvement for invoice handles that has already been authorized/settled
v 1.4.7 - Bugfix
v 1.4.6 - Fix for subscrition reneval process
v 1.4.5 - Added Klarna Slice It
v 1.4.4 - Bugfix
v 1.4.3 - Bugfixes
v 1.4.2 - Improvements; Bugfixes
v 1.4.1 - Bugfix
v 1.4.0 - Added Mobilepay Subscriptions
v 1.3.4 - Added Google Pay and Vipps; bugfixes
v 1.3.3 - Added support for Icelandic krona
v 1.3.2 - Compatibility with php 8.0; avoid using sessions for customers handling; added admin notifications
v 1.3.1 - Webhook configuration updates
v 1.3.0 - Make Apply Pay be available for Safari only
v 1.2.9 - Webhoop script + Apple Pay fix
v 1.2.8 - Bugfix for double email send
v 1.2.7 - Implemented background webhook processing
v 1.2.6 - Bugfix - two emails.
v 1.2.5 - Smaller bugfixes, Please see this video for further instructions on how to set up payment methods in WooCommerce with the new enhancements. https://youtu.be/dk083Yj4Lpg
v 1.2.4 - Small visual improvements
v 1.2.2 - Small visual improvements
v 1.2.1 - Smaller bugfixes
v 1.2.0 - Lot of chanegs including direct actions on an order in woorcommerce.
v 1.1.27 - Added advanced instant-settle + payment-widget in back-office
v 1.1.17 - Small minor bugfixes
v 1.1.16 - Set \"Save CC\" enabled by default
v 1.1.15 - Fixed: Unprocessable Entity error
v 1.1.14 - Bugfixes and Improvements
v 1.1.13 - Bugfixes
v 1.1.12 - Save card for later + minor updates
v 1.1.11 - Fixed problem with incorrect order handlers of renewal orders
v 1.1.10 - Fixed php notice add_footer is called statically and bugfix
v 1.1.9 - Bugfix - Require upgrade if wcs modules installed
v 1.1.8 - Bugfixes + improved logging
v 1.1.7 - Woo Subscriptions improvements
v 1.1.6 - Fixed subscription renewal
v 1.1.5 - Capture payment when order status change
v 1.1.4 - Fixed completed order status that receive via Webhook
v 1.1.3 - Bugfix
v 1.1.2 - Bugfix
v 1.1.1 - Bugfix
v 1.1.0 - Bugfix
v 1.0.0
