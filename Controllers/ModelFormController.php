<?php namespace Model\Form\Controllers;

use Model\Core\Controller;
use Model\Core\Model;
use Model\Form\Form;

class ModelFormController extends Controller
{
	public function post()
	{
		try {
			switch ($this->model->getRequest(1)) {
				case 'options':
					$input = Model::getInput();
					if (!isset($input['table'], $input['field'], $input['token']) or empty($input['field']) or empty($input['token']))
						die('Missing data');

					$form = new Form(['model' => $this->model]);
					if (!empty($input['parent'])) {
						$form->add($input['parent']['field'], [
							'type' => 'select',
							'table' => $input['parent']['table'] ?: null,
							'id-field' => $input['parent']['id-field'] ?: 'id',
							'text-field' => $input['parent']['text-field'],
							'separator' => $input['parent']['separator'],
							'order_by' => $input['parent']['order_by'] ?: null,
							'where' => $input['parent']['where'] ?: [],
							'options' => $input['parent']['options'] ?? false,
							'additionals' => $input['parent']['additionals'] ?: [],
						]);
					}

					$form->add($input['field'], [
						'type' => 'select',
						'table' => $input['table'] ?: null,
						'id-field' => $input['id-field'] ?: 'id',
						'text-field' => $input['text-field'],
						'separator' => $input['separator'],
						'order_by' => $input['order_by'] ?: null,
						'where' => $input['where'] ?: [],
						'additionals' => $input['additionals'] ?: [],
						'depending-on' => (!empty($input['parent']) and !empty($input['db-field'])) ? [
							'name' => $input['parent']['field'],
							'db' => $input['db-field'],
						] : null,
					]);

					$token = $form[$input['field']]->makeToken();
					if ($token !== $input['token'])
						throw new \Exception('Invalid token', 401);

					if (isset($input['parent_value']))
						$form[$input['parent']['field']]->setValue($input['parent_value']);

					return [
						'options' => $form[$input['field']]->getFrontendOptions(),
					];
				default:
					throw new \Exception('Unknown action', 404);
			}
		} catch (\Exception $e) {
			$code = $e->getCode();
			if ($code <= 0)
				$code = 500;

			http_response_code($code);
			return [
				'error' => getErr($e),
			];
		}
	}
}
