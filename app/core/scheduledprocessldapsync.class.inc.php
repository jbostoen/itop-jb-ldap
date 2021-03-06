<?php

/**
 * @copyright   Copyright (C) 2019 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2019-10-28 13:58:34
 *
 * Definition of ScheduledProcessLDAPSync
 */

namespace jb_itop_extensions\ldap_sync;

// jb-framework
use \jb_itop_extensions\components\ScheduledProcess;

// iTop internals
use \CoreUnexpectedValue;
use \iScheduledProcess;
use \MetaModel;

/**
 * Class ScheduledProcessLDAPSync
 */
class ScheduledProcessLDAPSync extends ScheduledProcess implements iScheduledProcess
{
	const MODULE_CODE = 'jb-ldap';

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
		$this->Trace('Disabled max_execution_time and set memory_limit to 512M');
		
		// Ignore time limit, it should run nightly and it will take some time.
		try {
			
			LDAPSyncProcessor::ProcessLDAPs();
			
		}
		catch(Exception $e) {
			$this->Trace($e->GetMessage());
		}
		finally {
			
			// Restore limits
			ini_set('max_execution_time', $iTimeLimit_PHP);
			ini_set('memory_limit', $iMemoryLimit_PHP);
			
		}
		
	}

}
