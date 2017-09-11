<?php
namespace Model;

class Form_Config extends Module_Config {
	public $configurable = true;

	public function makeCache(){
		if(!is_dir(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'Form'.DIRECTORY_SEPARATOR.'data'))
			mkdir(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'Form'.DIRECTORY_SEPARATOR.'data');
		if(!is_dir(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'Form'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'cache'))
			mkdir(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'Form'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'cache');

		return true;
	}

	/**
	 * Returns the config template
	 *
	 * @param array $request
	 * @return string
	 */
	public function getTemplate(array $request){
		return INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'Form'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'config';
	}

	/**
	 * Clears the cache (only possible configuration for the module at the moment)
	 *
	 * @param string $type
	 * @param array $data
	 * @return bool
	 */
	public function saveConfig($type, array $data){
		if(checkCsrf() and isset($_POST['empty']) and $_POST['empty']){
			$files = glob(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'Form'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'*');
			foreach($files as $f){
				unlink($f);
			}
		}
		return true;
	}
}
