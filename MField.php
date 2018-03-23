<?php namespace Model\Form;

class MField
{
	/** @var array */
	public $options = [];
	/** @var \Model\Core\Core */
	protected $model;
	/** @var Form */
	protected $form;

	/**
	 * MField constructor.
	 *
	 * @param string $name
	 * @param array $options
	 * @throws \Model\Core\Exception
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
			'dependsOn' => false,
			'maxlength' => false,

			'table' => false,
			'id-field' => 'id',
			'text-field' => null,
			'where' => [],
			'order_by' => false,
			'if-null' => '',

			'attributes' => [],
			'mandatory' => false,
			'label' => false,

			'options' => false,
		], $options);

		$this->model = $this->options['model'];
		if ($this->model === null)
			throw new \Model\Core\Exception('MField class need a model reference!');
		$this->form = $this->options['form'];

		if ($this->options['multilang'] and !$this->model->isLoaded('Multilang'))
			$this->options['multilang'] = false;

		if ($this->options['value'] === false and $this->options['default'] !== false)
			$this->options['value'] = $this->options['default'];
		if ($this->options['value'] !== false)
			$this->setValue($this->options['value']);
	}

	/**
	 * Sets the current value
	 *
	 * @param mixed $v
	 * @param string $lang
	 */
	public function setValue($v, string $lang = null)
	{
		if ($this->options['multilang']) {
			if ($lang) {
				$this->options['value'][$lang] = $v;
			} else {
				if (is_array($v)) {
					$this->options['value'] = $v;
				} else {
					$this->options['value'][$this->model->_Multilang->lang] = $v;
				}
			}
		} else {
			$this->options['value'] = $v;
		}

		/*if(!empty($this->depending_children)){
			foreach($this->depending_children as $ch){
				if(isset($this->form->dati[$ch]))
					$this->form->dati[$ch]->loadSelectOptions();
			}
		}*/
	}

	/**
	 * Returns the current value
	 * For multilang fields, if no $lang is passed, the current one will be used; if false is passed, an array with all languages will be returned
	 *
	 * @param string|bool $lang
	 * @return mixed
	 */
	public function getValue($lang = null)
	{
		if ($this->options['multilang']) {
			if ($lang === false) {
				return $this->options['value'];
			} else {
				if ($lang === null)
					$lang = $this->model->_Multilang->lang;
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
		], $options);

		$value = $this->getValue($options['lang']);
		if ($value === null)
			return '';

		switch ($this->options['type']) {
			case 'select':
			case 'radio':
				$this->loadSelectOptions();
				return $this->getTextFromSelect($this->options['options'], $value);
				break;
			case 'date':
			case 'datetime':
				$format = $options['dateFormat'];
				if ($this->options['type'] == 'datetime')
					$format .= ' H:i';
				$data = $value ? date_create($value) : false;
				return $data ? $data->format($format) : '';
				break;
			case 'price':
				switch ($options['priceFormat']) {
					case 'vd':
						return number_format($value, 2, ',', '.') . '€';
						break;
					case 'vp':
						return '€ ' . number_format($value, 2, ',', '.');
						break;
					case 'pd':
						return number_format($value, 2, '.', '') . '€';
						break;
					case 'pp':
						return '€ ' . number_format($value, 2, '.', '');
						break;
				}
				break;
			case 'checkbox':
				return $this->getValue() ? 'Sì' : 'No';
				break;
			default:
				return $value;
				break;
		}

		return '';
	}

	/**
	 * A group of options can be passed in selects, so I have to recursively iterate through options to find the correct value
	 *
	 * @param array $options
	 * @param $value
	 * @return bool|string
	 */
	private function getTextFromSelect(array $options, $value)
	{
		foreach ($options as $id => $opt) {
			if (is_array($opt)) {
				$text = $this->getTextFromSelect($opt, $value);
				if ($text !== false)
					return $text;
			} else {
				if ((string)$id === (string)$value)
					return $opt;
			}
		}

		return false;
	}

