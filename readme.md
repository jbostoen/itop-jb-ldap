# jb-ldap

Copyright (c) 2021-2022 Jeffrey Bostoen

[![License](https://img.shields.io/github/license/jbostoen/iTop-custom-extensions)](https://github.com/jbostoen/iTop-custom-extensions/blob/master/license.md)
[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.me/jbostoen)
🍻 ☕

Need assistance with iTop or one of its extensions?  
Need custom development?  
Please get in touch to discuss the terms: **info@jeffreybostoen.be** / https://jeffreybostoen.be

## What?

Imports objects from Active Directory into iTop.

## Features

* Run different LDAP queries on different servers (default settings are possible).
* Use LDAP info when creating/updating iTop objects and make it possible to have workflows such as:
    1. Create a person when a new Active Directory user is found.
	2. Create a user account linked to this newly created person object and add predefined profiles/organizations.


# Configuration

See documented demo configuration in [module.jb-ldap.php](module.jb-ldap.php)

Placeholders

| Name                        	     | Replacement                                                                                |
| ---------------------------------- | ------------------------------------------------------------------------------------------ |
| `$ldap_object->ldap_attribute$`    | Replace ldap_attribute with queried LDAP attribute.                                        |
| `$first_object->att_code$`         | iTop object. Replace att_code with an attribute of the first found/created object.         |
| `$previous->att_code$`             | iTop object. Replace att_code with an attribute of the previously found/created object     |
| `$current_datetime$`               | Current datetime.                                                                          |
| `$ldap_specific_placeholder->key$` | Placeholders linked to this LDAP configuration. Replace key with a configured placeholder. |                                                                     |




## Cookbook

PHP:
* How to implement a cron job process in iTop (iScheduledProcess).
* Use DBObjectSearch and DBObjectSet to fetch data.

## Hints

To get this working on XAMPP, it may be neccessary to create an ldap.conf file (C:\OpenLDAP\sysconf\ldap.conf) with a setting like this:  

```TLS_REQCERT never # insecure, add proper trusted certificate```

Then reboot Apache2.

## Upgrade notes

**Upgrading from a version before 23rd of September, 2022:**

In the iTop configuration for this extension, create_objects and update_objects are deprecated since it only allowed to create/update all objects within a sync rule.
Instead, create and update settings have been added for each object within a sync rule.


**Upgrading from a version before 27th of April, 2022:**

In the settings (iTop configuration file):
* Setting `user_query` has been renamed to `ldap_query`
* In the placeholders: `ldap_user` has been renamed to `ldap_object`
