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
		
			// Specifies defaults (if any).
			// Any setting in the example below of a more specific sync rule, can be used here - and the other way around.
			// The specific rules (later in this configuration) inherit all settings which are defined here.
			// In practice, in the more recent versions of this extension, "ldap_servers" is the most likely one to be used.
			'default_sync_rule' => array(
			
				// LDAP servers to query
				'ldap_servers' => array(

					// Naming here is optional.
					'FirstServer' => array(
					
						// Option 1: Preferred, takes precedence. Point to an existing LDAP configuration to use the host, port, user, password, baase DN, start TLS and options specified in that configuration.
							
							// 'default' will take the default LDAP server configuration defined in authent-ldap. 
							// If another value is specified, it should match a named LDAP configuration.
							// Possible values are: 
							// "authent-ldap" - Configure servers under the authent-ldap settings -> servers
							// "jb-ldap" - Configure servers under the jb-ldap settings -> servers (to define additional servers not used in other configs)
							// "knowitop-multi-ldap-auth" - Configure servers under the knowitop-multi-ldap-auth settings -> ldap_settings
							'ldap_config_name' => 'default',
							
						// Custom placeholders. This array contains place holders which can be used in "ldap_query", "objects" -> "reconcile_on", "objects" -> "attributes".
						// If $ldap_specific_placeholder->org_id$ is used in any of the settings specified above, it will be replaced with its configured value.
							'ldap_specific_placeholders' => array(
								'org_id' => 1
							),
		
					),
		
					
					// Option 2: Specify an LDAP configuration like this:
					'SecondServer' => array(
					
							'host' => '127.0.0.1', // IP address or FQDN (iTop web server must be able to resolve it) of the LDAP server.
							'port' => 389, // LDAP: 389, LDAPS: 636
							'default_user' => 'intranet.domain.org\\someuser', // Mind to escape certain characters (PHP). For security, it's highly recommended to only use an account with read-only permissions.
							'default_pwd' => 'somepassword',
							'base_dn' => 'DC=intranet,DC=domain,DC=org',
							'start_tls' => false,
							
							'options' => array(
								17 => 3,
								8 => 0,
								// LDAP_OPT_X_TLS_REQUIRE_CERT => 0,
							),
							
						// See above.
							'ldap_specific_placeholders' => array(
								'org_id' => 2
							),
					
					),
					
				),
				
			),
			
					
			// This setting is optional and defaults to 'authent-ldap'. It's only needed if deviating from authent-ldap.
			// Alternatively, 'knowitop-multi-ldap-auth' can be used if this third-party extension is in use.
			'ldap_config_source' => 'authent-ldap',
			
			
			// One or more sync rules should be placed here.
			//
			// Each object rule can be given a name instead of an index if wanted, but there's no different behavior.
			//
			// A synchronization rule determines which LDAP server to query (all config from 'default_sync_rule' can be overruled here by specifying the keys again).
			// It also contains info on how to map the LDAP object to an iTop object.
			'sync_rules' => array(
			
				'CreateUsers' => array(
				
					// Retrieve all non-disabled users for which an e-mail account is set and for which the admin account name is not 'admin'
					// Hint: 'not set' would be: (!(mail=*)); while (mail=*) means mail MUST be set.
					'ldap_query' => '(&(objectclass=user)(objectcategory=person)(!(sAMAccountName=admin))(mail=*)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))',
					
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
					
						// List iTop classes where the info can be used.
						// Objects will be created or updated (unique match) if enabled, but they will never be deleted.
						// In this example, one iTop object (Person) will be created; and an iTop user account for this person.
						//
						// Each object rule can be given a name instead of an index if wanted, but there's no different behavior.
						//
						// Placeholders (can be used to set new attribute values and in OQL queries)
						//
						// - $ldap_object->ldap_att$ (attributes determined in ldap_attributes setting), 
						// - $first_object->id$ (only available after the first object has been created!)
						//   Use case example: refer to a created Person object to create user accounts
						// - $previous_object->id$
						//   Use case: link between a first and second object
						// - $current_datetime$ will add the current datetime of sync.
						'SyncPerson' => array(
							'create' => true,
							'update' => true,
							'class' => 'Person',
							'attributes' => array(
								'org_id' => '$ldap_specific_placeholder->org_id$', // Organization for the object. Required attribute. Use placeholder defined above.
								'email' => '$ldap_object->mail$',
								'first_name' => '$ldap_object->givenname$',
								'name' => '$ldap_object->sn$',
								'phone' => '$ldap_object->telephonenumber$',
							),
							'reconcile_on' => 'SELECT Person WHERE email LIKE "$ldap_object->mail$"'
						),
						
						
						'CreateUser' => array(
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
				
				
				'DisableUsers' => array(
					// Retrieve all disabled users for which an e-mail account is set and for which the admin account name is not 'admin'
					'ldap_query' => '(&(objectclass=user)(objectcategory=person)(!(sAMAccountName=admin))(mail=*)(userAccountControl:1.2.840.113556.1.4.803:=2))',
					
					
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
				
				'EnableUsers' => array(
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
				
				'SyncComputers' => array(
					// Obtain all computers
					'ldap_query' => '(objectClass=computer)',
					'ldap_attributes' => array('name'),
					'objects' => array(
					
						0 => array(
							'class' => 'PC',
							'attributes' => array(
								'org_id' => '$ldap_specific_placeholder->org_id$', // Organization for the object. Required attribute.
								'name' => '$ldap_object->name$',
							),
							'reconcile_on' => 'SELECT PC WHERE name LIKE "$ldap_object->name$"'
						),
						
					)
				),


			)
		
		),
	)
);


