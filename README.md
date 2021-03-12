<p align="center">
  <a href="https://www.inwx.com/en/" target="_blank">
    <img src="https://images.inwx.com/logos/inwx.png">
  </a>
</p>

INWX WHMCS Registrar Module
=========

## Supported WHMCS Features

* Automatic Domain Synchronisation
* Availability Checks (+ Support for Premium Domains)
* DNS Record Management (Record-Types: A, AAAA, MX, MXE (Mail Easy), CNAME, SPF (TXT), URL, FRAME, SRV)
* Domain Deletion
* Domain Registration
* Domain Release
* Domain Renewal
* Domain Transfer
* EPP Code Retrieval
* ID Protection
* Lock / Unlock Domains
* Register / Manage Private Nameservers
* TestMode, using our Test Environment (OTE)
* TLD & Pricing Sync
* View / Change Nameservers
* View / Update WHOIS Information

## Extra Features

* Toggleable support for all record types supported by INWX (AFSDB, ALIAS ,CAA, CERT, HINFO, KEY, LOC, NAPTR, PTR, RP, SMIMEA, SOA, SRV, SSHFP, TLSA)
* Toggleable short record display in DNS Record Management (omits domain name from hostnames of records for subdomains and replaces hostname of records which are not a subdomain with @)
* Changeable location for Domrobot Cookiefile


#### Supported WHMCS versions on current branch
* 8.1.X

#### Supported / tested WHMCS versions on older branches
* [8.0.X](https://github.com/inwx/WHMCS/tree/whmcs-8.0) 
* [7.10](https://github.com/inwx/WHMCS/tree/whmcs-7.10) 
* [7.9](https://github.com/inwx/WHMCS/tree/whmcs-7.9) 

## Installation
1. Copy the folder `inwx` into `/modules/registrars/`

2. Activate and configure the inwx module at **Configuration Icon > System Settings > Domain Registrars**

### Optional:

#### Extradata for TLDs

It is recommended to use our module's additionaldomainfields for required domain registration extra data.

If you only have inwx as a registrar you can simply copy `modules/registrars/inwx/additionaldomainfields.php` to `resources/domains/additionalfields.php`.

If you have to or want to support multiple registrars you have to add the following statement to `resources/domains/additionalfields.php`:
```php
include __DIR__ . "/../../modules/registrars/inwx/additionaldomainfields.php";
```


#### Support more record types

To activate support for records that are not natively provided by WHMCS the
template file `modules/registrars/inwx/custom_templates/clientareadomaindns.tpl` needs to be copied to the active
template directory. There are two ways to achieve this:

* Just copy `clientareadomaindns.tpl` onto `clientareadomaindns.tpl` in your current template (default is `templates/twenty-one/`).
* Create a copy of your current template folder (default is `templates/twenty-one/`) with a name 
  you prefer in the `templates` folder. Next copy `clientareadomaindns.tpl` onto `clientareadomaindns.tpl`. In order for this to take effect you will need to login to your admin area,
  navigate to **Setup > General Settings** and select your newly created template.
  Clients should now be able to select SRV records in their DNS management.

After that it is recommended to delete all files in the cache folder `templates_c/`.

License
----

MIT
