<?php namespace Model\Form\Controllers;

use Model\Core\Controller;

class ModelFormController extends Controller
{
	public function post()
	{
		try {
			switch ($this->model->getRequest(1)) {
				case 'options':
					if (!isset($_POST['element'], $_POST['table'], $_POST['field'], $_POST['token']) or empty($_POST['field']) or empty($_POST['token']))
						die('Missing data');

					$token = [
						'table' => $_POST['table'] ?: null,
						'element' => $_POST['element'] ?: null,
						'field' => $_POST['field'],
						'additionals' => $_POST['additionals'] ?: '[]',
						'token' => $this->model->_RandToken->getToken('Form'),
					];

					if (sha1(json_encode($token)) !== $_POST['token'])
						throw new \Exception('Invalid token', 401);

					$dummy = $this->model->_ORM->create(trim($_POST['element']) ?: 'Element', [
						'table' => $_POST['table'],
					]);

					if (isset($_POST['parent_field'], $_POST['parent_value']))
						$dummy->update([$_POST['parent_field'] => $_POST['parent_value']]);

					$form = $dummy->getForm();

					if (!$form[$_POST['field']])
						throw new \Exception('Invalid field');

					if (!in_array($form[$_POST['field']]->options['type'], ['radio', 'select', 'instant-search']))
						throw new \Exception('Provided field cannot have options');

					$form[$_POST['field']]->options['additional-fields'] = json_decode($token['additionals'], true, 512, JSON_THROW_ON_ERROR);

					return [
						'options' => $form[$_POST['field']]->getFrontendOptions(),
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
