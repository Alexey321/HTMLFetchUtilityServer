<?php
class HtmlElementCounter
{
	public function __construct($webPageUrl, $htmlElementName)
	{
		DataBase::check_is_or_create_global_data_base_obj(); // проверить наличие и, при отсутствии, создать $GLOBALS['db']
		$mysqli = $GLOBALS['db']->mysqli; // Объект для общения с MySQL
		
		/* Request results */
		$this->webPageUrl = self::get_web_page_url($webPageUrl); // Адрес проверяемой веб страницы в нижнем регистре.
		$this->htmlElementName = self::get_htmlElementName($htmlElementName); // Имя html тега в нижнем регистре.
		
		self::delete_from_the_tables_records_older_than_24_hours_and_5_minutes($mysqli); // Удалить из таблицы 'request_during_last_24_hours' записи давностью более 5 минут и из таблицы 'request_during_last_24_hours' записи давностью более 24 часов.
		
		$savedDataNotOlderThan5MinutesOrUpdatedData = self::get_saved_data_not_older_than_5_minutes_or_updated_data($mysqli, $this->webPageUrl, $this->htmlElementName); // получить сохранённые данные не старше 5 минут или обновлённые данные
		
		$this->fetchTimeUTC = $savedDataNotOlderThan5MinutesOrUpdatedData->responseUtcDatetime; // Время прихода ответа от проверяемой веб страницы.
		$this->durationMs = $savedDataNotOlderThan5MinutesOrUpdatedData->duration_ms; // Время между запросом и откликом проверяемой веб страницы.
		$this->countOfHtmlElements = $savedDataNotOlderThan5MinutesOrUpdatedData->countOfHtmlElements; // Количество искомых html элементов, найденных в проверяемой веб странице.
		
		/* General Statistics */
		$generalStatisticsData = self::get_general_statistics_data($mysqli, $this->webPageUrl, $this->htmlElementName);
		
		$this->countOfCheckedURLsfromThatDomain = $generalStatisticsData->countOfCheckedURLsfromThatDomain; // Количество URL этого домена было проверено до сих пор.
		$this->averagePageFetchTimeFromThatDomainDuringTheLast24Hours = $generalStatisticsData->averagePageFetchTimeFromThatDomainDuringTheLast24Hours; // Среднее время загрузки страницы с этого домена за последние 24 часа.
		$this->totalCountOfThisElementFromThisDomain = $generalStatisticsData->totalCountOfThisElementFromThisDomain; // Общее количество этого элемента в этом домене.
		$this->totalCountOfThisElementFromAllRequestsEverMade = $generalStatisticsData->totalCountOfThisElementFromAllRequestsEverMade; // Общее количество этого элемента из всех когда-либо сделанных запросов.
	}
	
	private function get_url_validity_test($webPageUrl)
	{
		return filter_var($webPageUrl, FILTER_VALIDATE_URL) ? TRUE : FALSE;
	}
	
	private function get_htmlElementName_validity_test($htmlElementName)
	{
		$arrayOfHtmlElementName = array('!--', '!doctype', 'a', 'abbr', 'acronym', 'address', 'applet', 'area', 'article', 'aside', 'audio', 'b', 'base', 'basefont', 'bdi', 'bdo', 'big', 'blockquote', 'body', 'br', 'button', 'canvas', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'dir', 'div', 'dl', 'dt', 'em', 'embed', 'fieldset', 'figcaption', 'figure', 'font', 'footer', 'form', 'frame', 'frameset', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hr', 'html', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'legend', 'li', 'link', 'main', 'map', 'mark', 'meta', 'meter', 'nav', 'noframes', 'noscript', 'object', 'ol', 'optgroup', 'option', 'output', 'p', 'param', 'picture', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'script', 'section', 'select', 'small', 'source', 'span', 'strike', 'strong', 'style', 'sub', 'summary', 'sup', 'svg', 'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track', 'tt', 'u', 'ul', 'var', 'video', 'wbr');
		
		$bool = in_array($htmlElementName, $arrayOfHtmlElementName);
		
		return $bool;
	}
	
	private function get_web_page_url($webPageUrl)
	{
		if (!HtmlElementCounter::get_url_validity_test($webPageUrl))
		{
			echo '{"responseId": "failed", "description": "Not valid url"}';
			exit;
		}
		
		return strtolower($webPageUrl);
	}
	
	private function get_htmlElementName($htmlElementName)
	{
		$htmlElementName = strtolower($htmlElementName);
		
		if (!HtmlElementCounter::get_htmlElementName_validity_test($htmlElementName))
		{
			echo '{"responseId": "failed", "description": "Invalid html element name"}';
			exit;
		}
		
		return $htmlElementName;
	}
	
	private function get_real_count_of_html_elements($htmlPageContent, $htmlElementName) // 
	{
		return 	preg_match_all ("/<$htmlElementName\b(?=[^>]*>)/i", $htmlPageContent, $matches); // Количество html элементов.
	}
	
	private function get_html_page_response
	(
		$url // Example: http://127.0.0.1:777/?url=https%3A%2F%2Fexample.com&r=1142207190
	)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Blindly accept the certificate
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		// decode response
		curl_setopt($ch, CURLOPT_ENCODING, true);
		$response = curl_exec($ch);
		curl_close($ch);

		return $response;
	}
	
