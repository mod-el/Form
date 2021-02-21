function setSelect(s, v) {
	if (v == null)
		return false;

	for (let cp in s.options) {
		if (typeof s.options[cp].value !== 'undefined' && s.options[cp].value.toString() === v.toString()) {
			s.selectedIndex = cp;
			return true;
		}
	}

	s.selectedIndex = 0;
	return false;
}

Element.prototype.hasOption = function (i) {
	return Array.from(this.options).some(function (opt) {
		return opt.value === i;
	});
};

var setElementValue = function (v, trigger_onchange, use_custom_function) {
	if (typeof trigger_onchange === 'undefined')
		trigger_onchange = true;
	if (typeof use_custom_function === 'undefined')
		use_custom_function = true;

	if (v === null)
		v = '';

	return this.getValue().then((function (element, v, trigger_onchange) {
		return async function (currentValue) {
			if (v === true || v === false)
				return null;

			var ret = true;

			if (element instanceof NodeList) { // Radio
				element.value = v;
			} else if (use_custom_function && element.getAttribute('data-setvalue-function') !== null) {
				var func = element.getAttribute('data-setvalue-function');
				if (typeof window[func] === 'undefined')
					return null;
				ret = window[func].call(element, v);
				await Promise.resolve(ret);
			} else if (element.type === 'checkbox' || element.type === 'radio') {
				if (v == 1 || v == true) element.checked = true;
				else element.checked = false;
			} else if (element.type === 'select-multiple') {
				for (let i = 0; i < element.options.length; i++) {
					if (in_array(element.options[i].value, v))
						element.options[i].selected = true;
					else
						element.options[i].selected = false;
				}
			} else if (element.type === 'select-one') {
				ret = setSelect(element, v);
			} else if (element.getAttribute('type') === 'date') {
				if (isDateSupported()) {
					if (v.match(/[0-9]{2}-[0-9]{2}-[0-9]{4}/)) {
						v = v.split('-');
						v = v[2] + '-' + v[1] + '-' + v[0];
					}
				} else {
					if (v.match(/[0-9]{4}-[0-9]{2}-[0-9]{2}/)) {
						v = v.split('-');
						v = v[2] + '-' + v[1] + '-' + v[0];
					}
				}
				element.value = v;
			} else {
				element.value = v;
			}

			if (v !== currentValue && trigger_onchange)
				triggerOnChange(element);

			return ret;
		};
	})(this, v, trigger_onchange));
};

function triggerOnChange(field) {
	if ('createEvent' in document) {
		let evt = document.createEvent('HTMLEvents');
		evt.initEvent('change', false, true);
		field.dispatchEvent(evt);

		evt = document.createEvent('HTMLEvents');
		evt.initEvent('input', false, true);
		field.dispatchEvent(evt);
	} else {
		field.fireEvent('onchange');
		field.fireEvent('oninput');
	}
}

var basicGetElementValue = function (element) {
	var v = null;

	if (element instanceof NodeList) { // Radio
		v = element.value;
	} else if (element.getAttribute('data-getvalue-function') !== null) {
		var func = element.getAttribute('data-getvalue-function');
		if (typeof window[func] === 'undefined')
			return null;
		v = window[func].call(element);
	} else if (element.type === 'checkbox') {
		if (element.checked) v = 1;
		else v = 0;
	} else if (element.type === 'radio') {
		if (element.checked) v = element.value;
		else v = 0;
	} else if (element.type === 'select-one') {
		if (element.selectedIndex > -1)
			v = element.options[element.selectedIndex].value;
		else v = '';
	} else if (element.type === 'select-multiple') {
		v = [];
		for (let i = 0; i < element.options.length; i++)
			if (element.options[i].selected)
				v.push(element.options[i].value);
	} else if (element.getAttribute('type') === 'date') {
		v = element.value;

		if (!isDateSupported()) {
			if (v.match(/[0-9]{2}-[0-9]{2}-[0-9]{4}/)) {
				v = v.split('-');
				v = v[2] + '-' + v[1] + '-' + v[0];
			}
		}
	} else {
		v = element.value;
	}

	return v;
};

var getElementValue = function (direct_value) {
	if (typeof direct_value === 'undefined')
		direct_value = false;

	if (direct_value) {
		return basicGetElementValue(this);
	} else {
		return new Promise((function (element) {
			return function (resolve) {
				let v = basicGetElementValue(element);
				resolve(v);
			};
		})(this));
	}
};

