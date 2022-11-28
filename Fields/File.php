<?php namespace Model\Form\Fields;

use Model\Form\Field;
use Model\Images\Image;

class File extends Field
{
	/** @var array[] */
	protected array $paths = [];

	/**
	 * Class File constructor.
	 * @param string $nome
	 * @param array $options
	 * @throws \Exception
	 */
	public function __construct(string $nome, array $options = [])
	{
		if (!is_array($options))
			$options = ['path' => $options];

		if (isset($options['path'])) {
			$options['paths'] = array(
				array('path' => $options['path']),
			);
			unset($options['path']);

			if (isset($options['mime'])) {
				$options['paths'][0]['mime'] = $options['mime'];
				unset($options['mime']);
			}
			if (isset($options['w'])) {
				$options['paths'][0]['w'] = $options['w'];
				unset($options['w']);
			}
			if (isset($options['h'])) {
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
			'external' => null, // There is a db field to optionally retrieve the image from an external url?
		], $options);

		parent::__construct($nome, $options);

		$this->paths = $this->options['paths'];

		foreach ($this->paths as &$p) {
			if (!is_array($p))
				$p = ['path' => $p];
		}
		unset($p);
	}

	/**
	 * @param array $attributes
	 * @param string $lang
	 */
	public function renderWithLang(array $attributes, string $lang = null)
	{
		$name = $attributes['name'];
		unset($attributes['name']);

		$attributesBox = [];
		if (isset($attributes['style'])) {
			$attributesBox['style'] = $attributes['style'];
			unset($attributes['style']);
		}
		if (isset($attributes['class'])) {
			$attributesBox['class'] = $attributes['class'];
			unset($attributes['class']);
		}

		$is_image = $this->isImage();

		echo '<div data-file-box="' . $name . '">';
		echo '<input type="file" name="' . $name . '" ' . ($is_image ? 'style="display: none"' : '') . ' id="file-input-' . $name . '" ' . $this->implodeAttributes($attributes) . ' onchange="if(typeof this.files[0]!=\'undefined\') fileSetValue.call(this, this.files[0])" data-getvalue-function="fileGetValue" data-setvalue-function="fileSetValue" />';
		echo '<div class="file-box-cont" ' . (!$is_image ? 'style="display: none"' : '') . ' ' . $this->implodeAttributes($attributesBox) . '><div class="file-box" data-file-cont onclick="document.getElementById(\'file-input-' . $name . '\').click(); return false">Upload</div></div>';
		echo '<div class="file-tools" style="display: none">
				<a href="#" onclick="emptyExternalFileInput(this.parentNode.parentNode); document.getElementById(\'file-input-' . $name . '\').click(); return false"><img src="' . PATH . 'model/Form/assets/img/upload.png" alt="" /> Carica nuovo</a>
				<a href="#" onclick="emptyExternalFileInput(this.parentNode.parentNode); document.getElementById(\'file-input-' . $name . '\').setValue(null); return false"><img src="' . PATH . 'model/Form/assets/img/delete.png" alt="" /> Elimina</a>
			</div>';

		if ($this->options['external'])
			echo '<input type="hidden" name="' . $this->wrapName($this->options['external']) . ($lang !== null ? '-' . $lang : '') . '" data-external/>';

		echo '</div>';

		$v = $this->getValue($lang);
		if ($v) {
			echo '<script>fileSetValue.call(document.getElementById(\'file-input-' . $name . '\'), ' . json_encode($v) . ', false)</script>';
		}
	}

	/**
	 * @return bool
	 */
	private function isImage(): bool
	{
		$path = $this->getPath();
		$mime = false;
		if (file_exists(INCLUDE_PATH . $path)) {
			$mime = mime_content_type(INCLUDE_PATH . $path);
		} else {
			$path = reset($this->paths);
			if (isset($path['mime']))
				$mime = $path['mime'];
		}
		return in_array($mime, ['image/jpeg', 'image/png', 'image/gif']);
	}

	/**
	 * @param string $lang
	 * @return mixed
	 */
	public function getValue($lang = null)
	{
		if ($this->options['multilang'] and $lang === false) {
			$values = [];
			foreach (\Model\Multilang\Ml::getLangs() as $l) {
				if ($this->fileExists($l))
					$values[$l] = $this->getPath(null, $l);
				else
					$values[$l] = null;
			}
			return $values;
		} else {
			if (!$lang and class_exists('\\Model\\Multilang\\Ml'))
				$lang = \Model\Multilang\Ml::getLang();

			if ($this->fileExists($lang))
				return $this->getPath(null, $lang);
			else
				return null;
		}
	}