	/*
	function get_updated_html_page_data
	return example:
	
	stdClass Object
	(
		[requested_uri] => https://example.com
		[duration_ms] => 900
		[fetch_time_UTC] => 2019-01-20T09:28:42
		[header] =>
			Cache-Control: max-age=604800
			Content-Type: text/html; charset=UTF-8
			Date: Sun, 20 Jan 2019 09:28:41 GMT
			ETag: "1541025663+ident"
			Expires: Sun, 27 Jan 2019 09:28:41 GMT
			Last-Modified: Fri, 09 Aug 2013 23:54:35 GMT
			Server: ECS (dca/53DB)
			Vary: Accept-Encoding
			<unknown-field>: HIT
			Content-Length: 1270
		[content] => 
			<!doctype html>
			<html>
			...
			...
			</body>
			</html>
		[status] => ok
	)
	*/
	private function get_updated_html_page_data($webPageUrl) // Получить данные от запроса к реальной веб странице. (requested_uri, duration_ms, fetch_time_UTC, header, content, status)
	{
		$urlForTakingHtmlContent = PROTOCOL_HOSTNAME_PORT_FOR_TAKING_HTML_CONTENT.'?url='.urlencode($webPageUrl).'&r='.mt_rand(); // Запрос для получения даных из страницы чужого домена.
		$htmlPageResponse = self::get_html_page_response($urlForTakingHtmlContent);
		$htmlPageData = json_decode($htmlPageResponse);
		
		if ($htmlPageData->status === 'empty responce')
		{
			echo '{"responseId": "Inaccessible URL"}';
			exit;
		}
		
		return $htmlPageData;
	}
	
	/*
	function get_partsUrlData_from_web_page_url
	return example:
	
	$partsUrlData =
	stdClass Object
	(
		[protocolName] => https
		[userPassHostPortName] => colnect.com
		[pathName] => en/forum/viewforum!f=144&start=90
		[queryName] => 
		[fragmentName] => 
	)		
	*/
	private function get_partsUrlData_from_web_page_url($webPageUrl) // Получаю объект, наполненный параметрами: protocolName, userPassHostPortName, pathName (без переднего /), queryName
	{
		$partsUrlData = new stdClass();
		$partsUrlData->protocolName = parse_url($webPageUrl, PHP_URL_SCHEME);
		$startPos = stripos($webPageUrl, '://') + 3;
		$endPos = stripos($webPageUrl, '/', $startPos);
		
		if (!$endPos)
		{
			$endPos = stripos($webPageUrl, '?', $startPos);
		}
		
		if (!$endPos)
		{
			$endPos = stripos($webPageUrl, '#', $startPos);
		}
		
		$str = $endPos ? substr($webPageUrl, $startPos, $endPos - $startPos) : substr($webPageUrl, $startPos);
		$partsUrlData->userPassHostPortName = $str;
		$partsUrlData->pathName = substr(parse_url($webPageUrl, PHP_URL_PATH), 1);
		$partsUrlData->queryName = parse_url($webPageUrl, PHP_URL_QUERY);
		$partsUrlData->fragmentName = parse_url($webPageUrl, PHP_URL_FRAGMENT);
		
		return $partsUrlData;
	}
	
	/*
	function get_real_webPage_and_htmlElementName_data
	return example:
	
	stdClass Object
	(
		[protocolName] => https
		[userPassHostPortName] => colnect.com
		[pathName] => en
		[queryName] => 
		[fragmentName] => 
		[responseUtcDatetime] => 2019-01-21 13:45:37
		[duration_ms] => 632
		[htmlElementName] => img
		[countOfHtmlElements] => 2
	)
	*/
	private function get_real_webPage_and_htmlElementName_data($webPageUrl, $htmlElementName) // Получить реальные данные веб страницы и поиска html элемента для записи в БД.
	{
		$webPageAndHtmlElementNameData = new stdClass();
		
		$partsUrlData = self::get_partsUrlData_from_web_page_url($webPageUrl); // Получаю объект, наполненный параметрами: protocolName, userPassHostPortName, pathName (без переднего /), queryName
		
		$htmlPageData = self::get_updated_html_page_data($webPageUrl);// Получить данные от запроса к реальной веб странице. (requested_uri, duration_ms, fetch_time_UTC, header, content, status)
		
		if (!isset($htmlPageData)) // Нет соединения с сервером, который читает данные веб-страницы.
		{
			echo '{"responseId": "failed", "description": "No connection to the server that reads the webpage data."}';
			exit;
		}
		
		$responseUtcDatetime = date("Y-m-d H:i:s", strtotime($htmlPageData->fetch_time_UTC));
		$countOfHtmlElements = self::get_real_count_of_html_elements($htmlPageData->content, $htmlElementName);
		$webPageAndHtmlElementNameData->protocolName = $partsUrlData->protocolName;
		$webPageAndHtmlElementNameData->userPassHostPortName = $partsUrlData->userPassHostPortName;
		$webPageAndHtmlElementNameData->pathName = $partsUrlData->pathName;
		$webPageAndHtmlElementNameData->queryName = $partsUrlData->queryName;
		$webPageAndHtmlElementNameData->fragmentName = $partsUrlData->fragmentName;
		$webPageAndHtmlElementNameData->responseUtcDatetime = $responseUtcDatetime;
		$webPageAndHtmlElementNameData->duration_ms = $htmlPageData->duration_ms;
		$webPageAndHtmlElementNameData->htmlElementName = $htmlElementName;
		$webPageAndHtmlElementNameData->countOfHtmlElements = $countOfHtmlElements;
		
		return $webPageAndHtmlElementNameData;
	}
	
