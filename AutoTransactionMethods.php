<?php

namespace app\models;

use app\controllers\SiteController;
use DateInterval;
use DateTime;
use Yii;
use yii\db\Exception;

class AutoTransactionMethods extends AutoTransaction {

	private $requiredAutoPayFields = array(
		'creator',
		'project_id',
		'template_id',
		'cause_id'
	);

	private static $requiredFields = array(
		'amount' => 'Amount',
		'currency_id' => 'Currency'
	);

	private $nextDatePattern = array(
		1 => 'P1D',
		2 => 'P1W',
		3 => 'P1M',
		4 => 'P3M',
		5 => 'P1Y',
	);

//	--- AUTOPAY METHODS FOR CONSOLE CONTROLLER ---
/*
 * Changed files to display the username as "System"
 * - this (methods: applyAutoPayRequestData);
 * - views/transactions/index (attribute created_user_id)
 * - models/Slack (slackMessageGenerator(TR_AUTHOR))
 * - TransactionsLogs (methods: transactionLogHTMLGenerator, added userName())
 * */

	private function getTemplatesToPayToday() {

		$toPayToday = AutoTransaction::find()
			->where(['to_create_date' => date('Y-m-d')])
			->all();
		return $toPayToday;
	}

	public function pay() {

		/**@var $toPayToday AutoTransaction[]*/
		$toPayToday = $this->getTemplatesToPayToday();
		if ($toPayToday) {

			foreach ($toPayToday as $newRequestData) {

				if ($newRequestData->active === 1) {

					$this->createRequest($newRequestData);
				}else{

					$this->setNextStartDate($newRequestData);
				}
			}
		}
	}

	private function createRequest($requestData) {

		if ($requestData && $this->checkRequiredAutoPayFields($requestData)) {

			$templateData = $this->getTemplateData($requestData->template_id);
			if ($templateData && $this->checkRequiredTemplateFields($templateData)) {

				$this->newAutoRequest($requestData, $templateData);

			}
		}
	}

	private function checkRequiredAutoPayFields($requestData) {

		if ($this->requiredAutoPayFields && $requestData) {

			foreach ($this->requiredAutoPayFields as $requiredAutoPayField) {

				if (empty($requestData->{$requiredAutoPayField})) {

					return false;
				}
			}
		}
		return true;
	}

	private function getTemplateData($id) {

		if ($id) {

			return Template::find()
				->select(['amount', 'currency_id', 'comment', 'partner', 'counterparty_id'])
				->where(['id' => $id])
				->one();
		}
		return null;
	}

	private function checkRequiredTemplateFields($templateData) {

		if (self::$requiredFields && $templateData) {

			foreach (self::$requiredFields as $requiredTemplateKey => $requiredTemplateValue) {

				if (empty($templateData->{$requiredTemplateKey})) {

					return false;
				}
			}
		}
		return true;
	}

	private function newAutoRequest($requestData, $templateData) {

		$transaction = Yii::$app->db->beginTransaction();
		try{

			$newAutoRequest = new Transactions();
			$this->applyAutoPayRequestData($newAutoRequest, $requestData);
			$this->applyTemplateRequestData($newAutoRequest, $templateData);
			$this->applyDefaultRequestValues($newAutoRequest);
			$this->disableAfterSaveLogger($newAutoRequest);
//			$newAutoRequest->save();
			if ($newAutoRequest->save()) {
				$this->saveLogFromConsole($newAutoRequest);
				$transaction->commit();

				Slack::sendMessage($newAutoRequest, 'review', false, true);
				$this->setNextStartDate($requestData);
			}

			return true;
		} catch (Exception $e) {

			echo $e->getMessage();
			$transaction->rollBack();
			return false;
		}
	}

	/**
	 * @param $newAutoRequest Transactions
	 * @param $requestData AutoTransaction
	 */
	private function applyAutoPayRequestData($newAutoRequest, $requestData) {

		if ($newAutoRequest && $requestData) {

			$newAutoRequest->project_id = $requestData->project_id;
			$newAutoRequest->created_user_id = 0;
			$newAutoRequest->cause_id = $requestData->cause_id;
		}
	}