Element.prototype.getValues = function () {
	if (this.nodeName.toLowerCase() !== 'form')
		return new Promise(function (resolve) {
			resolve([]);
		});

	var promises = [];
	var elements = this.elements;
	for (let i = 0, f; f = elements[i++];) {
		if (!f.name)
			continue;

		promises.push(f.getValue().then((function (name, type) {
			return function (v) {
				if (type === 'radio' && !v)
					v = null;

				return [name, v];
			};
		})(f.name, f.type.toLowerCase())));
	}

	return Promise.all(promises).then(function (data) {
		var ret = {};

		data.forEach(function (v) {
			if (typeof ret[v[0]] === 'undefined' || v[1] !== null) // Per i radio
				ret[v[0]] = v[1];
		});

		return ret;
	});
};

Element.prototype.setValues = function (values, trigger_onchange, mark) {
	if (this.nodeName.toLowerCase() !== 'form')
		return false;
	if (typeof trigger_onchange === 'undefined')
		trigger_onchange = true;
	if (typeof mark === 'undefined')
		mark = null;

	let promises = [];

	let elements = this.elements;
	for (let i = 0, f; f = elements[i++];) {
		if (mark) {
			if (f.getAttribute('data-' + mark))
				continue;
		}

		let name;
		let value;

		if (f.getAttribute('data-multilang') && f.getAttribute('data-lang') && typeof values[f.getAttribute('data-multilang')] === 'object') {
			name = f.getAttribute('data-multilang');
			if (typeof values[name] === 'undefined')
				continue;
			value = values[name][f.getAttribute('data-lang')];
		} else {
			name = f.name;
			if (typeof values[name] === 'undefined')
				continue;
			value = values[name];
		}

		if (!name)
			continue;

		if (f.type.toLowerCase() === 'radio') {
			if (f.value === value)
				f.checked = true;
		} else {
			promises.push(f.setValue(value, trigger_onchange).then((function (f, mark) {
				return function () {
					if (mark)
						f.setAttribute('data-' + mark, '1');

					return f;
				};
			})(f, mark)));
		}
	}

	return Promise.all(promises);
};

Element.prototype.fill = Element.prototype.setValues; // Alias

Object.defineProperty(NodeList.prototype, "value", {
	get: getRadioNodeListValue,
	set: setRadioNodeListValue,
	configurable: true
});

function getRadioNodeListValue() {
	for (let i = 0, len = this.length; i < len; i++) {
		var el = this[i];
		if (el.checked) {
			return el.value;
		}
	}
}

function setRadioNodeListValue(value) {
	for (let i = 0, len = this.length; i < len; i++) {
		var el = this[i];
		if (el.checked) {
			el.value = value;
			return;
		}
	}
}

Element.prototype.setValue = setElementValue;
NodeList.prototype.setValue = setElementValue;
Element.prototype.getValue = getElementValue;
NodeList.prototype.getValue = getElementValue;

function switchFieldLang(cont, lang) {
	let currentFlag = cont.querySelector('.multilang-field-lang-container > a[data-lang]');
	if (!currentFlag || currentFlag.getAttribute('data-lang') === lang)
		return false;

	let newFlag = cont.querySelector('.multilang-field-other-langs-container > a[data-lang="' + lang + '"]');
	if (!newFlag)
		return false;

	cont.querySelector('.multilang-field-lang-container').insertBefore(newFlag, currentFlag);
	cont.querySelector('.multilang-field-other-langs-container').appendChild(currentFlag);

	cont.querySelectorAll('.multilang-field-container > [data-lang]').forEach(function (el) {
		if (el.getAttribute('data-lang') === lang) {
			el.style.display = 'block';
			if (fileBox = el.querySelector('[data-file-cont][data-natural-width][data-natural-height]')) {
				resizeFileBox(fileBox);
			}
		} else {
			el.style.display = 'none';
		}
	});

	changedHtml();
}

function fileGetValue() {
	var promises = [];
	for (i = 0; i < this.files.length; i++) {
		promises.push(new Promise((function (file) {
			return function (resolve) {
				var reader = new FileReader();
				reader.onload = function (e) {
					var mime = e.target.result.match(/^data:(.*);/)[1];
					resolve({
						'name': file.name,
						'file': e.target.result.substr(('data:' + mime + ';base64,').length),
						'type': mime
					});
				};
				reader.readAsDataURL(file);
			}
		})(this.files[i])));
	}

	return Promise.all(promises).then((function (field) {
		return function (values) {
			if (values.length === 0) {
				if (field.getAttribute('data-changed'))
					return null;
				else
					return [];
			} else
				return values;
		};
	})(this));
}