	private function get_or_insert_row_id_of_simple_table($mysqli, $tableName, $idName, $parameterName, $parameterValue) // Получить идентификатор строки простой таблицы. Если такого нет, то делается запись и отдаётся идентификатор записи.
	{
		$idSelectQuery = Common::get_string_without_extra_spaces
		(
			"
				SELECT
					$idName
				FROM
					$tableName
				WHERE
					$parameterName = '$parameterValue'
			"
		);
		
		if ($idResult = $mysqli->query($idSelectQuery))
		{
			if ($idRow = $idResult->fetch_assoc())
			{
				$idValue = $idRow[$idName];
			}
			else
			{
				$protocolInsertQuery = Common::get_string_without_extra_spaces
				(
					"
						INSERT INTO
							$tableName
							(
								$parameterName
							)
						VALUES
							(
								'$parameterValue'
							)
					"
				);
				
				if ($mysqli->query($protocolInsertQuery))
				{
					$idValue = $mysqli->insert_id;
				}
				else
				{
					echo '{"responseId": "failed", "description": "n3sgTWuMGr5FyNhF"}';
					exit;
				}
			}
		}
		else
		{
			echo '{"responseId": "failed", "description": "CUc33vPUqxzbYVVp"}';
			exit;
		}
		
		return $idValue;
	}
	
	private function getHrefId // Получить hrefId из БД.
	(
		$mysqli,
		$protocolId, // id протокола (http | https | ...)
		$userPassHostPortId, // id источника (часть между протоколом и pathName) Например: username:password@hostname:9090
		$pathNameId, // id пути к странице от корня без / впереди
		$queryId, // id части после ? и перед # (Например: arg=value)
		$fragmentId // якорь
	)
	{
		$hrefIdSelectQuery = Common::get_string_without_extra_spaces
		(
			"
				SELECT
					hrefId
				FROM
					href
				WHERE
					protocolId = $protocolId
				AND
					userPassHostPortId = $userPassHostPortId
				AND
					pathNameId = $pathNameId
				AND
					queryId = $queryId
				AND
					fragmentId = $fragmentId
			"
		);
		
		if ($hrefIdResult = $mysqli->query($hrefIdSelectQuery))
		{
			if ($hrefIdRow = $hrefIdResult->fetch_assoc())
			{
				$hrefId = $hrefIdRow['hrefId'];
			}
			else
			{
				$protocolInsertQuery = Common::get_string_without_extra_spaces
				(
					"
						INSERT INTO
							href
							(
								protocolId,
								userPassHostPortId,
								pathNameId,
								queryId,
								fragmentId
							)
						VALUES
							(
								'$protocolId',
								'$userPassHostPortId',
								'$pathNameId',
								'$queryId',
								'$fragmentId'
							)
					"
				);
				
				if ($mysqli->query($protocolInsertQuery))
				{
					$hrefId = $mysqli->insert_id;
				}
				else
				{
					echo '{"responseId": "failed", "description": "SumUBQphUzqdyjqY"}';
					exit;
				}
			}
		}
		else
		{
			echo '{"responseId": "failed", "description": "wQY5MccpnrvCHHxk"}';
			exit;
		}
		
		return $hrefId;
	}
	
	private function insert_request_during_last_24_hours // return $requestDuringLast24HoursId
	(
		$mysqli,
		$hrefId,
		$responseUtcDatetime,
		$duration_ms
	)
	{
		$requestDuringLast24HoursInsertQuery = Common::get_string_without_extra_spaces
		(
			"
				INSERT INTO
					request_during_last_24_hours
					(
						hrefId,
						responseUtcDatetime,
						duration_ms
					)
				VALUES
					(
						$hrefId,
						'$responseUtcDatetime',
						'$duration_ms'
					)
			"
		);
		
		if ($mysqli->query($requestDuringLast24HoursInsertQuery))
		{
			$requestDuringLast24HoursId = $mysqli->insert_id;
			
			return $requestDuringLast24HoursId;
		}
		else
		{
			echo '{"responseId": "failed", "description": "waPHrZZpUdNyqcPu"}';
			exit;
		}
	}
	
