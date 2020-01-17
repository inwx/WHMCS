<p align="center">
  <a href="https://www.inwx.com/en/" target="_blank">
    <img src="https://images.inwx.com/logos/inwx.png">
  </a>
</p>

INWX WHMCS Registrar Module
=========

## Supported Features

* Domain Registration
* Domain Transfer
* EPP Code Retrieval
* ID Protection
* View/Change Nameservers
* View/Update WHOIS Information
* Lock/Unlock Domains
* Domain Renewal (not supported for all TLDs)
* DNS Record Management (Record-Types: A, AAAA, MX, MXE (Mail Easy), CNAME, SPF (TXT), URL, FRAME, SRV)
* Register/Manage Private Nameservers
* TestMode, using Test Environment (OTE)
* Automatic Domain synchronisation

The InterNetworX Registrar Module does not yet support:

* Email Forwarding

#### Supported / tested WHMCS versions
* 7.9


## Installation
1. Copy the folder `internetworx` into `/modules/registrars/`

2. Activate and configure the internetworx module in 
**Setup > Products/Services > Domain Registrars**

3. It's recommend to use our module's `additionaldomainfields.php` for needed 
domain registration extra data. For that you need to add the following 
command to `includes/additionaldomainfields.php`:

```php
include __DIR__ . "/../modules/registrars/internetworx/additionaldomainfields.php";
```

### Optional:

**Support SRV records:**

To activate support for records that are not natively provided by WHMCS the
template file `inwx_clientareadomaindns.tpl` needs to be copied to the active
template directory and renamed to `clientareadomaindns.tpl`. There are two ways to achieve this:

* Just copy `inwx_clientareadomaindns.tpl` onto `clientareadomaindns.tpl` in your current template.
* Create a copy of your current template folder (default is `templates/six/`) with a name 
  you prefer in the `templates` folder. Next copy `inwx_clientareadomaindns.tpl` onto `clientareadomaindns.tpl`.

In order for this to take effect you will need to login to your admin area, 
navigate to **Setup > General Settings** and select your newly created template.
Clients should now be able to select SRV records in their DNS management.


License
----

MIT