function fileSetValue(v, user_triggered) {
	if (typeof user_triggered === 'undefined')
		user_triggered = true;

	var mainBox = this.parentNode;
	var fileBoxCont = mainBox.querySelector('.file-box-cont');
	var fileBox = mainBox.querySelector('[data-file-cont]');
	var fileTools = mainBox.querySelector('.file-tools');

	if (user_triggered)
		this.setAttribute('data-changed', '1');

	if (Array.isArray(v) && v.length === 0)
		v = null;

	if (v) {
		fileBoxCont.style.display = 'block';
		fileTools.style.display = 'block';
		this.style.display = 'none';

		let isImage = false;

		if (typeof v === 'string') {
			let filename = v.split('.');
			if (filename.length > 1) {
				let ext = filename.pop().toLowerCase();
				if (ext === 'jpg' || ext === 'jpeg' || ext === 'bmp' || ext === 'png' || ext === 'gif')
					isImage = true;
			}

			if (v.toLowerCase().indexOf('http://') !== 0 && v.toLowerCase().indexOf('https://') !== 0)
				v = PATHBASE + v;
			fileBox.setAttribute('data-file-path', v);

			if (isImage && !this.getAttribute('data-only-text')) {
				return setFileImage(fileBox, v + "?nocache=" + Math.random());
			} else {
				filename = v.split('/').pop();
				fileBox.setAttribute('data-file-path', v);
				return setFileText(fileBox, filename);
			}
		} else {
			return new Promise((function (field) {
				return function (resolve) {
					let reader = new FileReader();
					reader.onload = (function (box, file, field) {
						return function (e) {
							var mime = e.target.result.match(/^data:(.*);/)[1];
							var fileBox = box.querySelector('[data-file-cont]');

							if (in_array(mime, ['image/jpeg', 'image/png', 'image/gif', 'image/x-png', 'image/pjpeg']) && !field.getAttribute('data-only-text')) {
								resolve(setFileImage(fileBox, e.target.result));
							} else {
								box.removeAttribute('onclick');
								resolve(setFileText(fileBox, file.name));
							}
						};
					})(mainBox, v, field);
					reader.readAsDataURL(v);
				};
			})(this));
		}
	} else {
		fileTools.style.display = 'none';
		fileBoxCont.style.display = 'none';
		fileBox.removeAttribute('data-file-path');
		this.style.display = 'inline-block';
		this.value = null;
		return true;
	}
}

function setFileImage(box, i) {
	return new Promise(function (resolve) {
		let img = new Image();
		img.onload = (function (box, i) {
			return function () {
				box.setAttribute('data-natural-width', this.naturalWidth);
				box.setAttribute('data-natural-height', this.naturalHeight);

				resizeFileBox(box);

				box.style.backgroundImage = "url('" + i + "')";
				box.innerHTML = '';

				resolve();
			};
		})(box, i);
		img.onerror = (function (box) {
			return function () {
				box.removeAttribute('data-natural-width');
				box.removeAttribute('data-natural-height');

				box.innerHTML = 'Corrupted image';

				resolve();
			};
		})(box);
		img.src = i;
	});
}

function setFileText(box, text) {
	box.style.backgroundImage = '';
	box.innerHTML = text;

	box.style.width = '100%';
	box.style.height = 'auto';

	box.removeAttribute('data-natural-width');
	box.removeAttribute('data-natural-height');

	return true;
}

function resizeFileBox(box) {
	var w = parseInt(box.getAttribute('data-natural-width'));
	var h = parseInt(box.getAttribute('data-natural-height'));

	box.style.width = '100%';
	let boxW = box.offsetWidth;
	if (boxW > w)
		boxW = w;

	let boxH = Math.round(h / w * boxW);
	box.style.width = boxW + 'px';
	box.style.height = boxH + 'px';
}

function checkForm(form, mandatory, appendRequired = true) {
	if (appendRequired) {
		var required = form.querySelectorAll('input[required], select[required], textarea[required]');
		for (var idx in required) {
			if (!required.hasOwnProperty(idx))
				continue;
			if (required[idx].offsetParent === null)
				continue;
			if (required[idx].name && mandatory.indexOf(required[idx].name) === -1)
				mandatory.push(required[idx].name);
		}
	}

	var missings = [];
	for (var idx in mandatory) {
		if (!mandatory.hasOwnProperty(idx)) continue;
		if (mandatory[idx].constructor === Array) {
			var atLeastOne = false;
			for (var sub_idx in mandatory[idx]) {
				if (!mandatory[idx].hasOwnProperty(sub_idx)) continue;
				if (checkField(form, mandatory[idx][sub_idx]))
					atLeastOne = true;
			}

			if (!atLeastOne) {
				missings.push(mandatory[idx]);
			}
		} else {
			if (!checkField(form, mandatory[idx])) {
				missings.push(mandatory[idx]);
			}
		}
	}

	if (missings.length > 0) {
		console.log('Missing fields:');
		var alreadyFocused = false;
		var missingsString = [];
		for (var field in missings) {
			if (!missings.hasOwnProperty(field)) continue;
			console.log(missings[field]);

			if (missings[field].constructor === Array) {
				if (!alreadyFocused && form[missings[field][0]].offsetParent !== null) {
					if (typeof form[missings[field][0]].focus !== 'undefined') {
						form[missings[field][0]].focus();
						alreadyFocused = true;
					}
				}

				missingsString.push(missings[field].join(' or '));
			} else {
				if (!alreadyFocused && form[missings[field]].offsetParent !== null) {
					if (typeof form[missings[field]].focus !== 'undefined') {
						form[missings[field]].focus();
						alreadyFocused = true;
					}
				}

				missingsString.push(missings[field]);
				markFieldAsMandatory(form[missings[field]]);
			}
		}

		alert("Required fields:\n\n" + missingsString.join("\n"))
		return false;
	} else {
		return true;
	}
}

