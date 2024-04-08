
# Joomla Virtuemart Plugin

Welcome to the Paystack Joomla Virtuemart plugin repository on GitHub. 
Here you can browse the source code, look at open issues and keep track of development.

## :warning: **Deprecation Notice**

We regret to inform you that the Joomla Virtuemart Plugin is now deprecated and will no longer be actively maintained or supported.

**Reasons for deprecation**:
- Compatibility issues with the latest software versions
- Security vulnerabilities that cannot be addressed sufficiently.
- Obsolete functionality that is no longer relevant

To ensure a seamless experience, we recommend exploring the Paystack Integrations Directory for [alternative plugins](https://paystack.com/gh/integrations?category=cart#:~:text=Online-,Store,-Site%20Builder) that are actively maintained and supported.

## Installation 

1. Install the plugin using normal Joomla extension installation
2. Go to `Extensions-&gt;Plugin Manager` and search for `VM Payment - Paystack`
3. Click on the plugin name and enable the plugin
4. Go to `Components-&gt;Virtuemart-&gt;Payment methods`.
6. Fill the form and select Payment Method: `VM Payment - Paystack` then `apply/save`. You may need to select all the available Shopper Groups.
7. Click on the `Configuration` tab, fill the parameters from your [Paystack Dashboard](https://dashboard.paystack.com/#/settings/developer) and save.
8. Please remember to set `Test Mode` to `No` when you are ready to start receiving payments.

## Documentation

* [Paystack Documentation](https://developers.paystack.co/v2.0/docs/)
* [Paystack Helpdesk](https://paystack.com/help)

## Support

For bug reports and feature requests directly related to this plugin, please use the [issue tracker](https://github.com/PaystackHQ/plugin-joomla-virtuemart/issues). 

For general support or questions about your Paystack account, you can reach out by sending a message from [our website](https://paystack.com/contact).

## Community

If you are a developer, please join our Developer Community on [Slack](https://slack.paystack.com).

## Contributing to the Joomla Virtuemart plugin

If you have a patch or have stumbled upon an issue with the Joomla Virtuemart plugin, you can contribute this back to the code. Please read our [contributor guidelines](https://github.com/PaystackHQ/plugin-joomla-virtuemart/blob/master/CONTRIBUTING.md) for more information how you can do this.

## Change Log
Mar 14 2018 - v1.0.5
- Fix amounts being displayed in kobo

Sept 25 2016 - v1.0.4
- Canceling the inline popup redirects
- Show HTML table after verification

Sept 21 2016 - v1.0.2
- Fix issues found by JEDchecker
- DS to DIRECTORY_SEPARATOR
- Use GPL license
- Use Currency code obtained and remove backticks from file

Sep 19 2016 - v1.0.0
- First Release
