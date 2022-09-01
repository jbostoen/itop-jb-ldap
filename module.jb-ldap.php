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
	'jb-ldap/2.7.220629',
	array(
		// Identification
		//
		'label' => 'Feature: LDAP Synchronization (cron)',
		'category' => 'business',

		// Setup
		//
		'dependencies' => array(
			'jb-framework/2.6.0'
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
			'debug' => false,
		
			// Specifies defaults (if any)			
			// For security, it's highly recommended to only use an account with read-only permissions.
			// Settings are similar to Combodo's authent-ldap and used as default settings for any sync rule below (the specific sync rules can overrule this)
			'default_sync_rule' => array(
			
				'host' => 'ldap://127.0.0.1', // Note that even for ldaps, the protocol is ldap://
				'port' => 636, // LDAP: 389, LDAPS: 636
				'default_user' => 'intranet.domain.org\\someuser', // Mind to escape certain characters (PHP)
				'default_pwd' => 'somepassword',
				'base_dn' => 'DC=intranet,DC=domain,DC=org',
				'start_tls' => false,
				'options' => array(
					17 => 3,
					8 => 0,
					// LDAP_OPT_X_TLS_REQUIRE_CERT => 0,
				),
				
				'create_objects' => true,
				'update_objects' => true,
				
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
			
			// One or more sync rules should be placed here.
			// A synchronization rule determined which server to query, which base DN, which options, which LDAP query to use and how to map the LDAP object to an iTop object.
			'sync_rules' => array(
			
				array(
					// Retrieve all users for which an e-mail account is set and for which the admin account name is not 'admin'
					// Hint: 'not set' would be: (!(mail=*)); while (mail=*) means mail MUST be set.
					'ldap_query' => '(&(objectclass=user)(objectcategory=person)(!(sAMAccountName=admin))(mail=*))',
					
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


