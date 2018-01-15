function setSelect(s, v){
	if(v==null) return false;
	if(isNaN(v)) v = v.toLowerCase();
	for(let cp in s.options){
		if(typeof s.options[cp].value!='undefined' && s.options[cp].value.toLowerCase()==v){
			s.selectedIndex = cp;
			return true;
		}
	}
	s.selectedIndex = 0;
	return false;
}

var setElementValue = function(v, trigger_onchange){
	if(typeof trigger_onchange==='undefined')
		trigger_onchange = true;

	if(v===null)
		v = '';

	return this.getValue().then((function(element, v, trigger_onchange){
		return function(currentValue){
			if(this instanceof NodeList){ // Radio
				element.value = v;
			}else if(element.getAttribute('data-setvalue-function')!==null){
				var func = element.getAttribute('data-setvalue-function');
				if(typeof window[func]==='undefined')
					return null;
				window[func].call(element, v, trigger_onchange);
			}else if(element.type==='checkbox' || element.type==='radio'){
				if(v==1 || v==true) element.checked = true;
				else element.checked = false;
			}else if(element.type==='select-multiple'){
				for(let i=0;i<element.options.length;i++){
					if(in_array(element.options[i].value, v))
						element.options[i].selected = true;
					else
						element.options[i].selected = false;
				}
			}else if(element.type==='select-one') setSelect(element, v);
			else if(element.type==='hidden' && element.getAttribute('data-zkra')){
				var zkra = element.getAttribute('data-zkra');
				var campo = ricerca_assistita_findMainTextInput(zkra);
				if(typeof v==='object' && campo){
					setta_ricerca_assistita(v.id, zkra, campo, v.text);
				}else{
					element.value = v;
					// Possibilit� futura: si cerca in automatico il testo da inserire nelle caselle - MA ATTENZIONE alla recursione, visto che setta_ricerca_assistita utilizza anche setValue per impostare l'id!
				}
			}else if(element.getAttribute('type')==='date'){
				if(isDateSupported()){
					if(v.match(/[0-9]{2}-[0-9]{2}-[0-9]{4}/)){
						v = v.split('-');
						v = v[2]+'-'+v[1]+'-'+v[0];
					}
				}else{
					if(v.match(/[0-9]{4}-[0-9]{2}-[0-9]{2}/)){
						v = v.split('-');
						v = v[2]+'-'+v[1]+'-'+v[0];
					}
				}
				element.value = v;
			}else if(element.nodeName.toLowerCase()==='textarea' && element.nextSibling && typeof element.nextSibling.hasClass!='undefined' && element.nextSibling.hasClass('cke')){ // CK Editor
				var found = false;
				for(let i in CKEDITOR.instances){
					if(!CKEDITOR.instances.hasOwnProperty(i)) continue;
					if(CKEDITOR.instances[i].element.$==element){
						CKEDITOR.instances[i].setData(v);
						found = true;
						break;
					}
				}
				if(!found){
					element.value = v;
				}
			}else{
				element.value = v;
			}

			if(trigger_onchange && v!==currentValue) {
				triggerOnChange(this);
			}
		};
	})(this, v, trigger_onchange));
};

function triggerOnChange(field){
	if ("createEvent" in document) {
		var evt = document.createEvent("HTMLEvents");
		evt.initEvent("change", false, true);
		field.dispatchEvent(evt);
	}else{
		field.fireEvent("onchange");
	}
}

var basicGetElementValue = function(element){
	var v = null;

	if(this instanceof NodeList){ // Radio
		v = element.value;
	}else if(element.getAttribute('data-getvalue-function')!==null){
		var func = element.getAttribute('data-getvalue-function');
		if(typeof window[func]==='undefined')
			return null;
		v = window[func].call(element);
	}else if(element.type==='checkbox' || element.type==='radio'){
		if(element.checked) v = 1;
		else v = 0;
	}else if(element.type==='select-one'){
		if(element.selectedIndex>-1)
			v = element.options[element.selectedIndex].value;
		else v = '';
	}else if(element.type==='select-multiple'){
		v = [];
		for(let i=0;i<element.options.length;i++)
			if(element.options[i].selected)
				v.push(element.options[i].value);
	}else if(element.getAttribute('type')==='date'){
		v = element.value;

		if(!isDateSupported()){
			if(v.match(/[0-9]{2}-[0-9]{2}-[0-9]{4}/)){
				v = v.split('-');
				v = v[2]+'-'+v[1]+'-'+v[0];
			}
		}
	}else{
		v = element.value;
	}

	return v;
};

