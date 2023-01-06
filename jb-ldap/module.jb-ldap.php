<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220427
 *
 * iTop module definition file
 */

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'jb-ldap/2.7.220901',
	array(
		// Identification
		//
		'label' => 'Feature: LDAP Synchronization (cron)',
		'category' => 'business',

		// Setup
		//
		'dependencies' => array(
			'jb-news/2.7.0',
		),
		'mandatory' => false,
		'visible' => true,

		// Components
		//
		'datamodel' => array(
			'app/common/ldapsync.class.inc.php',
			'app/core/scheduledprocessldapsync.class.inc.php',
		),
		'webservice' => array(
			
		),
		'data.struct' => array(
			// add your 'structure' definition XML files here,
		),
		'data.sample' => array(
			// add your sample data XML files here,
		),
		
		// Documentation
		//
		'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
		'doc.more_information' => '', // hyperlink to more information, if any 

		// Default settings
		//
		'settings' => array(
		
			// Module specific settings go here, if any
			// These settings determine if the extension is enabled and when the sync will occur (once a day)
			'time' => '03:00',
			'week_days' => 'monday, tuesday, wednesday, thursday, friday',
			'enabled' => true,
			'trace_log' => false,
		
			// Specifies defaults (if any)			
			// For security, it's highly recommended to only use an account with read-only permissions.
			// Settings are similar to Combodo's authent-ldap and used as default settings for any sync rule below (the specific sync rules can overrule this)
			'default_sync_rule' => array(
			
				// LDAP configuration
				
				// Option 1: Preferred, takes preceence. Point to an existing LDAP configuration.
					
					// 'default' will take the default LDAP server configuration defined in authent-ldap. 
					// If another value is specified, it should match a named LDAP configuration (authent-ldap: servers, knowitop-multi-ldap-auth: ldap_settings
					'ldap_config_name' => 'default',
					
				// Option 2: Specify an LDAP configuration like this:
				
					'host' => '127.0.0.1', // IP address or FQDN (iTop web server must be able to resolve it) of the LDAP server.
					'port' => 389, // LDAP: 389, LDAPS: 636
					'default_user' => 'intranet.domain.org\\someuser', // Mind to escape certain characters (PHP)
					'default_pwd' => 'somepassword',
					'base_dn' => 'DC=intranet,DC=domain,DC=org',
					'start_tls' => false,
					
					'options' => array(
						17 => 3,
						8 => 0,
						// LDAP_OPT_X_TLS_REQUIRE_CERT => 0,
					),
					
					
					
				// Currently only strings and integers are supported; not lists/arrays/...
				// These LDAP attributes will be fetched and are then available in the $ldap_object->ldap_att$ placeholder
				'ldap_attributes' => array(
					'sn',
					'givenname',
					'mail',
					'telephonenumber',
					'company',
					'samaccountname',
					'userprincipalname',
					'displayname'
					// 'objectsid', -> sadly this is hexadecimal
				),
				
				'objects' => array(
				
				)
				
			),
			
					
			// This setting is optional and defaults to 'authent-ldap'. It's only needed if deviating from authent-ldap.
			// Alternatively, 'knowitop-multi-ldap-auth' can be used if this third-party extension is in use.
			'ldap_config_source' => 'authent-ldap',
			
			
			// One or more sync rules should be placed here.
			// A synchronization rule determined which server to query, which base DN, which options, which LDAP query to use and how to map the LDAP object to an iTop object.
			'sync_rules' => array(
			
				array(
					// Retrieve all non-disabled users for which an e-mail account is set and for which the admin account name is not 'admin'
					// Hint: 'not set' would be: (!(mail=*)); while (mail=*) means mail MUST be set.
					'ldap_query' => '(&(objectclass=user)(objectcategory=person)(!(sAMAccountName=admin))(mail=*)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))',
					
					'objects' => array(
					
						// List iTop classes where the info can be used. Objects will be created or updated (unique match), not deleted.
						// In this example, one iTop object (Person) will be created; but it's possible to add multiple objects here.
						//
						// Placeholders (can be used to set new attribute values and in OQL queries)
						//
						// - $ldap_object->ldap_att$ (attributes determined in ldap_attributes setting), 
						// - $first_object->id$ (only available after the first object has been created!)
						//   Use case example: refer to a created Person object to create user accounts
						// - $previous_object->id$
						//   Use case: link between a first and second object
						// - $current_datetime$ will add the current datetime of sync.
						0 => array(
							'create' => true,
							'update' => true,
							'class' => 'Person',
							'attributes' => array(
								'org_id' => 1, // Organization for the object. Required attribute
								'email' => '$ldap_object->mail$',
								'first_name' => '$ldap_object->givenname$',
								'name' => '$ldap_object->sn$',
								'phone' => '$ldap_object->telephonenumber$',
							),
							'reconcile_on' => 'SELECT Person WHERE email LIKE "$ldap_object->mail$"'
						),
						
						
						1 => array(
							'create' => true,
							'update' => false, // This is an example where updating is unwanted (don't want to reset the password each time)
							'class' => 'UserLocal',
							'attributes' => array(
								'contactid' => '$first_object->id$',
								'login' => '$ldap_object->mail$',
								'language' => 'EN US',
								'status' => 'enabled',
								'password' => 'ThisIsJustAnExample;PickSomethingComplicatedAndMakeSureItIsUniqueSomehow*123',
								'profile_list' => array(
									array(
										'profileid' => 2,
									)
								)
							),
							'reconcile_on' => 'SELECT UserLocal WHERE email LIKE "$ldap_object->mail$"'
							
						),
						
					)
				),
				
				
				array(
					// Retrieve all disabled users for which an e-mail account is set and for which the admin account name is not 'admin'
					'ldap_query' => '(&(objectclass=user)(objectcategory=person)(!(sAMAccountName=admin))(mail=*)(userAccountControl:1.2.840.113556.1.4.803:=2))',
					
					'objects' => array(
					
						0 => array(
							'create' => false,
							'update' => true, // This is an example where only updating is desired
							'class' => 'UserLocal',
							'attributes' => array(
								'status' => 'disabled'
							),
							'reconcile_on' => 'SELECT UserLocal WHERE email LIKE "$ldap_object->mail$"'
							
						),
						
					)
				),
				
				array(
					// Retrieve all enabled users for which an e-mail account is set and for which the admin account name is not 'admin'
					// Note: mind that the original rule only allowed to create UserLocal accounts, because it also set the password.
					'ldap_query' => '(&(objectclass=user)(objectcategory=person)(!(sAMAccountName=admin))(mail=*)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))',
					
					'objects' => array(
					
						0 => array(
							'create' => false,
							'update' => true, // This is an example where only updating is desired
							'class' => 'UserLocal',
							'attributes' => array(
								'status' => 'enabled'
							),
							'reconcile_on' => 'SELECT UserLocal WHERE email LIKE "$ldap_object->mail$"'
							
						),
						
					)
				),
				/*
				
				array(
					// Obtain all computers
					'ldap_query' => '(objectClass=computer)',
					'ldap_attributes' => array('name'),
					'objects' => array(
					
						0 => array(
							'class' => 'PC',
							'attributes' => array(
								'org_id' => 1, // Organization for the object. Required attribute
								'name' => '$ldap_object->name$',
							),
							'reconcile_on' => 'SELECT PC WHERE name LIKE "$ldap_object->name$"'
						),
						
					)
				),

				*/

			)
		
		),
	)
);