	private function get_countOfHtmlElementsId // Получить countOfHtmlElementsId из таблицы count_of_html_elements по $hrefId и $htmlElementId.
	(
		$mysqli,
		$hrefId,
		$htmlElementId
	)
	{
		$countOfHtmlElementsIdSelectQuery = Common::get_string_without_extra_spaces
		(
			"
				SELECT
					countOfHtmlElementsId
				FROM
					count_of_html_elements
				WHERE
					hrefId = $hrefId
				AND
					htmlElementId = $htmlElementId
			"
		);
		
		if ($countOfHtmlElementsIdResult = $mysqli->query($countOfHtmlElementsIdSelectQuery))
		{
			if ($countOfHtmlElementsIdRow = $countOfHtmlElementsIdResult->fetch_assoc())
			{
				$countOfHtmlElementsId = $countOfHtmlElementsIdRow['countOfHtmlElementsId'];
				
				return $countOfHtmlElementsId;
			}
			
			return FALSE;
		}
		else
		{
			echo '{"responseId": "failed", "description": "DyszFcVaj9s7Z5Ed"}';
			exit;
		}
	}
	
	private function insert_count_of_html_elements // Запись в таблицу count_of_html_elements. (return $countOfHtmlElementsId)
	(
		$mysqli,
		$hrefId,
		$htmlElementId,
		$countOfHtmlElements
	)
	{
		$countOfHtmlElementsId = self::get_countOfHtmlElementsId // Получить countOfHtmlElementsId из таблицы count_of_html_elements по $hrefId и $htmlElementId.
		(
			$mysqli,
			$hrefId,
			$htmlElementId
		);
		
		if ($countOfHtmlElementsId)
		{
			$countOfElementsUpdateQuery = Common::get_string_without_extra_spaces
			(
				"
					UPDATE
						count_of_html_elements
					SET
						countOfHtmlElements = $countOfHtmlElements
					WHERE
						hrefId = $hrefId
					AND
						htmlElementId = $htmlElementId
				"
			);
			
			if ($mysqli->query($countOfElementsUpdateQuery))
			{
				return $countOfHtmlElementsId;
			}
			else
			{
				echo '{"responseId": "failed", "description": "HAF8JVcLem8bLNfD"}';
				exit;
			}
		}
		else
		{
			$countOfElementsInsertQuery = Common::get_string_without_extra_spaces
			(
				"
					INSERT INTO
						count_of_html_elements
						(
							hrefId,
							htmlElementId,
							countOfHtmlElements
						)
					VALUES
						(
							$hrefId,
							$htmlElementId,
							'$countOfHtmlElements'
						)
				"
			);
			
			if ($mysqli->query($countOfElementsInsertQuery))
			{
				$countOfHtmlElementsId = $mysqli->insert_id;
				
				return $countOfHtmlElementsId;
			}
			else
			{
				echo '{"responseId": "failed", "description": "tqrTTmXA9kTWDT8p"}';
				exit;
			}
		}
		
		return TRUE;
	}
	
	private function insert_into_countOfHtmlElementsLast5Minutes_table($mysqli, $countOfHtmlElementsId, $requestDuringLast24HoursId) // Запись или обновление записей о подсчёте элементов за последние 5 минут.
	{
		$insertUserLoginCookieHashQuery = Common::get_string_without_extra_spaces
		(
			"
				INSERT INTO
					count_of_html_elements_last_5_minutes
					(
						countOfHtmlElementsId,
						requestDuringLast24HoursId
					)
				VALUES
					(
						$countOfHtmlElementsId,
						$requestDuringLast24HoursId
					)
				ON DUPLICATE KEY UPDATE
					requestDuringLast24HoursId = $requestDuringLast24HoursId
			"
		);
		
		if ($mysqli->query($insertUserLoginCookieHashQuery))
		{
			return TRUE;
		}
		else
		{
			echo '{"responseId": "failed", "description": "euBmzU6WRHhGS7Cw"}';
			exit;
		}
	}
	
	private function getStrTime5MinutesAgoFromNow()
	{
		$date5MinutesAgoFromNow = new DateTime(); // Будет дата вида 'Y-m-d H:i:s' 5 минут назад от нынешнего времени.
		date_sub($date5MinutesAgoFromNow, date_interval_create_from_date_string('5 minutes')); // Вычитаю 5 минут.
		$strTime5MinutesAgoFromNow = date_format($date5MinutesAgoFromNow, 'Y-m-d H:i:s');
		
		return $strTime5MinutesAgoFromNow;
	}
	
	private function getStrTime24HoursAgoFromNow()
	{
		$date24HoursAgoFromNow = new DateTime(); // Будет дата вида 'Y-m-d H:i:s' 24 часа назад от нынешнего времени.
		date_sub($date24HoursAgoFromNow, date_interval_create_from_date_string('24 hours')); // Вычитаю 5 минут.
		$strTime24HoursAgoFromNow = date_format($date24HoursAgoFromNow, 'Y-m-d H:i:s');
		
		return $strTime24HoursAgoFromNow;
	}
	
