<?php namespace Model\Form;

use Model\Core\Module_Config;

class Config extends Module_Config
{
	public $configurable = true;

	protected function assetsList()
	{
		$this->addAsset('data', 'cache');
	}

	/**
	 * Returns the config template
	 *
	 * @param array $request
	 * @return string
	 */
	public function getTemplate(array $request)
	{
		return $request[2] == 'config' ? 'config' : null;
	}

	/**
	 * Clears the cache (only possible configuration for the module at the moment)
	 *
	 * @param string $type
	 * @param array $data
	 * @return bool
	 */
	public function saveConfig(string $type, array $data): bool
	{
		if (isset($_POST['empty']) and $_POST['empty']) {
			$files = glob(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Form' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '*');
			foreach ($files as $f) {
				unlink($f);
			}
		}
		return true;
	}

	/**
	 * Rules for general form actions controller
	 *
	 * @return array
	 */
	public function getRules(): array
	{
		return [
			'rules' => [
				'model-form',
			],
			'controllers' => [
				'ModelForm',
			],
		];
	}
}
