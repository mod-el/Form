function setSelect(s, v) {
	if (v == null)
		return false;

	for (let cp in s.options) {
		if (typeof s.options[cp].value !== 'undefined' && s.options[cp].value.toString() === v.toString()) {
			s.selectedIndex = cp;
			if (s.getAttribute('data-attempted-value'))
				s.removeAttribute('data-attempted-value');
			return true;
		}
	}

	s.setAttribute('data-attempted-value', v);
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
		return function (currentValue) {
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
			else if (element.getAttribute('data-depending-parent'))
				reloadDependingSelects(element, trigger_onchange);

			return ret;
		};
	})(this, v, trigger_onchange));
};

function triggerOnChange(field) {
	if ("createEvent" in document) {
		var evt = document.createEvent("HTMLEvents");
		evt.initEvent("change", false, true);
		field.dispatchEvent(evt);
	} else {
		field.fireEvent("onchange");
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

function switchFieldLang(name, lang) {
	var main = document.querySelector('.multilang-field-container[data-name="' + name + '"]');
	if (!main)
		return false;

	var currentFlag = main.querySelector('.multilang-field-lang-container > a[data-lang]');
	if (!currentFlag || currentFlag.getAttribute('data-lang') === lang)
		return false;

	var newFlag = main.querySelector('.multilang-field-other-langs-container > a[data-lang="' + lang + '"]');
	if (!newFlag)
		return false;

	main.querySelector('.multilang-field-lang-container').insertBefore(newFlag, currentFlag);
	main.querySelector('.multilang-field-other-langs-container').appendChild(currentFlag);

	main.querySelectorAll('.multilang-field-container > [data-lang]').forEach(function (el) {
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

		var isImage = false;

		if (typeof v === 'string') {
			var filename = v.split('.');
			if (filename.length > 1) {
				var ext = filename.pop().toLowerCase();
				if (ext === 'jpg' || ext === 'jpeg' || ext === 'bmp' || ext === 'png' || ext === 'gif')
					isImage = true;
			}

			if (isImage && !this.getAttribute('data-only-text')) {
				if (v.toLowerCase().indexOf('http://') !== 0 && v.toLowerCase().indexOf('https://') !== 0)
					v = PATHBASE + v;
				return setFileImage(fileBox, v + "?nocache=" + Math.random());
			} else {
				var filename = v.split('/').pop();
				fileBox.setAttribute('onclick', 'window.open(\'' + PATHBASE + v + '\')');
				return setFileText(fileBox, filename);
			}
		} else {
			return new Promise((function (field) {
				return function (resolve) {
					var reader = new FileReader();
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
		this.style.display = 'inline-block';
		this.value = null;
		return true;
	}
}

function setFileImage(box, i) {
	return new Promise(function (resolve) {
		var img = new Image();
		img.onload = (function (box, i) {
			return function () {
				box.setAttribute('data-natural-width', this.naturalWidth);
				box.setAttribute('data-natural-height', this.naturalHeight);

				resizeFileBox(box);

				box.style.backgroundImage = "url('" + i + "')";
				box.innerHTML = '';

				if (i.charAt(0) === '/')
					box.setAttribute('onclick', 'window.open(\'' + i + '\')');
				else
					box.removeAttribute('onclick');

				resolve();
			};
		})(box, i);
		img.onerror = (function (box) {
			return function () {
				box.removeAttribute('data-natural-width');
				box.removeAttribute('data-natural-height');

				box.removeAttribute('onclick');

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
	var boxW = box.offsetWidth;
	if (boxW > w)
		boxW = w;

	var boxH = Math.round(h / w * boxW);
	box.style.width = boxW + 'px';
	box.style.height = boxH + 'px';
}

function checkForm(form, mandatory) {
	var required = form.querySelectorAll('input[required], select[required], textarea[required]');
	for (var idx in required) {
		if (!required.hasOwnProperty(idx))
			continue;
		if (required[idx].offsetParent === null)
			continue;
		if (required[idx].name && mandatory.indexOf(required[idx].name) === -1)
			mandatory.push(required[idx].name);
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

	if (form[name].type === 'checkbox' && v.toString() === 0)
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

function reloadDependingSelects(parent, trigger_onchange) {
	if (parent.hasAttribute('data-attempted-value'))
		return;

	if (typeof trigger_onchange === 'undefined')
		trigger_onchange = true;

	let form = parent.form;
	if (!form || !parent.getAttribute('data-depending-parent'))
		return;
	let fields = JSON.parse(parent.getAttribute('data-depending-parent'));
	if (!fields)
		return;

	parent.getValue().then(function (parentV) {
		fields.forEach(function (f) {
			if (typeof form[f.name] === 'undefined')
				return;

			form[f.name].getValue().then(function (v) {
				let img = document.createElement('img');
				img.src = PATHBASE + 'model/Output/files/loading.gif';
				form[f.name].parentNode.insertBefore(img, form[f.name]);
				form[f.name].style.display = 'none';

				ajax(PATH + 'model-form', '', {
					'field': JSON.stringify(f),
					'v': parentV
				}).then(function (r) {
					if (typeof r !== 'object') {
						throw r;
					} else {
						form[f.name].innerHTML = '';
						r.forEach(function (opt) {
							let option = document.createElement('option');
							option.value = opt.id;
							option.innerHTML = opt.text;
							Object.keys(opt['additional-fields']).forEach(function (k) {
								option.setAttribute('data-' + k, opt['additional-fields'][k]);
							});
							form[f.name].appendChild(option);
						});
						form[f.name].style.display = '';
						img.parentNode.removeChild(img);

						if (form[f.name].hasAttribute('data-attempted-value')) {
							if (form[f.name].hasOption(form[f.name].getAttribute('data-attempted-value'))) {
								form[f.name].setValue(form[f.name].getAttribute('data-attempted-value'), trigger_onchange);
								form[f.name].removeAttribute('data-attempted-value');
							}
						} else {
							if (trigger_onchange)
								triggerOnChange(form[f.name]);
						}
						if (form[f.name].getAttribute('data-depending-parent'))
							reloadDependingSelects(form[f.name], trigger_onchange);
					}
				});
			});
		});
	}).catch(function (err) {
		alert(err);
	});
}

function switchAllFieldsLang(lang) {
	Array.from(document.querySelectorAll('.lang-switch-cont [data-lang]')).forEach(function (el) {
		if (el.getAttribute('data-lang') === lang)
			el.addClass('selected');
		else
			el.removeClass('selected');
	});

	document.querySelectorAll('.multilang-field-container[data-name]').forEach(function (f) {
		switchFieldLang(f.getAttribute('data-name'), lang);
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