	private function delete_from_the_table_records_older_than_5_minutes($mysqli) // Удалить из таблицы 'count_of_html_elements_last_5_minutes' записи давностью более 5 минут.
	{
		$strTime5MinutesAgoFromNow = self::getStrTime5MinutesAgoFromNow(); // Получить строку времени 5 минут назад от сейчас в формате 'Y-m-d H:i:s'
		
		$countOfHtmlElementsLast5MinutesDeleteQuery = Common::get_string_without_extra_spaces
		(
			"
				DELETE
				FROM
					count_of_html_elements_last_5_minutes
				WHERE
				EXISTS
				(
					SELECT
						*
					FROM
						(
							SELECT
								count_of_html_elements_last_5_minutes.countOfHtmlElementsId
							FROM
								count_of_html_elements_last_5_minutes,
								count_of_html_elements,
								request_during_last_24_hours
							WHERE
								count_of_html_elements_last_5_minutes.countOfHtmlElementsId = count_of_html_elements.countOfHtmlElementsId
							AND
								count_of_html_elements_last_5_minutes.requestDuringLast24HoursId = request_during_last_24_hours.requestDuringLast24HoursId
							AND
								request_during_last_24_hours.responseUtcDatetime <= '$strTime5MinutesAgoFromNow'
						) t1
					WHERE
						t1.countOfHtmlElementsId = count_of_html_elements_last_5_minutes.countOfHtmlElementsId
				)
			"
		);
		
		if (!$mysqli->query($countOfHtmlElementsLast5MinutesDeleteQuery))
		{
			echo '{"responseId": "failed", "description": "6TA9FGhfxak89cBK"}';
			exit;
		}
		
		return TRUE;
	}
	
	private function delete_from_the_tables_records_older_than_24_hours_and_5_minutes($mysqli) // Удалить из таблицы 'request_during_last_24_hours' записи давностью более 5 минут и из таблицы 'request_during_last_24_hours' записи давностью более 24 часов.
	{
		self::delete_from_the_table_records_older_than_5_minutes($mysqli); // Удалить из в таблицы 'count_of_html_elements_last_5_minutes' записи давностью более 5 минут.
		
		$strTime24HoursAgoFromNow = self::getStrTime24HoursAgoFromNow(); // Получить строку времени 5 минут назад от сейчас в формате 'Y-m-d H:i:s'
		
		$countOfHtmlElementsLast5MinutesDeleteQuery = Common::get_string_without_extra_spaces
		(
			"
				DELETE
				FROM
					request_during_last_24_hours
				WHERE
					request_during_last_24_hours.responseUtcDatetime <= '$strTime24HoursAgoFromNow'
			"
		);
		
		if (!$mysqli->query($countOfHtmlElementsLast5MinutesDeleteQuery))
		{
			echo '{"responseId": "failed", "description": "xDJtpRLdxyPdRQGY"}';
			exit;
		}
		
		return TRUE;
	}
	
