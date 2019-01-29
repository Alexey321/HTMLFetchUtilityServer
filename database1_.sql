CREATE TABLE `protocol`
(
	`protocolId` TINYINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`protocolName` VARCHAR(8) NOT NULL UNIQUE /* протокол (http | https | ...) */
);

CREATE TABLE `user_pass_host_port`
(
	`userPassHostPortId` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`userPassHostPortName` VARCHAR(255) NOT NULL UNIQUE /* источник (часть между протоколом и pathName) Например: username:password@hostname:9090 */
);

CREATE TABLE `pathname`
(
	`pathNameId` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`pathName` VARCHAR(255) NOT NULL UNIQUE /* путь к странице от корня без / впереди */
);

CREATE TABLE `query`
(
	`queryId` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`queryName` TEXT(2000) NOT NULL /* часть после ? (Например: arg=value) */
);

CREATE TABLE `fragment`
(
	`fragmentId` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`fragmentName` TEXT(2000) NOT NULL /* часть после ? (Например: arg=value) */
);

CREATE TABLE `href`
(
	`hrefId` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`protocolId` TINYINT NOT NULL, /*  id протокола (http | https | ...) */
	`userPassHostPortId` INT NOT NULL, /* id источника (часть между протоколом и pathName) Например: username:password@hostname:9090 */
	`pathNameId` INT NOT NULL, /* id пути к странице от корня без / впереди */
	`queryId` INT NOT NULL, /* id части после ? и перед # (Например: arg=value) */
	`fragmentId` INT NOT NULL /* якорь */
);

CREATE TABLE `html_element`
(
	`htmlElementId` SMALLINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`htmlElementName` VARCHAR(10) NOT NULL UNIQUE
);

CREATE TABLE `request_during_last_24_hours`
(
	`requestDuringLast24HoursId` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`hrefId` INT NOT NULL,
	`responseUtcDatetime` DATETIME NOT NULL, /* Время прихода данных с проверяемого сайта */
	`duration_ms` INT NOT NULL /* Время между запросом и откликом сайта */
);

CREATE TABLE `count_of_html_elements`
(
	`countOfHtmlElementsId` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`hrefId` INT NOT NULL,
	`htmlElementId` SMALLINT NOT NULL,
	`countOfHtmlElements` INT NOT NULL
);

CREATE TABLE `count_of_html_elements_last_5_minutes`
(
	`countOfHtmlElementsId` INT NOT NULL PRIMARY KEY,
	`requestDuringLast24HoursId` INT NOT NULL
);
