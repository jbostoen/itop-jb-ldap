# jb-ldap

Copyright (c) 2021-2022 Jeffrey Bostoen

[![License](https://img.shields.io/github/license/jbostoen/iTop-custom-extensions)](https://github.com/jbostoen/iTop-custom-extensions/blob/master/license.md)
[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.me/jbostoen)
🍻 ☕

Need assistance with iTop or one of its extensions?  
Need custom development?  
Please get in touch to discuss the terms: **info@jeffreybostoen.be** / https://jeffreybostoen.be

## What?
Imports users from Active Directory and can create several types of iTop objects.

## Features
* Run different LDAP queries on different servers (default settings are possible)
* Use LDAP info in subsequent OQL-queries when creating/updating iTop objects


# Config
See demo config in module.jb-ldap.php

Placeholders

| Name                        	| Replacement                                                                              	|
| -----------------------------	| -----------------------------------------------------------------------------------------	|
| $ldap_object->ldap_attribute$	| Replace ldap_attribute with queried LDAP attribute.                                     	|
| $first_object->att_code$    	| iTop object. Replace att_code with an attribute of the first found/created object.       	|
| $previous->att_code$        	| iTop object. Replace att_code with an attribute of the previously found/created object.  	|


## Important notes
* Experimental

## Requirements

iTop extensions
* [jb-framework](https://github.com/jbostoen/itop-jb-framework)

## Cookbook

PHP:
* how to implement a cron job process in iTop (iScheduledProcess)
* using DBObjectSearch and DBObjectSet to fetch data

## Hints

To get this working on XAMPP, you might need to create an ldap.conf file (C:\OpenLDAP\sysconf\ldap.conf) with a setting like this:  

```TLS_REQCERT never # insecure, add proper trusted certificate```

Then reboot Apache2.

## Upgrade notes

Upgrading from a version before 27th of April, 2022:

* in the settings: 

  * setting `user_query` has been renamed to `ldap_query`
  * in the placeholders: `ldap_user` has been renamed to `ldap_object`
