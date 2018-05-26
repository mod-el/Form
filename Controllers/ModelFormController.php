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

		$toHash = $arr;
		unset($toHash['name']); // Name can be dinamically assigned by javascript, cannot rely on it
		$toHash = json_encode($toHash) . $formToken;

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

		$fields = array_unique(array_merge(array_merge(
			[$arr['id-field']],
			$arr['text-field']
		), $arr['additional-fields']));

		$q = $this->model->_Db->select_all($arr['table'], $where, [
			'fields' => $fields,
			'order_by' => $orderBy,
			'stream' => true,
		]);

		$return = [
			[
				'id' => '',
				'text' => '',
				'additional-fields' => [],
			],
		];
		foreach ($arr['additional-fields'] as $k)
			$return[0]['additional-fields'][$k] = '';

		foreach ($q as $row) {
			$text = [];
			foreach ($arr['text-field'] as $f)
				$text[] = $row[$f];
			$opt = [
				'id' => $row[$arr['id-field']],
				'text' => implode(' ', $text),
				'additional-fields' => [],
			];
			foreach ($arr['additional-fields'] as $k)
				$opt['additional-fields'][$k] = $row[$k];
			$return[] = $opt;
		}

		$this->model->sendJSON($return);
	}
}
