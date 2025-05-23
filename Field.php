<?php namespace Model\Form;

use Model\Core\Core;
use Model\Db\Db;

class Field
{
	public array $options = [];
	protected Core $model;
	protected ?Form $form;
	public array $depending_children = [];
	private array $additionalFields = [];

	/**
	 * Field constructor.
	 *
	 * @param string $name
	 * @param array $options
	 */
	public function __construct(string $name, array $options = [])
	{
		$this->options = array_merge([
			'name' => $name,
			'field' => $name,

			'type' => false,
			'form' => null,
			'model' => null,
			'nullable' => null,
			'default' => false,
			'value' => false,
			'multilang' => false,
			'depending-on' => null,
			'maxlength' => false,

			'table' => false,
			'id-field' => 'id',
			'text-field' => null,
			'separator' => ' ',
			'where' => [],
			'order_by' => false,
			'if-null' => '',
			'additional-fields' => [],

			'attributes' => [],
			'label-attributes' => [],
			'mandatory' => false,
			'required' => false,
			'label' => false,

			'options' => false,
			'token' => false,
		], $options);

		$this->model = $this->options['model'];
		$this->form = $this->options['form'];

		if ($this->options['multilang'] and !class_exists('\\Model\\Multilang\\Ml'))
			$this->options['multilang'] = false;

		if ($this->options['value'] === false and $this->options['default'] !== false)
			$this->options['value'] = $this->options['default'];
		if ($this->options['value'] !== false)
			$this->setValue($this->options['value']);

		if ($this->options['depending-on']) {
			if (!in_array($this->options['type'], ['radio', 'select', 'instant-search']))
				$this->model->error('The field cannot have "depending on" option');

			if (!is_array($this->options['depending-on'])) {
				$this->options['depending-on'] = [
					'name' => $this->options['depending-on'],
					'db' => $this->options['depending-on'],
				];
			}
		}

		if ($this->options['mandatory']) // Backward compatibility
			$this->options['required'] = true;

		if (in_array($this->options['type'], ['instant-search', 'select', 'radio']) and !$this->options['nullable'])
			$this->options['required'] = true;

		if ($this->form) {
			$current_dataset = $this->form->getDataset();
			if ($this->options['depending-on'] and isset($current_dataset[$this->options['depending-on']['name']]))
				$current_dataset[$this->options['depending-on']['name']]->depending_children[] = $this->options['name'];

			foreach ($current_dataset as $fName => $field) {
				if ($field->options['depending-on'] === $this->options['name'] and !in_array($fName, $this->depending_children))
					$this->depending_children[] = $fName;
			}
		}
	}

	/**
	 * When cloning, "depending" relationships get wiped
	 */
	public function __clone()
	{
		$this->depending_children = [];
		$this->options['depending-on'] = null;
	}

	/**
	 * @param Form $form
	 */
	public function setForm(Form $form): void
	{
		$this->options['form'] = $form;
		$this->form = $form;
	}

	/**
	 * Sets the current value
	 *
	 * @param mixed $v
	 * @param string|null $lang
	 */
	public function setValue(mixed $v, ?string $lang = null): void
	{
		if ($this->options['multilang']) {
			if ($lang) {
				$this->options['value'][$lang] = $v;
			} else {
				if (is_array($v)) {
					$this->options['value'] = $v;
				} else {
					if (!is_array($this->options['value']))
						$this->options['value'] = [];
					$this->options['value'][\Model\Multilang\Ml::getLang()] = $v;
				}
			}
		} else {
			$this->options['value'] = $v;
		}
	}

	/**
	 * Returns the current value
	 * ("password" type fields are never showed in the form)
	 * For multilang fields, if no $lang is passed, the current one will be used; if false is passed, an array with all languages will be returned
	 *
	 * @param string|bool|null $lang
	 * @return mixed
	 */
	public function getValue(string|bool|null $lang = null): mixed
	{
		if ($this->options['type'] === 'password')
			return null;

		if ($this->options['multilang']) {
			if ($lang === false) {
				return $this->options['value'];
			} else {
				if ($lang === null)
					$lang = \Model\Multilang\Ml::getLang();
				if (isset($this->options['value'][$lang]))
					return $this->options['value'][$lang];
				else
					return null;
			}
		} else {
			return $this->options['value'];
		}
	}

