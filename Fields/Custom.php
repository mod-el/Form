<?php namespace Model\Form\Fields;

use Model\Form\MField;

class Custom extends MField
{
	public function renderWithLang(array $attributes, string $lang = null)
	{
		echo '<div data-custom="' . $this->options['name'] . '">';
		echo $this->getText(['lang' => $lang]);
		echo '</div>';
	}

	public function getText(array $options = []): string
	{
		if (isset($this->options['custom'])) {
			if (!is_string($this->options['custom']) and is_callable($this->options['custom'])) {
				return call_user_func($this->options['custom'], $this->form->options['element']);
			} else {
				return $this->options['custom'];
			}
		}
	}

	public function getValue($lang = null)
	{
		return $this->getText(['lang' => $lang]);
	}
}
