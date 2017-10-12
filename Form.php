<?php
namespace Model;

class Form implements \ArrayAccess{
	/** @var array */
	private $dataset = array();
	/** @var array */
	public $options = array();
	/** @var Core */
	private $model;

	/**
	 * Form constructor.
	 *
	 * @param array $options
	 * @throws ZkException
	 */
	public function __construct(array $options = []){
		$this->options = array_merge([
			'table' => null,
			'model' => null,
            'element' => null,
            'pair-fields'=>[
                ['nome', 'cognome'],
                ['name', 'surname'],
            ],
		], $options);

		$this->model = $this->options['model'];
		if($this->model===null)
			throw new ZkException('Form class need a model reference!');
	}

	/**
	 * Adds a new field to the dataset
	 *
	 * @param string|MField $datum
	 * @param array $options
	 * @return bool|MField
	 */
	public function add($datum, array $options = []){
		if(is_object($datum)){
			if(get_class($datum)=='Model\\MField' or is_subclass_of($datum, 'Model\\MField')){
				$name = $datum->options['name'];
			}else{
				$this->model->error('Only MField can be passed directly as objects, in Form "add" method.');
			}
		}else{
			$name = $datum;
			$datum = false;
		}

		if($datum===false){
			if(!is_array($options))
				$options = ['type'=>$options];

			$options = array_merge([
				'field'=>$name,
				'form' => $this,
				'model' => $this->model,
				'type' => false,
				'nullable' => null,
				'default' => false,
				'multilang' => null,
				'dependsOn' => false,

                'table' => false,
                'id-field' => false,
                'text-field' => null,
                'where' => [],

				'group' => '',
			], $options);

			if($this->model->isLoaded('Multilang')){
				if($options['multilang']===null){
					if(isset($this->model->_Multilang->tables[$this->options['table']]) and in_array($options['field'], $this->model->_Multilang->tables[$this->options['table']]['fields']))
						$options['multilang'] = true;
					else
						$options['multilang'] = false;
				}

				if($options['multilang'] and $this->options['table'] and !isset($this->model->_Multilang->tables[$this->options['table']]))
					$options['multilang'] = false;
			}else{
				if($options['multilang'])
					$options['multilang'] = false;
			}

			if($this->options['table'] and $options['multilang']){
				$table = $this->options['table'].$this->model->_Multilang->tables[$this->options['table']]['suffix'];
			}else{
				$table = $this->options['table'];
			}

			$tableModel = $this->model->_Db->getTable($table);
			if($table and $tableModel===false)
				$this->model->error('Missing table model. Please generate cache.');

			if(isset($tableModel->columns[$options['field']])){
				$column = $tableModel->columns[$options['field']];
				if($options['nullable']===null)
					$options['nullable'] = $column['null'];

				if($options['type']===false){
					switch($column['type']){
						case 'tinyint':
						case 'smallint':
						case 'int':
						case 'mediumint':
						case 'bigint':
						case 'float':
						case 'double':
						case 'real':
							if($column['foreign_key']){
								$fk = $tableModel->foreign_keys[$column['foreign_key']];
								if($this->model->_Db->count($fk['ref_table'], $options['where'])>50 and !$options['dependsOn'] and $this->model->moduleExists('InstantSearch')){
									$options['type'] = 'instant-search';

									$options['table'] = $fk['ref_table'];

									if($options['text-field']===null){
										$ref_table = $this->model->_Db->getTable($fk['ref_table']);
										foreach($ref_table->columns as $ref_ck=>$ref_cc){
											if(in_array($ref_cc['type'], array('char', 'varchar', 'tinytext'))){
												$options['text-field'] = $ref_ck;
												break;
											}
										}

										if($options['text-field']){
											$options['text-field'] = $this->checkFieldsPairing($options['text-field'], $ref_table->columns);
										}
									}

									if($options['text-field']===null)
										$options['text-field'] = 'id';
								}else{
									$options['type'] = 'select';
								}
							}else{
								$options['type'] = 'number';
							}
							break;
						case 'decimal':
							$length = explode(',', $column['length']);
							$options['type'] = 'number';
							$options['attributes']['step'] = round($length[0]>0 ? 1/pow(10, $length[1]) : 1, $length[1]);
							break;
						case 'enum':
							$options['type'] = 'select';
							$options['sel_options'] = [];
							if($options['nullable'])
								$options['sel_options'][''] = '';
							foreach($column['length'] as $v)
								$options['sel_options'][$v] = ucwords($v);
							break;
						case 'date':
							$options['type'] = 'date';
							break;
						case 'time':
							$options['type'] = 'time';
							break;
						case 'datetime':
							$options['type'] = 'datetime';
							break;
						case 'text':
							$options['type'] = 'textarea';
							break;
						default:
							$options['type'] = 'text';
							break;
					}
				}

				if(in_array($options['type'], ['select', 'instant-search']) and $column['foreign_key']){
					$fk = $tableModel->foreign_keys[$column['foreign_key']];

					if(!$options['table'])
					    $options['table'] = $fk['ref_table'];

					if(!$options['id-field'])
    					$options['id-field'] = $fk['ref_column'];

					if(!$options['text-field']){
						$options['text-field'] = null;
						$ref_table = $this->model->_Db->getTable($options['table']);
						$refTable_columns = $ref_table->columns;

						if($this->model->isLoaded('Multilang') and array_key_exists($options['table'], $this->model->_Multilang->tables)){
							$ref_ml_table = $this->model->_Db->getTable($options['table'].$this->model->_Multilang->tables[$options['table']]['suffix']);
							foreach($this->model->_Multilang->tables[$options['table']]['fields'] as $ref_ck)
								$refTable_columns[$ref_ck] = $ref_ml_table->columns[$ref_ck];
						}

						foreach($refTable_columns as $ref_ck=>$ref_cc){
							if(in_array($ref_cc['type'], ['char', 'varchar', 'tinytext'])){
								$options['text-field'] = $this->checkFieldsPairing($ref_ck, $refTable_columns);
								break;
							}
						}

						if(!$options['text-field'])
							$options['text-field'] = $options['id'];
                    }
                }

				if(in_array($column['type'], ['varchar', 'char']) and $column['length']!==false)
					$options['maxlength'] = $column['length'];

				if($options['default']===false)
					$options['default'] = $column['default'];
			}else{
				if($options['type']===false)
					$options['type'] = 'text';
			}

			$checkClassExists = preg_replace('/[^a-z0-9_]/', '', strtolower($options['type']));
			if(class_exists('\\Model\\MField_'.$checkClassExists))
				$className = '\\Model\\MField_'.$checkClassExists;
			else
				$className = '\\Model\\MField';

			$datum = new $className($name, $options);
		}

		$this->dataset[$name] = $datum;

		return $datum;
	}

