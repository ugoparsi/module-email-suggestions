# Email suggestions for Maho

Offer customers a correction when the email domain they enter on the account registration or guest checkout form looks like a typo. For example, `shopper@gmial.com` prompts the customer to use `shopper@gmail.com`.

## Install

From the root of your Maho project, run:

```bash
composer require ugoparsi/module-email-suggestions
./maho cache:flush
```

## How suggestions work

The browser checks the domain as the customer enters their email address. It suggests a correction if exactly one known provider name matches after a single edit, including swapping two neighboring letters. It keeps the top-level domain and the text before `@`. The customer can accept the suggestion or continue with the address they entered.

The comparison runs in the browser with a built-in list of common providers. The module sends no requests and stores no email addresses.

If a custom theme uses different email fields, add the `data-email-suggestions` attribute to its `input type="email"` field. On other pages, add `<update handle="email_suggestions"/>` to the page's layout handle to load the script.
