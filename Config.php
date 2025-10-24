<?php namespace Model\Form;

use Model\Core\Module_Config;

class Config extends Module_Config
{
	public bool $configurable = true;

	protected function assetsList(): void
	{
		$this->addAsset('data', 'cache');
	}

	/**
	 * Returns the config template
	 *
	 * @param string $type
	 * @return string|null
	 */
	public function getTemplate(string $type): ?string
	{
		return $type === 'config' ? 'config' : null;
	}

	public function getConfigData(): ?array
	{
		return [];
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
}