var getElementValue = function(direct_value){
	if(typeof direct_value === 'undefined')
		direct_value = false;

	if(direct_value){
		return basicGetElementValue(this);
	}else{
		return new Promise((function(element){
			return function(resolve){
				let v = basicGetElementValue(element);
				resolve(v);
			};
		})(this));
	}
};

Element.prototype.getValues = function(){
	if(this.nodeName.toLowerCase()!=='form')
		return new Promise(function(resolve){ resolve([]); });

	var promises = [];
	var elements = this.elements;
	for(let i = 0, f; f = elements[i++];) {
		if(!f.name)
			continue;

		promises.push(f.getValue().then((function(name, type){
			return function(v){
				if(type==='radio' && !v)
					v = null;

				return [name, v];
			};
		})(f.name, f.type.toLowerCase())));
	}

	return Promise.all(promises).then(function(data){
		var ret = {};

		data.forEach(function(v, k){
			ret[k] = v;
		});

		return ret;
	});
}

Element.prototype.setValues = function(values, trigger_onchange, mark){
	if(this.nodeName.toLowerCase()!=='form')
		return false;
	if(typeof trigger_onchange==='undefined')
		trigger_onchange = true;
	if(typeof mark==='undefined')
		mark = null;

	let promises = [];

	let elements = this.elements;
	for(let i = 0, f; f = elements[i++];) {
		if(mark){
			if(f.getAttribute('data-'+mark))
				continue;
		}

		let name;
		let value;

		if(f.getAttribute('data-multilang') && f.getAttribute('data-lang') && typeof values[f.getAttribute('data-multilang')]==='object'){
			name = f.getAttribute('data-multilang');
			if(typeof values[name]==='undefined')
				continue;
			value = values[name][f.getAttribute('data-lang')];
		}else{
			name = f.name;
			if(typeof values[name]==='undefined')
				continue;
			value = values[name];
		}

		if(!name)
			continue;

		if(f.type.toLowerCase()==='radio'){
			if(f.value===value)
				f.checked = true;
		}else{
			promises.push(f.setValue(value, trigger_onchange).then(((f, mark) => {
				return () => {
					if(mark)
						f.setAttribute('data-'+mark, '1');

					return f;
				};
			})(f, mark)));
		}
	}

	return Promise.all(promises);
}

Element.prototype.fill = Element.prototype.setValues; // Alias

Object.defineProperty(NodeList.prototype, "value", {
	get: getRadioNodeListValue,
	set: setRadioNodeListValue,
	configurable: true
});

function getRadioNodeListValue() {
	for(let i = 0, len = this.length; i < len; i++) {
		var el = this[i];
		if (el.checked) {
			return el.value;
		}
	}
}