	/**
	 * In case of a select, this will fills the "options" array (called when requested)
	 *
	 * @return bool
	 */
	private function loadSelectOptions(): bool
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
				if (is_array($this->options['text-field'])) {
					$qry_options['order_by'] = implode(',', $this->options['text-field']);
				} elseif (is_string($this->options['text-field'])) {
					$qry_options['order_by'] = $this->options['text-field'];
				}
			}

			$q = $this->model->_Db->select_all($this->options['table'], $this->options['where'], $qry_options);
			foreach ($q as $r) {
				$id = $r[$this->options['id-field']];

				if (!is_string($this->options['text-field']) and is_callable($this->options['text-field'])) {
					$options[$id] = $this->options['text-field']($r);
				} elseif (is_array($this->options['text-field'])) {
					$multiple_fields = [];
					foreach ($this->options['text-field'] as $tf) {
						$multiple_fields[] = $r[$tf];
					}
					$options[$id] = implode(' ', $multiple_fields);
				} else {
					$options[$id] = $r[$this->options['text-field']];
				}
			}
		}

		if ($this->options['nullable'])
			$options = ['' => $this->options['if-null']] + $options;

		$this->options['options'] = $options;

		return true;
	}

	/**
	 * Renders the field
	 *
	 * @param array $attributes
	 * @param bool $return
	 * @throws \Exception
	 */
	public function render(array $attributes = [], bool $return = false)
	{
		$attributes = array_merge($this->options['attributes'], $attributes);

		if (!isset($attributes['name']))
			$attributes['name'] = $this->options['name'];

		if ($this->options['form'] and $this->options['form']->options['wrap-names']) {
			$attributes['name'] = str_replace('[name]', $attributes['name'], $this->options['form']->options['wrap-names']);
			/*$this->depending_children = array_map(function($n) use($child_el_name){
				return 'ch-'.$n.'-'.$child_el_name;
			}, $this->depending_children);*/
		}

		if ($this->options['maxlength'] !== false and !array_key_exists('maxlength', $attributes))
			$attributes['maxlength'] = $this->options['maxlength'];

		try {
			if ($return)
				ob_start();

			if ($this->options['multilang']) {
				if (!is_array($this->options['value']))
					$this->model->error('Multilang fields needs an array of values!');

				$def_lang = $this->model->_Multilang->options['default'];
				$originalName = $attributes['name'];

				echo '<div class="multilang-field-container" data-name="' . entities($originalName) . '">';
				foreach ($this->model->_Multilang->langs as $lang) {
					echo '<div data-lang="' . $lang . '" style="' . ($lang !== $def_lang ? 'display: none' : '') . '">';
					$attributes['name'] = $originalName . '-' . $lang;
					$attributes['data-lang'] = $lang;
					$attributes['data-multilang'] = $originalName;
					$this->renderWithLang($attributes, $lang);
					echo '</div>';
				}
				echo '<div class="multilang-field-lang-container">';
				echo '<a href="#" onclick="switchFieldLang(\'' . entities($originalName) . '\', \'' . $def_lang . '\'); return false" data-lang="' . $def_lang . '"><img src="' . PATH . 'model/Form/files/img/langs/' . $def_lang . '.png" alt="" /></a>';
				echo '<div class="multilang-field-other-langs-container">';
				foreach ($this->model->_Multilang->langs as $lang) {
					if ($lang === $def_lang)
						continue;
					echo '<a href="#" onclick="switchFieldLang(\'' . entities($originalName) . '\', \'' . $lang . '\'); return false" data-lang="' . $lang . '"><img src="' . PATH . 'model/Form/files/img/langs/' . $lang . '.png" alt="" /></a>';
				}
				echo '</div>';
				echo '</div>';
				echo '</div>';
			} else {
				if (is_array($this->options['value']))
					$this->model->error('Array of values can go only in multilang fields!');

				$this->renderWithLang($attributes);
			}
		} catch (\Exception $e) {
			if ($return)
				ob_clean();

			throw $e;
		}
	}

	/**
	 * Actually renders the field with a given language (called as many times as the number of languages by render() method)
	 *
	 * @param array $attributes
	 * @param string $lang
	 */
	protected function renderWithLang(array $attributes, string $lang = null)
	{
		if ($this->options['form'] and $this->options['form']->options['print']) {
			echo entities($this->getText(['lang' => $lang]), true);
			return;
		}
		switch ($this->options['type']) {
			case 'textarea':
				echo '<textarea ' . $this->implodeAttributes($attributes) . '>' . entities($this->getValue($lang)) . '</textarea>';
				break;
			case 'select':
				$this->loadSelectOptions();
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
				if (!isset($attributes['id']))
					$attributes['id'] = 'checkbox-' . $attributes['name'];

				$label = isset($attributes['label']) ? $attributes['label'] : $this->getLabel();

				echo '<input type="checkbox" value="1"' . ($this->getValue($lang) ? ' checked' : '') . ' ' . $this->implodeAttributes($attributes) . ' />';

				if (!isset($attributes['hide-label']) and $label) {
					echo ' <label for="' . $attributes['id'] . '">' . $label . '</label>';
				}
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
	private function renderSelectOptions(array $options, $value)
	{
		foreach ($options as $id => $opt) {
			if (is_array($opt)) {
				echo '<optgroup label="' . entities($id) . '">';
				$this->renderSelectOptions($opt, $value);
				echo '</optgroup>';
			} else {
				echo '<option value="' . $id . '"' . (((string)$id === (string)$value) ? ' selected' : '') . '>' . entities($opt) . '</option>';
			}
		}
	}

	/**
	 * @param array $attributes
	 * @return string
	 */
	protected function implodeAttributes(array $attributes): string
	{
		$str_attributes = array();
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
				break;
			case 'date':
				return 180;
				break;
			case 'datetime':
				return 250;
				break;
			case 'textarea':
				return 200;
				break;
			case 'number':
				return 100;
				break;
			default:
				return 200;
				break;
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
			case 'datetime':
				$px = 450;
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
				break;
			case 'textarea':
				return 3;
				break;
			default:
				return 1;
				break;
		}
	}

	/**
	 * @param mixed $data
	 * @return bool
	 */
	public function save($data = null): bool
	{
		return true;
	}

	/**
	 * @param string|null $lang
	 * @return bool
	 */
	public function delete(string $lang = null): bool
	{
		return true;
	}
}
