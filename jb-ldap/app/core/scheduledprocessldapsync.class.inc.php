<?php

/**
 * @copyright   Copyright (c) 2019-2023 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.230109
 *
 * Definition of ScheduledProcessLDAPSync
 */

namespace jb_itop_extensions\ldap_sync;

// iTop internals
use \AbstractWeeklyScheduledProcess;
use \CoreUnexpectedValue;
use \iScheduledProcess;
use \MetaModel;

/**
 * Class ScheduledProcessLDAPSync
 */
class ScheduledProcessLDAPSync extends AbstractWeeklyScheduledProcess {
	
	const MODULE_CODE = 'jb-ldap';

	/**
	 * @inheritdoc
	 */
	protected function GetModuleName() {
		return 'itop-backup';
	}

	/**
	 * @inheritdoc
	 */
	protected function GetDefaultModuleSettingTime() {
		return '23:30';
	}

	/**
	 * @inheritdoc
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MissingQueryArgument
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	public function Process($iTimeLimit) {
	
		// Increase limits, temporarily.
		$iTimeLimit_PHP = ini_get('max_execution_time');
		$iMemoryLimit_PHP = ini_get('memory_limit');
		set_time_limit(0);
		ini_set('memory_limit', '512M');
		
		$this->Trace('Processing LDAP Synchronization...');
		$this->Trace('Temporarily disabled max_execution_time and set memory_limit to 512M / Revert to '.(int)$iTimeLimit_PHP.' and '.$iMemoryLimit_PHP.' after sync.');
		
		// Ignore time limit, it should run nightly and it will take some time.
		try {
			
			LDAPSyncProcessor::ProcessRules();
			
		}
		catch(Exception $e) {
			
		
			$this->Trace($sEntry);
			
		}
		finally {
			
			$this->Trace('LDAP Synchronization complete.');
		
			// Restore limits
			ini_set('max_execution_time', $iTimeLimit_PHP);
			ini_set('memory_limit', $iMemoryLimit_PHP);
			
		}
		
	}
	
	public function Trace($sEntry) {
		
		$bTrace = MetaModel::GetModuleSetting('jb-ldap', 'trace_log', false);
		
		if($bTrace == true) {
			echo date('Y-m-d H:i:s').' | '.$sEntry.PHP_EOL;
		}
		
	}

}