	/**
	 * In order to build a nicer select or an instant search field, some fields can be automatically paired together (most commonly, name and surname)
	 * This will check if this is the case
	 * Returns the array of fields if a pairing is found, otherwise returns the single field
	 *
	 * @param string $f
	 * @param array $columns
	 * @return array
	 */
	private function checkFieldsPairing($f, array $columns){
	    foreach($this->options['pair-fields'] as $pairing){
	        if(in_array($f, $pairing)){
	            foreach($pairing as $pf){
	                if(!array_key_exists($pf, $columns) or !in_array($columns[$pf]['type'], ['char', 'varchar', 'tinytext']))
	                    continue 2;
                }
                return $pairing;
            }
        }

        return [$f];
    }

	/* ArrayAccess implementations */
	public function offsetSet($offset, $value){
		$this->model->error('Form array is read-only. Please use add method to add a new field.');
	}

	public function offsetExists($offset){
		return isset($this->dataset[$offset]);
	}

	public function offsetUnset($offset){
		$this->model->error('Form array is read-only. Please use remove method to delete a field.');
	}

	public function offsetGet($offset){
		if(!isset($this->dataset[$offset]))
			$this->model->error('Index "'.$offset.'" does not exists in the form.');

		return $this->dataset[$offset];
	}

	/**
	 * Returns an array with the fields in the form
	 *
	 * @return array
	 */
	public function getDataset(){
		return $this->dataset;
	}

	/**
	 * Deletes all the fields
	 *
	 * @return bool
	 */
	public function clear(){
		$this->dataset = [];
		return true;
	}

	/**
	 * Sets all the fields to their defaults
	 */
	public function reset(){
		foreach($this->dataset as $d){
			$d->setValue($d->options['default']);
		}
	}

	/**
	 * Renders the form on screen
	 * Just two steps, retrieves the form-template and then renders it
	 *
	 * @param array $options
	 */
	public function render(array $options = []){
		$template = $this->getTemplate($options);
		$this->renderTemplate($template, $options);
	}