	/**
	 * Some types of field can return different values if they are needed for JavaScript
	 *
	 * @param string|bool $lang
	 * @return mixed
	 */
	public function getJsValue($lang = null)
	{
		return $this->getValue($lang);
	}

	/**
	 * Returns the current text value (the text content of a select, for example)
	 *
	 * @param array $options
	 * @return string
	 */
	public function getText(array $options = []): string
	{
		$options = array_merge([
			'priceFormat' => 'vd',
			'dateFormat' => 'd/m/Y',
			'lang' => null,
			'preview' => false,
		], $options);

		$value = $this->getValue($options['lang']);
		if ($value === null)
			return '';

		switch ($this->options['type']) {
			case 'select':
			case 'radio':
				$this->loadSelectOptions();
				return $this->getTextFromSelect($this->options['options'], $value) ?: '';

			case 'date':
				$data = $value ? date_create($value) : false;
				return $data ? $data->format($options['dateFormat']) : '';

			case 'price':
				switch ($options['priceFormat']) {
					case 'vd':
						return number_format($value, 2, ',', '.') . '€';

					case 'vp':
						return '€ ' . number_format($value, 2, ',', '.');

					case 'pd':
						return number_format($value, 2, '.', '') . '€';

					case 'pp':
						return '€ ' . number_format($value, 2, '.', '');
				}

			case 'checkbox':
				return $this->getValue() ? 'Sì' : 'No';

			case 'point':
				return $value[1] . ' - ' . $value[0];

			default:
				return (string)$value;
		}
	}

	/**
	 * A group of options can be passed in selects, so I have to recursively iterate through options to find the correct value
	 *
	 * @param array $options
	 * @param $value
	 * @return string|null
	 */
	private function getTextFromSelect(array $options, $value): ?string
	{
		foreach ($options as $id => $opt) {
			if (is_array($opt)) {
				$text = $this->getTextFromSelect($opt, $value);
				if ($text !== null)
					return $text;
			} else {
				if ((string)$id === (string)$value)
					return $opt ?: '';
			}
		}

		return null;
	}

	/**
	 * In case of a select, this will fills the "options" array (called when requested)
	 *
	 * @param bool $ignoreDepending
	 * @return bool
	 */
	public function loadSelectOptions(bool $ignoreDepending = false): bool
	{
		if ($this->options['options'] !== false)
			return true;

		$options = [];

		if ($this->options['table'] and $this->options['text-field']) {
			$qry_options = [
				'stream' => true,
			];

			if ($this->options['order_by'] !== false) {
				$qry_options['order_by'] = $this->options['order_by'];
			} else {
				if (is_array($this->options['text-field']))
					$qry_options['order_by'] = $this->options['text-field'];
				elseif (is_string($this->options['text-field']))
					$qry_options['order_by'] = [$this->options['text-field']];
			}

			$where = $this->options['where'];
			if ($this->form and !$ignoreDepending and $this->options['depending-on'] and isset($this->form->getDataset()[$this->options['depending-on']['name']])) {
				$parentValue = $this->form->getDataset()[$this->options['depending-on']['name']]->getValue();
				if ($parentValue === null) {
					$tableModel = Db::getConnection()->getTable($this->options['table']);
					if (!$tableModel->columns[$this->options['depending-on']['db']]['null'])
						$parentValue = 0;
				}
				$where[$this->options['depending-on']['db']] = $parentValue;
			}

			// I only select requested fields, so I can take advantages of eventual db indexes, if any
			if (!is_string($this->options['text-field']) and is_callable($this->options['text-field'])) {
				$qry_options['fields'] = [];
			} elseif (is_array($this->options['text-field'])) {
				$qry_options['fields'] = array_merge(
					[
						$this->options['id-field'],
					],
					$this->options['text-field']
				);
			} else {
				$qry_options['fields'] = [
					$this->options['id-field'],
					$this->options['text-field']
				];
			}
			if ($qry_options['fields'])
				$qry_options['fields'] = array_unique(array_merge($qry_options['fields'], $this->options['additional-fields']));

			$q = \Model\Db\Db::getConnection()->selectAll($this->options['table'], $where, $qry_options);
			foreach ($q as $r) {
				// I take the id field
				$id = $r[$this->options['id-field']];

				// I take the text field(s)
				if (!is_string($this->options['text-field']) and is_callable($this->options['text-field'])) {
					$options[$id] = $this->options['text-field']($r);
				} elseif (is_array($this->options['text-field'])) {
					$multiple_fields = [];
					foreach ($this->options['text-field'] as $tf)
						$multiple_fields[] = $r[$tf];

					$options[$id] = implode($this->options['separator'], $multiple_fields);
				} else {
					$options[$id] = $r[$this->options['text-field']];
				}

				// I take any additional field
				$this->additionalFields[$id] = [];
				foreach ($this->options['additional-fields'] as $k)
					$this->additionalFields[$id][$k] = $r[$k] ?? null;
			}
		}

		$options = ['' => $this->options['if-null']] + $options;

		$this->options['options'] = $options;

		return true;
	}

