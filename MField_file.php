<?php
namespace Model;

class MField_file extends MField{
	/** @var array[] */
	protected $paths = [];

	/**
	 * MField_file constructor.
	 * @param string $nome
	 * @param array $options
	 */
	public function __construct($nome, array $options = []){
		if(!is_array($options))
			$options = ['path'=>$options];

		if(isset($options['path'])){
			$options['paths'] = array(
				array('path'=>$options['path']),
			);
			unset($options['path']);

			if(isset($options['mime'])){
				$options['paths'][0]['mime'] = $options['mime'];
				unset($options['mime']);
			}
			if(isset($options['w'])){
				$options['paths'][0]['w'] = $options['w'];
				unset($options['w']);
			}
			if(isset($options['h'])){
				$options['paths'][0]['h'] = $options['h'];
				unset($options['h']);
			}
		}

		$options = array_merge([
			'type' => 'file',
			'paths' => [], // Paths where the file has to be stored (can be multiple paths and, in case of images, stored in different sizes and formats)
			'ext_db' => false, // If the extension of the file is not costant, you need to save it in a database field; specify here the column name
			'name_db' => false, // Need to save the name of the file, also?
			'accepted' => false, // Accept only some file types?
		], $options);

		parent::__construct($nome, $options);

		$this->paths = $this->options['paths'];

		foreach($this->paths as &$p){
			if(!is_array($p))
				$p = ['path'=>$p];
		}
		unset($p);
	}

	/**
	 * @param array $attributes
	 * @param string $lang
	 */
	public function renderWithLang(array $attributes, $lang = null){
		$name = $attributes['name'];
		unset($attributes['name']);

		$attributesBox = [];
		if(isset($attributes['style'])){
			$attributesBox['style'] = $attributes['style'];
			unset($attributes['style']);
		}
		if(isset($attributes['class'])){
			$attributesBox['class'] = $attributes['class'];
			unset($attributes['class']);
		}

		$is_image = $this->isImage();

		echo '<div data-file-box="'.$name.'">';
		echo '<input type="file" name="' . $name . '" '.($is_image ? 'style="display: none"' : '').' id="file-input-' . $name . '" '.$this->implodeAttributes($attributes).' onchange="if(typeof this.files[0]!=\'undefined\') fileSetValue.call(this, this.files[0])" data-getvalue-function="fileGetValue" data-setvalue-function="fileSetValue" />';
		echo '<div class="file-box-cont" '.(!$is_image ? 'style="display: none"' : '').' '.$this->implodeAttributes($attributesBox).'><div class="file-box" data-file-cont></div></div>';
		echo '<div class="file-tools" style="display: none">
				<a href="#" onclick="document.getElementById(\'file-input-'.$name.'\').click(); return false"><img src="'.PATH.'model/Form/files/img/upload.png" alt="" /> Carica nuovo</a>
				<a href="#" onclick="document.getElementById(\'file-input-'.$name.'\').setValue(null); return false"><img src="'.PATH.'model/Form/files/img/delete.png" alt="" /> Elimina</a>
			</div>';

		echo '</div>';
	}

	private function isImage(){
		$path = $this->getPath();
		$mime = false;
		if(file_exists(INCLUDE_PATH.$path)){
			$mime = mime_content_type(INCLUDE_PATH.$path);
		}else{
			$path = reset($this->paths);
			if(isset($path['mime']))
				$mime = $path['mime'];
		}
		return in_array($mime, ['image/jpeg', 'image/png', 'image/gif']);
	}

	/**
	 * @param string $lang
	 * @return mixed
	 */
	public function getValue($lang = null){
		if($this->options['multilang'] and $lang===false){
			$values = [];
			foreach($this->model->_Multilang->langs as $l){
				if($this->fileExists($l)){
					$values[$l] = $this->getPath(null, $l);
				}else{
					$values[$l] = null;
				}
			}
			return $values;
		}else{
			if($this->options['multilang'] and $lang===null)
				$lang = $this->model->_Multilang->lang;

			if($this->fileExists($lang)){
				return $this->getPath(null, $lang);
			}else{
				return null;
			}
		}
	}

