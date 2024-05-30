<?php

namespace cmsc\classes\core;

use cmsc\classes\cron\Schedule;

class Deactivator {

	public function __construct() {
		Schedule::remove_tasks();
	}

}
