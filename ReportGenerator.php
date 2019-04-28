<?php

namespace app\models;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\base\Model;
use yii\db\Query;

/**
 * This is the model class for table "report".
 *
 * @property string $start_date
 * @property string $end_date
 * @property integer $project_id
 */

class ReportGenerator extends Model {

	private $totalSum = 0;
	private $fileName;
	private $filePath;

	public static $requiredPostParams = array(
		'project_id',
		'start_date',
		'end_date',
	);

	private static $requiredDataParams = array(
		'log_time',
		'task_id',
		'name',
		'link',
	);

	private $styleArray = [
		'font' => [
			'bold' => true,
		],
		'borders' => [
			'allBorders' => [
				'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
			],
		],
		'fill' => [
			'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
			'startColor' => [
				'argb' => 'F5F5F5',
			],
		],
	];

	public function __construct(array $config = [])
	{
		parent::__construct($config);
		$this->fileName = $this->fileNameBuilder($_POST['project_id']);
		$this->filePath = '../reports/'.$this->fileName.'.xlsx';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['start_date', 'end_date', 'project_id'], 'required'],
			[['project_id'], 'integer'],
			[['start_date', 'end_date'], 'string', 'max' => 512],
		];
	}

	public function generate() {

		$timeLog = $this->getLogs($_POST['project_id'], $_POST['start_date'], $_POST['end_date']);
		$taskLogTime =  $this->calculateTaskLogTime($timeLog);
		$this->excelFileGenerator($taskLogTime);
		$this->downloadFile($this->filePath);
	}

	private function getLogs($projectID, $startDate, $endDate) {

		$result = null;
		if ($projectID && $startDate && $endDate) {

			$query = new Query();
			$result = $query
				->select([
					'time_log.log_time',
					'time_log.task_id',
					'task.name',
					'task.link',
					'task.description'
				])
				->from(['time_log'])
				->innerJoin('task', 'time_log.task_id = task.id')
				->where(['task.project_id' => $projectID])
				->andWhere(['between', 'date', $startDate, $endDate])
				->all();
		}
		return $result;
	}

	private function fileNameBuilder($projectID) {

		$result = date('d-m-Y');
		if ($projectID) {

			$project = Project::findOne(['id' => $projectID]);
			if ($project) {

				$result = "{$project->name}_$result";
			}
		}
		return $result;
	}

	private function excelFileGenerator($reportData) {

//		print_r($reportData); die;
		if ($reportData && is_array($reportData)) {

			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$counter = 2;

			$sheet->setCellValue('A1', 'Таск');
			$sheet->setCellValue('B1', 'Описание');
			$sheet->setCellValue('C1', 'Время');
			$sheet->getStyle('A1:C1')->applyFromArray($this->styleArray);

			foreach ($reportData as $reportDatum) {

				$sheet->setCellValue('A' . $counter, '=Hyperlink("' . $reportDatum['link'] . '","' . $reportDatum['name'] . '")');
				$sheet->setCellValue('B' . $counter, $reportDatum['description']);
				$sheet->setCellValue('C' . $counter, Common::hoursMinutes($reportDatum['task_time']));
				$this->totalSum += $reportDatum['task_time'];
				$counter++;
			}

			$totalRow = $counter+2;
			$sheet->setCellValue('A' . $totalRow, 'Всего');
			$sheet->setCellValue('C' . $totalRow, Common::hoursMinutes($this->totalSum));
			$sheet->getStyle("A$totalRow:C$totalRow")->applyFromArray($this->styleArray);

			$writer = new Xlsx($spreadsheet);
			$writer->save($this->filePath);
		}
	}

	private function calculateTaskLogTime($timeLogData) {

		$result = array();

		if ($timeLogData && is_array($timeLogData)) {

//			if (self::checkRequiredParams(self::$requiredDataParams, $timeLogData)) {

				foreach ($timeLogData as $logDatum) {

					$result[$logDatum['task_id']]['task_time'] += $logDatum['log_time'];
					$result[$logDatum['task_id']]['name'] = $logDatum['name'];
					$result[$logDatum['task_id']]['link'] = $logDatum['link'];
					$result[$logDatum['task_id']]['description'] = $logDatum['description'];
				}
//			}
		}
		return $result;

	}

	public static function checkRequiredParams($params, $data) {

		if ($params) {

			foreach ($params as $param) {

				if (!Common::arrayExistsKeyAndValue($param, $data)) {

					return false;
				}
			}
		}
		return true;
	}

	private function downloadFile($path) {

		if	($path && file_exists($path)) {

			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.basename($path));
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: ' . filesize($path));
			ob_clean();
			flush();
			readfile($path);
			exit;
		}
	}

	public function saveReport() {

		echo "fdjfjkddfh";
		$report = new Report();
		$report->date = date('Y-m-d');
		$report->link = $this->fileName.'.xlsx';
		$report->save();
	}
}