	/**
	 * @param mixed $data
	 * @return bool
	 */
	public function save($data){
		if($data===null and isset($_FILES[$this->options['name']]) and $_FILES[$this->options['name']]['error']===0){
			$data = $this->reArrayFiles($_FILES[$this->options['name']]);
		}

		if($this->options['multilang']){
			$saving = true;
			foreach($this->model->_Multilang->langs as $lang){
				if(is_array($data) and array_key_exists($lang, $data)){
					if(!$this->saveWithLang($data[$lang], $lang))
						$saving = false;
				}
			}
			return $saving;
		}else{
			return $this->saveWithLang($data);
		}
	}

	/**
	 * @param array $data
	 * @param string $lang
	 * @return bool
	 */
	private function saveWithLang($data, $lang = null){
		if(!$data){
			return $this->delete($lang);
		}

		$file = reset($data); // Multiple files upload currently not supported

		if(!isset($file['name'], $file['type']))
			return false;

		if($this->options['accepted'] and !in_array($file['type'], $this->options['accepted']))
			$this->model->error('Unaccepted file format');

		$filename = pathinfo($file['name'], PATHINFO_FILENAME);
		$fileext = pathinfo($file['name'], PATHINFO_EXTENSION);

		if(isset($file['tmp_name'])){
			$temp_file = $file['tmp_name'];
			$method = 'file';
		}elseif(isset($file['file'])){
			$temp_file = INCLUDE_PATH.'app-data'.DIRECTORY_SEPARATOR.$this->model->_User_Admin->id.($fileext ? '.'.$fileext : '');
			$scrittura = file_put_contents($temp_file, base64_decode($file['file']));
			if($scrittura===false)
				return false;

			$method = 'post';
		}else{
			return false;
		}

		if($this->options['multilang'] and $lang){
			$multilangTableOptions = $this->model->_Multilang->getTableOptionsFor($this->form->options['table']);
			$multilangColumns = $multilangTableOptions['fields'];
		}else{
			$multilangColumns = [];
		}

		$updateArr = []; $pathData = [];
		if($this->options['name_db']){
			$updateArr[$this->options['name_db']] = in_array($this->options['name_db'], $multilangColumns) ? [$lang => $filename] : $filename;
			$pathData[$this->options['name_db']] = $filename;
		}
		if($this->options['ext_db']){
			$updateArr[$this->options['ext_db']] = in_array($this->options['ext_db'], $multilangColumns) ? [$lang => $fileext] : $fileext;
			$pathData[$this->options['ext_db']] = $fileext;
		}

		$this->form->options['element']->update($updateArr);

		$img = false;

		foreach($this->paths as $i => $p){
			$path = $this->getPath($i, $lang, $pathData);

			if(in_array($file['type'], ['image/png', 'image/jpeg', 'image/gif', 'image/x-png', 'image/pjpeg']) and (isset($p['mime']) or isset($p['w']) or isset($p['h']))){
				$imgOpt = array();

				if(array_key_exists('mime', $p))
					$imgOpt['type'] = $p['mime'];
				if(array_key_exists('w', $p))
					$imgOpt['w'] = $p['w'];
				if(array_key_exists('h', $p))
					$imgOpt['h'] = $p['h'];
				if(array_key_exists('extend', $p))
					$imgOpt['extend'] = $p['extend'];

				if(!$img)
					$img = new ImgResize($temp_file);

				if(!$img->save(INCLUDE_PATH.$path, $imgOpt))
					$this->model->error('Unable to save image');
			}else{
				switch($method){
					case 'post':
						copy($temp_file, INCLUDE_PATH.$path);
						break;
					case 'file':
						if(is_uploaded_file($temp_file)){
							if (!copy($temp_file, INCLUDE_PATH . $path))
								$this->model->error('Unable to save file');
							$temp_file = INCLUDE_PATH . $path;
						}else{
							if (!copy($temp_file, INCLUDE_PATH . $path))
								$this->model->error('Unable to save copy of the file');
						}
						break;
				}
			}
		}

		if($img){
			$img->destroy();
			unset($img);
		}

		if($method==='post'){
			unlink($temp_file);
		}

		$this->form->options['element']->save($updateArr);

		return true;
	}

