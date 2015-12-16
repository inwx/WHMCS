InterNetworX Registrar Module for WHMCS
Version 2.0.1 (2015-12-16)
____________________________________________________________________________________

#SUPPORTED FEATURES:
* Domain Registration
* Domain Transfer
* EPP Code Retrieval
* View/Change Nameservers
* View/Update WHOIS Information
* Lock/Unlock Domains
* Domain Renewal (not supported for all TLDs)
* DNS Record Management (Record-Types: A, AAAA, MX, CNAME, SPF (TXT), URL, FRAME)
* Register/Manage Private Nameservers
* TestMode, using Test Environment (OTE)
* Automatical Domain synchronisation

The InterNetworX Registrar Module does not yet support:

* Email Fowarding
* ID Protection

#INSTALLATION:
1)
When you are using the old WHMCS-Module with the name "inwx" (the folder 
/modules/registrars/inwx/ exists), please execute the following SQL-Queries
to your WHMCS-Database:

	UPDATE tbldomainpricing set autoreg='internetworx' WHERE autoreg='inwx' ;
	UPDATE tbldomains set registrar='internetworx' WHERE registrar='inwx' ;
	DELETE FROM tblregistrars WHERE registrar='inwx' ;
2)
Copy the provided folder "internetworx" in /modules/registrars/
(when the folder /modules/registrars/inwx exists, please delete it!)

3)
Activate and configure the internetworx module in 
Config > Domain Registrars > Internetworx

4)
It's recommend to use our module additionaldomainfields.php for needed 
domain registration extra data. For that you need to add the following 
command to includes/additionaldomainfields.php:

include dirname(__FILE__).DIRECTORY_SEPARATOR
	."..".DIRECTORY_SEPARATOR
	."modules".DIRECTORY_SEPARATOR
	."registrars".DIRECTORY_SEPARATOR
	."internetworx".DIRECTORY_SEPARATOR
	."additionaldomainfields.php";

5)
Since version 2.0.1 the file "internetworxsync.php" was deleted because of
the new domain-sync integration in WHMCS.

If you have a cronjob for that file, delete them and activate the domain-sync
in your WHMCS settings.

____________________________________________________________________________________
(c) 2015 InterNetworX Ltd. & Co. KG, Prinzessinnenstrasse 30, DE-10969 Berlin<br>
support@inwx.de<br>
http://www.inwx.com
