<?php namespace Model\Form\Controllers;

use Model\Core\Controller;

class ModelFormController extends Controller
{
	public function index()
	{
		if (!isset($_POST['field'], $_POST['v']))
			die('Missing data');

		$arr = json_decode($_POST['field'], true);
		if (!$arr or !isset($arr['field'], $arr['id-field'], $arr['name'], $arr['order_by'], $arr['table'], $arr['text-field'], $arr['where'], $arr['hash']))
			die('Wrong data');

		$givenHash = $arr['hash'];
		unset($arr['hash']);

		ksort($arr);

		$formToken = $this->model->_RandToken->getToken('Form');
		$toHash = json_encode($arr) . $formToken;
		$hash = sha1($toHash);
		if ($hash !== $givenHash)
			die('Unauthorized');

		if (!is_array($arr['text-field']))
			$arr['text-field'] = [$arr['text-field']];

		$where = array_merge($arr['where'], [
			$arr['field'] => $_POST['v'],
		]);

		if ($arr['order_by']) {
			$orderBy = $arr['order_by'];
		} else {
			if (is_array($arr['text-field'])) {
				$orderBy = implode(',', $arr['text-field']);
			} elseif (is_string($arr['text-field'])) {
				$orderBy = $arr['text-field'];
			}
		}

		$q = $this->model->_Db->select_all($arr['table'], $where, [
			'order_by' => $orderBy,
			'stream' => true,
		]);

		$return = [
			[
				'id' => '',
				'text' => '',
			],
		];
		foreach ($q as $row) {
			$text = [];
			foreach ($arr['text-field'] as $f)
				$text[] = $row[$f];
			$return[] = [
				'id' => $row[$arr['id-field']],
				'text' => implode(' ', $text),
			];
		}

		$this->model->sendJSON($return);
	}
}