	/**
	 * @param mixed $data
	 * @return bool
	 * @throws \Exception
	 */
	public function save(mixed $data = null): bool
	{
		if ($data === null and isset($_FILES[$this->options['name']]) and $_FILES[$this->options['name']]['error'] === 0)
			$data = $this->reArrayFiles($_FILES[$this->options['name']]);

		if ($this->options['multilang']) {
			$saving = true;
			foreach (\Model\Multilang\Ml::getLangs() as $lang) {
				if (is_array($data) and array_key_exists($lang, $data)) {
					if (!$this->saveWithLang($data[$lang], $lang))
						$saving = false;
				}
			}
			return $saving;
		} else {
			return $this->saveWithLang($data);
		}
	}

	/**
	 * @param array|null $data
	 * @param string|null $lang
	 * @return bool
	 * @throws \Exception
	 */
	private function saveWithLang(array $data = null, string $lang = null): bool
	{
		if ($data === null)
			return $this->delete($lang);

		if (!$data)
			return true;

		$file = reset($data); // Multiple files upload currently not supported

		if (!isset($file['name'], $file['type']))
			return false;

		if ($this->options['accepted'] and !in_array($file['type'], $this->options['accepted']))
			$this->model->error('Unaccepted file format');

		$filename = pathinfo($file['name'], PATHINFO_FILENAME);
		$fileext = pathinfo($file['name'], PATHINFO_EXTENSION);

		if (isset($file['tmp_name'])) {
			$temp_file = $file['tmp_name'];
			$method = 'file';
		} elseif (isset($file['admin_upload'])) {
			$temp_file = INCLUDE_PATH . 'app-data' . DIRECTORY_SEPARATOR . 'temp-admin-files' . DIRECTORY_SEPARATOR . $file['admin_upload'];
			$method = 'post';
		} elseif (isset($file['file'])) {
			$temp_file = INCLUDE_PATH . 'app-data' . DIRECTORY_SEPARATOR . $this->model->_User_Admin->id . ($fileext ? '.' . $fileext : ''); // TODO: svincolarlo dall'admin
			$scrittura = file_put_contents($temp_file, base64_decode($file['file']));
			if ($scrittura === false)
				return false;

			$method = 'post';
		} else {
			return false;
		}

		if ($this->options['multilang'] and $lang) {
			$multilangTableOptions = \Model\Multilang\Ml::getTableOptionsFor($this->model->_Db->getConnection(), $this->form->options['table']);
			$multilangColumns = $multilangTableOptions['fields'];
		} else {
			$multilangColumns = [];
		}

		$updateArr = [];
		$pathData = [];
		if ($this->options['name_db']) {
			$updateArr[$this->options['name_db']] = in_array($this->options['name_db'], $multilangColumns) ? [$lang => $filename] : $filename;
			$pathData[$this->options['name_db']] = $filename;
		}
		if ($this->options['ext_db']) {
			$updateArr[$this->options['ext_db']] = in_array($this->options['ext_db'], $multilangColumns) ? [$lang => $fileext] : $fileext;
			$pathData[$this->options['ext_db']] = $fileext;
		}

		if ($this->form->options['element'])
			$this->form->options['element']->update($updateArr);

		$img = false;

		foreach ($this->paths as $i => $p) {
			$path = $this->getPath($i, $lang, $pathData);
			$this->isPathWritable($path);

			$imgConversionOptions = [];

			if (in_array($file['type'], ['image/png', 'image/jpeg', 'image/gif', 'image/x-png', 'image/pjpeg']) and (isset($p['mime']) or isset($p['w']) or isset($p['h']))) {
				if (array_key_exists('mime', $p) and $p['mime'] !== $file['type'])
					$imgConversionOptions['type'] = $p['mime'];
				if (array_key_exists('w', $p))
					$imgConversionOptions['w'] = $p['w'];
				if (array_key_exists('h', $p))
					$imgConversionOptions['h'] = $p['h'];
				if (array_key_exists('extend', $p))
					$imgConversionOptions['extend'] = $p['extend'];
			}

			if ($imgConversionOptions) {
				if (!$img)
					$img = new Image($temp_file);

				$img->save(INCLUDE_PATH . $path, $imgConversionOptions);
			} else {
				switch ($method) {
					case 'post':
						copy($temp_file, INCLUDE_PATH . $path);
						break;
					case 'file':
						if (is_uploaded_file($temp_file)) {
							if (!copy($temp_file, INCLUDE_PATH . $path))
								$this->model->error('Unable to save file');
							$temp_file = INCLUDE_PATH . $path;
						} else {
							if (!copy($temp_file, INCLUDE_PATH . $path))
								$this->model->error('Unable to save copy of the file');
						}
						break;
				}
			}
		}

		if ($img) {
			$img->destroy();
			unset($img);
		}

		if ($method === 'post')
			unlink($temp_file);

		if ($this->form->options['element'])
			$this->form->options['element']->save($updateArr);

		return true;
	}

