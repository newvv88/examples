<?php

namespace app\models\methods;

use app\models\entities\Company;
use app\utils\Utilities;
use Yii;
use yii\db\Query;

/**
 * Class CompanyMethods
 * @package app\models\methods
 * @property $template Template|null
 */
class CompanyMethods extends Company {
	
	private $template = null;
	private $catalogues = null;
	
	public function __construct($templateID, $catalogues)
	{
		parent::__construct();
		$this->templateInit($templateID);
		$this->cataloguesInit($catalogues);
	}
	
	private function templateInit($templateID) {
		
		if ($templateID) {
			
			$this->template = TemplateMethods::checkTemplate($templateID, Yii::$app->params['templateRequiredFieldsForMailing']);
		}
	}
	
	private function cataloguesInit($catalogues) {
		
		if (Utilities::isArray($catalogues)) {
			
			$this->catalogues = $catalogues;
		}
	}
	
	public function saveCompaniesToTurn() {
		
//		$companiesForMailing = $this->companiesForMailing();
		$companiesForMailing = [
			[
				'company_id' => 1964,
        'template_id' => 5,
        'send_mark' => 0,
			],
			[
				'company_id' => 1965,
        'template_id' => 5,
				'send_mark' => 0,
			]
		];
		if (Utilities::isArray($companiesForMailing)) {
		
			return $this->turnBatchInsertQuery($companiesForMailing, ['company_id', 'template_id', 'send_mark']);
		}
		return null;
	}
	
	/**
	 * @param array $companiesForMailing
	 * @param array $needleFields
	 */
	private function turnBatchInsertQuery(array $companiesForMailing, array $needleFields) {
			
			return Yii::$app->db
				->createCommand()
				->batchInsert(
					'mail',
					$needleFields,
					$companiesForMailing)
				->execute();
	}
	
	public function companiesForMailing() {
		
		$result = null;
		$companyCommunications = $this->companiesCommunication();
		
		if (Utilities::isArray($companyCommunications) && $this->template) {
			
			foreach ($companyCommunications as $key => $companyCommunication) {
				
				if (Utilities::isArray($companyCommunication)) {
					
					if (Utilities::arrayExists('company_id', $companyCommunication)
						&& Utilities::arrayExists('email', $companyCommunication)) {
						
						$filteredMail = $this->mailExploder($companyCommunication['email']);
						if ($filteredMail) {
							
							$result[$key]['company_id'] = $companyCommunication['company_id'];
							$result[$key]['template_id'] = $this->template->id;
							$result[$key]['send_mark'] = 0;
						}
					}
				}
			}
		}
		return $result;
	}
	
	private function companiesCommunication() {
		
		$uniqueCompanyIDsQueryResult = $this->uniqueCompanyIDsByCats();
		$uniqueCompanyIDs = Utilities::oneLevelArray($uniqueCompanyIDsQueryResult, 'company_id');
		return $this->companiesData($uniqueCompanyIDs, ['communication.email', 'company.id as company_id']);
	}
	
//	/**
//	 * @param $catalogues array
//	 * @return
//	 */
//	public function companyMails() {
//
//		$uniqueCompanyIDsQueryResult = $this->uniqueCompanyIDsByCats();
//		$uniqueCompanyIDs = Utilities::oneLevelArray($uniqueCompanyIDsQueryResult, 'company_id');
//		$companyCommunications = $this->companiesData($uniqueCompanyIDs, ['communication.email', 'company.id as company_id']);
//		$companyMails = Utilities::oneLevelArray($companyCommunications, 'email');
//		$companyMails = ['novikovvvia@gmail.com, ne_novikov@mail.comercione,', 'new8_8@mail.ru'];
//		$filteredCompanyMails = $this->mailCleaner($companyMails);
//		return $filteredCompanyMails;
//	}
	
	private function mailCleaner($mails) {
		
		$result = null;
		if ($mails && is_array($mails)) {
			
			$result = $this->onlyOneMail($mails);
			$result = $this->filter($result);
		}
		return $result;
	}
	
	private function filter($mails) {
		
		return $mails && is_array($mails) ? array_filter($mails) : null;
	}
	
	private function onlyOneMail($mails) {
		
		if ($mails && is_array($mails)) {
			
			foreach ($mails as $key => $mailFieldValue) {
				
				$mails[$key] = $this->mailExploder($mailFieldValue);
			}
		}
		return $mails;
	}
	
	public function mailExploder($mailFieldValue) {
		
		$result = null;
		if ($mailFieldValue) {
			
			$mails = explode(',', $mailFieldValue);
			if ($mails && is_array($mails)) {
				
				foreach ($mails as $mail) {
					
					$validMail = $this->isValidMail($mail);
					if ($validMail) {
						
						return $validMail;
					}
				}
			}
		}
		return null;
	}
	
	private function isValidMail($mail) {
		
		if ($mail) {
			
			$trimmedMail = $this->spaceReplacer($mail);
			return filter_var($trimmedMail, FILTER_VALIDATE_EMAIL);
		}
		return null;
	}
	
	private function spaceReplacer($text) {
		
		return $text
			? preg_replace('/\s+/', '', $text)
			: null;
	}
	
	private function companiesData($companyIDs, array $needleParams = []) {
		
		$result = null;
		if ($companyIDs && is_array($companyIDs)) {
			
			$query = new Query();
			$result = $query
				->select($needleParams)
				->from(['company'])
				->leftJoin('communication', 'communication.id=company.communication_id')
				->where(['in', 'company.id', $companyIDs])
				->distinct()
				->all();
		}
		return $result;
	}
	
	private function uniqueCompanyIDsByCats() {
		
		$result = null;
		if ($this->catalogues && is_array($this->catalogues)) {
			
			$query = new Query();
			$result = $query
				->select(['company_id'])
				->from(['company_to_catalogue'])
				->where(['in', 'catalogue_id', $this->catalogues])
				->distinct()
				->all();
		}
		return $result;
	}
	
	
}