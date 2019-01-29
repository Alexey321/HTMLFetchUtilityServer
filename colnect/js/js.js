(
	function ()
	{
		"use strict";
		
		//begin: Main page service
		function getHtmlElementCounterObj()
		{
			var
				htmlElementCounterObj = {},
				urlToTheCheckPageInputElement = document.getElementById("url_to_the_check_page_input"),
				htmlElementNameInputElement = document.getElementById("html_element_name_input"),
				allFieldsAreRequiredMessageElement = document.getElementById("all_fields_are_required_message"),
				invalidUrlMessageElement = document.getElementById("invalid_url_message"),
				invalidHtmlElementNameMessageElement = document.getElementById("invalid_html_element_name_message"),
				inaccessibleUrlMessageElement = document.getElementById("inaccessible_url_message");
			
			htmlElementCounterObj.runHtmlElementCounter = function ()
			{
				var
					urlToTheCheckPage = urlToTheCheckPageInputElement.value.replace(/^\s+|\s+$/g, ""),
					htmlElementName = htmlElementNameInputElement.value.replace(/^\s+|\s+$/g, "");
				
				urlToTheCheckPageInputElement.oninput = htmlElementNameInputElement.oninput = htmlElementCounterObj.hideErrorMessages;
				
				//begin: Validation of form fields.
				var errorFlagOfFormFill = false; // Initially I believe that there are no errors.
				
				//begin: Check the form is filled.
				if
				(
					urlToTheCheckPage === "" || htmlElementName === ""
				)
				{
					allFieldsAreRequiredMessageElement.classList.remove("display_none");
					
					if (urlToTheCheckPage === "")
						urlToTheCheckPageInputElement.focus();
					else
						htmlElementNameInputElement.focus();
					
					errorFlagOfFormFill = true;
				}
				//end: Check the form is filled.
				
				if (urlToTheCheckPage !== "" && !/^(ftp|http|https):\/\/[^ "]+$/i.test(urlToTheCheckPage)) // URL validation check
				{
					invalidUrlMessageElement.classList.remove("display_none");
					urlToTheCheckPageInputElement.focus();
					urlToTheCheckPageInputElement.select();
					errorFlagOfFormFill = true;
				}
				
				var arrOfHtmlElementNames = ["!--", "!doctype", "a", "abbr", "acronym", "address", "applet", "area", "article", "aside", "audio", "b", "base", "basefont", "bdi", "bdo", "big", "blockquote", "body", "br", "button", "canvas", "caption", "center", "cite", "code", "col", "colgroup", "data", "datalist", "dd", "del", "details", "dfn", "dialog", "dir", "div", "dl", "dt", "em", "embed", "fieldset", "figcaption", "figure", "font", "footer", "form", "frame", "frameset", "h1", "h2", "h3", "h4", "h5", "h6", "head", "header", "hr", "html", "i", "iframe", "img", "input", "ins", "kbd", "label", "legend", "li", "link", "main", "map", "mark", "meta", "meter", "nav", "noframes", "noscript", "object", "ol", "optgroup", "option", "output", "p", "param", "picture", "pre", "progress", "q", "rp", "rt", "ruby", "s", "samp", "script", "section", "select", "small", "source", "span", "strike", "strong", "style", "sub", "summary", "sup", "svg", "table", "tbody", "td", "template", "textarea", "tfoot", "th", "thead", "time", "title", "tr", "track", "tt", "u", "ul", "var", "video", "wbr"];
				
				if (htmlElementName !== "" && -1 === arrOfHtmlElementNames.indexOf(htmlElementName.toLowerCase()))
				{
					invalidHtmlElementNameMessageElement.classList.remove("display_none");
					htmlElementNameInputElement.focus();
					htmlElementNameInputElement.select();
					errorFlagOfFormFill = true;
				}
				//end: Validation of form fields.
				
				if (!errorFlagOfFormFill) // No errors.
				{ // Transfer data to server.
					document.body.classList.add("loading"); // Animation of the process of transferring data and receiving a response.
					
					sendGet
					(
						"php\/i.php?url=" + encodeURIComponent(urlToTheCheckPage) + "&htmlElementName=" + encodeURIComponent(htmlElementName) + "&r=" + Math.random(),
						function (result) // callbackFun
						{
							var resultObj = getResultObj_initialParsingOfTheResponseFromTheServer // Getting the result object from the server and initial parsing the response from the server. (To eliminate errors.)
							(
								result // response from the server
							);
							
							switch (resultObj.responseId)
							{
								case "infoData":
									document.getElementById("web_page_url_id").innerHTML = resultObj.infoData.webPageUrl;
									document.getElementById("html_element_name_id_0").innerHTML =
										document.getElementById("html_element_name_id_1").innerHTML =
										document.getElementById("html_element_name_id_2").innerHTML =
										resultObj.infoData.htmlElementName;
									document.getElementById("fetch_time_id").innerHTML = getDatetimeStr(convertUTCDateToLocalDate(getJsDateObjByDbDateString(resultObj.infoData.fetchTimeUTC)));
									document.getElementById("duration_ms_id").innerHTML = resultObj.infoData.durationMs;
									document.getElementById("count_of_html_elements_id").innerHTML = resultObj.infoData.countOfHtmlElements;
									document.getElementById("count_of_checked_urls_from_that_domain_id").innerHTML = resultObj.infoData.countOfCheckedURLsfromThatDomain;
									document.getElementById("average_page_fetch_time_from_that_domain_during_the_last_24_hours_id").innerHTML = resultObj.infoData.averagePageFetchTimeFromThatDomainDuringTheLast24Hours;
									document.getElementById("total_count_of_this_element_from_this_domain_id").innerHTML = resultObj.infoData.totalCountOfThisElementFromThisDomain;
									document.getElementById("total_count_of_this_element_from_all_requests_ever_made_id").innerHTML = resultObj.infoData.totalCountOfThisElementFromAllRequestsEverMade;
									document.getElementById("domain_id_0").innerHTML =
										document.getElementById("domain_id_1").innerHTML =
										document.getElementById("domain_id_2").innerHTML =
										resultObj.infoData.webPageUrl.split("/")[2];
									document.getElementById("result_box_id").classList.remove("display_none");
									break;
								case "Inaccessible URL":
									inaccessibleUrlMessageElement.classList.remove("display_none");
									break;
								default:
									handlingCommonResponseErrorsFromTheServer // handling common response errors from the server
									(
										resultObj // response from the server
									);
							}
							
							document.body.classList.remove("loading");
						}
					);
				}
			};
			
			htmlElementCounterObj.hideErrorMessages = function()
			{
				allFieldsAreRequiredMessageElement.classList.add("display_none");
				invalidUrlMessageElement.classList.add("display_none");
				invalidHtmlElementNameMessageElement.classList.add("display_none");
				inaccessibleUrlMessageElement.classList.add("display_none");
				document.getElementById("result_box_id").classList.add("display_none");
			};
			
			return htmlElementCounterObj;
		}
		
		var htmlElementCounterObj = getHtmlElementCounterObj();
		//end: Main page service
		
		//begin: Common
		function sendGet
		(
			uri,
			callbackFun
		)
		{
			var xhr = new XMLHttpRequest();
			
			xhr.open("GET", uri, true);
			
			xhr.onreadystatechange = function()
			{
				if (this.readyState != 4)
					return;
				
				if (this.status != 200)
				{
					alert( "error: " + (this.status ? this.statusText : "request failed") ); // handle error
					return;
				}

				callbackFun(this.responseText, this.responseXML);
			};
			
			xhr.send();
		}
		
		//begin: Error processing.
		function getResultObj_initialParsingOfTheResponseFromTheServer // Getting the result object from the server and initial parsing the response from the server. (To eliminate errors.)
		(
			responseFromTheServer
		)
		{
			var resultObj = {};
			
			if (responseFromTheServer === "")
				resultObj.responseId = "emptyError"; // Empty response from server.
			else
			{
				try {
					resultObj = JSON.parse(responseFromTheServer);
				} catch (err) {
					resultObj.responseId = "jsonParseError";
				}
			}
			
			if (resultObj === null)
			{
				resultObj =
				{
					"responseId": "nullError"
				};
			}
			
			return resultObj;
		}
		
		function handlingCommonResponseErrorsFromTheServer // handling common response errors from the server
		(
			resultObj // response from the server
		)
		{
			switch (resultObj.responseId)
			{
				case "Could not connect to mysql":
					alert("Could not connect to database." + "\n" + "Error:\n" + resultObj.connectError);
					break;
				case "emptyError": // Could not parse JSON string because line is empty.
					alert("Error:\n" + "blank response from the server.");
					break;
				case "nullError": // Could not parse JSON string because line is empty.
					alert("nullError");
					break;
				case "jsonParseError": // Could not parse JSON string.
					alert("jsonParseError");
					break;
				default:
					if (typeof resultObj.description !== "undefined")
						alert("Error: " + resultObj.description);
					else
						alert("Unknown error");
			}
		}
		//end: Error processing.
		
		function convertUTCDateToLocalDate(date)
		{
			var
				newDate = new Date(date.getTime() + date.getTimezoneOffset() * 60 * 1000),
				offset = date.getTimezoneOffset() / 60,
				hours = date.getHours();
			
			newDate.setHours(hours - offset);
			
			return newDate;   
		}
		
		function getJsDateObjByDbDateString // For IE11
		(
			strDbDate // "YYYY-mm-dd HH:ii:ss"
		)
		{
			var arrDbDate = strDbDate.split(/\s|-|:/g);
			
			return new Date
			(
				arrDbDate[0], // yyyy // year
				parseInt(arrDbDate[1]) - 1, // mm // month
				arrDbDate[2], // dd // date
				arrDbDate[3], // HH
				arrDbDate[4], // ii
				arrDbDate[5] // ss
			);
		}
		
		function getDatetimeStr(dateObj)
		{
			return ("0" + dateObj.getDate()).slice(-2) + "\/" + ("0" + (dateObj.getMonth() + 1)).slice(-2) + "\/" + dateObj.getFullYear() + " " + ("0" + dateObj.getHours()).slice(-2) + ":" + ("0" + dateObj.getMinutes()).slice(-2) + ":" + ("0" + dateObj.getSeconds()).slice(-2);
		}
		//end: Common
		
		// begin: public
		window.htmlElementCounterApi =
		{
			hideErrorMessages: function ()
			{
				htmlElementCounterObj.hideErrorMessages();
			},
			runHtmlElementCounter: function ()
			{
				htmlElementCounterObj.runHtmlElementCounter();
			}
		};
		// end: public
	}()
);
