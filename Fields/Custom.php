<?php namespace Model\Form\Fields;

use Model\Form\Field;

class Custom extends Field
{
	protected function renderWithLang(array $attributes, ?string $lang = null): void
	{
		echo $this->getText(['lang' => $lang]);
	}

	public function getText(array $options = []): string
	{
		if (isset($this->options['custom'])) {
			if (!is_string($this->options['custom']) and is_callable($this->options['custom'])) {
				return call_user_func($this->options['custom'], $this->form->options['element']);
			} else {
				return $this->options['custom'];
			}
		} else {
			return $this->options['value'];
		}
	}

	public function getValue(string|bool|null $lang = null): mixed
	{
		return $this->getText(['lang' => $lang]);
	}
}