function checkField(form, name) {
	if (typeof form[name] !== 'object')
		return false;

	var v = form[name].getValue(true);
	if (v === null)
		v = '';

	if (form[name].type === 'checkbox' && v.toString() === '0')
		v = '';

	return v !== '';
}

function markFieldAsMandatory(field) {
	if (typeof field === 'object') {
		if (field[0] && field[0].type && field[0].type.toLowerCase() === 'radio') {
			for (var i = 0, length = field.length; i < length; i++)
				field[i].style.outline = 'solid #F00 1px';
		} else if (field.type && field.type.toLowerCase() === 'hidden' && field.getAttribute('data-instant-search')) {
			var textField = getInstantSearchInputs(field.getAttribute('data-instant-search'));
			if (textField) {
				textField.style.outline = 'solid #F00 1px';
			}
		} else {
			field.style.outline = 'solid #F00 1px';
		}
	}
}

function simulateTab(current, forward) {
	if (typeof current.form === 'undefined' || !current.form)
		return false;
	if (typeof forward === 'undefined')
		forward = true;

	var next = false;
	if (forward) {
		var start = 0;
		var end = current.form.elements.length;
		var step = 1;
	} else {
		var start = current.form.elements.length;
		var end = 0;
		var step = -1;
	}
	for (i = start; i * step < end * step; i += step) {
		if (next && !current.form.elements[i].readOnly && !current.form.elements[i].disabled && !in_array(current.form.elements[i].type, ['hidden'])) {
			current.form.elements[i].focus();
			if (current.form.elements[i].type == "text" || current.form.elements[i].type == "number") {
				current.form.elements[i].select();
			}
			return true;
		}
		if (current.form.elements[i] == current)
			next = true;
	}
	return false;
}

function switchAllFieldsLang(lang) {
	Array.from(document.querySelectorAll('.lang-switch-cont [data-lang]')).forEach(function (el) {
		if (el.getAttribute('data-lang') === lang)
			el.addClass('selected');
		else
			el.removeClass('selected');
	});

	document.querySelectorAll('.multilang-field-container[data-name]').forEach(function (f) {
		switchFieldLang(f, lang);
	});
}

function setDatetimeSingle(f) {
	let cont = f.parentNode.parentNode;
	let date = cont.querySelector('input[type="date"]').getValue(true);
	let time = cont.querySelector('input[type="time"]').getValue(true);

	console.log(date);
	console.log(time);

	let full;
	if (!date || !time)
		full = '';
	else
		full = date + ' ' + time;

	return cont.querySelector('input[type="hidden"]').setValue(full, true, false);
}

function setDatetime(v) {
	let splitted = v.split(' ');
	if (splitted.length === 1)
		splitted.push('');
	if (splitted.length > 2)
		splitted = ['', ''];

	let cont = this.parentNode;
	let datePromise = cont.querySelector('input[type="date"]').setValue(splitted[0], false);
	let timePromise = cont.querySelector('input[type="time"]').setValue(splitted[1], false);
	let hiddenPromise = cont.querySelector('input[type="hidden"]').setValue(v ? v : '', false, false);

	return Promise.all([datePromise, timePromise, hiddenPromise]);
}

function emptyExternalFileInput(box) {
	if (input = box.querySelector('input[type="hidden"][data-external]'))
		input.setValue('');
}

/************************ CLASSI STRUTTURATE ************************/

var formSignatures = new Map();

class FormManager {
	constructor(name) {
		this.name = name;
		this.version = 1;
		this.fields = new Map()
		this.changedValues = {};
		this.ignore = false;
	}

	async add(field) {
		field.form = this;
		field.historyDefaultValue = await field.getValue();

		field.addEventListener('change', event => {
			let old = null;
			if (typeof this.changedValues[field.name] === 'undefined') {
				old = field.historyDefaultValue;
			} else {
				old = this.changedValues[field.name];
			}

			field.getValue().then(v => {
				if (v === old)
					return;

				this.changedValues[field.name] = v;

				if (typeof historyMgr === 'object') // Per admin
					historyMgr.append(this.name, field.name, old, v, event ? (event.langChanged || null) : null);
			});
		});

		this.fields.set(field.name, field);
	}

