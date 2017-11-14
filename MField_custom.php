<?php
namespace Model;

class MField_custom extends MField{
	public function renderWithLang(array $attributes, $lang = null){
		echo '<div data-custom="'.$this->options['name'].'">';
		echo $this->getText(['lang' => $lang]);
		echo '</div>';
	}

	public function getText(array $options = []){
		if(isset($this->options['custom'])){
			if(!is_string($this->options['custom']) and is_callable($this->options['custom'])){
				return call_user_func($this->options['custom'], $this->form->options['element']);
			}else{
				return $this->options['custom'];
			}
		}
	}

	public function getValue($lang = null){
		return $this->getText(['lang' => $lang]);
	}
}