	/**
	 * Creates the form template, using the given settings (width in pixel, number of columns, etc)
	 * If a template for those settings is already cached, that one will be used, otherwise a new one will be calculated and cached
	 *
	 * @param array $options
	 * @return array
	 */
	public function getTemplate(array $options = []){
		$options = array_merge([
			'width' => 1000,
			'columns' => false,
			'one-row' => false,
			'cache' => true,

			'score-rows' => 1,
			'score-entropy' => 1,
			'score-shortage' => 1,
		], $options);

		$cacheKey = $this->getCacheKey($options);

		if($options['cache'] and file_exists(INCLUDE_PATH.'model/Form/data/cache/'.$cacheKey.'.php')){
			require(INCLUDE_PATH.'model/Form/data/cache/'.$cacheKey.'.php');
			if(!isset($template))
				$this->model->error('Couldn\'t file form template in the cache file.');
		}else{
			if(!is_numeric($options['width']) or $options['width']<=0)
				$this->model->error('A width in pixel must be provided');
			if(!$options['columns'])
				$options['columns'] = round($options['width']/100);
			if($options['columns']<1)
				$options['columns'] = 1;

			$options['column-width'] = round($options['width']/$options['columns']);

			$signature = $this->getSignature($options);

			$template = $this->getTemplateFromSignature($signature, $options);

			if($options['cache'])
				$this->cacheTemplate($cacheKey, $template);
		}

		return $template;
	}

	/**
	 * In order to store a particular form configuration I need to store in a portable key all the data that can't change (if they change, I'll have to calculate the template again)
	 *
	 * @param array $options
	 * @return string
	 */
	private function getCacheKey(array $options){
		$arr = [];
		foreach($this->dataset as $k => $f){
			$arr[$k] = [
				'type' => $f->options['type'],
				'label' => $f->getLabel(),
				'group' => $f->options['group'],
			];
		}
		ksort($options);
		ksort($arr);

		return sha1(json_encode($options).json_encode($arr));
	}