	/**
	 * Renders the field
	 *
	 * @param array $attributes
	 * @param bool $return
	 * @return string|null
	 */
	public function render(array $attributes = [], bool $return = false): ?string
	{
		if ($this->form and $this->form->options['render-only-placeholders']) {
			$ret = '<div data-fieldplaceholder="' . entities($this->options['name']) . '"></div>';
			if ($return) {
				return $ret;
			} else {
				echo $ret;
				return null;
			}
		}

		$attributes = array_merge($this->options['attributes'], $attributes);

		if (!isset($attributes['name']))
			$attributes['name'] = $this->options['name'];

		$attributes['name'] = $this->wrapName($attributes['name']);

		if ($this->options['maxlength'] !== false and !array_key_exists('maxlength', $attributes))
			$attributes['maxlength'] = $this->options['maxlength'];

		try {
			if ($return)
				ob_start();

			if ($this->options['multilang']) {
				if (!is_array($this->options['value']))
					$this->model->error('Multilang fields needs an array of values!');

				if ($this->options['depending-on'] or $this->depending_children)
					$this->model->error('Multilang fields cannot be depending on another field, nor have children fields');

				$def_lang = $this->model->_Multilang->options['default'];
				$originalName = $attributes['name'];

				echo '<div class="multilang-field-container">';
				foreach (\Model\Multilang\Ml::getLangs() as $lang) {
					echo '<div data-lang="' . $lang . '" style="' . ($lang !== $def_lang ? 'display: none' : '') . '">';
					$attributes['name'] = $originalName . '-' . $lang;
					$attributes['data-lang'] = $lang;
					$attributes['data-multilang'] = $originalName;
					$this->renderWithLang($attributes, $lang);
					echo '</div>';
				}
				echo '<div class="multilang-field-lang-container">';
				echo '<a href="#" onclick="switchFieldLang(this.parentNode.parentNode, \'' . $def_lang . '\'); return false" data-lang="' . $def_lang . '"><img src="' . PATH . 'model/Form/assets/img/langs/' . $def_lang . '.png" alt="" /></a>';
				echo '<div class="multilang-field-other-langs-container">';
				foreach (\Model\Multilang\Ml::getLangs() as $lang) {
					if ($lang === $def_lang)
						continue;
					echo '<a href="#" onclick="switchFieldLang(this.parentNode.parentNode.parentNode, \'' . $lang . '\'); return false" data-lang="' . $lang . '"><img src="' . PATH . 'model/Form/assets/img/langs/' . $lang . '.png" alt="" /></a>';
				}
				echo '</div>';
				echo '</div>';
				echo '</div>';
			} else {
				if (is_array($this->options['value']))
					$this->model->error('Array of values can go only in multilang fields!');

				$this->renderWithLang($attributes);
			}

			if ($return)
				return ob_get_clean();
		} catch (\Exception $e) {
			if ($return)
				ob_clean();

			throw $e;
		}

		return null;
	}