	private function record_web_page_data_and_html_element_in_the_database // Запись данных веб страницы и html элемента в БД.
	(
		$mysqli,
		$webPageAndHtmlElementNameData
		/*
		$webPageAndHtmlElementNameData example:
		
		stdClass Object
		(
			[protocolName] => https
			[userPassHostPortName] => colnect.com
			[pathName] => en
			[queryName] => 
			[fragmentName] => 
			[responseUtcDatetime] => 2019-01-21 13:45:37
			[duration_ms] => 632
			[htmlElementName] => img
			[countOfHtmlElements] => 2
		)
		*/
	)
	{
		//begin: Get protocolId by protocolName
		$protocolId = self::get_or_insert_row_id_of_simple_table // Получить идентификатор строки простой таблицы. Если такого нет, то делается запись и отдаётся идентификатор записи.
		(
			$mysqli,
			'protocol', // $tableName,
			'protocolId', // $idName
			'protocolName', // $parameterName,
			$webPageAndHtmlElementNameData->protocolName // $parameterValue
		);
		//end: Get protocolId by userPassHostPortName
		
		//begin: Get userPassHostPortId by userPassHostPortName
		$userPassHostPortId = self::get_or_insert_row_id_of_simple_table // Получить идентификатор строки простой таблицы. Если такого нет, то делается запись и отдаётся идентификатор записи.
		(
			$mysqli,
			'user_pass_host_port', // $tableName,
			'userPassHostPortId', // $idName
			'userPassHostPortName', // $parameterName,
			$webPageAndHtmlElementNameData->userPassHostPortName // $parameterValue
		);
		//end: Get userPassHostPortId by userPassHostPortName
		
		//begin: Get pathNameId by pathName
		$pathNameId = self::get_or_insert_row_id_of_simple_table // Получить идентификатор строки простой таблицы. Если такого нет, то делается запись и отдаётся идентификатор записи.
		(
			$mysqli,
			'pathname', // $tableName,
			'pathNameId', // $idName
			'pathName', // $parameterName,
			$webPageAndHtmlElementNameData->pathName // $parameterValue
		);
		//end: Get pathNameId by pathName
		
		//begin: Get queryId by queryName
		$queryId = self::get_or_insert_row_id_of_simple_table // Получить идентификатор строки простой таблицы. Если такого нет, то делается запись и отдаётся идентификатор записи.
		(
			$mysqli,
			'query', // $tableName,
			'queryId', // $idName
			'queryName', // $parameterName,
			$webPageAndHtmlElementNameData->queryName // $parameterValue
		);
		//end: Get queryId by queryName
		
		//begin: Get fragmentId by fragmentName
		$fragmentId = self::get_or_insert_row_id_of_simple_table // Получить идентификатор строки простой таблицы. Если такого нет, то делается запись и отдаётся идентификатор записи.
		(
			$mysqli,
			'fragment', // $tableName,
			'fragmentId', // $idName
			'fragmentName', // $parameterName,
			$webPageAndHtmlElementNameData->fragmentName // $parameterValue
		);
		//end: Get fragmentId by fragmentName
		
		$hrefId = self::getHrefId
		(
			$mysqli,
			$protocolId, // id протокола (http | https | ...)
			$userPassHostPortId, // id источника (часть между протоколом и pathName) Например: username:password@hostname:9090
			$pathNameId, // id пути к странице от корня без / впереди
			$queryId, // id части после ? и перед # (Например: arg=value)
			$fragmentId // якорь
		);
		
		$requestDuringLast24HoursId = self::insert_request_during_last_24_hours // Запись в таблицу request_during_last_24_hours. (return $requestDuringLast24HoursId)
		(
			$mysqli,
			$hrefId,
			$webPageAndHtmlElementNameData->responseUtcDatetime, // $responseUtcDatetime,
			$webPageAndHtmlElementNameData->duration_ms // $duration_ms
		);
		
		$htmlElementId = self::get_or_insert_row_id_of_simple_table // Получить идентификатор строки простой таблицы. Если такого нет, то делается запись и отдаётся идентификатор записи.
		(
			$mysqli,
			'html_element', // $tableName,
			'htmlElementId', // $idName
			'htmlElementName', // $parameterName,
			$webPageAndHtmlElementNameData->htmlElementName // $parameterValue
		);
		
		$countOfHtmlElementsId = self::insert_count_of_html_elements // Запись в таблицу count_of_html_elements. (return $countOfHtmlElementsId)
		(
			$mysqli,
			$hrefId,
			$htmlElementId,
			$webPageAndHtmlElementNameData->countOfHtmlElements
		);
		
		$res5MinutesTable = self::insert_into_countOfHtmlElementsLast5Minutes_table($mysqli, $countOfHtmlElementsId, $requestDuringLast24HoursId); // Запись или обновление записей о подсчёте элементов за последние 5 минут. (таблица 'count_of_html_elements_last_5_minutes')
		
		return TRUE;
	}
	
	/*
	function get_saved_data_from_db_not_older_than_5_minutes
	return example:
	
	Array
	(
		[responseUtcDatetime] => 2019-01-25 10:58:11
		[duration_ms] => 1005
		[countOfHtmlElements] => 2
	)
	*/
	private function get_saved_data_from_db_not_older_than_5_minutes($mysqli, $webPageUrl, $htmlElementName) // Получить сохранённые не старше 5 минут данные из БД. (от $webPageUrl и $htmlElementName)
	{
		$partsUrlData = self::get_partsUrlData_from_web_page_url($webPageUrl); // Получаю объект, наполненный параметрами: protocolName, userPassHostPortName, pathName (без переднего /), queryName
		
		$protocolName = $partsUrlData->protocolName;
		$userPassHostPortName = $partsUrlData->userPassHostPortName;
		$pathName = $partsUrlData->pathName;
		$queryName = $partsUrlData->queryName;
		$fragmentName = $partsUrlData->fragmentName;
		
		$requestSavedNotOlderThan5MinutesSelectQuery = Common::get_string_without_extra_spaces // запрос сохранённый не старше 5 минут 
		(
			"
				SELECT
					request_during_last_24_hours.responseUtcDatetime,
					request_during_last_24_hours.duration_ms,
					count_of_html_elements.countOfHtmlElements
				FROM
					request_during_last_24_hours,
					count_of_html_elements_last_5_minutes,
					count_of_html_elements,
					html_element
				WHERE
					request_during_last_24_hours.requestDuringLast24HoursId = count_of_html_elements_last_5_minutes.requestDuringLast24HoursId
				AND
					request_during_last_24_hours.hrefId =
					(
						SELECT
							hrefId
						FROM
							href
						WHERE
							protocolId =
							(
								SELECT
									protocolId
								FROM
									protocol
								WHERE
									protocolName = '$protocolName'
							)
						AND
							userPassHostPortId =
							(
								SELECT
									userPassHostPortId
								FROM
									user_pass_host_port
								WHERE
									userPassHostPortName = '$userPassHostPortName'
							)
						AND
							pathNameId =
							(
								SELECT
									pathNameId
								FROM
									pathname
								WHERE
									pathName = '$pathName'
							)
						AND
							queryId =
							(
								SELECT
									queryId
								FROM
									query
								WHERE
									queryName = '$queryName'
							)
						AND
							fragmentId =
							(
								SELECT
									fragmentId
								FROM
									fragment
								WHERE
									fragmentName = '$fragmentName'
							)
					)
				AND
					count_of_html_elements.countOfHtmlElementsId = count_of_html_elements_last_5_minutes.countOfHtmlElementsId
				AND
					count_of_html_elements.hrefId = request_during_last_24_hours.hrefId
				AND
					count_of_html_elements.htmlElementId = html_element.htmlElementId
				AND
					html_element.htmlElementName = '$htmlElementName'
			"
		);
		
		if ($requestSavedNotOlderThan5MinutesResult = $mysqli->query($requestSavedNotOlderThan5MinutesSelectQuery))
		{
			if ($requestSavedNotOlderThan5MinutesRow = $requestSavedNotOlderThan5MinutesResult->fetch_assoc())
			{
				return $requestSavedNotOlderThan5MinutesRow;
			}
			
			return FALSE;
		}
		else
		{
			echo '{"responseId": "failed", "description": "yfxXeuUeejVbqa89"}';
			exit;
		}
	}
	