	/**
	 * @param array $file_post
	 * @return array
	 */
	private function reArrayFiles(array $file_post): array
	{
		if (!is_array($file_post['name'])) {
			return [
				$file_post,
			];
		}

		$file_ary = array();
		$file_count = count($file_post['name']);
		$file_keys = array_keys($file_post);

		for ($i = 0; $i < $file_count; $i++) {
			foreach ($file_keys as $key) {
				$file_ary[$i][$key] = $file_post[$key][$i];
			}
		}

		return $file_ary;
	}

	/**
	 * @param string $path
	 */
	private function isPathWritable(string $path)
	{
		$dir = pathinfo(INCLUDE_PATH . $path, PATHINFO_DIRNAME);
		$smalldir = pathinfo($path, PATHINFO_DIRNAME);
		if (!is_dir($dir) and !mkdir($dir, 0775, true))
			$this->model->error('Couldn\'t create directory "' . $smalldir . '"');
		if (!is_writable($dir))
			$this->model->error('Specified directory "' . $smalldir . '" is not writable');
	}

	/**
	 * @param int|string $i
	 * @param string $lang
	 * @param array $data
	 * @return string|null
	 */
	public function getPath(string $i = null, string $lang = null, array $data = []): ?string
	{
		if (count($this->paths) === 0)
			return null;

		if ($i === null) {
			if ($this->options['external'] and $this->form->options['element']) {
				$url = $this->retrieveFieldWithLang($this->options['external'], $lang);
				if ($url !== null)
					return $url;
			}

			$i = current(array_keys($this->paths));
		}

		$path = $this->paths[$i]['path'];

		preg_match_all('/\[([a-z0-9:_-]+)\]/i', $path, $matches);
		foreach ($matches[1] as $k) {
			if ($k === ':lang') {
				$rep = $lang;
			} else {
				if (array_key_exists($k, $data)) {
					$rep = $data[$k];
				} elseif ($this->form->options['element']) {
					$rep = $this->retrieveFieldWithLang($k, $lang);
				} else {
					$rep = null;
				}
			}

			if ($rep === null and ($this->options['name_db'] === $k or $this->options['ext_db'] === $k))
				return null;

			$path = str_replace('[' . $k . ']', (string)$rep, $path);
		}

		return $path;
	}

	/**
	 * @param string $field
	 * @param string|null $lang
	 * @return string|null
	 */
	private function retrieveFieldWithLang(string $field, string $lang = null): ?string
	{
		if ($lang === null or !class_exists('\\Model\\Multilang\\Ml') or $lang === \Model\Multilang\Ml::getLang()) {
			// In case of multilang fields, I have to retrieve the correct field from the database
			return $this->form->options['element'][$field]; // If the language is the current one, then I just need the element
		} else {
			// If it's not the current language, I'll have to make another query to find out the info in the correct language
			$check = $this->model->_Db->select($this->form->options['table'], $this->form->options['element']['id'], ['lang' => $lang]);
			return $check ? $check[$field] : null;
		}
	}

	/**
	 * @return array
	 */
	public function getPaths(): array
	{
		$paths = [];
		foreach ($this->paths as $i => $p) {
			if ($this->options['multilang']) {
				foreach (\Model\Multilang\Ml::getLangs() as $lang)
					$paths[$i . '-' . $lang] = $this->getPath($i, $lang);
			} else {
				$paths[$i] = $this->getPath($i);
			}
		}
		return $paths;
	}

	/**
	 * @param string $lang
	 * @return bool
	 */
	public function fileExists(string $lang = null): bool
	{
		$path = $this->getPath(null, $lang);
		if ($path === null)
			return false;
		if (stripos($path, 'http://') === 0 or stripos($path, 'https://') === 0)
			return true;
		return file_exists(INCLUDE_PATH . $path);
	}

	/**
	 * @param string|null $lang
	 * @return bool
	 */
	public function delete(string $lang = null): bool
	{
		foreach ($this->paths as $i => $p) {
			if ($this->options['multilang'] and !$lang) {
				foreach (\Model\Multilang\Ml::getLangs() as $l) {
					$path = $this->getPath($i, $l);
					if (file_exists(INCLUDE_PATH . $path) and !is_dir(INCLUDE_PATH . $path))
						unlink(INCLUDE_PATH . $path);
				}
			} else {
				$path = $this->getPath($i, $lang);
				if (file_exists(INCLUDE_PATH . $path) and !is_dir(INCLUDE_PATH . $path))
					unlink(INCLUDE_PATH . $path);
			}
		}

		return true;
	}
}