	protected function makeDependingFieldsAttributes(array $attributes): array
	{
		if ($this->form and !empty($this->depending_children)) {
			$formToken = $this->model->_RandToken->getToken('Form');
			$fieldsArr = [];
			foreach ($this->depending_children as $ch) {
				if (!isset($this->form->getDataset()[$ch]))
					continue;
				$field = $this->form->getDataset()[$ch];
				if (!$field->options['depending-on'])
					continue;

				$ch = $this->wrapName($ch);

				$fArr = [
					'name' => $ch,
					'field' => $field->options['depending-on']['db'],
					'table' => $field->options['table'],
					'id-field' => $field->options['id-field'],
					'text-field' => $field->options['text-field'],
					'order_by' => $field->options['order_by'],
					'where' => $field->options['where'],
					'additional-fields' => $field->options['additional-fields'],
				];
				ksort($fArr);

				$toHash = $fArr;
				unset($toHash['name']); // Name can be dinamically assigned by javascript, cannot rely on it
				$toHash = json_encode($toHash) . $formToken;

				$fArr['hash'] = sha1($toHash);
				$fieldsArr[] = $fArr;
			}

			$attributes['data-depending-parent'] = json_encode($fieldsArr);
		}

		return $attributes;
	}

	/**
	 * Actually renders the field with a given language (called as many times as the number of languages by render() method)
	 *
	 * @param array $attributes
	 * @param string|null $lang
	 */
	protected function renderWithLang(array $attributes, ?string $lang = null): void
	{
		if ($this->form and $this->form->options['print']) {
			echo entities($this->getText(['lang' => $lang]), true);
			return;
		}

		$attributes = $this->makeDependingFieldsAttributes($attributes);

		$labelAttributes = $this->options['label-attributes'];

		if ($this->form and $this->form->options['bootstrap']) {
			if (in_array($this->options['type'], ['radio', 'checkbox'])) {
				$inputClass = 'form-check-input';
				$labelAttributes['class'] = isset($labelAttributes['class']) ? $labelAttributes['class'] . ' form-check-label' : 'form-check-label';
			} else {
				$inputClass = 'form-control';
			}

			if (isset($attributes['class'])) {
				if (strpos($attributes['class'], $inputClass) === false)
					$attributes['class'] .= ' ' . $inputClass;
			} else {
				$attributes['class'] = $inputClass;
			}
		}

		if ($this->options['required'] and !isset($attributes['required']))
			$attributes['required'] = '';

		if ($this->options['token'])
			$attributes['data-token'] = $this->makeToken();

		switch ($this->options['type']) {
			case 'textarea':
				echo '<textarea ' . $this->implodeAttributes($attributes) . '>' . entities($this->getValue($lang)) . '</textarea>';
				break;
			case 'radio':
				$this->loadSelectOptions();
				if (isset($attributes['required']) and $attributes['required'] === false)
					unset($attributes['required']);

				$value = $this->getValue($lang);
				foreach ($this->options['options'] as $id => $opt) {
					if ($this->form and $this->form->options['bootstrap'])
						echo '<div class="form-check form-check-inline">';

					if (isset($attributes['name']))
						$attributes['id'] = 'radio-' . $attributes['name'] . '-' . $id;
					$attributes['value'] = $id;
					echo '<input type="radio" ' . $this->implodeAttributes($attributes) . (((string)$id === (string)$value) ? ' checked' : '') . ' />';
					echo ' <label for="' . ($attributes['id'] ?? '') . '" ' . $this->implodeAttributes($labelAttributes) . '>' . entities($opt) . '</label> ';

					if ($this->form and $this->form->options['bootstrap'])
						echo '</div>';
				}
				break;
			case 'select':
				$this->loadSelectOptions();
				if (isset($attributes['required']) and $attributes['required'] === false)
					unset($attributes['required']);

				echo '<select ' . $this->implodeAttributes($attributes) . '>';
				$this->renderSelectOptions($this->options['options'], $this->getValue($lang));
				echo '</select>';
				break;
			case 'date':
				$value = $this->getValue($lang);
				$value = $value ? date_create($value) : '';
				$value = $value ? $value->format('Y-m-d') : '';
				echo '<input type="date" value="' . entities($value) . '" ' . $this->implodeAttributes($attributes) . ' />';
				break;
			case 'checkbox':
				if ($this->form and $this->form->options['bootstrap'])
					echo '<div class="form-check form-check-inline">';

				if (!isset($attributes['id']))
					$attributes['id'] = 'checkbox-' . $attributes['name'];

				$label = isset($attributes['label']) ? $attributes['label'] : $this->getLabel();

				echo '<input type="checkbox" value="1"' . ($this->getValue($lang) ? ' checked' : '') . ' ' . $this->implodeAttributes($attributes) . ' />';

				if (!isset($attributes['hide-label']) and $label)
					echo ' <label for="' . $attributes['id'] . '" ' . $this->implodeAttributes($labelAttributes) . '>' . $label . '</label>';

				if ($this->form and $this->form->options['bootstrap'])
					echo '</div>';
				break;
			default:
				if (!isset($attributes['type']))
					$attributes['type'] = $this->options['type'];

				echo '<input value="' . entities($this->getValue($lang)) . '" ' . $this->implodeAttributes($attributes) . ' />';
				break;
		}
	}