	private function get_saved_data_not_older_than_5_minutes_or_updated_data($mysqli, $webPageUrl, $htmlElementName) // получить сохранённые данные не старше 5 минут или обновлённые данные
	{
		$data = new stdClass();
		
		$savedDataFromDbNotOlderThan5Minutes = self::get_saved_data_from_db_not_older_than_5_minutes($mysqli, $webPageUrl, $htmlElementName); // Получить сохранённые не старше 5 минут данные из БД. (от $webPageUrl и $htmlElementName)
		
		if ($savedDataFromDbNotOlderThan5Minutes === FALSE)
		{ // Получить реальные данные веб страницы для записи в БД.
			$webPageAndHtmlElementNameData = self::get_real_webPage_and_htmlElementName_data($webPageUrl, $htmlElementName); // Получить реальные данные веб страницы и поиска html элемента для записи в БД.
			
			$data->responseUtcDatetime = $webPageAndHtmlElementNameData->responseUtcDatetime;
			$data->duration_ms = intval($webPageAndHtmlElementNameData->duration_ms);
			$data->countOfHtmlElements = $webPageAndHtmlElementNameData->countOfHtmlElements;
			
			$dbRecordResult = self::record_web_page_data_and_html_element_in_the_database($mysqli, $webPageAndHtmlElementNameData); // Запись данных веб страницы и html элемента в БД.
		}
		else
		{
			$data->responseUtcDatetime = $savedDataFromDbNotOlderThan5Minutes['responseUtcDatetime'];
			$data->duration_ms = intval($savedDataFromDbNotOlderThan5Minutes['duration_ms']);
			$data->countOfHtmlElements = intval($savedDataFromDbNotOlderThan5Minutes['countOfHtmlElements']);
		}
		
		return $data;
	}
	
	private function get_count_of_checked_urls_from_that_domain($mysqli, $partsUrlData)
	{
		$countOfCheckedURLsfromThatDomainSelectQuery = Common::get_string_without_extra_spaces
		(
			"
				SELECT
					COUNT(*) AS countOfCheckedURLsfromThatDomain
				FROM
					href
				WHERE
					protocolId =
					(
						SELECT
							protocolId
						FROM
							protocol
						WHERE
							protocolName = '{$partsUrlData->protocolName}'
					)
				AND
					userPassHostPortId =
					(
						SELECT
							userPassHostPortId
						FROM
							user_pass_host_port
						WHERE
							userPassHostPortName = '{$partsUrlData->userPassHostPortName}'
					)
			"
		);
		
		if ($countOfCheckedURLsfromThatDomainResult = $mysqli->query($countOfCheckedURLsfromThatDomainSelectQuery))
		{
			if ($countOfCheckedURLsfromThatDomainRow = $countOfCheckedURLsfromThatDomainResult->fetch_assoc())
			{
				$countOfCheckedURLsfromThatDomain = intval($countOfCheckedURLsfromThatDomainRow['countOfCheckedURLsfromThatDomain']);
				
				return $countOfCheckedURLsfromThatDomain;
			}
			
			return FALSE;
		}
		else
		{
			echo '{"responseId": "failed", "description": "6ChpVfrgCYPNqJyw"}';
			exit;
		}
	}
	
	private function get_average_page_fetch_time_from_that_domain_during_the_last_24_hours($mysqli, $partsUrlData) // Среднее время загрузки страницы с этого домена за последние 24 часа.
	{
		$durationMsAvgSelectQuery = Common::get_string_without_extra_spaces
		(
			"
				SELECT
					AVG(request_during_last_24_hours.duration_ms)
				FROM
					(
						SELECT
							hrefId
						FROM
							href
						WHERE
							protocolId =
							(
								SELECT
									protocolId
								FROM
									protocol
								WHERE
									protocolName = '{$partsUrlData->protocolName}'
							)
						AND
							userPassHostPortId =
							(
								SELECT
									userPassHostPortId
								FROM
									user_pass_host_port
								WHERE
									userPassHostPortName = '{$partsUrlData->userPassHostPortName}'
							)
					) href_id_table,
					request_during_last_24_hours
				WHERE
					request_during_last_24_hours.hrefId = href_id_table.hrefId
			"
		);
		
		if ($durationMsAvgResult = $mysqli->query($durationMsAvgSelectQuery))
		{
			if ($durationMsAvgRow = $durationMsAvgResult->fetch_assoc())
			{
				$averagePageFetchTimeFromThatDomainDuringTheLast24Hours = round($durationMsAvgRow['AVG(request_during_last_24_hours.duration_ms)']);
				
				return $averagePageFetchTimeFromThatDomainDuringTheLast24Hours;
			}
		}
		else
		{
			echo '{"responseId": "failed", "description": "fKBPBzfJuRt4BJjs"}';
			exit;
		}
	}
	