	/**
	 * @param array $file_post
	 * @return array
	 */
	private function reArrayFiles(array $file_post) {
		if(!is_array($file_post['name'])){
			return [
				$file_post,
			];
		}

		$file_ary = array();
		$file_count = count($file_post['name']);
		$file_keys = array_keys($file_post);

		for ($i=0; $i<$file_count; $i++) {
			foreach ($file_keys as $key) {
				$file_ary[$i][$key] = $file_post[$key][$i];
			}
		}

		return $file_ary;
	}

	/**
	 * @param string $dir_name
	 * @return bool|string
	 */
	private function checkDir($dir_name){
		$dir = INCLUDE_PATH.$dir_name;

		if(!file_exists($dir))
			return 'Path '.entities($dir_name).' does not exist.';
		if(!is_dir($dir))
			return entities($dir_name).' is not a directory.';
		if(!is_writable($dir))
			return entities($dir_name).' is not writable.';

		return true;
	}

	/**
	 * @param int|string $i
	 * @param string $lang
	 * @param array $data
	 * @return array|null
	 */
	public function getPath($i = null, $lang = null, $data = []){
		if(count($this->paths)===0)
			return null;

		if($i===null)
			$i = current(array_keys($this->paths));

		$path = $this->paths[$i]['path'];

		preg_match_all('/\[([a-z0-9:_-]+)\]/i', $path, $matches);
		foreach($matches[1] as $k){
			if($k===':lang'){
				$rep = (string) $lang;
			}else{
				if(array_key_exists($k, $data)){
					$rep = (string) $data[$k];
				}elseif($this->form->options['element']){
					if($lang===null or $lang===$this->model->_Multilang->lang) // In case of multilang fields, I have to retrieve the correct field from the database
						$rep = (string) $this->form->options['element'][$k]; // If the language is the current one, than I just need the element
					else // If it's not the current language, I'll have to make another query to find out the info in the correct language
						$rep = (string) $this->model->_Db->select($this->form->options['table'], $this->form->options['element']['id'], ['field' => $k, 'lang' => $lang]);
				}else{
					$rep = '';
				}
			}
			$path = str_replace('['.$k.']', $rep, $path);
		}

		return $path;
	}

	/**
	 * @return array
	 */
	public function getPaths(){
		$paths = [];
		foreach($this->paths as $i => $p){
			if($this->options['multilang']){
				foreach($this->model->_Multilang->langs as $lang){
					$paths[$i.'-'.$lang] = $this->getPath($i, $lang);
				}
			}else{
				$paths[$i] = $this->getPath($i);
			}
		}
		return $paths;
	}

	/**
	 * @param string $lang
	 * @return bool
	 */
	public function fileExists($lang = null){
		$path = $this->getPath(null, $lang);
		return file_exists(INCLUDE_PATH.$path);
	}

	/**
	 * @param string $lang
	 * @return bool
	 */
	public function delete($lang = null){
		foreach($this->paths as $i => $p){
			if($this->options['multilang'] and !$lang){
				foreach($this->model->_Multilang->langs as $l){
					$path = $this->getPath($i, $l);
					if(file_exists(INCLUDE_PATH.$path))
						unlink(INCLUDE_PATH.$path);
				}
			}else{
				$path = $this->getPath($i, $lang);
				if(file_exists(INCLUDE_PATH.$path))
					unlink(INCLUDE_PATH.$path);
			}
		}

		return true;
	}
}