	/**
	 * @param array $options
	 * @param mixed $value
	 */
	private function renderSelectOptions(array $options, mixed $value): void
	{
		foreach ($options as $id => $opt) {
			if (is_array($opt)) {
				echo '<optgroup label="' . entities($id) . '">';
				$this->renderSelectOptions($opt, $value);
				echo '</optgroup>';
			} else {
				$additionals = [];
				if (isset($this->additionalFields[$id])) {
					foreach ($this->additionalFields[$id] as $k => $v) {
						$additionals[] = 'data-' . entities($k) . '="' . entities($v ?: '') . '"';
					}
				}
				echo '<option value="' . $id . '"' . (((string)$id === (string)$value) ? ' selected' : '') . ' ' . implode(' ', $additionals) . '>' . entities($opt) . '</option>';
			}
		}
	}

	/**
	 * @param array $attributes
	 * @return string
	 */
	protected function implodeAttributes(array $attributes): string
	{
		$str_attributes = [];
		foreach ($attributes as $k => $v) {
			$str_attributes[] = $k . '="' . entities($v) . '"';
		}

		return implode(' ', $str_attributes);
	}

	/**
	 * @return string
	 */
	public function getLabel(): string
	{
		return $this->options['label'] !== false ? $this->options['label'] : ucwords(str_replace(['-', '_'], ' ', $this->options['name']));
	}

	/**
	 * @return int
	 */
	public function getMinWidth(): int
	{
		switch ($this->options['type']) {
			case 'password':
				return 180;

			case 'date':
				return 180;

			case 'textarea':
				return 200;

			case 'number':
				return 100;

			default:
				return 200;
		}
	}

	/**
	 * @param array $options
	 * @return int
	 */
	public function getEstimatedWidth(array $options): int
	{
		switch ($this->options['type']) {
			case 'textarea':
				$px = 300;
				break;

			case 'checkbox':
			case 'radio':
				$px = 30 + (strlen($this->getLabel()) * 7);
				break;

			case 'date':
				$px = 300;
				break;

			case 'time':
				$px = 150;
				break;

			case 'number':
				$px = 100;
				break;

			default:
				if ($this->options['maxlength']) {
					$px = $this->options['maxlength'] * 5;
					if ($px > 500)
						$px = 500;
				} else {
					$px = 300;
				}
				break;
		}

		if ($px < $this->getMinWidth())
			$px = $this->getMinWidth();

		return round($px / $options['column-width']);
	}

	/**
	 * @param array $options
	 * @return int
	 */
	public function getEstimatedHeight(array $options): int
	{
		if ($options['one-row'])
			return 1;

		switch ($this->options['type']) {
			case 'ckeditor':
				return 4;

			case 'textarea':
				return 3;

			default:
				return 1;
		}
	}

	/**
	 * @param mixed $data
	 * @return bool
	 */
	public function save(mixed $data = null): bool
	{
		return true;
	}