function setRadioNodeListValue(value) {
	for(let i = 0, len = this.length; i < len; i++) {
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

function switchFieldLang(name, lang){
	var main = document.querySelector('.multilang-field-container[data-name="'+name+'"]');
	if(!main)
		return false;

	var currentFlag = main.querySelector('.multilang-field-lang-container > a[data-lang]');
	if(!currentFlag || currentFlag.getAttribute('data-lang')===lang)
		return false;

	var newFlag = main.querySelector('.multilang-field-other-langs-container > a[data-lang="'+lang+'"]');
	if(!newFlag)
		return false;

	main.querySelector('.multilang-field-lang-container').insertBefore(newFlag, currentFlag);
	main.querySelector('.multilang-field-other-langs-container').appendChild(currentFlag);

	main.querySelectorAll('.multilang-field-container > [data-lang]').forEach(function(el){
		if(el.getAttribute('data-lang')===lang){
			el.style.display = 'block';
			if(fileBox = el.querySelector('[data-file-cont][data-natural-width][data-natural-height]')){
				resizeFileBox(fileBox);
			}
		}else{
			el.style.display = 'none';
		}
	});
}

function fileGetValue(){
	var promises = [];
	for(i=0;i<this.files.length;i++){
		promises.push(new Promise((function(file){
			return function(resolve){
				var reader = new FileReader();
				reader.onload = function(e){
					var mime = e.target.result.match(/^data:(.*);/)[1];
					resolve({
						'name': file.name,
						'file': e.target.result.substr(('data:'+mime+';base64,').length),
						'type': mime
					});
				};
				reader.readAsDataURL(file);
			}
		})(this.files[i])));
	}

	return Promise.all(promises).then(function(values){
		if(values.length===0)
			return null;
		else
			return values;
	});
}

function fileSetValue(v){
	var mainBox = this.parentNode;
	var fileBoxCont = mainBox.querySelector('.file-box-cont');
	var fileBox = mainBox.querySelector('[data-file-cont]');
	var fileTools = mainBox.querySelector('.file-tools');

	if(v){
		fileBoxCont.style.display = 'block';
		fileTools.style.display = 'block';
		this.style.display = 'none';

		var isImage = false;

		if(typeof v==='string'){
			var filename = v.split('.');
			if(filename.length>1){
				var ext = filename.pop().toLowerCase();
				if(ext==='jpg' || ext==='jpeg' || ext==='bmp' || ext==='png' || ext==='gif')
					isImage = true;
			}

			if(isImage && !this.getAttribute('data-only-text')){
				setFileImage(fileBox, base_path+v);
			}else{
				var filename = v.split('/').pop();
				setFileText(fileBox, filename);
				fileBox.setAttribute('onclick', 'window.open(\''+base_path+v+'\')');
			}
		}else{
			var reader = new FileReader();
			reader.onload = (function(box, file, field) {
				return function(e){
					var mime = e.target.result.match(/^data:(.*);/)[1];
					var fileBox = box.querySelector('[data-file-cont]');

					if(in_array(mime, ['image/jpeg', 'image/png', 'image/gif', 'image/x-png', 'image/pjpeg']) && !field.getAttribute('data-only-text')){
						setFileImage(fileBox, e.target.result);
					}else{
						setFileText(fileBox, file.name);
					}
				};
			})(mainBox, v, this);
			reader.readAsDataURL(v);
		}
	}else{
		fileTools.style.display = 'none';
		fileBoxCont.style.display = 'none';
		this.style.display = 'inline-block';
	}
}

function setFileImage(box, i){
	var img = new Image();
	img.onload = (function(box, i){
		return function(){
			box.setAttribute('data-natural-width', this.naturalWidth);
			box.setAttribute('data-natural-height', this.naturalHeight);

			resizeFileBox(box);

			box.style.backgroundImage = "url('"+i+"')";
			box.innerHTML = '';

			if(i.charAt(0)==='/')
				box.setAttribute('onclick', 'window.open(\''+i+'\')');
			else
				box.removeAttribute('onclick');
		};
	})(box, i);
	img.src = i;
}

function setFileText(box, text){
	box.style.backgroundImage = '';
	box.innerHTML = text;

	box.style.width = '100%';
	box.style.height = 'auto';

	box.removeAttribute('data-natural-width');
	box.removeAttribute('data-natural-height');

	box.removeAttribute('onclick');
}

function resizeFileBox(box){
	var w = parseInt(box.getAttribute('data-natural-width'));
	var h = parseInt(box.getAttribute('data-natural-height'));

	box.style.width = '100%';
	var boxW = box.offsetWidth;
	if(boxW>w)
		boxW = w;

	var boxH = Math.round(h/w*boxW);
	box.style.width = boxW+'px';
	box.style.height = boxH+'px';
}