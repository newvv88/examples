<?php

namespace app\models\methods;

use app\models\entities\Template;
use app\models\entities\TemplateToCatalogue;
use app\utils\Utilities;
use Yii;
use yii\web\NotFoundHttpException;

class TemplateMethods extends Template {
	
	public static function saveTemplateCatalogue($templateID) {
		
		$catalogues = Utilities::arrayExists('catalogues', $_POST)
			? $_POST['catalogues']
			: null;
		
		if ($templateID && $catalogues && is_array($catalogues)) {
			
			foreach ($catalogues as $catalogueID) {
				
				$dbTransaction = Yii::$app->db->beginTransaction();
				$templateToCatalogue = new TemplateToCatalogue();
				$templateToCatalogue->catalogue_id = $catalogueID;
				$templateToCatalogue->template_id = $templateID;
				$templateToCatalogue->save()
					? $dbTransaction->commit()
					:$dbTransaction->rollBack();
			}
		}
	}
	
	public static function deleteTemplateCatalogues($templateID) {
		
		if ($templateID) {
			
			return TemplateToCatalogue::deleteAll(['template_id' => $templateID]);
		}
		return null;
	}
	
	public static function selectedCatalogs($templateID) {
		
		$result = [];
		if ($templateID) {
			
			$templateCatalogues = TemplateToCatalogue::find()
				->where(['template_id' => $templateID])
				->all();
			if ($templateCatalogues) {
				
				$result = self::generateSelectedList($templateCatalogues);
			}
		}
		return $result;
	}
	
	/**
	 * @param $catalogues TemplateToCatalogue[]
	 */
	private static function generateSelectedList($catalogues) {
		
		$result = [];
		if ($catalogues) {
			
			foreach ($catalogues as $catalogue) {
				
				$result[] = $catalogue->catalogue_id;
			}
		}
		return $result;
	}
	
	public static function checkTemplate($templateID, $requiredFields) {
		
		if ($templateID && Utilities::isArray($requiredFields)) {
			
			if (($template = Template::findOne($templateID)) !== null) {
				
				Utilities::checkRequiredFields($requiredFields, $template);
				return $template;
			} else {
				throw new NotFoundHttpException('The requested template does not exist.');
			}
		}
		throw new NotFoundHttpException('There is enough data to check.');
	}
}