	async build(cont, data) {
		if (data.version)
			this.version = data.version;

		for (let k of Object.keys(data.fields)) {
			let fieldCont = cont.querySelector('[data-fieldplaceholder="' + k + '"]');
			if (!fieldCont)
				continue;

			let fieldOptions = data.fields[k];
			if (typeof data.data[k] !== 'undefined')
				fieldOptions.value = data.data[k];

			let field;
			if (fieldOptions.hasOwnProperty('type') && formSignatures.get(fieldOptions.type)) {
				let fieldClass = formSignatures.get(fieldOptions.type);
				field = new fieldClass(k, fieldOptions);
			} else {
				field = new Field(k, fieldOptions);
			}
			await this.add(field);

			fieldCont.innerHTML = '';
			fieldCont.appendChild(await field.render());
			field.emit('append');
		}

		for (let fieldName of this.fields.keys()) {
			if (this.fields.get(fieldName).options.type === 'select')
				await this.fields.get(fieldName).reloadDependingSelects(false);
		}

		changedHtml();
	}

	getChangedValues() {
		return this.changedValues;
	}

	getRequired() {
		let required = [];
		for (let k of this.fields.keys()) {
			let field = this.fields.get(k);
			if (field.options.required)
				required.push(k);
		}
		return required;
	}

	async checkRequired() {
		let required = this.getRequired();
		let missingFields = [];
		for (let fieldName of required) {
			let field = this.fields.get(fieldName);
			let v = await field.getValue();
			if (v === null)
				v = '';

			if (field.options.type === 'checkbox' && v.toString() === '0')
				v = '';

			if (v === '') {
				markFieldAsMandatory(field.node);
				missingFields.push(field.options.label);
			}
		}

		if (missingFields.length) {
			alert("Compilare i seguenti campi:\n" + missingFields.join("\n"));
			return false;
		} else {
			return true;
		}
	}

	async getValues() {
		let values = {};
		for (let k of this.fields.keys())
			values[k] = await this.fields.get(k).getValue();

		return values;
	}

	async setValues(values, trigger = true) {
		for (let k of Object.keys(values)) {
			let field = this.fields.get(k);
			if (field)
				await field.setValue(values[k], trigger);
		}
	}
}

class Field {
	constructor(name, options = {}) {
		this.name = name;
		this.node = null;
		this.form = null;
		this.options = {
			'type': 'text',
			'value': null,
			'attributes': {},
			'options': [],
			'multilang': false,
			'required': false,
			'label': null,
			...options
		};

		this.value = this.options.value;
		this.options.type = this.options.type.toLowerCase();

		this.listeners = new Map();

		this.rendered = null;

		if (!this.options.label)
			this.options.label = this.makeLabel(name);
	}

	makeLabel(name) {
		let label = name.split(/[_ -]/).join(' ');
		return label.substr(0, 1).toUpperCase() + label.substr(1).toLowerCase();
	}

	addEventListener(event, callback) {
		let listeners = this.listeners.get(event);
		if (!listeners)
			listeners = [];
		listeners.push(callback);
		this.listeners.set(event, listeners);
	}

	emit(eventName, event = null) {
		let listeners = this.listeners.get(eventName);
		if (!listeners)
			return;

		for (let listener of listeners)
			listener.call(this, event);
	}

	async setValue(v, trigger = true) {
		this.value = v;

		let node = await this.getNode();
		if (this.options.multilang) {
			for (let lang of this.options.multilang) {
				if (node.hasOwnProperty(lang) && v.hasOwnProperty(lang))
					await node[lang].setValue(v[lang], trigger);
			}
		} else {
			await node.setValue(v, trigger);
		}

		if (trigger)
			this.emit('change');
	}

	async getValue() {
		if (this.value === null || typeof this.value !== 'object')
			return this.value;
		else
			return {...this.value};
	}

	focus(lang = null) {
		this.getNode().then(obj => {
			let node;
			if (this.options.multilang) {
				if (lang === null)
					lang = this.options.multilang[0];
				node = obj[lang];
			} else {
				node = obj;
			}

			node.focus();
			if (node.select)
				node.select();
		});
	}

	getSingleNode(lang = null) {
		let nodeType = null;
		let attributes = this.options['attributes'] || {};

		switch (this.options['type']) {
			case 'textarea':
				nodeType = 'textarea';
				break;
			case 'date':
				nodeType = 'input';
				attributes['type'] = 'date';
				break;
			default:
				nodeType = 'input';
				attributes['type'] = this.options['type'];
				break;
		}

		let node = document.createElement(nodeType);

		this.assignAttributes(node, attributes);
		this.assignEvents(node, attributes, lang);

		return node;
	}