	/**
	 * @param $newAutoRequest Transactions
	 * @param $templateData Template
	 */
	private function applyTemplateRequestData($newAutoRequest, $templateData) {

		if ($newAutoRequest && $templateData) {

			$newAutoRequest->amount = $templateData->amount;
			$newAutoRequest->amount_value = -$templateData->amount;
			$newAutoRequest->currency_id = $templateData->currency_id;
			$newAutoRequest->counterparty_id = $templateData->counterparty_id;
//			$newAutoRequest->paymentDetails = $templateData->partner;
			$newAutoRequest->comment = $templateData->comment;
		}
	}

	/**
	 * @param $newAutoRequest Transactions
	 */
	private function applyDefaultRequestValues($newAutoRequest) {

		$newAutoRequest->cause_type_id = 2;
		$newAutoRequest->priority_id = 2;
		$newAutoRequest->status_id = 6;
		$newAutoRequest->date = date("Y-m-d H:i:s");
		$newAutoRequest->request = true;
	}

	/**
	 * @param $model LoggableModel
	 */
	public function disableAfterSaveLogger($model) {

		$model->disableLoggerUseCarefully = true;
	}

	/**
	 * @param $model Transactions
	 */
	public function saveLogFromConsole($model) {

		$log = new TransactionsLogs();
		$log->transaction_id = $model->id;
		$log->user_id = $model->created_user_id;
		$log->status_id = $model->status_id;
		$log->date_time = $model->date;
		$this->disableAfterSaveLogger($log);

		$log->save(false);
	}

	/**
	 * @param $requestData AutoTransaction
	 */
	private function setNextStartDate($requestData) {

		if ($requestData) {

			if ($requestData->period_id === 1 && !empty($requestData->schema_id)) {

				$requestData->to_create_date = $this->nextDateCalculator($requestData->to_create_date, $requestData->schema_id);
				$requestData->save();
			}
		}
	}

	private function nextDateCalculator($date, $schemaID) {

		if ($date) {

			$date = new DateTime($date);
			$schemaPattern = $this->nextDatePattern[$schemaID];
			$date->add(new DateInterval($schemaPattern)); // P1D means a period of 1 day
			return $date->format('Y-m-d');
		}
		return null;
	}
	// --- AUTOPAY METHODS FOR CONSOLE CONTROLLER ---

	// --- CHECK ACCESS TO CRATE AUTOPAY ---
	public static function autopayCreatePrepare($templateID) {
		return self::getEmptyRequiredFields($templateID);
	}

	private static function getEmptyRequiredFields($templateID) {

		$emptyRequiredFields = self::checkTemplateFields($templateID);
		if (!empty($emptyRequiredFields)) {

			return json_encode($emptyRequiredFields);
		}
		return null;
	}

	private static function checkTemplateFields($templateID) {

		$result = array();

		if ($templateID) {

			$template = Template::findOne($templateID);

			if ($template) {

				foreach (self::$requiredFields as $requiredFieldKey => $requiredFieldName) {

					if (empty($template->{$requiredFieldKey})) {

						$result[] = $requiredFieldName;
					}
				}
			}

			$templateCauses = self::getTemplateCausesQuery($templateID);
			if (!$templateCauses) {

				$result[] = 'Expense Cause';
			}
		}
		return $result;
	}
	// --- CHECK ACCESS TO CRATE AUTOPAY ---

	// --- GET TEMPLATE CAUSES ---
	public static function getTemplateCauses($templateID) {

		if ($templateID) {
			$templateCausesResultQuery = self::getTemplateCausesQuery($templateID);
			$templateCauses = self::prettyArrayBuilder($templateCausesResultQuery);

			if ($templateCauses) {

				return self::templateCausesAnswerBuilder($templateCauses);
			}
		}
		return null;
	}

	public static function getTemplateCausesQuery($templateID) {

		if ($templateID) {

			$query = "SELECT causes.id as id, causes.title as title FROM template_causes_cross as tcc
                  LEFT JOIN causes ON tcc.cause_id = causes.id
                  WHERE tcc.template_id = " . $templateID .
				"AND causes.cause_type_id = 2";

			return Yii::$app->db->createCommand($query)->queryAll();
		}
		return null;
	}

	private static function prettyArrayBuilder($templateCauses) {

		$result	= array();
		if ($templateCauses) {

			foreach ($templateCauses as $templateCause) {

				$result[$templateCause['id']] = $templateCause['title'];
			}
		}
		return $result;
	}

