<?php
class DataBase
{
	public function __construct($host, $username, $password, $database)
	{
		$this->mysqli = @new mysqli($host, $username, $password, $database);
		
		if ($this->mysqli->connect_errno)
		{
			echo json_encode
			(
				array
				(
					'responseId' => 'Could not connect to mysql',
					'connectError' => $this->mysqli->connect_error
				)
			);
			
			exit();
		}
		
		$this->mysqli->set_charset('utf8');
	}
	
	public function check_is_or_create_global_data_base_obj()
	{
		if (!isset($GLOBALS['db']))
		{
			require_once 'config.php';
			$GLOBALS['db'] = new DataBase(HOST, USERNAME, PASSWORD, DATABASE);
		}
	}
}