	assignAttributes(node, attributes, assignName = true) {
		attributes = {...attributes}; // Clono per evitare interferenze

		node.modelField = this;
		node.modelForm = this.form;

		if (this.options.required)
			attributes.required = '';

		if (assignName && typeof attributes['name'] === 'undefined')
			attributes['name'] = this.name;

		if (attributes.hasOwnProperty('onchange'))
			delete attributes.onchange;

		Object.keys(attributes).forEach(k => {
			node.setAttribute(k, attributes[k]);
		});
	}

	assignEvents(node, attributes, lang, events = null) {
		if (events === null)
			events = ['keyup', 'keydown', 'click', 'change', 'input'];

		for (let eventName of events) {
			node.addEventListener(eventName, async event => {
				if (eventName === 'change') {
					let v = await node.getValue();
					if (this.options.multilang) {
						event.langChanged = lang;
						if (this.value === null || typeof this.value !== 'object')
							this.value = {};

						this.value[lang] = v;
					} else {
						this.value = v;
					}
				}

				if (attributes.hasOwnProperty('on' + eventName)) {
					let customFunc;
					eval('customFunc = () => { ' + attributes['on' + eventName] + ' };');
					customFunc.call(node);
				}

				this.emit(eventName, event);
			});
		}
	}

	async getNode() {
		if (this.node === null) {
			if (this.options.multilang) {
				this.node = {};
				for (let lang of this.options.multilang)
					this.node[lang] = this.getSingleNode(lang);
			} else {
				this.node = this.getSingleNode();
			}

			await this.setValue(this.value, false);
		}

		return this.node;
	}

	async render() {
		let node = await this.getNode();

		if (this.options.multilang) {
			let cont = document.createElement('div');
			cont.className = 'multilang-field-container';
			cont.setAttribute('data-name', this.name);

			let firstLang = true;
			for (let lang of this.options.multilang) {
				let langCont = document.createElement('div');
				langCont.setAttribute('data-lang', lang);
				langCont.appendChild(await this.renderNode(node[lang]));

				if (firstLang) {
					firstLang = false;
				} else {
					langCont.style.display = 'none';
				}

				cont.appendChild(langCont);
			}

			let defaultLang = this.options.multilang[0];

			let flagsCont = document.createElement('div');
			flagsCont.className = 'multilang-field-lang-container';

			let mainFlag = document.createElement('a');
			mainFlag.href = '#';
			mainFlag.setAttribute('data-lang', defaultLang);
			mainFlag.addEventListener('click', event => {
				event.preventDefault();
				switchFieldLang(cont, defaultLang);
			});
			mainFlag.innerHTML = `<img src="${PATH}model/Form/assets/img/langs/${defaultLang}.png" alt="${defaultLang}"/>`;
			flagsCont.appendChild(mainFlag);

			let otherFlagsCont = document.createElement('div');
			otherFlagsCont.className = 'multilang-field-other-langs-container';
			for (let lang of this.options.multilang) {
				if (lang === defaultLang)
					continue;

				let flag = document.createElement('a');
				flag.href = '#';
				flag.setAttribute('data-lang', lang);
				flag.addEventListener('click', event => {
					event.preventDefault();
					switchFieldLang(cont, lang);
				});
				flag.innerHTML = `<img src="${PATH}model/Form/assets/img/langs/${lang}.png" alt="${lang}"/>`;
				otherFlagsCont.appendChild(flag);
			}
			flagsCont.appendChild(otherFlagsCont);

			cont.appendChild(flagsCont);

			this.rendered = cont;
		} else {
			this.rendered = await this.renderNode(node);
		}

		return this.rendered;
	}

	async renderNode(node) {
		if (this.options.type === 'checkbox' && this.options.label) {
			let id = node.id;
			if (!id) {
				id = 'checkbox-' + Math.round(Math.random() * 100000);
				node.id = id;
			}

			let span = document.createElement('span');
			span.appendChild(node);

			let whitespace = document.createTextNode(' ');
			span.appendChild(whitespace);

			let label = document.createElement('label');
			label.setAttribute('for', id);
			label.innerHTML = this.options.label;
			span.appendChild(label);

			return span;
		} else {
			return node;
		}
	}
}

class FieldDatetime extends Field {
	cont;
	dateNode;
	timeNode;
	hiddenNode;

