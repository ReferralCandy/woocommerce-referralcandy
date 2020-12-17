=== ReferralCandy for WooCommerce ===

Contributors: referralcandy
Tags: referral program, customer referral program, refer-a-friend, tell-a-friend, marketing, customer acquisition, word-of-mouth, marketing app, viral marketing, social marketing, marketing, woocommerce
Requires at least: 3.0
Tested up to: 5.4
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The 'ReferralCandy for WooCommerce' plugin automatically integrates ReferralCandy with your WooCommerce store. Get more referred sales now!

== Description ==

[ReferralCandy](http://www.referralcandy.com/) is a powerful tool allowing you to automate customer referral campaigns for your WooCommerce store.

* Get more sales from word-of-mouth
* Reward customers for referring their friends to your store
* Easy install. 30 day risk-free trial. Get up and running today!

= Boost your sales with a referral marketing program =

ReferralCandy helps you increase your sales by rewarding your customers when they refer people to your store. We give your customers a personal reward link that they can share with their friends.

= Easily add a refer-a-friend program to your store =

Simple to get up and running today -- no developers needed!

= How ReferralCandy works: =

* A customer buys something from your store
* We send them a coupon code to share with friends
* When their friends buy from you, the customer receives a referral reward

Everybody wins: your original customer gets rewarded, their friends get a discount, you make more sales and get a new customer! We do all the referral tracking, so you don't have to do anything.

Learn about the details on our [How ReferralCandy Works](http://www.referralcandy.com/how-it-works) page.

== Installation ==

**Requires WooCommerce** 2.3.3 or higher

= From your WordPress dashboard (recommended) =

1. Go to **Plugins > Add New**
2. Search for: `ReferralCandy`
3. Click on **Install Now** to install and activate the plugin

= From WordPress.org =

1. Download the latest version zip file
3. Go to **Plugins > Add New**
4. Click on **Upload Plugin** to upload the zip file
5. Activate the plugin after the installation

= Configure =

1. Go to **WooCommerce > Settings > Integration > ReferralCandy**
2. Fill-out your App ID and Secret Key. You can get them from [ReferralCandy Admin Settings > Plugin tokens](https://my.referralcandy.com/settings))
3. Click on **Save changes** and it's done!

**Please make sure that the plugin can make outbound requests for the API calls


== Frequently Asked Questions ==

We maintain a list of FAQs on our [help page](http://answers.referralcandy.com/)!

== Screenshots ==

1. ReferralCandy for WooCommerce Plugin Settings Page

== Changelog ==

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
