<?php

namespace azztinvest;

use function GuzzleHttp\Psr7\str;

/**
 * Class Tinvest
 * @package azztinvest
 *
 * !!!! ВАЖНО !!!!
 * !!! Я не покупаю другой валюты кроме доллара !!!!
 * если у вас есть другие бумаги считающиеся в Тинькофф Инвестиции как Currencies, то отчет не сойдется
 *
 * Ещё нюансы:
 * 1. НДФЛ,
 * с купонов и дивидендов ТИ удерживает сразу при выплате на брокерский счет,
 * от спекуляций - либо при выводе денег ( в т.ч. частичном ) либо в конце года, поэтому расчет точно провести сложно
 * 2. НДФЛ на валюту
 * 3. С ИИС мой скрипт не работает, там с НДФЛ вообще все отдельно
 * 4. Расчет комиссии установлен по фикс ставке, однако на тарифе Трейдер и выше ставка может плавать (0,05 - 0,025).
 *
 */
class Tinvest {

	const TAX = 0.13;
	const BROKER_COMISSION = 0.003;

	private $api_entry_url = "https://api-invest.tinkoff.ru/openapi/";
	private $token = null;

	private $usd = 0;
	private $figis = [];
	private $data =  [];
	private $profit = [];

	/**
	 * Tinvest constructor.
	 * здесь же при создании объекта и запрашиваю все данные от апи
	 */
	public function __construct() {
		$this->usd = $this->getUsd();
		$this->operations();
		$this->portfolio();
	}

	/**
	 * Получение Токена из POST
	 * и его валидация
	 * @return string
	 * @throws \Exception
	 */
	public function tokenTinvest() {
		if($this->token === null) {
			// токен не сохраняю
			if(!isset($_POST['tinvest_token_real'])) throw new \Exception("Нету токена для API Тинькофф Инвестиции");

			$token = sanitize_text_field(trim($_POST['tinvest_token_real']));
			// валидация токена
			if(
				!$token
				|| strlen($token) < 80
				|| substr($token, 0, 2) !== 't.'
			) throw new \Exception("Ошибочный токен для API Тинькофф Инвестиции");

			$this->token = $token;
		}

		return $this->token;
	}


