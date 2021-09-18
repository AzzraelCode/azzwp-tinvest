<?php

namespace azztinvest;

/**
 * Загрузка google-api-php-client с зависимостями
 */
require_once (AZZTINVEST_PLUGIN_DIR."vendor/autoload.php");

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_BatchUpdateValuesRequest;
use Google_Service_Sheets_ClearValuesRequest;
use Google_Service_Sheets_ValueRange;

/**
 * Class Sheet
 * @package azztinvest
 *
 * Функционала минимум, только для записи конкретных данных
 * вся красота в Tinvest
 */
class Sheet {

	private $service = null;
	private $spreadsheet_id = null;

	/**
	 * Получение google_spreadsheet_id из POST запроса
	 * и валидация
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getSpreadsheetId() {
		if($this->spreadsheet_id === null) {
			// токен не сохраняю
			if(!isset($_POST['google_spreadsheet_id'])) throw new \Exception("Не указан ID таблицы Google Sheets");

			$spreadsheet_id = sanitize_text_field(trim($_POST['google_spreadsheet_id']));

			if(
				!$spreadsheet_id ||
				strlen($spreadsheet_id) < 8
			) throw new \Exception("Ошибочный ID таблицы Google Sheets");

			$this->spreadsheet_id = $spreadsheet_id;
		}

		return $this->spreadsheet_id;
	}

	/**
	 * Создание службы для работы с Sheets API
	 * через сервисный аккаунт Google Cloud Platform
	 *
	 * @return Google_Service_Sheets
	 * @throws \Google\Exception
	 */
	private function getGoogleService() {
		if($this->service === null) {
			$client = new Google_Client();
		    $client->setApplicationName('azz-tinvest');
		    $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
		    $client->setAuthConfig(AZZTINVEST_PLUGIN_DIR.'creds/sacc.json');
		    $client->setAccessType('offline');
		    $this->service = new Google_Service_Sheets($client);
		}

	    return $this->service;
	}

	/**
	 * Функция создания нового Листа в Таблице
	 *
	 * @return string имя созданного листа типа "Лист2"
	 * @throws \Google\Exception
	 */
	private function addSheet() {
		$sheet_name = "Отчет ".date("Y-m-d H:i:s");
		$body = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest( [
				'requests' => [
					'addSheet' => [
						'properties' => [
							'title' => $sheet_name,
						]
					]
				]
			]
		);

		$this->getGoogleService()->spreadsheets->batchUpdate($this->getSpreadsheetId(), $body);

		return $sheet_name;
	}

	/**
	 * Запись сделок и расчитанного профита из Tinvest в заданную таблицу Google Sheet
	 *
	 * @param array $data
	 *
	 * @throws \Google\Exception
	 */
	public function writeSheetsApi( $data, $profit = [] ) {
		// в зависимости от галки в запросе либо пишу в Лист1
		// (предварительно очистив его от данных, но не от форматирования, что можно использовать)
		// либо создаю новый
		if(intval(@$_POST['create_sheet'])) {
			$sheet_name = $this->addSheet();
		} else {
			$sheet_name = "Лист1"; // $this->addSheet();
			// если пишу на существующий лист то чищу от значений (но не от форматирования)
			$this->getGoogleService()->spreadsheets_values->clear($this->getSpreadsheetId(), "$sheet_name!A:Z", new Google_Service_Sheets_ClearValuesRequest());
		}

		// Пишу на Лист
		$body = new Google_Service_Sheets_BatchUpdateValuesRequest([
		    'valueInputOption' => 'RAW',
		    'data' => [
		    	new Google_Service_Sheets_ValueRange([
			        'range' => "$sheet_name!A:M",
				    'values' =>  $data
			    ]),
		    	new Google_Service_Sheets_ValueRange([
			        'range' => "$sheet_name!N3:Z",
				    'values' =>  $profit
			    ])
		    ]
		]);

		$this->getGoogleService()->spreadsheets_values->batchUpdate($this->getSpreadsheetId(), $body);
	}
}