	private function get_total_count_of_this_element_from_this_domain($mysqli, $partsUrlData, $htmlElementName) // Общее количество этого элемента в этом домене.
	{
		$countOfHtmlElementsSumSelectQuery = Common::get_string_without_extra_spaces
		(
			"
				SELECT
					SUM(count_of_html_elements.countOfHtmlElements)
				FROM
					(
						SELECT
							hrefId
						FROM
							href
						WHERE
							protocolId =
							(
								SELECT
									protocolId
								FROM
									protocol
								WHERE
									protocolName = '{$partsUrlData->protocolName}'
							)
						AND
							userPassHostPortId =
							(
								SELECT
									userPassHostPortId
								FROM
									user_pass_host_port
								WHERE
									userPassHostPortName = '{$partsUrlData->userPassHostPortName}'
							)
					) href_id_table,
					count_of_html_elements
				WHERE
					count_of_html_elements.hrefId = href_id_table.hrefId
				AND
					count_of_html_elements.htmlElementId =
					(
						SELECT
							htmlElementId
						FROM
							html_element
						WHERE
							htmlElementName = '$htmlElementName'
					)
			"
		);
		
		if ($countOfHtmlElementsSumResult = $mysqli->query($countOfHtmlElementsSumSelectQuery))
		{
			if ($countOfHtmlElementsSumRow = $countOfHtmlElementsSumResult->fetch_assoc())
			{
				$totalCountOfThisElementFromThisDomain = intval($countOfHtmlElementsSumRow['SUM(count_of_html_elements.countOfHtmlElements)']);
				
				return $totalCountOfThisElementFromThisDomain;
			}
		}
		else
		{
			echo '{"responseId": "failed", "description": "wGqdmTexLVVf4e3h"}';
			exit;
		}
	}
	
	private function get_total_count_of_this_element_from_all_requests_evermade($mysqli, $htmlElementName) // Общее количество этого элемента из всех когда-либо сделанных запросов.
	{
		$countOfHtmlElementsSumSelectQuery = Common::get_string_without_extra_spaces
		(
			"
				SELECT
					SUM(count_of_html_elements.countOfHtmlElements)
				FROM
					count_of_html_elements
				WHERE
					count_of_html_elements.htmlElementId =
					(
						SELECT
							htmlElementId
						FROM
							html_element
						WHERE
							htmlElementName = '$htmlElementName'
					)
			"
		);
		
		if ($countOfHtmlElementsSumResult = $mysqli->query($countOfHtmlElementsSumSelectQuery))
		{
			if ($countOfHtmlElementsSumRow = $countOfHtmlElementsSumResult->fetch_assoc())
			{
				$totalCountOfThisElementFromAllRequestsEverMade = intval($countOfHtmlElementsSumRow['SUM(count_of_html_elements.countOfHtmlElements)']);
				
				return $totalCountOfThisElementFromAllRequestsEverMade;
			}
		}
		else
		{
			echo '{"responseId": "failed", "description": "wGqdmTexLVVf4e3h"}';
			exit;
		}
	}
	
	private function get_general_statistics_data($mysqli, $webPageUrl, $htmlElementName)
	{
		$data = new stdClass();
		
		$partsUrlData = self::get_partsUrlData_from_web_page_url($webPageUrl); // Получаю объект, наполненный параметрами: protocolName, userPassHostPortName, pathName (без переднего /), queryName
		
		$data->countOfCheckedURLsfromThatDomain = self::get_count_of_checked_urls_from_that_domain($mysqli, $partsUrlData);
		
		$data->averagePageFetchTimeFromThatDomainDuringTheLast24Hours = self::get_average_page_fetch_time_from_that_domain_during_the_last_24_hours($mysqli, $partsUrlData); // Среднее время загрузки страницы с этого домена за последние 24 часа.
		
		$data->totalCountOfThisElementFromThisDomain = self::get_total_count_of_this_element_from_this_domain($mysqli, $partsUrlData, $htmlElementName); // Общее количество этого элемента в этом домене.
		
		$data->totalCountOfThisElementFromAllRequestsEverMade = self::get_total_count_of_this_element_from_all_requests_evermade($mysqli, $htmlElementName); // Общее количество этого элемента из всех когда-либо сделанных запросов.
		
		return $data;
	}
}
