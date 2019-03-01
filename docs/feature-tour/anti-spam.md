# Anti Spam

The Comments plugin provides some protection against spam bots filling out your comment forms. By including the `{{ craft.comments.protect() }}` call in your form template, you'll be protected from the below methods.

## reCAPTCHA

Arguably the best spam-protection service out there from Google, [reCAPTCHA v3](https://www.google.com/recaptcha) can protect your comments from spam-submission. Best of all, being an invisible CAPTCHA field means your users don't have to do anything - it just works.

Turn this on by enabling the `Enable reCAPTCHA` setting in the plugin settings, and fill in your `Site Key` and `Secret Key`. To create these keys, head to the [reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin).

## Additional Protection

Instead of, or in addition to reCAPTCHA, there are 3 main methods below that are incorporated into Comments.

**Prevent non-browser form submissions**

This method ensures that your form is submitted from your website and not from a third-party website or a headless browser. This method implements behaviour similar to CSRF tokens.

**Users must have JavaScript enabled**

Often, when robots access your website programatically, they do not have Javascript enabled. The Javascript spam protection method tests if a user submitting your form has Javascript enabled in their browser and rejects the submission if they do not.

**Prevent against robots who auto-fill all of your form fields**

Many robots fill out every single form field before they submit. The honeypot method of spam prevention creates a hidden field that should not be filled in by a user on your site because they will never see the field or know it exists. When a robot automatically fills in the field and submits the form, the form submission will be denied.

### Spam Guard

While the above methods will keep the majority of spam bots at bay, you may require a more hardened solution. We would highly recommend using [craft.spamguard](https://github.com/selvinortiz/craft.spamguard), which itself uses Akismet to prevent spam.

### Contributions

Many thanks go out to [@selvinortiz](https://github.com/selvinortiz) for his suggestions on the above.