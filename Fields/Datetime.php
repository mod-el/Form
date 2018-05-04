<?php namespace Model\Form\Fields;

use Model\Form\Field;

class Datetime extends Field
{
	/**
	 * @param array $options
	 * @return string
	 */
	public function getText(array $options = []): string
	{
		$options = array_merge([
			'dateFormat' => 'd/m/Y',
			'lang' => null,
		], $options);

		$value = $this->getValue($options['lang']);
		if ($value === null)
			return '';

		$format = $options['dateFormat'] . ' H:i';
		$data = $value ? date_create($value) : false;
		return $data ? $data->format($format) : '';
	}

	/**
	 * @param array $attributes
	 * @param string|null $lang
	 */
	protected function renderWithLang(array $attributes, string $lang = null)
	{
		$value = $this->getValue($lang);
		$value = $value ? date_create($value) : null;
		$full = $value ? $value->format('Y-m-d H:i:s') : '';
		$date = $value ? $value->format('Y-m-d') : '';
		$time = $value ? $value->format('H:i:s') : '';

		$textAttributes = [
			'onchange' => 'setDatetimeSingle(this)',
		];
		foreach ($attributes as $k => $v) {
			if (in_array($k, ['class', 'style'])) {
				$textAttributes[$k] = $v;
				unset($attributes[$k]);
			}
		}

		echo '<div class="rob-field-cont">';
		echo '<div class="rob-field" style="padding: 0 3px 0 0; width: 70%">';
		echo '<input type="date" value="' . entities($date) . '" ' . $this->implodeAttributes($textAttributes) . ' />';
		echo '</div>';
		echo '<div class="rob-field" style="padding: 0 0 0 3px; width: 30%">';
		echo '<input type="time" value="' . entities($time) . '" ' . $this->implodeAttributes($textAttributes) . ' />';
		echo '</div>';
		echo '<input type="hidden" value="' . entities($full) . '" ' . $this->implodeAttributes($attributes) . ' data-setvalue-function="setDatetime" />';
		echo '</div>';
	}

	/**
	 * @return int
	 */
	public function getMinWidth(): int
	{
		return 250;
	}

	/**
	 * @param array $options
	 * @return int
	 */
	public function getEstimatedWidth(array $options): int
	{
		return round(450 / $options['column-width']);
	}
}