	/**
	 * HTTP запрос к API Тинькофф Инвестиции
	 *
	 * можно использовать wp_remote_get или guzzle из зависимостей google-api-php-client
	 * но QRATOR кот защищает API как то странно на них реагирует отправляя в бан
	 * стабильнее всего работает обычный курл
	 *
	 * все Исключения отлавливаю на уровне Шортката
	 *
	 * @param string $method
	 * @param array $params
	 *
	 * @throws \Exception
	 */
	public function queryTinvestApi($endpoint = "/user/accounts", $params = []) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->api_entry_url . $endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);


        curl_setopt( $curl, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json', 'Authorization: Bearer ' . $this->tokenTinvest() ] );
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // для тестов нужно

        $resp = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

		if($code !== 200) throw new \Exception("Ошибка запроса к серверу API Тинькофф Инвестиции $code / ".curl_error($curl));
		if(!$json = json_decode($resp)) throw new \Exception("Ошибочный ответ API Тинькофф Инвестиции");

		return $json->payload;
	}

	/**
	 * /operations нам отдает только FIGI (/portfolio отдает и тикер и имя бумаги)
	 * поэтому чтобы получить имя и тикер нужно дергать /market/search/by-figi
	 * если бумаг много, то можем упереться в лимиты апи или пхп умрет раньше
	 * да и в принципе процесс сильно удлиняется
	 * поэтому собираем все запрошенные бумаги в json архив и грузим его сразу в массив
	 * - минус - гипотетически может жрать больше памяти и места на диске, но врядли
	 *
	 * @param $figi
	 * @param $key
	 *
	 * @return mixed
	 */
	public function getByFigi($figi, $key) {
		if($figi === null) return ""; // для тестов

		// для экономии запросов к апи - сохраняю соответствие figi -> name, tiker локально
		$file_path = AZZTINVEST_PLUGIN_DIR.'figis.json';
		if(empty($this->figis)) $this->figis = json_decode(@file_get_contents( $file_path), true);

		if(!isset($this->figis[$figi])) {
			$r = $this->queryTinvestApi('/market/search/by-figi?figi='.$figi);
			$this->figis[$figi] = [ 'name' => $r->name, 'ticker' => $r->ticker];
			@file_put_contents($file_path, json_encode($this->figis, true)); // обновляю файл
		}

		return $this->figis[$figi][$key];
	}


	/**
	 * Открыты Позиции /portfolio
	 *
	 * Подготовка данных, сведение в один массив
	 * расчет сумм оборота и комиссии брокера как если бы я продал бумаги прямо сейчас
	 *
	 * ВАЖНО!!! При расчете стакан не запрашивается !!!
	 * Я использую только информацию от /portfolio, т.е. только expectedYield и averagePositionPrice
	 * - комиссия переводится в рубли
	 * - разные комиссии (от оборота) не учитываются
	 * - ндфл здесь не считается
	 * - также не учитывается нкд и всякое такое
	 *
	 * @throws \Exception
	 */
	private function portfolio() {

		// * симулирую продажу позиций по текущей среднерыночной цене
		foreach ($this->queryTinvestApi('/portfolio')->positions as $i => $p) {

			// ТИ считает валюту бумагой и по логике её тоже стоило бы тут продавать
			// но симулировать продажу валюты на данном этапе не буду (пусть висит в виртуальных кошельках)
			if($p->instrumentType == 'Currency') continue;

			$money = ( $p->averagePositionPrice->value * $p->balance ) + $p->expectedYield->value;

			$e = [
				'date'      => date( "Y-m-d H:i:s", time() + $i ), // чтобы немного отличать время
				'op_type'   => "Sell",
				'in_type'   => $p->instrumentType,
				'name'      => $p->name,
				'tiker'     => $p->ticker,
				'count'     => $p->balance, // акций (НЕ лотов)
				'money'     => $money,
				'curr'      => $p->expectedYield->currency,
				'RUB'       => 0,
				'USD'       => 0,
				'exp_price' => $p->averagePositionPrice->value, // ожидаемая средняя цена (при продаже??)
				'exp_yield' => $p->expectedYield->value, // ожидаемая прибыль на позицию целиком
			];

			$e[$p->expectedYield->currency] = $money; // пополняю виртуальный кошелек

			$this->data[] = $e;

			// * считаю виртуальную комиссию брокера
			// ! налоги здесь считать не буду
			$e['op_type'] = 'BrokerCommission';
			$e['count'] = 0;
			$e['exp_price'] = 0;
			$e['exp_yield'] = 0;

			// если expectedYield у нас в долларах, то нужно перевести в рубли
			if($e['curr'] == 'USD') {
				$e['curr'] = 'RUB';
				$e['money'] = round(-1*$e['money']*$this->usd*self::BROKER_COMISSION,2);
				$e['USD'] = 0;
			} else {
				$e['money'] = round(-1*$e['money']*self::BROKER_COMISSION,2);
			}

			$e['RUB'] = $e['money']; // списываю с вирт кошелька

			$this->data[] = $e;
		}
	}

	/**
	 * Получение завершенных сделок /operations
	 * сейчас считаю за весь период
	 *
	 * - собираю все в единый массив идентичный /portfolio, для унификации
	 * - создаю движения по виртуальным кошелькам (для меня только RUB/USD), в т.ч. покупку валюты
	 * - получаю name/tiker по figi
	 *
	 *
	 * @throws \Exception
	 */
	private function operations() {
		// * закрытые сделки за весь период
		// !!!! по суммам столбцов кошельков этой части можно проверить что ошибок нет !!!
		$period = http_build_query( [
			'from' => '2010-01-01T00:00:00+03:00',
			'to'   => '2025-01-01T00:00:00+03:00'
		] );

		foreach ($this->queryTinvestApi("/operations?$period")->operations as $p) {

			$figi = isset($p->figi) ? $p->figi : null;
			$instrumentType = isset($p->instrumentType) ? $p->instrumentType : "";

			// форматирование
			$e = [
				'date'    => date( "Y-m-d H:i:s", strtotime( $p->date ) ),
				'op_type' => $p->operationType,
				'in_type' => $instrumentType,
				'name'    => $this->getByFigi( $figi, "name" ),
				'tiker'   => $this->getByFigi( $figi, "ticker" ),
				'count'   => in_array( $p->operationType, [ 'Buy', 'Sell' ] ) ? $p->quantityExecuted : "",
				'money'   => $p->payment,
				'curr'    => $p->currency,
				'RUB'       => 0,
				'USD'       => 0,
			];

			// оборот в виртуальном кошельке
			$e[$p->currency] = $p->payment;

			// -- покупка продажа валюты,
			if(in_array($p->operationType, ['Buy', 'Sell']) && $instrumentType == 'Currency') {
				$op_currency = "USD"; // !!!! для меня это только USD !!!
				$e[$op_currency] = ($p->operationType == 'Buy' ? 1 : -1) * $p->quantityExecuted;

				// для расчета профита понадобится,
				// надо бы считать в расчете профита, но лень -> фуфуфу так делать, не будь как Azzrael
				$this->add_profit("balance_closed_$op_currency", $e[$op_currency]);
			}

			$this->data[] = $e;
		}
	}

	/**
	 * Расчет Профита и прочих итоговых показателей
	 * оформление данных для вывода на таблицу
	 *
	 * @return array
	 */
	public function getProfit() {

		foreach ($this->data as $r) {

			if(isset($r['exp_yield'])) {
				// * статистика по ещё открытым сделкам
				$this->add_profit("balance_exp_{$r['curr']}", $r['money']);
			}
			else {
				// * статистика по УЖЕ ЗАКРЫТЫМ сделкам
				$this->add_profit("balance_closed_{$r['curr']}", $r[$r['curr']]); // все движения денег кроме покупки и продажи валюты
			}

			// ** стат по всем сделкам

			// считаю суммы ввода/вывода денег (только рубли пока)
			if($r['op_type'] == 'PayIn') $this->add_profit("payin_{$r['curr']}", $r[$r['curr']]);
			if($r['op_type'] == 'PayOut') $this->add_profit("payout_{$r['curr']}", abs($r[$r['curr']]));

			// Считаю суммы продаж и покупок  для расчета налогооблагаемой базы
			// все бумаги кроме валют, хотя их тоже стоило бы считать
			if(in_array($r['op_type'], ['Buy', 'Sell']) && $r['in_type'] != 'Currency') $this->add_profit("vol_{$r['op_type']}_{$r['curr']}", abs($r['money']));

			if($r['op_type'] == 'Coupon') $this->add_profit('Coupon', $r['money']);
			if($r['op_type'] == 'Dividend') $this->add_profit('Dividend', $r['money']);
			if(in_array($r['op_type'], ['TaxDividend', 'TaxCoupon'])) $this->add_profit('dc_tax', abs($r['money']));

			if($r['op_type'] == 'Tax') $this->add_profit('Tax', abs($r['money']));
			if($r['op_type'] == 'BrokerCommission') $this->add_profit('BrokerCommission', abs($r['money']));

		}


		// разница покупок и продаж бумаг для расчета налогооблагаемой базы
		$diff_vol = $this->profit['vol_Sell_RUB'] +
		            ($this->profit['vol_Sell_USD']*$this->usd) -
					$this->profit['vol_Buy_RUB'] -
	               ($this->profit['vol_Buy_USD']*$this->usd)
		;


		// доллары от продажи позиций перевожу в рубли
		$balance_exp_usd_rub = round($this->profit['balance_exp_USD']*$this->usd, 2);

		// * балансовый доход за весь период
		// т.е. пришло - куплено + продано - выведено
		// т.е. включает уже все списанные налоги и комиссии, но может не включать (а может и вкл.) НДФЛ на спекуляции
		$balance_profit = $this->profit['balance_exp_RUB'] +
		                $this->profit['balance_closed_RUB'] +
		                ($this->profit['balance_closed_USD'] * $this->usd) +
		                $balance_exp_usd_rub +
		                $this->profit['payout_RUB'] -
		                $this->profit['payin_RUB']
						// todo: если будет заведение валюты - учесть
		;

		$days = floor(( time() - strtotime(end($this->data)['date']))/86400);
		$year_expected_profit = ($balance_profit / $days)*365;
		$year_expected_profit_proc = round($year_expected_profit *100/ $this->profit['payin_RUB'], 2);

		$profit = [
			['Курс, USD/RUB', $this->usd],
			['Остатки, RUB', $this->profit['balance_closed_RUB']],
			['Остатки, USD', round($this->profit['balance_closed_USD']*$this->usd, 2), $this->profit['balance_closed_USD']],
			[],
			['Завел, RUB', $this->profit['payin_RUB']],
			['Вывел, RUB', -1*$this->profit['payout_RUB']],
			[],
			['Открыто на, RUB', $this->profit['balance_exp_RUB']],
			['Открыто на, USD', $this->profit['balance_exp_USD'],  $balance_exp_usd_rub],
			[],
			['Всего покупок, RUB', -1*$this->profit['vol_Buy_RUB']],
			['Всего покупок, USD', round($this->profit['vol_Buy_USD']*$this->usd*-1, 2), -1*$this->profit['vol_Buy_USD']],
			['Всего продаж, RUB', $this->profit['vol_Sell_RUB']],
			['Всего продаж, USD', round($this->profit['vol_Sell_USD']*$this->usd, 2), $this->profit['vol_Sell_USD']],
			['База налога, RUB', $diff_vol],
			['НДФЛ, начисленный ориентировочно, RUB', round($diff_vol*self::TAX*-1, 2), 'может быть уже частично списанным'],
			[],
			['Прибыль балансовая, RUB', round($balance_profit,2), 'т.е. со всем списанным, но без НДФЛ на открытые'],
			['Ожид. доходность, %', $year_expected_profit_proc],
			[],
			['Комиссии брокера, RUB', -1*$this->profit['BrokerCommission']],
			['Списанный НДФЛ от спекуляций, RUB', -1*$this->profit['Tax']],
			['Списанный НДФЛ от купонов/дивиденов, RUB', -1*$this->profit['dc_tax']],
			['Доход от купонов, RUB', $this->profit['Coupon']],
			['Доход от дивидендов, RUB', $this->profit['Dividend']],
		];

		return $profit;
	}

	/**
	 * Оформление массива данных сделок для вывода в таблицу
	 *
	 * @return array
	 */
	public function getData() {
		// * сортировка выводимых строк по дате
		usort($this->data, function ($a, $b) {
			$sort_by = "date";
			return strtotime($a[$sort_by]) >= strtotime($b[$sort_by]) ? -1 : 1;
		});

		// делаю массив плоским
		$data = [];
		foreach ($this->data as $r) $data[] = array_values($r);

		// заголовки таблицы
		array_unshift( $data, [
			'Дата',
			'Тип операции',
			'Тип бумаги',
			'Имя бумаги',
			'Тикер',
			'Колво бумаги',
			'Деньги',
			'Валюта',
			'RUB',
			'USD',
			'Ср. цена текущей позы',
			'Ожид. прибыль в валюте позиции'
		]);

		return $data;
	}

	/**
	 * Текущий курс долл
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	private function getUsd() {
		$r = $this->queryTinvestApi("/market/orderbook?figi=BBG0013HGFT4&depth=1");
//		return $r->closePrice;
		return $r->lastPrice;
	}

	/**
	 * Хелпер для работы с массивом профита
	 *
	 * @param $arr
	 * @param $key
	 * @param $val
	 * @param int $start
	 */
	private function add_profit($key, $val, $start = 0) {
		if(!isset($this->profit[$key])) $this->profit[$key] = $start;
		$this->profit[$key] += $val;
	}
}