	getSingleNode(lang = null) {
		this.dateNode = document.createElement('input');
		this.dateNode.type = 'date';
		this.dateNode.addEventListener('change', () => {
			this.updateInputValue();
		});

		this.timeNode = document.createElement('input');
		this.timeNode.type = 'time';
		this.timeNode.addEventListener('change', () => {
			this.updateInputValue();
		});

		this.hiddenNode = document.createElement('input');
		this.hiddenNode.type = 'hidden';

		let attributes = this.options['attributes'] || {};
		let textAttributes = {};
		if (attributes.class)
			textAttributes.class = attributes.class;
		if (attributes.style)
			textAttributes.style = attributes.style;

		this.assignAttributes(this.dateNode, textAttributes);
		this.assignAttributes(this.timeNode, textAttributes);

		this.assignAttributes(this.hiddenNode, attributes);
		this.assignEvents(this.hiddenNode, attributes, lang);

		this.cont = document.createElement('div');
		this.cont.className = 'flex-fields';

		let dateCont = document.createElement('div');
		dateCont.style.padding = '0 3px 0 0';
		dateCont.appendChild(this.dateNode);
		this.cont.appendChild(dateCont);

		let timeCont = document.createElement('div');
		timeCont.style.padding = '0 0 0 3px';
		timeCont.appendChild(this.timeNode);
		this.cont.appendChild(timeCont);

		this.cont.appendChild(this.hiddenNode);

		return this.cont;
	}

	updateInputValue() {
		if (!this.cont)
			return;

		let date = this.dateNode.value;
		let time = this.timeNode.value;

		let full = '';
		if (date && time)
			full = date + ' ' + time;

		return this.hiddenNode.setValue(full);
	}

	async setValue(v, trigger = true) {
		this.value = v;

		if (!this.cont)
			return;

		let splitted = v ? v.toString().split(' ') : ['', ''];
		if (splitted.length === 1)
			splitted.push('');
		if (splitted.length > 2)
			splitted = ['', ''];

		let datePromise = this.dateNode.setValue(splitted[0], false);
		let timePromise = this.timeNode.setValue(splitted[1], false);
		let hiddenPromise = this.hiddenNode.setValue(v ? v : '', trigger);

		return Promise.all([datePromise, timePromise, hiddenPromise]);
	}
}

class FieldSelect extends Field {
	attemptedValue = null;

	async setValue(v, trigger = true) {
		if (!this.options['options'].some(option => option.id == v)) {
			this.attemptedValue = v;
			v = null;
		}

		return super.setValue(v, trigger);
	}

	getSingleNode(lang = null) {
		let node = document.createElement('select');

		let attributes = this.options['attributes'] || {};
		this.assignAttributes(node, attributes);
		this.assignEvents(node, attributes, lang);

		if (attributes['data-depending-parent'])
			this.addEventListener('change', this.reloadDependingSelects);

		return node;
	}

	async getNode() {
		let node = super.getNode();
		this.setOptions();
		return node;
	}

	setOptions(options = null) {
		if (options !== null)
			this.options['options'] = options;

		if (!this.options['options'].some(option => option.id == this.value))
			this.value = '';

		if (this.options.multilang) {
			for (let lang of Object.keys(this.node)) {
				this.setNodeOptions(this.node[lang]);
			}
		} else {
			this.setNodeOptions(this.node);
		}
	}

	setNodeOptions(node) {
		node.innerHTML = '<option value=""></option>';
		this.options['options'].forEach(option => {
			let el = document.createElement('option');
			el.value = option.id;
			el.innerHTML = option.text;
			if (option.id == this.value)
				el.setAttribute('selected', '');

			if (option.additionals) {
				for (let k of Object.keys(option.additionals))
					el.setAttribute('data-' + k, option.additionals[k]);
			}

			node.appendChild(el);
		});
	}

	async reloadOptions(parent_field = null, parent_value = null, trigger_onchange = true) {
		let currentValue = await this.getValue();

		let loading;
		if (this.rendered) {
			loading = document.createElement('img');
			loading.src = PATH + 'model/Output/files/loading.gif';
			this.rendered.parentNode.insertBefore(loading, this.rendered);
			this.rendered.addClass('d-none');
		}

		let payload = {
			'table': this.options.token.table || null,
			'element': this.options.token.element || null,
			'field': this.name,
			'additionals': this.options.token.additionals || null,
			'token': this.options.token.token
		};

		if (parent_field && parent_value) {
			payload.parent_field = parent_field;
			payload.parent_value = parent_value;
		}

		let response = await ajax(PATH + 'model-form/options', {}, payload);

		if (response && response.options)
			this.setOptions(response.options);

		if (this.rendered) {
			loading.remove();
			this.rendered.removeClass('d-none');
		}

		if (this.attemptedValue) {
			currentValue = this.attemptedValue;
			this.attemptedValue = null;
		}

		return this.setValue(currentValue, trigger_onchange);
	}

	async reloadDependingSelects(trigger_onchange = true) {
		let node = this.node;
		if (this.options.multilang)
			node = node[Object.keys(node)[0]];

		if (!node.getAttribute('data-depending-parent'))
			return;

		let fields = JSON.parse(node.getAttribute('data-depending-parent'));
		if (!fields)
			return;

		let v = await this.getValue();

		for (let f of fields) {
			let field = this.form.fields.get(f.name);
			if (!field)
				continue;

			await field.reloadOptions(this.name, v, trigger_onchange);
		}
	}
}

