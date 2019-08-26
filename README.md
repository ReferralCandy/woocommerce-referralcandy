# ReferralCandy for WooCommerce

This plugin automatically integrates your WooCommerce store with ReferralCandy app using ReferralCandy Advanced Integration.

![ReferralCandy for WooCommerce Plugin Settings Page](assets/screenshot-1.png)

# Installation

## From your WordPress dashboard

1. Go to **Plugins > Add New**
2. Search for: `ReferralCandy`
3. Click on **Install Now** to install and activate the plugin

## From WordPress.org

1. Download the latest version zip file
3. Go to **Plugins > Add New**
4. Click on **Upload Plugin** to upload the zip file
5. Activate the plugin after the installation

## Configure

1. Go to **WooCommerce > Settings > Integration > ReferralCandy**
2. Fill-out your API Access ID, App ID, and Secret Key. You can get them from [ReferralCandy Admin Settings > Plugin tokens](https://my.referralcandy.com/settings))
3. Select which order status will be sent to ReferralCandy. Default is orders that are marked as `completed`.
4. Select which page the tracking code should be rendered for referral detection. Default is WooCommerce checkout page
5. Click on **Save changes**
6. Create a test order via admin / checkout flow, then update the status to the one selected at step 3
7. Activate your ReferralCandy campaign!
