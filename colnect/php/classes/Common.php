<?php
class Common
{
	public function get_string_without_extra_spaces($str)
	{
		return trim(preg_replace("/\s+/"," ",$str));
	}
}