class FieldFile extends Field {
	constructor(name, options = {}) {
		super(name, options);

		this.cont = null;

		this.addEventListener('append', () => {
			if (this.cont && this.cont.querySelector('.file-box'))
				resizeFileBox(this.cont.querySelector('.file-box'));
		});
	}

	getSingleNode(lang = null) {
		let attributes = this.options['attributes'];

		let boxAttributes = {};
		if (attributes.hasOwnProperty('style')) {
			boxAttributes.style = attributes.style;
			delete attributes.style;
		}
		if (attributes.hasOwnProperty('class')) {
			boxAttributes.class = attributes.class;
			delete attributes.class;
		}

		this.cont = document.createElement('div');
		this.cont.setAttribute('data-file-box', this.name);

		let input = document.createElement('input');
		input.type = 'file';
		input.name = this.name;
		input.setAttribute('data-getvalue-function', 'fileGetValue');
		input.setAttribute('data-setvalue-function', 'fileSetValue');
		input.addEventListener('change', function () {
			if (typeof this.files[0] !== 'undefined')
				fileSetValue.call(this, this.files[0]);
		});

		super.assignAttributes(input, attributes);
		super.assignEvents(input, attributes, lang);

		this.cont.appendChild(input);

		let box = document.createElement('div');
		box.className = 'file-box-cont';
		box.style.display = 'none';
		super.assignAttributes(box, boxAttributes);
		this.cont.appendChild(box);

		let innerBox = document.createElement('div');
		innerBox.className = 'file-box';
		innerBox.setAttribute('data-file-cont', '');
		innerBox.addEventListener('click', event => {
			event.preventDefault();
			if (innerBox.hasAttribute('data-file-path'))
				window.open(innerBox.getAttribute('data-file-path'));
			else
				input.click();
		});
		innerBox.innerHTML = 'Upload';
		box.appendChild(innerBox);

		let tools = document.createElement('div');
		tools.className = 'file-tools';
		tools.style.display = 'none';

		let newTool = document.createElement('a');
		newTool.href = '#';
		newTool.addEventListener('click', event => {
			event.preventDefault();
			emptyExternalFileInput(this.cont);
			input.click();
		});
		newTool.innerHTML = '<img src="' + PATHBASE + 'model/Form/assets/img/upload.png" alt="" /> Carica nuovo';
		tools.appendChild(newTool);

		let deleteTool = document.createElement('a');
		deleteTool.href = '#';
		deleteTool.addEventListener('click', event => {
			event.preventDefault();
			if (confirm('Vuoi rimuovere questo file?')) {
				emptyExternalFileInput(this.cont);
				this.setValue(null);
			}
		});
		deleteTool.innerHTML = '<img src="' + PATHBASE + 'model/Form/assets/img/delete.png" alt="" /> Elimina';
		tools.appendChild(deleteTool);

		this.cont.appendChild(tools);

		return this.cont;
	}

	async setValue(v, trigger = true) {
		this.value = v;

		let node = await this.getNode();
		let inputs = node.querySelectorAll('input[type="file"]');
		for (let input of inputs)
			await input.setValue(v, trigger);
	}
}

class FieldRadio extends Field {
	constructor(name, options = {}) {
		super(name, options);
		this.cont = null;
	}

	getSingleNode(lang = null) {
		let attributes = this.options['attributes'];

		this.cont = document.createElement('div');

		this.options['options'].forEach(option => {
			let input = document.createElement('input');
			input.type = 'radio';
			input.value = option.id;

			let id = input.id;
			if (!id) {
				id = 'radio-' + Math.round(Math.random() * 100000);
				input.id = id;
			}

			super.assignAttributes(input, attributes);
			super.assignEvents(input, attributes, lang);

			let label = document.createElement('label');
			label.setAttribute('for', id);
			label.innerHTML = option.text;

			this.cont.appendChild(input);
			this.cont.appendChild(document.createTextNode(' '));
			this.cont.appendChild(label);
			this.cont.appendChild(document.createTextNode(' '));
		});

		return this.cont;
	}

	async setValue(v, trigger = true) {
		this.value = v;

		if (typeof v === 'number')
			v = v.toString();

		let node = await this.getNode();
		let inputs = node.querySelectorAll('input[type="radio"]');
		for (let input of inputs) {
			if (input.value === v)
				await input.setValue(1, trigger);
		}
	}
}

formSignatures.set('select', FieldSelect);
formSignatures.set('radio', FieldRadio);
formSignatures.set('file', FieldFile);
formSignatures.set('datetime', FieldDatetime);