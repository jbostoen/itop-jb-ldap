<?php 

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220427
 *
 * Definition of LDAPSyncProcessor
 */
 
namespace jb_itop_extensions\ldap_sync;

use \Exception;

// iTop internals
use \AttributeDateTime;
use \AttributeDecimal;
use \AttributeExternalKey;
use \AttributeInteger;
use \AttributeLinkedSet;
use \AttributeLinkedSetIndirect;
use \AttributeOneWayPassword;
use \AttributeString;
use \CMDBObjectSet;
use \DBObjectSearch;
use \MetaModel;
use \utils;

	/**
	 * Class LDAPSyncProcessor. Contains methods to compress files
	 */
	abstract class LDAPSyncProcessor {
		
		/**
		 * Logs trace message.
		 *
		 * @var \String $sMessage Message
		 *
		 * @return void
		 */
		public static function Trace($sMessage, $bThrowException = false) {
			
			$bTrace = MetaModel::GetModuleSetting('jb-ldap', 'trace_log', false);
			
			if($bTrace == true) {
				echo date('Y-m-d H:i:s').' | '.$sMessage.PHP_EOL;
			}
		
		}

		/**
		 * Logs trace message and throws exception
		 *
		 * @var \String $sMessage Message
		 *
		 * @return void
		 */
		public static function Throw($sMessage) {
			
			static::Trace($sMessage);
			throw new Exception($sMessage);
			
		}
		
		/**
		 * Processes each synchronization rule in the config settings.
		 *
		 * @return void
		 */
		public function ProcessRules() {
		
			// Ignore invalid cert?
			$sEnvIgnoreCert = (getenv('TLS_REQCERT') !== false ? getenv('TLS_REQCERT') : null);
			$sEnvIgnoreLDAPCert = (getenv('LDAPTLS_REQCERT') !== false ? getenv('LDAPTLS_REQCERT') : null);
			putenv('TLS_REQCERT=never');
			putenv('LDAPTLS_REQCERT=never');
			
			// Note: the certificate ignore options only work when this setting is allowed in php.ini (variables_order)
			static::Trace('PHP Environment: '.json_encode($_ENV));
			
			static::Trace('Start processing sync_rules...');
			
			$aDefaultSyncRule = MetaModel::GetModuleSetting('jb-ldap', 'default_sync_rule', []);
			$aSyncRules = MetaModel::GetModuleSetting('jb-ldap', 'sync_rules', []);
			
			// Process each LDAP.
			// Each LDAP can have a different settings.
			foreach($aSyncRules as $sIndex => $aSyncRule) {
				
				$aSyncRule = array_replace_recursive($aDefaultSyncRule, $aSyncRule);
				
				try {
					static::ProcessRule($sIndex, $aSyncRule);		
				}
				catch(Exception $e) {
					// Nothing for now?
					static::Trace('Failed to process sync rule (index '.$sIndex.'): '.$e->GetMessage());
				}
				
			}
			
			// Restore
			if($sEnvIgnoreCert !== null) {
				putenv('TLS_REQCERT='.$sEnvIgnoreCert);
			}
			if($sEnvIgnoreLDAPCert !== null) {
				putenv('LDAPTLS_REQCERT='.$sEnvIgnoreLDAPCert);
			}
			
			static::Trace('Finished synchronization.');
			
		}
		
		/**
		 * Processes an individual synchronization rule.
		 *
		 * @var \String $sIndex Index of the sync rule
		 * @var \Array $aSyncRule Hash table of scope settings
		 *
		 * @return void
		 */
		public function ProcessRule($sIndex, $aSyncRule) {
			
			static::Trace('. '.str_repeat('=', 25).' Sync rule "'.$sIndex.'"');
			
			if(isset($aSyncRule['ldap_servers']) == false) {
				
				static::Throw('.. Error: sync rule (index '.$sIndex.'): no LDAP servers specified.');
				
			}
			
			$aLDAPQueryConfigs = $aSyncRule['ldap_servers'];
			
			foreach($aLDAPQueryConfigs as $sIndex => $aLDAPQueryConfig) {
				
				static::Trace('. Process LDAP configuration '.$sIndex);
			
				static::DetermineFinalLDAPConfig($aLDAPQueryConfig, $sIndex);
			
				
			
				// Create objects as needed
				foreach($aSyncRule['objects'] as $sIndex => $aObject) {
					
					// OQL query specified?
					if(isset($aObject['reconcile_on']) == false) {
						static::Throw('.. Error: sync rule (index '.$sIndex.'): no "reconcile_on" specified for object index '.$sIndex);
					}
					
					// Valid class specified?
					preg_match('/SELECT ([A-z0-9]{1,}).*$/', $aObject['reconcile_on'], $aMatches);
					
					if(count($aMatches) < 2) {
						static::Throw('.. Error: sync rule (index '.$sIndex.'): invalid "reconcile_on" specified for object index '.$sIndex);
					}
					
					$sClass = $aMatches[1];
					
					if(MetaModel::IsValidClass($sClass) == false) {
						static::Throw('.. Error: sync rule (index '.$sIndex.'): invalid "reconcile_on" specified for object index '.$sIndex.' - class: '.$sClass);					
					}
					
					// Valid attributes specified in configuration?
					// Check with iTop Datamodel
					$aValidAttributes = MetaModel::GetAttributesList($sClass);
					foreach($aObject['attributes'] as $sAttCode => $sAttValue) {
						if(in_array($sAttCode, $aValidAttributes) == false) {
							static::Throw('.. Error: sync rule (index '.$sIndex.'): invalid attribute "'.$sAttCode.'" specified for object index '.$sIndex.' - class: '.$sClass);	
						}
					}
					
					
				}
				
				// Connect
				// ldap_set_option(null, LDAP_OPT_DEBUG_LEVEL, 7); // Enable for debugging issues with XAMPP and LDAPS
				$hConnection = @ldap_connect($aLDAPQueryConfig['host'], $aLDAPQueryConfig['port']);
				
				if($hConnection === false) {
					static::Throw('.. Error: sync rule (index '.$sIndex.'): unable to connect to the LDAP-server: '.$aLDAPQueryConfig['host'].':'.$aLDAPQueryConfig['port']);
				}
				
				foreach($aLDAPQueryConfig['options'] as $sKey => $uValue) {
					if(!ldap_set_option($hConnection, $sKey, $uValue)) {
						static::Throw('.. Error: sync rule (index '.$sIndex.'): invalid LDAP-option or value: '.$sKey);
					}
				}
				
				if($aLDAPQueryConfig['start_tls'] == true) {
					
					$hStartTLS = ldap_start_tls($hConnection);
				
					if($hStartTLS == false) {
						
						static::Throw('.. Error: sync rule (index '.$sIndex.'): start TLS failed.');
						
					}
				}
				
				
				// Try to bind
				@ldap_bind($hConnection, $aLDAPQueryConfig['default_user'], $aLDAPQueryConfig['default_pwd']) or static::Throw('Error: sync rule (index '.$sIndex.'): unable to bind to server '.$aLDAPQueryConfig['host'].':'.$aLDAPQueryConfig['port'].' with user '.$aLDAPQueryConfig['default_user']);

				if(isset($aSyncRule['ldap_query']) == false) {
					static::Throw('.. Error: sync rule (index '.$sIndex.'): "ldap_query" not specified');
				}
				
				if(isset($aSyncRule['ldap_attributes']) == false) {
					static::Throw('.. Error: sync rule (index '.$sIndex.'): "ldap_attributes" not specified');
				}
				
				static::Trace('. LDAP Filter: '.$aSyncRule['ldap_query']);

				$oResult = ldap_search($hConnection, $aLDAPQueryConfig['base_dn'], $aSyncRule['ldap_query'], $aSyncRule['ldap_attributes']);
				$aLDAP_Entries = [];

				if($oResult !== false) {
					$aLDAP_Entries = ldap_get_entries($hConnection, $oResult);
				}
				else {
					static::Throw('Error: sync rule (index '.$sIndex.'): no results');
				}
				
				// The result has a 'count' key.
				static::Trace('. Found '.(count($aLDAP_Entries) -1).' LDAP object(s), for each '.count($aSyncRule['objects']).' iTop object(s) should be created or updated.');

				// Process
				foreach($aLDAP_Entries as $sKey => $aEntry) {
					
					// The result has a 'count' key.
					if(strtolower($sKey) == 'count') {
						continue;
					}
					
					// Start for each LDAP object with an empty placeholders set
					$aPlaceHolders = [];
					$aPlaceHolders['current_datetime'] = date('Y-m-d H:i:s');
					$aPlaceHolders['first_object->id'] = -1;
					
					// All other entries should have the values listed.
					// Since the limitation in this extension is that it should be a string/integer,
					// only the first item is taken into account for now.
					foreach($aEntry as $sKey => $aValue) {
						
						if(in_array((String)$sKey, $aSyncRule['ldap_attributes']) == false) {
							// Should unset numbers (but keys are strings here, not integers)
							// Should also unset 'count' and likely 'dn'
							unset($aEntry[(String)$sKey]);
						}
						else {
							// Setting an object instead of 'standalone' variables won't work well, 
							// since it then requires a GetForTemplate() method (see \MetaModel::ApplyParams())
							// Usually 'Count' and '0'
							$aPlaceHolders['ldap_object->'.$sKey] = $aValue[(String)'0'];
						}
						
					}
					
					// If null, LDAP does not return certain attributes (example: no phone number specified).
					// For this implementation, set empty values.
					foreach($aSyncRule['ldap_attributes'] as $sAttLDAP) {
						
						if(isset($aPlaceHolders['ldap_object->'.$sAttLDAP]) == false) {
							$aPlaceHolders['ldap_object->'.$sAttLDAP] = '';
						}
						
					}

					static::Trace('.. '.json_encode($aEntry));
					
						
					// Create objects as needed
					foreach($aSyncRule['objects'] as $sObjectIndex => $aObject) {
						
						static::Trace('.. '.str_repeat('-', 25).' Object rule: "'.$sObjectIndex.'"');
						
							
						if(isset($aObject['class']) == false) {
							static::Throw('Error: "class" for object not defined');
						}
						
						$sObjClass = $aObject['class'];
											
						$sOQL = MetaModel::ApplyParams($aObject['reconcile_on'], $aPlaceHolders);
						static::Trace('.. OQL: '.$sOQL);
						
						$oFilter = DBObjectSearch::FromOQL($sOQL);
						$oSet = new CMDBObjectSet($oFilter);
						$sClassName = $oSet->GetClass();
						
						switch($oSet->Count()) {
							
							case 0:
								// Create							
								
								if(isset($aObject['create']) == false || $aObject['create'] != true) {
									static::Trace('... Object does not exist. Not creating object because "create" is not explicitly set to true.');
									break;
								}
								
								static::Trace('... Object does not exist. Create ' . $sClassName);
								
								try {
									
									$oObj = MetaModel::NewObject($sObjClass);
									
									$aAttDefs = MetaModel::ListAttributeDefs($sObjClass);
									$aAttList = MetaModel::GetAttributesList($sObjClass);
									
									foreach($aObject['attributes'] as $sAttCode => $value) {
										
										if(in_array($sAttCode, $aAttList) == false) {
											
											static::Trace('..... Invalid attribute code: '.$sAttCode);
											
										}
										else {
											
											// More types could be added at some point
											$oAttDef = $aAttDefs[$sAttCode];
											switch(true) {
												
												case ($oAttDef instanceof AttributeLinkedSet):
												case ($oAttDef instanceof AttributeLinkedSetIndirect):
												
											
													/** @var \ormLinkedSet $oSet Linked set **/
													$oSet = $oObj->Get($sAttCode);
													$sLnkClass = $oAttDef->GetLinkedClass();
													$aSetLinkedobjAttDefs = MetaModel::ListAttributeDefs($sLnkClass);
													$aSetLinkedObjAttList = MetaModel::GetAttributesList($sLnkClass);
													
													static::Trace('..... '.$sAttCode.' ('.get_class($oAttDef).') - linked class: '.$sLnkClass.' - '.count($value).' linked objects');
													
													// - Linked object
														
														foreach($value as $aLinkedSetObject) {
														
															$oLinkedObject = MetaModel::NewObject($sLnkClass, []);

															foreach($aLinkedSetObject as $sLinkedObjAttCode => $sLinkedObjAttValue) {
																
																if(in_array($sLinkedObjAttCode, $aSetLinkedObjAttList) == false) {
																	static::Trace('...... Invalid attribute code: '.$sLinkedObjAttCode);
																}
																else {
																		
																	$oAttDefLinkedSet = $aSetLinkedobjAttDefs[$sLinkedObjAttCode];
																	
																	switch(true) {
																		
																		case ($oAttDefLinkedSet instanceof AttributeDateTime):
																		case ($oAttDefLinkedSet instanceof AttributeDecimal):
																		case ($oAttDefLinkedSet instanceof AttributeExternalKey):
																		case ($oAttDefLinkedSet instanceof AttributeInteger):
																		case ($oAttDefLinkedSet instanceof AttributeOneWayPassword):
																		case ($oAttDefLinkedSet instanceof AttributeString):
																													
																			// Allow placeholders in attributes; replace them here
																			$sLinkedObjAttValue = MetaModel::ApplyParams($sLinkedObjAttValue, $aPlaceHolders);
																			static::Trace('...... '.$sLinkedObjAttCode.' ('.get_class($oAttDef).') => '.$sLinkedObjAttValue);
																			
																			$oLinkedObject->Set($sLinkedObjAttCode, $sLinkedObjAttValue);
																			break;
																			
																		default:
																		
																			static::Trace('...... '.$sLinkedObjAttCode.' ('.get_class($oAttDefLinkedSet).') not supported yet at this level.');
																			break;
																			
																	}
																	
																}
																
															}

															$oSet->AddItem($oLinkedObject);
															
														}
													
															
														$oObj->Set($sAttCode, $oSet);
													
														static::Trace('..... ' . $sAttCode . ': added '.$oSet->Count().' links');
														
													break;
													
												case ($oAttDef instanceof AttributeDateTime):
												case ($oAttDef instanceof AttributeDecimal):
												case ($oAttDef instanceof AttributeExternalKey):
												case ($oAttDef instanceof AttributeInteger):
												case ($oAttDef instanceof AttributeOneWayPassword):
												case ($oAttDef instanceof AttributeString):
																							
													// Allow placeholders in attributes; replace them here
													$sAttValue = $value;
													$sAttValue = MetaModel::ApplyParams($sAttValue, $aPlaceHolders);
													static::Trace('..... '.$sAttCode.' ('.get_class($oAttDef).') => '.$sAttValue);
													
													$oObj->Set($sAttCode, $sAttValue);
													break;
													
												default:
												
													static::Trace('..... '.$sAttCode.' ('.get_class($oAttDef).') => Not supported yet!');
													break;
													
											}
											
										}
										
									}
									
									
									
									// This may throw errors. 
									// Example: using $ldap_object->telephonenumber$ (but empty value) while a NULL value is not allowed
									// Silently supress
									try {
										$iKey = $oObj->DBInsert();
									}
									catch(Exception $e) {
										static::Throw('Exception occurred while creating object: '.$e->GetMessage());
									}
									
									$aPlaceHolders['previous_object->id'] = $iKey;
									
									// Only if first object in chain
									if($sObjectIndex == 0) {
										$aPlaceHolders['first_object->id'] = $iKey;
									}
									
									static::Trace('.... Created '.$sObjClass.' for LDAP-object.');
									
								}
								catch(Exception $e) {
									static::Trace('.... Unable to create a new '.$sObjClass.' for LDAP-object: ' . $e->GetMessage());
								}
								
								
								break;
								
							case 1:
							
								if(isset($aObject['update']) == false || $aObject['update'] != true) {
									static::Trace('... Not updating object. Not creating object because "update" is not explicitly set to true.');
									break;
								}
								// Fetch first object from set
								$oObj = $oSet->Fetch();
								
								// Update							
								static::Trace('... Update ' . $sClassName.'::'.$oObj->GetKey());
								
								$bUpdated = false;
								$aAttDefs = MetaModel::ListAttributeDefs($sObjClass);
								
								foreach($aObject['attributes'] as $sAttCode => $sAttValue) {
									
									$oAttDef = $aAttDefs[$sAttCode];
									
									switch(true) {
										
										case ($oAttDef instanceof AttributeDateTime):
										case ($oAttDef instanceof AttributeDecimal):
										case ($oAttDef instanceof AttributeExternalKey):
										case ($oAttDef instanceof AttributeInteger):
										case ($oAttDef instanceof AttributeOneWayPassword):
										case ($oAttDef instanceof AttributeString):
											
											// Allow placeholders in attributes; replace them here
											$sAttValue = MetaModel::ApplyParams($sAttValue, $aPlaceHolders);
											
											if($oObj->Get($sAttCode) != $sAttValue) {
												$oObj->Set($sAttCode, $sAttValue);
												$bUpdated = true;
											}
											
											$oObj->Set($sAttCode, $sAttValue);
											break;
											
										default:
										
											static::Trace('..... '.$sAttCode.' ('.get_class($oAttDef).') => Not supported yet!');
											break;
											
									}
									
									
								}
								
								if($bUpdated == true) {
									
									try {
										$oObj->DBUpdate();
										static::Trace('.... '.$sObjClass.' updated.');
									}
									catch(Exception $e) {
										static::Trace('Exception while updating object: '.$e->GetMessage());
									}
								}
								else {
									static::Trace('.... '.$sObjClass.' was already synced.');								
								}
								
								$aPlaceHolders['previous_object->id'] = $oObj->GetKey();
								if($sObjectIndex == 0) {
									$aPlaceHolders['first_object->id'] = $oObj->GetKey();
								}
								
								break;
								
							default:
								// Unable to uniquely reconcile. Skip, no error.
								// Set first object ID (if unset!) to something non-existing to prevent errors in chained instructions.
								// Set previous object ID to something non-existing to prevent errors in chained instructions.
								$aPlaceHolders['previous_object->id'] = -1;
								static::Trace('... Could not uniquely reconcile ' . $sClassName . '. Ignoring this for the current object.');
								break;
								
						}
						
					}
					
					static::Trace('..'); // Done, just adding some spacing in the logs
				
				}
	
			}
			
		}
		
		/**
		 * Drives the LDAP config from other existing configuration.
		 *
		 * @param \Array $aFinalLDAPConfig Configuration which includes either 'ldap_config_name' or all these keys: 'host', 'port', 'default_user', 'default_pwd', 'base_dn', 'start_tls', 'options'
		 * @param \String $sIndex Sync rule index
		 *
		 * @return void
		 *
		 */
		public static function DetermineFinalLDAPConfig(&$aFinalLDAPConfig, $sIndex) {
			
		
			// - Determine configuration
			
				if(isset($aFinalLDAPConfig['ldap_config_name']) == false) {
					
					// Nothing to do
					static::Trace('.. Not pointing to an existing LDAP configuration (missing ldap_config_name)');
					
				}
				else {
					
					$sConfigName = $aFinalLDAPConfig['ldap_config_name'];
					$sSourceConfig = MetaModel::GetModuleSetting('jb-ldap', 'ldap_config_source', 'authent-ldap');
					$aServerConfigs = [];
					
					static::Trace('. Use this LDAP config from source "'.$sSourceConfig.'": "'.$sConfigName.'"');
						
					if($sSourceConfig == 'authent-ldap') {
						
						// Grab from authent-ldap
						if($sConfigName == 'default') {
							
							foreach(['host', 'port', 'default_user', 'default_pwd', 'base_dn', 'start_tls', 'options'] as $sSetting) {
								
								if($sSetting == 'options') {
									$aFinalLDAPConfig[$sSetting] = MetaModel::GetModuleSetting('authent-ldap', $sSetting, []);
								}
								elseif($sSetting == 'start_tls') {
									$aFinalLDAPConfig[$sSetting] = MetaModel::GetModuleSetting('authent-ldap', $sSetting, false);
								}
								else {
									$aFinalLDAPConfig[$sSetting] = MetaModel::GetModuleSetting('authent-ldap', $sSetting, '');
								}
								
							}
							
							static::Trace('.. Used default configuration from authent-ldap.');
							
						}
						else {
						
							$aServerConfigs = MetaModel::GetModuleSetting('authent-ldap', 'servers', []);
							
						}
						
					}
					// Note: this will be deprecated after 2.7 is phased out, unless someone requests to keep this for iTop 3.0 too (no good reason since the built-in authent-ldap does the same thing).
					elseif($sSourceConfig == 'knowitop') {
						
						$aServerConfigs = MetaModel::GetModuleSetting('knowitop-multi-ldap-auth', 'ldap_settings', []);
						
					}
					elseif($sSourceConfig == 'jb-ldap') {
						
						$aServerConfigs = MetaModel::GetModuleSetting('jb_ldap', 'servers', []);
						
					}
					else {
						
						static::Trace('.. Unsupported "ldap_config_source": '.$sSourceConfig);
						return;
						
					}
					
					
					if($sConfigName != 'default') {
							
						static::Trace('. Found '.count($aServerConfigs).' LDAP server configurations: "'.implode('", "', array_keys($aServerConfigs)).'"');
						
						// Does this ldap config exist?
						if(isset($aServerConfigs[$sConfigName]) == false) {
							
							static::Trace('.. Missing configuration: '.$sConfigName);
						
							// Nothing to do
							return;
							
						}
						
						$aServerConfig = $aServerConfigs[$sConfigName];
						
						foreach(['host', 'port', 'default_user', 'default_pwd', 'base_dn', 'start_tls', 'options'] as $sSetting) {
						
							// Validate already before passing on.
							if(
								(in_array($sSetting, ['host', 'default_user', 'default_pwd', 'base_dn']) == true && is_string($aServerConfig[$sSetting]) == true) ||
								($sSetting == 'options' && is_array($aServerConfig[$sSetting])) || 
								($sSetting == 'start_tls' && is_bool($aServerConfig[$sSetting]) == true) ||
								($sSetting == 'port' && is_int($aServerConfig[$sSetting]) == true)
							) { 
								$aFinalLDAPConfig[$sSetting] = $aServerConfig[$sSetting];
							}
							
						}
				
					}
					
					
				}
		
			// - Perform validation
			
			
				$aKeys = ['host', 'port', 'default_user', 'default_pwd', 'base_dn', 'options', 'start_tls'];
				$aMissingKeys = [];
				
				// Check if there is enough info to connect to an LDAP
				foreach($aKeys as $sKey) {
					if(isset($aFinalLDAPConfig[$sKey]) == false) {
						$aMissingKeys[] = $sKey;
					}
				}
				
				if(count($aMissingKeys) > 0) {
					static::Throw('.. Error: sync rule (index '.$sIndex.'): invalid LDAP configuration: no value set for "'.implode('", "', $aMissingKeys).'"');
					return;
				}
				
				
				if(is_array($aFinalLDAPConfig['options']) == false) {
					static::Throw('.. Error: sync rule (index '.$sIndex.'): "options" expects an array');
				}
				
			
			
		}
		
		
		
	}
	
