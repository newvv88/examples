<?php

namespace app\models;

use app\controllers\SiteController;
use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;

class Common extends Model {

	public static $rus=array('А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я','а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',' ');
	
	public static $lat=array('a','b','v','g','d','e','e','gh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','c','ch','sh','sch','y','y','y','e','yu','ya','a','b','v','g','d','e','e','gh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','c','ch','sh','sch','y','y','y','e','yu','ya',' ');
	
	public static function parseFloat($number) {

		$result = null;
		if ($number) {

			$result = str_replace(',', '.', $number);
			$result = floatval($result);
		}
		return $result;
	}

	public static function isAsyncPostRequest() {

		if (!Yii::$app->user->isGuest && Yii::$app->request->isAjax && Yii::$app->request->post()) {

			return true;
		}
		return false;
	}

	public static function postExists($key) {

		if (array_key_exists($key, $_POST) && !empty($_POST[$key])) {

			return true;
		}
		return false;
	}

	public static function arrayExists($key, $array) {

		if ($array && is_array($array)) {

			if (array_key_exists($key, $array) && !empty($array[$key])) {

				return true;
			}
		}
		return false;
	}

	private static function callChain($providerModel, $chain) {

		return array_reduce(explode('.', $chain), function ($providerModel, $method) {
			return $providerModel->{$method};
		}, $providerModel);
	}
	
	/**
	 * @param $requiredFields array
	 * @param $data null|array|object|ActiveRecord
	 * @return bool
	 */
	public static function checkRequiredFields($requiredFields, $data) {
		
		if ($requiredFields && is_array($requiredFields) && $data) {
			
			foreach ($requiredFields as $requiredField) {
				
				if (is_array($data)) {
					
					if (empty($data[$requiredField])) {
						
						return false;
					}
				}
				elseif (is_object($data)) {
					
					if (empty($data->{$requiredField})) {
						
						return false;
					}
				}
			}
			return true;
		}
		return false;
	}

	// --- TOTAL SUM ---

	public static function amountSumByCurrency($providerModels, $currencyChain, $amountChain) {

		$currencies = Currency::findAllCurrencies();
		$transactionsAmountSum = self::getAmountSum($providerModels, $currencyChain, $amountChain);

		$result = array();
		if ($transactionsAmountSum) {

			foreach ($transactionsAmountSum as $key => $sum) {

				$result['amount_result'] .= "<div class='footer-total footer-total-right footer-total--". ($sum < 0 ? 'negative' : 'positive') ."'>" . abs($sum) . "</div>";
				$result['currency_result'] .= "<div class='footer-total footer-total-left '>{$currencies[$key]['title']}</div>";
			}
		}
		return $result;
	}

	/**
	 * @param $providerModels
	 * @param $columnName
	 * @return null
	 */
	public static function getAmountSum($providerModels, $currencyChain, $amountChain) {

		$result = null;
		if ($providerModels) {

			foreach ($providerModels as $providerModel) {
//				echo self::generate($providerModel, $columnName); die;
				$result[self::callChain($providerModel, $currencyChain)] += self::callChain($providerModel, $amountChain);
			}
		}
		return $result;
	}
	// --- TOTAL SUM  END ---
}