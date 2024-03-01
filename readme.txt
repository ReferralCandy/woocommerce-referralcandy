=== ReferralCandy – Referral & Affiliate Program ===

Contributors: referralcandy
Tags: referral program, customer referral program, referral software, refer-a-friend, affiliate program, referral, word-of-mouth, referral marketing, affiliate, affiliate marketing, affiliate manager, woo commerce
Requires at least: 3.0
Tested up to: 6.3
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

ReferralCandy: A customer referral program for clients who love your brand! Get more referral sales now!

== Description ==

[ReferralCandy](http://www.referralcandy.com/) is a powerful tool allowing you to automate customer referral campaigns for your WooCommerce store.

* Get more sales from referrals
* Reward customers for referring their friends to your store
* Easy install. 14 day risk-free trial. Get up and running today!

= Create a customer referral program for your brand and grow sales with referral and affiliate marketing =

ReferralCandy helps Woocommerce stores attract customers, increase sales and grow using the power of referral, affiliate marketing and rewards. Referral programs and affiliate programs allow you to retain customers and reward them when they refer their friends. Customers get a referral link they can share with friends to promote the brand. Referral and affiliate programs work for all industries: fashion, nutrition, electronics, home, pet and more.

* Set up a referral or affiliate program and get the first sales within 30 minutes
* Enable post-purchase popups, emails and pages to enroll customers in the program
* Automate customer rewards: Cash and coupons for one-off and subscription sales
* Customize your referral program with your logo, images and brand colors
* Integrate with Klaviyo, ReCharge, Skio, Awtomic, Bold, Loop, Appstle, Stay, Seal

= Easily add a refer-a-friend program to your store =

Simple to get up and running today -- no developers needed!

= How ReferralCandy works: =

* A customer buys something from your store
* We send them a coupon code to share with friends
* When their friends buy from you, the customer receives a referral reward

Everybody wins: your original customer gets rewarded, their friends get a discount, you make more sales and get a new customer! We do all the referral tracking, so you don't have to do anything.

Learn about the details on our [How ReferralCandy Works](http://www.referralcandy.com/how-it-works) page.

https://www.youtube.com/watch?v=arlElJkyGwc

== Installation ==

**Requires WooCommerce 2.3.3 or higher**

Welcome to ReferralCandy! Get started with our easy integration process:

**Note: If you have already completed your account setup in the ReferralCandy dashboard, you may skip step 1.**

1. Start Your Free Trial: [Sign up here](https://my.referralcandy.com/signup?utm_source=woocommerce-plugin&utm_medium=plugin&utm_campaign=woocommerce-integration-signup).
2. Integrate with WooCommerce: In your dashboard, go to ["Integrations" > "WooCommerce"](https://my.referralcandy.com/integration).
3. Enter API Details: Copy your API Access ID, App ID, and Secret Key and paste it in the Woocommerce plugin integration page.

That's it! Your store is now connected. A purchase is required to confirm integration success.

Need help with integration? Check out our [blog](https://www.referralcandy.com/blog/woocommerce-setup?utm_source=woocommerce-plugin&utm_medium=plugin&utm_campaign=woocommerce-integration-blog) for an extensive guide and useful tips.

== Frequently Asked Questions ==

We maintain a list of FAQs on our [help page](https://help.referralcandy.com/)!

== Screenshots ==

1. ReferralCandy for WooCommerce Plugin Settings Page

== Changelog ==

= 2.4.5 =
* Update readme.txt

= 2.4.4 =
* Ensure links open in a new tab

= 2.4.3 =
* Update integration description

= 2.4.2 =
* Update readme.txt

= 2.4.1 =
* Fixed issue where plugin form fields not displaying tooltip

= 2.4.0 =
* Updated to use WC CRUD class for compatibility with HPOS

= 2.3.4 =
* Fixed issue where contact being unsubscribed

= 2.3.3 =
* Fixed errors from API calls - PR from Jared Hill (https://github.com/jaredhill4)

= 2.3.2 =
* Added locale field on order meta to be sent to ReferralCandy to send emails with correct languages to customers
* Updated additional order meta to have short keys to save space
* Fixed translation of post-purchase popup widget on checkout

= 2.3.1 =
* Added checkbox to enable/disable marketing checkbox on checkout
* Post-purchase popup widget uses store locale upon checkout

= 2.3.0 =
* Added marketing acceptance checkbox on checkout
* Added option to customize marketing acceptance label
* Updated order class to include `accepts_marketing` data taken from marketing acceptance upon checkout
* Added feature that adds a note on the order if a purchase was successfully submitted to ReferralCandy

= 2.2.4 =
* Fixed issue where there the tracking code is rendered before the html

= 2.2.3 =
* Fixed issue where the plugin uses the `rc_referrer_id` cookie even if non-existent

= 2.2.2 =
* Fixed failing code for logging API requests

= 2.2.1 =
* Fix for non-English stores not submitting purchases to ReferralCandy

= 2.2.0 =
* Reinstated tracking code rendering as backup referral detection if setting referrer cookies fail
* Added option to select the status of orders that are to be sent to ReferralCandy
* Added option to render tracking code on a different page if the thank you page was changed
* Updated method of fetching order information
* Removed saving of metadata except for `rc_referrer_id`
* Improved compatibility with WooCommerce v2.6 and above
* Improved compatibility with ShipStation integration

= 2.1.2 =
* Updated logic for setting cookies to improve referral detection

= 2.1.1 =
* Defaulted post-purchase popup's accepts marketing field to 'false'

= 2.1.0 =
* Fixed bug where the orders for subscriptions are being marked as completed (i.e. ShipStation, WooCommerce Subscriptions)

= 2.0.2 =
* Added link to Settings on the plugin page for easier access
* Added warnings for required keys that are not present

= 2.0.1 =
* Fixed bug where the plugin breaks the Woocommerce Integration tab

= 2.0.0 =
* Plugin now uses the API integration of ReferralCandy
* Only orders marked as completed are submitted to ReferralCandy
* Orders created from the Woocommerce dashboard are now registered in the ReferralCandy dashboard

= 1.3.7 =
* Fixed issue where orders with spaces in the names or have no name at all produce checksum errors

= 1.3.6 =
* Added an option to enable the post-purchase popup widget on the checkout completed / thank you page

= 1.3.5 =
* Fixed whitescreen of death issue when the Woocommerce plugin is deactivated while the ReferralCandy plugin is active

= 1.3.4 =
* Fixed deprecation messages for Woocommerce version 3.0 and above

= 1.3.3 =
* Fixed email encoding issue

= 1.3.2 =
* Fixed md5 calculation that caused invalid checksum.

= 1.3.1 =
* Fix data encoding cause invalid checksum.

= 1.3 =
* Use the order number as the external reference ID.

= 1.2 =
* Implement proper timezone handling.

= 1.1 =
* Automatically show the settings page after plugin activation.

= 1.0 =
* Use advanced integration to integrate with ReferralCandy app.