	/**
	 * Stores a template array in a cache file
	 *
	 * @param string $cacheKey
	 * @param array $template
	 * @return bool
	 */
	private function cacheTemplate($cacheKey, array $template){
		return (bool) file_put_contents(INCLUDE_PATH.'model/Form/data/cache/'.$cacheKey.'.php', '<?php
$template = '.var_export($template, true).';
');
	}

	/**
	 * Actually renders a template on screen
	 *
	 * @param array $template
	 * @param array $options
	 */
	private function renderTemplate(array $template, array $options){
		if(!$template)
			return;

		$options = array_merge([
			'labels-as-placeholders' => false,
			'show-labels' => true,
		], $options);

		echo '<div class="rob-field-cont">';
		foreach($template as $div){
			if(isset($div['field'])){
				echo '<div class="rob-field" style="width: '.$div['w'].'%">';
				if(!isset($this->dataset[$div['field']]))
					$this->model->error('Error in Form rendering: field "'.$div['field'].'" was not found.');

				$label = $this[$div['field']]->getLabel();
				if($options['show-labels'] and !$options['labels-as-placeholders'] and $this[$div['field']]->options['type']!='checkbox')
					echo entities($label).'<br />';
				$this[$div['field']]->render($options['labels-as-placeholders'] ? ['placeholder'=>$label] : []);
				echo '</div>';
			}else{
				echo '<div style="width: '.$div['w'].'%">';
				if(isset($div['content'])){
					$this->renderTemplate($div['content'], $options);
				}
				echo '</div>';
			}
		}
		echo '</div>';
	}

	/**
	 * HOW SIGNATURE MAKING WORKS:
	 *
	 * First, I create a basic signature with the fields in the initial order (moving all multirow fields to the end of the list), and assign it a score (the lower the better)
	 * From there, I make a modification at a time, and out of all possible "moves" I take the one that has the best score
	 * I keep going until there is no move that can improve the score anymore
	 *
	 * @param array $options
	 * @return array
	 */
	private function getSignature(array $options){
		$signature = [];
		foreach($this->dataset as $k=>$f){
			$h = $f->getEstimatedHeight($options);
			if($h>1)
				continue;
			$w = $f->getEstimatedWidth($options);
			$signature[] = [
				$k,
				$w,
				$h,
				false,
			];
		}
		foreach($this->dataset as $k=>$f){
			$h = $f->getEstimatedHeight($options);
			if($h==1)
				continue;
			$w = $f->getEstimatedWidth($options);
			$signature[] = [
				$k,
				$w,
				$h,
				true,
			];
		}

		do{
			$rows = $this->getRowsFromSignature($signature, $options);
			if($rows===false)
				$this->model->error('Error while generating the form: it looks like you started with an impossible-to-calculate fields placement. Try a different one please (pay attention to the textareas, never place them in the middle of a row).');

			$currentScore = $this->scoreSignature($rows, $options);

			$possibleSignatures = $this->getPossibleSignaturesFrom($signature, $options);
			$max = false;
			foreach($possibleSignatures as $s){
				$newScore = $this->scoreSignature($s['rows'], $options);
				if($max===false or $newScore<$max['score']){
					$max = [
						'score' => $newScore,
						'signature' => $s['signature'],
					];
				}
			}

			$found = false;
			if($max and $max['score']<$currentScore){
				$signature = $max['signature'];
				$found = true;
			}
		}while($found);

		return $signature;
	}

	/**
	 * Returns an array of rows arranged with the fields, or "false" if the given signature is not possible
	 *
	 * A signature is considered possible if the following rules are matched:
	 * - A field can't be shorter than its minimum width
	 * - A field can't be larger than the maximum width available
	 * - Two multirow field that share the same row-space have to start at the exact same row (or it won't be possible to calculate the template array out of the signature)
	 *
	 * @param array $signature
	 * @param array $options
	 * @return array|bool
	 */
	private function getRowsFromSignature(array $signature, array $options){
		$rows = [];

		$nRow = 0;
		while ($signature) { // I might loop through the fields multiple times, 'cause sometimes (if in a multirow-field-occupied row) I skip some of the fields to render them later
			$currentRowWidth = 0;
			$multiRows = false;
			foreach ($signature as $fIdx => &$f) {
				if(!isset($this->dataset[$f[0]])) // The field doesn't exist!
					return false;

				$minPixelWidth = $this->dataset[$f[0]]->getMinWidth();
				if($f[1]*$options['column-width']<$minPixelWidth) // The field is shorter than its minimum width
					return false;

				$totalRowWidth = $options['columns'];
				if ($multiRows) {
					foreach ($multiRows['fields'] as $mr)
						$totalRowWidth -= $mr['width'];
				}

				if ($f[1] > $totalRowWidth) { // The field is larger than the current row total space?
					if ($multiRows and $f[1] <= $options['columns']) { // If there is some multirow field occupying space (and, anyway, the field is shorter than the total columns number), that can be normal, I just skip
						continue;
					} else { // ...otherwise, this signature is just not possible to achieve
						return false;
					}
				}

				if ($currentRowWidth + $f[1] > $totalRowWidth) {
					$nRow++;
					$currentRowWidth = 0;

					if ($multiRows) {
						foreach ($multiRows['fields'] as $mrIdx => $mr) {
							if ($multiRows['start'] + $mr['height'] == $nRow) {
								$totalRowWidth += $mr['width'];
								unset($multiRows['fields'][$mrIdx]);
							}
						}
						if (empty($multiRows['fields']))
							$multiRows = false;
					}
				}

				if ($f[2] > 1) { // Multirow
					if ($currentRowWidth > 0 and $currentRowWidth + $f[1] < $totalRowWidth) { // The multirow field is neither at the beginning nor at the end of the row - I create a new row
						$currentRowWidth = 0;
						$nRow++;
					}

					if ($multiRows !== false) {
						if ($multiRows['start'] != $nRow) // Two "paralel" multirow fields have to start at the same row
							return false;
					} else {
						$multiRows = [
							'start' => $nRow,
							'fields' => [],
						];
					}
					$multiRows['fields'][] = [
						'width' => $f[1],
						'height' => $f[2],
					];

					$currentRowWidth = 0;
				} else {
					$currentRowWidth += $f[1];
				}

				if(!isset($rows[$nRow])){
					$rows[$nRow] = [
						'width' => $totalRowWidth,
						'fields' => [],
					];
				}
				$rows[$nRow]['fields'][] = $f;

				unset($signature[$fIdx]);
			}
			unset($f);

			if($multiRows){
				$multiRows = false;
				$nRow++;
			}
		}

		return $rows;
	}

	/**
	 * Assign a score to a given signature (passed in rows format fo convenience)
	 * The lower the score, the better is the fields placement
	 * Score is calculated based upon the following criteria:
	 * - The fewer rows, the better
	 * - Based upon the "group" to which each field belongs to, every rows has an entropy score (the lower the entropy, the better)
	 * - Any row with empty holes will worsen the score
	 *
	 * @param array $rows
	 * @param array $options
	 * @return float
	 */
	private function scoreSignature(array $rows, array $options){
		$rowsNumber = count($rows);

		$holes = 0;

		$entropy = 0;
		foreach($rows as $r){
			$entropy += $this->getEntropy($r);

			$occupiedSpace = 0;
			foreach($r['fields'] as $f)
				$occupiedSpace += $f[1];

			$holes += $r['width']-$occupiedSpace;
		}

		$score = $rowsNumber * $options['score-rows'] +
				 $entropy * $options['score-entropy'] +
				 $holes * $options['score-shortage'];

		return $score;
	}

	/**
	 * Calculate the entropy value of a row, based upon the groups each field belongs to
	 *
	 * @param array $row
	 * @return float
	 */
	private function getEntropy(array $row){
		$groups = [];
		foreach($row['fields'] as $f){
			$group = $this->dataset[$f[0]]->options['group'];

			if(!isset($groups[$group]))
				$groups[$group] = 0;
			$groups[$group]++;
		}

		$entropy = 0;
		foreach($groups as $g=>$c){
			$p = $c/count($row['fields']);
			$entropy += $p*log($p, 2);
		}
		$entropy *= -1;

		return $entropy;
	}

	/**
	 * Returns a set of possible signature moving from the given one
	 * It's called at every iteration of the getSignature method
	 * At every call, the possible steps include, for every field in the signature:
	 * - Widening the field by one
	 * - Shortening it by one
	 * - Raising or lowering the height by one, if it's a multirow field
	 * - Moving the position of the field to another one
	 *
	 * @param array $signature
	 * @param array $options
	 * @return array
	 */
	private function getPossibleSignaturesFrom(array $signature, array $options){
		$possibilities = [];
		foreach($signature as $fIdx => $f){
			/* Widening the field */
			$s_wide = $signature;
			$s_wide[$fIdx][1]++;
			$rows = $this->getRowsFromSignature($s_wide, $options);
			if($rows!==false){
				$possibilities[] = [
					'signature' => $s_wide,
					'rows' => $rows,
				];
			}
			unset($s_wide);

			/* Shortening the field */
			if($f[1]>1) {
				$s_short = $signature;
				$s_short[$fIdx][1]--;
				$rows = $this->getRowsFromSignature($s_short, $options);
				if ($rows !== false) {
					$possibilities[] = [
						'signature' => $s_short,
						'rows' => $rows,
					];
				}
				unset($s_short);
			}

			// The field is multirow
			if($f[3]){
				/* Higher */
				$s_high = $signature;
				$s_high[$fIdx][2]++;
				$rows = $this->getRowsFromSignature($s_high, $options);
				if($rows!==false){
					$possibilities[] = [
						'signature' => $s_high,
						'rows' => $rows,
					];
				}
				unset($s_high);

				/* Lower */
				if($f[2]>1) {
					$s_low = $signature;
					$s_low[$fIdx][2]--;
					$rows = $this->getRowsFromSignature($s_low, $options);
					if ($rows !== false) {
						$possibilities[] = [
							'signature' => $s_low,
							'rows' => $rows,
						];
					}
					unset($s_low);
				}
			}

			$signatureForMoving = $signature;
			array_splice($signatureForMoving, $fIdx, 1);

			// In order to limit memory usage for large forms, I try to move only to the position in a range of 10 around the original positions
			for($newPos=$fIdx-5;$newPos<=$fIdx+5;$newPos++){
				if($newPos==$fIdx or !isset($signature[$newPos]))
					continue;

				$possibility = $signatureForMoving;
				array_splice($possibility, $newPos, 0, [$f]);

				$rows = $this->getRowsFromSignature($possibility, $options);
				if ($rows !== false) {
					$possibilities[] = [
						'signature' => $possibility,
						'rows' => $rows,
					];
				}

				unset($possibility);
			}

			unset($signatureForMoving);
			unset($rows);

			gc_collect_cycles();
		}

		return $possibilities;
	}

	/**
	 * HOW FORM TEMPLATING WORKS:
	 *
	 * Given a form signature, which is a list of fields in the following format:
	 *  [
	 * 		[field_name, width, height, is_multiRow],
	 * 		[field_name, width, height, is_multiRow],
	 * 		etc...
	 *  ]
	 *
	 * And given a number of columns (in the options), the method returns a "template" array,
	 * namely a list of containers (that could also be nested) with percentage widths,
	 * representing what divs should be rendered in the html to render the form with the fields in that order.
	 * The template array has the following form:
	 * [
			[
				'w' => 80,
				'content' => [
					[
						'w' => 66.67,
						'field' => 'field1',
					],
					[
						'w' => 33.33,
						'field' => 'field2',
					],
				],
			],
			[
				'w' => 20,
				'field' => 'field3',
			],
	   ]
	 *
	 * @param array $signature
	 * @param array $options
	 * @return array
	 */
	private function getTemplateFromSignature(array $signature, array $options){
		if($options['one-row']){
			$totW = 0;
			foreach($signature as $f){
				$totW += $f[1];
			}

			$conts = [];
			foreach($signature as $f){
				$conts[] = [
					'w' => floor($f[1]/$totW*10000)/100,
					'field' => $f[0],
				];
			}
		}else{
			$conts = $this->putFieldsInContainers($options['columns'], $signature);

			if(count($signature)>0){ // If some of the fields are too large for the container, they have been ignored in putFieldsInContainer, but they still need to be rendered so I manually add 'em here
				foreach($signature as $f){
					$conts[] = [
						'w' => $f[1],
						'field' => $f[0],
					];
				}
			}

			$conts = $this->normalizeWidths($conts, $options['columns']);
		}

		return $conts;
	}

	/**
	 * Recursive method used by getTemplateFromSignature
	 *
	 * $w is the total width of the container
	 *
	 * @param int $w
	 * @param array $fields
	 * @param int|bool $maxRows
	 * @return array
	 */
	private function putFieldsInContainers($w, array &$fields, $maxRows = false){
		$conts = [];

		$rowsN = 0; $row = []; $currentRowWidth = 0;
		foreach($fields as $fIdx => &$f){
			if($f[1]>$w)
				continue;

			if($currentRowWidth+$f[1]>$w){
				$currentRowWidth = 0;
				$conts = array_merge($conts, $row);
				$row = [];
				$rowsN++;
				if($rowsN==$maxRows)
					break;
			}

			if($f[2]>1){
				unset($fields[$fIdx]);

				if($currentRowWidth>0){
					$row = [
						[
							'w' => $currentRowWidth,
							'content' => array_merge($row, $this->putFieldsInContainers($currentRowWidth, $fields, $f[2])),
						],
						[
							'w' => $f[1],
							'field' => $f[0],
						],
					];
				}else{
					$row = [
						[
							'w' => $f[1],
							'field' => $f[0],
						],
						[
							'w' => $w-$f[1],
							'content' => $w>$f[1] ? array_merge($row, $this->putFieldsInContainers($w-$f[1], $fields, $f[2])) : [],
						],
					];
				}

				if($fields){ // Some fields stayed out of the multirow grouping? I create a new row for them
					$conts = array_merge($conts, $row);
					$row = [];
				}

				$currentRowWidth = 0;
			}else{
				$row[] = [
					'w' => $f[1],
					'field' => $f[0],
				];

				$currentRowWidth += $f[1];

				unset($fields[$fIdx]);

				if($currentRowWidth==$w){
					$currentRowWidth = 0;
					$conts = array_merge($conts, $row);
					$row = [];
					$rowsN++;
					if($rowsN==$maxRows)
						break;
				}
			}
		}
		unset($f);

		if($row)
			$conts = array_merge($conts, $row);

		return $conts;
	}

	/**
	 * Recursively normalizes the width of the containers, so that the ones in the same row sum up to 100
	 *
	 * @param array $conts
	 * @param int $w
	 * @return array
	 */
	private function normalizeWidths(array $conts, $w){
		foreach($conts as $idx => &$c){
			if(isset($c['content']))
				$c['content'] = $this->normalizeWidths($c['content'], $c['w']);

			$c['w'] = floor($c['w']/$w*10000)/100;
		}
		unset($c);

		return $conts;
	}

	/**
	 * Retrieves the values from the dataset and returns an array of values
	 *
	 * @return array
	 */
	public function getValues(){
		$arr = [];
		foreach($this->dataset as $d){
			$arr[$d->options['name']] = $d->getValue();
		}
		return $arr;
	}

	/**
	 * Populate the dataset assigning the provided values
	 *
	 * @param array $values
	 */
	public function setValues(array $values){
		foreach($this->dataset as $d){
			if(array_key_exists($d->options['name'], $values))
				$d->setValue($values[$d->options['name']]);
		}
	}
}
