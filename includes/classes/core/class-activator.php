<?php

namespace cmsc\classes\core;

use cmsc\classes\cron\Schedule;

class Activator {

	public function __construct() {
		Schedule::add_tasks();
		new DB_Operations();
	}

}
