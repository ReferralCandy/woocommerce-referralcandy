# ReferralCandy for WooCommerce

This plugin automatically integrates your WooCommerce store with ReferralCandy app using ReferralCandy Advanced Integration.

![ReferralCandy for WooCommerce Plugin Settings Page](assets/screenshot-1.png)

# Installation

Welcome to ReferralCandy! Get started with our easy integration process:

**Note: If you have already completed your account setup in the ReferralCandy dashboard, you may skip step 1.**

1. Start Your Free Trial: [Sign up here](https://my.referralcandy.com/signup?utm_source=woocommerce-plugin&utm_medium=plugin&utm_campaign=woocommerce-integration-signup).
2. Integrate with WooCommerce: In your dashboard, go to ["Integrations" > "WooCommerce"](https://my.referralcandy.com/integration).
3. Enter API Details: Copy your API Access ID, App ID, and Secret Key and paste it in the Woocommerce plugin integration page.

That's it! Your store is now connected. A purchase is required to confirm integration success.

Need help with integration? Check out our [blog](https://www.referralcandy.com/blog/woocommerce-setup?utm_source=woocommerce-plugin&utm_medium=plugin&utm_campaign=woocommerce-integration-blog) for an extensive guide and useful tips.

# Quick start to developing the plugin locally

Your local environment needs to be configured to work with `wp-env`.

Ensure you have fulfilled the [prerequisites](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#prerequisites).

Using a package manager of your choice (in this case, we are using `pnpm`), enter in your CLI:

```
pnpm i
pnpm run start
```

Your WordPress site should now be running on `localhost:8888`

# Testing over HTTPS

Some flows cannot be exercised on `localhost`: the WooCommerce REST API rejects key authentication
without SSL, and ReferralCandy webhooks, mobile POS clients and payment gateways all need to reach
the store from outside your machine. For those, expose the local store through a
[cloudflared](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/)
tunnel. To bring up the site and the tunnel together:

```
pnpm run dev
```

That starts wp-env, flushes the rewrite rules, then runs the tunnel in the foreground. It prints a
public `https://` URL and keeps running until you press Ctrl-C, at which point only the tunnel stops
— the site stays up. `http://localhost:8888` keeps working the whole time.

If wp-env is already running, `pnpm run tunnel` starts just the tunnel.

By default it creates a quick tunnel, which gets a fresh random `*.trycloudflare.com` hostname on
every run. To use your own stable hostname instead, set both variables before running:

```
WP_TUNNEL_HOST=store.example.com WP_TUNNEL_NAME=my-tunnel pnpm run tunnel
```

`WP_TUNNEL_NAME` is the cloudflared tunnel name or UUID that serves that hostname; you need to have
created it with `cloudflared tunnel create` and pointed a DNS record at it beforehand. The ingress
entry for that hostname in `~/.cloudflared/config.yml` must already point at the wp-env port, for
example:

```yaml
ingress:
  - hostname: store.example.com
    service: http://localhost:8888
```

Named mode deliberately does not pass `--url` to cloudflared, because that flag would replace your
whole ingress and take every other hostname on the tunnel offline.