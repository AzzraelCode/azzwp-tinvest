<?php
/*
Plugin Name: Azz Wordpress Tinvest
Author: Azzrael
Plugin URI: https://azzrael.ru
Author URI: https://azzrael.ru
Description: Плагин для взаимодействия с Open API Тинькофф Инвестиции
Version: 1
*/

/*
Copyright (C) 2021 Azzrael, azzrael.ru
Original code by Azzrael of azzrael.ru

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace azztinvest;

if(!function_exists( 'add_filter' )) { header( 'Status: 403 Forbidden' ); header( 'HTTP/1.1 403 Forbidden' ); exit(); }

if ( !defined( 'AZZTINVEST_PLUGIN_DIR' ) ) define( 'AZZTINVEST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Class AzzTinvest
 *
 * @package azztinvest
 */
class AzzTinvest {

	public function __construct() {
		// Добавление кастомного шорткода
		add_shortcode("azztinvestsc", [$this, 'shortcode']);

	}

	/**
	 * Шорткод для вызова метода на любой странице сайта
	 * и единая точка входа в плагин
	 *
	 * В любом посте/странице вставь [azztinvestsc]
	 * @param $atts
	 *
	 * @return string
	 */
	public function shortcode($atts) {
		global $wp;
		$params = [ 'base_url' => home_url( $wp->request ) ];

		if(isset($_POST['nonce'])) {
			try{

				if(!wp_verify_nonce( $_POST['nonce'], 'azztinvest_action' )) throw new \Exception("Форма устарела");

				// загружаем только при отправке формы
				require_once ("inc/tinvest.php");
				require_once("inc/sheet.php");

				// создаю объекты
				$tinvest = new Tinvest(); // и сразу получаю данные в конструкторе
				$sheet = new Sheet();

				// форматирую данные и отправляю в таблицу
				$sheet->writeSheetsApi($tinvest->getData(), $tinvest->getProfit());
				// сообщение об успешности
				$params['message'] = "Экспорт прошел успешно.<br/><a href='https://docs.google.com/spreadsheets/d/". $sheet->getSpreadsheetId(). "/edit' target='_blank'>Посмотри какая красота!</a>";

			}catch (\Exception $e){
				// изо всех методов исключения отлавливаются здесь и выводятся в ренедере
				$params['error'] = 1;
				$params['message'] = $e->getMessage();
			}
		}

		// рендер
		return $this->render('options', $params);
	}

	/**
	 * Views render
	 * @param $view
	 * @param array $params
	 *
	 * @return bool|false|string
	 */
	private function render($view, $params = []) {
		$file = preg_replace('/[^a-zA-Z0-9-_]/i','', $view).".php";
		$path = AZZTINVEST_PLUGIN_DIR."views/".$file;
		if(!is_file($path)) return false;

		if(!empty($params)) extract($params);

		// Get template
		ob_start();
		include $path;
		$out = ob_get_contents();
		ob_end_clean();

		return $out;
	}
}

/**
 * Инициализация
 */
new AzzTinvest();
