# Email suggestions for Maho

Offer customers a correction when the email domain they enter on the account registration or guest checkout form looks like a typo. For example, `shopper@gmial.com` prompts the customer to use `shopper@gmail.com`.

## Install

Requires PHP 8.5.1 or later and Maho 26.11.x or 27.1.x. Tested on Maho 26.11.0, Maho 26.11.3, and the Maho 27.1 development branch.

Run these commands from the root of your Maho project.

```bash
composer config repositories.ugoparsi-email-suggestions vcs https://github.com/ugoparsi/module-email-suggestions
composer require 'ugoparsi/module-email-suggestions:^1.0'
./maho cache:flush
```

## How suggestions work

The browser checks the domain as the customer enters their email address. It suggests a correction if exactly one known provider name matches after a single edit, including swapping two neighboring letters. It keeps the top-level domain and the text before `@`. The customer can accept the suggestion or continue with the address they entered.

The comparison runs in the browser with a built-in list of common providers and sends no requests.

If a custom theme uses different email fields, add the `data-email-suggestions` attribute to its `input type="email"` field. On other pages, add `<update handle="email_suggestions"/>` to the page's layout handle to load the script.

## Check email domains

To enable the optional warning, open **System → Configuration → Customers → Customer Configuration → Email Suggestions**. Set **Check email domains** to **Yes** and save. The setting is off by default.

The browser sends the domain to your store when a customer changes a valid email address. The store checks DNS mail records and, when needed, IP address records. Domains without mail records can show a warning. Customers can continue with the address they entered. If DNS is unavailable, the form continues without a warning.

The check supports public DNS domain names and has a 350 ms lookup budget. The DNS resolvers configured on your server receive the domain and the record types the store queries.

The default limit is 30 requests per minute from each client IP across all store views. To change it, open **System → Configuration → Advanced → System → Rate limit → Per-endpoint Limits** in **Default Config**. Clear **Use Default** if selected, enter a limit in **Email domain check (storefront)** and save. A value of `0` disables the limit.