	/**
	 * @param string|null $lang
	 * @return bool
	 */
	public function delete(?string $lang = null): bool
	{
		return true;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	protected function wrapName(string $name): string
	{
		if ($this->form and $this->form->options['wrap-names'])
			$name = str_replace('[name]', $name, $this->form->options['wrap-names']);
		return $name;
	}

	/**
	 * @return array
	 */
	public function getJavascriptDescription(): array
	{
		$response = [
			'type' => $this->options['type'],
			'label' => $this->getLabel(),
			'required' => $this->options['required'],
			'multilang' => false,
			'attributes' => $this->options['attributes'],
			'label-attributes' => $this->options['label-attributes'],
		];

		if ($this->form and $this->form->options['bootstrap']) {
			if (in_array($this->options['type'], ['radio', 'checkbox'])) {
				$inputClass = 'form-check-input';
				$response['label-attributes']['class'] = isset($response['label-attributes']['class']) ? $response['label-attributes']['class'] . ' form-check-label' : 'form-check-label';
			} else {
				$inputClass = 'form-control';
			}

			if (isset($response['attributes']['class'])) {
				if (strpos($response['attributes']['class'], $inputClass) === false)
					$response['attributes']['class'] .= ' ' . $inputClass;
			} else {
				$response['attributes']['class'] = $inputClass;
			}
		}

		if (in_array($this->options['type'], ['select', 'radio']))
			$response['options'] = $this->getFrontendOptions();

		if (in_array($this->options['type'], ['radio', 'select', 'instant-search'])) {
			$dependingAttributes = $this->makeDependingFieldsAttributes([]);
			if ($dependingAttributes['data-depending-parent'] ?? null)
				$response['attributes']['data-depending-parent'] = $dependingAttributes['data-depending-parent'];
		}

		if ($this->options['maxlength'] !== false and !array_key_exists('maxlength', $response['attributes']))
			$response['attributes']['maxlength'] = $this->options['maxlength'];

		if ($this->options['default'])
			$response['default'] = $this->options['default'];

		if ($this->options['if-null'])
			$response['if-null'] = $this->options['if-null'];

		if ($this->options['multilang'] and class_exists('\\Model\\Multilang\\Ml'))
			$response['multilang'] = \Model\Multilang\Ml::getLangs();

		if ($this->options['token'] or $this->options['depending-on']) {
			$response['reloading_data'] = $this->buildTokenData();
			$response['token'] = $this->makeToken();
		}

		return $response;
	}

	public function getFrontendOptions(): array
	{
		$this->loadSelectOptions();
		$options = [];

		foreach ($this->options['options'] as $id => $optionValue) {
			if ($id === '')
				continue;

			$additionals = [];
			if (isset($this->additionalFields[$id])) {
				foreach ($this->additionalFields[$id] as $k => $v)
					$additionals[$k] = $v;
			}

			$options[] = [
				'id' => $id,
				'text' => $optionValue,
				'additionals' => $additionals,
			];
		}

		return $options;
	}

	private function buildTokenData(): array
	{
		$tokenData = [
			'table' => $this->options['table'],
			'field' => $this->options['name'],
			'id-field' => $this->options['id-field'],
			'text-field' => $this->options['text-field'],
			'separator' => $this->options['separator'],
			'order_by' => $this->options['order_by'] ?: null,
			'where' => $this->options['where'],
			'additionals' => $this->options['additional-fields'],
		];

		if ($this->options['depending-on']) {
			$tokenData['db-field'] = $this->options['depending-on']['db'];

			$current_dataset = $this->form->getDataset();
			if (isset($current_dataset[$this->options['depending-on']['name']])) {
				$parent_field = $current_dataset[$this->options['depending-on']['name']];
				$tokenData['parent'] = [
					'table' => $parent_field->options['table'] ?: null,
					'field' => $parent_field->options['name'],
					'id-field' => $parent_field->options['id-field'],
					'text-field' => $parent_field->options['text-field'],
					'separator' => $this->options['separator'],
					'order_by' => $parent_field->options['order_by'] ?: null,
					'where' => $parent_field->options['where'],
					'options' => !$parent_field->options['table'] ? ($parent_field->options['options'] ?? []) : null,
					'additionals' => $parent_field->options['additional-fields'],
				];
			}
		}

		return $tokenData;
	}

	public function makeToken(): string
	{
		$tokenData = $this->buildTokenData();
		$tokenData['token'] = $this->model->_RandToken->getToken('Form');
		return sha1(json_encode(array_filter($tokenData)));
	}
}