	private static function templateCausesAnswerBuilder($templateCauses) {

		if ($templateCauses) {

			$answerType = self::defineAnswerType($templateCauses);
			switch ($answerType) {

				case 'input':
					return self::generateInput($templateCauses);
					break;
				case 'select':
					return self::generateSelect($templateCauses);
					break;
				default:
					return null;
			}
		}
		return null;
	}

	private static function generateInput($templateCauses) {

		$result = null;
		if ($templateCauses) {

			foreach ($templateCauses as $templateCauseKey => $templateCause) {

				$result = "<input type='hidden' id='autotransaction-cause_id' class='autopay-cause-input' name='AutoTransaction[cause_id]' value='$templateCauseKey'>";
			}
		}
		return $result;
	}

	private static function generateSelect($templateCauses) {

		$result = null;
		if ($templateCauses) {

			$result['options'] = '<option value=""></option>';
			$result['uls'] = '<input class="select-search">';
			foreach ($templateCauses as $templateCauseKey => $templateCause) {

//				$result['options'] .= "<input type='text' id='autotransaction-cause_id' name='AutoTransaction[cause_id]' value='$templateCauseKey'>";
				$result['options'] .= "<option value='$templateCauseKey'>$templateCause</option>";
				$result['uls'] .= "<li value='$templateCauseKey'>$templateCause</li>";
			}
		}
		return json_encode($result);
	}

	private static function defineAnswerType($templateCauses) {

		if ($templateCauses && is_array($templateCauses)) {

			if (count($templateCauses) > 1) {

				return 'select';
			}
			elseif (count($templateCauses) == 1) {

				return 'input';
			}
		}
		return null;
	}
	// --- GET TEMPLATE CAUSES END ---

	// --- CHANGE AUTOPAY STATUS ---

	public static function changeAutoPayActiveStatus($autopayID) {

		if ($autopayID) {

			$autoTransaction = self::findModel($autopayID);
			if ($autoTransaction) {

				return self::changeActiveStatus($autoTransaction);
			}
		}
		return false;
	}
	
	/**
	 * @param $model AutoTransaction
	 * @return bool|int
	 */
	private static function changeActiveStatus($model) {

		if ($model) {

			$changed = false;
			if ($model->active === 1) {
				$model->active = 0;
				$changed = true;
			}
			elseif ($model->active === 0) {
				$model->active = 1;
				$changed = true;
			}
			elseif ($model->active !== 0 && $model->active !== 1) {
				$model->active = 0;
				$changed = true;
			}
			if ($changed && $model->save()) {

				return $model->active;
			}
		}
		return false;
	}

	private static function findModel($id)
	{
		return AutoTransaction::findOne(['id' => $id, 'project_id' => SiteController::getCurrentProjectId()]);

	}
	// --- CHANGE AUTOPAY STATUS END ---

	// --- TOTAL SUM OF AUTOPAYMENTS ---

	public static function transactionsAmountSumByCurrency($providerModels, $columnName) {

		$currencies = Currency::findAllCurrencies();
		$transactionsAmountSum = self::getTransactionsAmountSum($providerModels, $columnName);
		$result = array();
		if ($transactionsAmountSum) {

			foreach ($transactionsAmountSum as $key => $sum) {

				$result['amount_result'] .= "<div class='footer-total footer-total-right footer-total--". ($sum < 0 ? 'red' : 'green') ."'>" . abs($sum) . "</div>";
				$result['currency_result'] .= "<div class='footer-total footer-total-left footer-total--green'>{$currencies[$key]['title']}</div>";
			}
		}
		return $result;
	}

	/**
	 * @param $providerModels
	 * @param $columnName
	 * @return null
	 */
	public static function getTransactionsAmountSum($providerModels, $columnName) {

		$result = null;
		if ($providerModels) {

			foreach ($providerModels as $providerModel) {
				$result[$providerModel->currency_id] += $providerModel->{$columnName};
			}
		}
		return $result;
	}

	private static function generate($providerModel, $columnName) {

		return array_reduce(explode('.', $columnName), function ($providerModel, $method) {
			return $providerModel->{$method};
		}, $providerModel);
	}
	// --- TOTAL SUM OF AUTOPAYMENTS END ---
}