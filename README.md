InterNetworX Registrar Module for WHMCS
Version 2.1 (2018-04-04)
____________________________________________________________________________________

# SUPPORTED FEATURES

* Domain Registration
* Domain Transfer
* EPP Code Retrieval
* View/Change Nameservers
* View/Update WHOIS Information
* Lock/Unlock Domains
* Domain Renewal (not supported for all TLDs)
* DNS Record Management (Record-Types: A, AAAA, MX, CNAME, SPF (TXT), URL, FRAME, SRV)
* Register/Manage Private Nameservers
* TestMode, using Test Environment (OTE)
* Automatical Domain synchronisation

The InterNetworX Registrar Module does not yet support:

* Email Fowarding
* ID Protection

# INSTALLATION
1. When you are using the old WHMCS-Module with the name "inwx" (the folder 
/modules/registrars/inwx/ exists), please execute the following SQL-Queries
to your WHMCS-Database:

```sql
UPDATE tbldomainpricing 
SET    autoreg = 'internetworx' 
WHERE  autoreg = 'inwx'; 

UPDATE tbldomains 
SET    registrar = 'internetworx' 
WHERE  registrar = 'inwx'; 

DELETE FROM tblregistrars 
WHERE  registrar = 'inwx'; 
```
	
2. Copy the provided folder "internetworx" in */modules/registrars/*
(when the folder */modules/registrars/inwx* exists, please delete it!)
3. Activate and configure the internetworx module in 
*Config > Domain Registrars > Internetworx*

4. It's recommend to use our module *additionaldomainfields.php* for needed 
domain registration extra data. For that you need to add the following 
command to includes/additionaldomainfields.php:

```php
include dirname(__FILE__).DIRECTORY_SEPARATOR
."..".DIRECTORY_SEPARATOR
."modules".DIRECTORY_SEPARATOR
."registrars".DIRECTORY_SEPARATOR
."internetworx".DIRECTORY_SEPARATOR
."additionaldomainfields.php";
```
	
5. Since version 2.0.1 the file *internetworxsync.php* was deleted because of
the new domain-sync integration in WHMCS.

If you have a cronjob for that file, delete them and activate the domain-sync
in your WHMCS settings.

6. To activate support for records that are not natively provided by WHMCS the
template file *inwx_clientareadomaindns.tpl* needs to be copied to the active
template directory and renamed to *clientareadomaindns.tpl*. In this manner you
can either copy it directly to your currently used template or create a new
template. If you just copy it, we also strongly advise you to create a backup
of your old *clientareadomaindns.tpl* file. The second option requires you to
copy your currently used template. If you still use the default template, it
can be found in */templates/six*. Copy this folder and assign it your prefered
name like */templates/inwx* and replace the file *clientareadomaindns.tpl* with
the *inwx_clientareadomaindns.tpl*. In order for this to take effect you will
need to login to your admin area, navigate to *Setup > General Settings* and
select your newly created template. Clients should now be able to select SRV
records in their DNS Management.

____________________________________________________________________________________
(c) 2015 InterNetworX Ltd. & Co. KG, Prinzessinnenstrasse 30, DE-10969 Berlin<br>
support@inwx.de<br>
http://www.inwx.com
