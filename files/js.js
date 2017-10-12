function setSelect(s, v){
	if(v==null) return false;
	if(isNaN(v)) v = v.toLowerCase();
	for(var cp in s.options){
		if(typeof s.options[cp].value!='undefined' && s.options[cp].value.toLowerCase()==v){
			s.selectedIndex = cp;
			return true;
		}
	}
	s.selectedIndex = 0;
	return false;
}

var setElementValue = function(v, trigger_onchange){
	if(typeof trigger_onchange=='undefined')
		trigger_onchange = true;

	var currentValue = this.getValue();

	if(v===null)
		v = '';

	if(this instanceof NodeList){ // Radio
		this.value = v;
	}else if(this.getAttribute('data-setvalue-function')!==null){
		var func = this.getAttribute('data-setvalue-function');
		if(typeof window[func]==='undefined')
			return null;
		window[func].call(this, v, trigger_onchange);
	}else if(this.type=='checkbox' || this.type=='radio'){
		if(v==1 || v==true) this.checked = true;
		else this.checked = false;
	}else if(this.type=='select-multiple'){
		for(var i=0;i<this.options.length;i++){
			if(in_array(this.options[i].value, v))
				this.options[i].selected = true;
			else
				this.options[i].selected = false;
		}
	}else if(this.type=='select-one') setSelect(this, v);
	else if(this.type=='hidden' && this.getAttribute('data-zkra')){
		var zkra = this.getAttribute('data-zkra');
		var campo = ricerca_assistita_findMainTextInput(zkra);
		if(typeof v=='object' && campo){
			setta_ricerca_assistita(v.id, zkra, campo, v.text);
		}else{
			this.value = v;
			// Possibilit� futura: si cerca in automatico il testo da inserire nelle caselle - MA ATTENZIONE alla recursione, visto che setta_ricerca_assistita utilizza anche setValue per impostare l'id!
		}
	}else if(this.getAttribute('type')=='date'){
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
		this.value = v;
	}else if(this.nodeName.toLowerCase()=='textarea' && this.nextSibling && typeof this.nextSibling.hasClass!='undefined' && this.nextSibling.hasClass('cke')){ // CK Editor
		var found = false;
		for(var i in CKEDITOR.instances){
			if(!CKEDITOR.instances.hasOwnProperty(i)) continue;
			if(CKEDITOR.instances[i].element.$==this){
				CKEDITOR.instances[i].setData(v);
				found = true;
				break;
			}
		}
		if(!found){
			this.value = v;
		}
	}else{
		this.value = v;
	}

	if(trigger_onchange && v!=currentValue) {
		triggerOnChange(this);
	}
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

var getElementValue = function(){
	if(this instanceof NodeList){ // Radio
		var v = this.value;
	}else if(this.getAttribute('data-getvalue-function')!==null){
		var func = this.getAttribute('data-getvalue-function');
		if(typeof window[func]==='undefined')
			return null;
		var v = window[func].call(this);
	}else if(this.type==='checkbox' || this.type==='radio'){
		if(this.checked) var v = 1;
		else var v = 0;
	}else if(this.type==='select-one'){
		if(this.selectedIndex>-1)
			var v = this.options[this.selectedIndex].value;
		else var v = '';
	}else if(this.type==='select-multiple'){
		var v = [];
		for(var i=0;i<this.options.length;i++)
			if(this.options[i].selected)
				v.push(this.options[i].value);
	}else if(this.getAttribute('type')==='date'){
		var v = this.value;

		if(!isDateSupported()){
			if(v.match(/[0-9]{2}-[0-9]{2}-[0-9]{4}/)){
				v = v.split('-');
				v = v[2]+'-'+v[1]+'-'+v[0];
			}
		}
	}else{
		var v = this.value;
	}

	return v;
};

Element.prototype.getValues = function(){
	if(this.nodeName.toLowerCase()!='form')
		return [];

	var ret = {};
	var elements = this.elements;
	for (var i = 0, f; f = elements[i++];) {
		if(!f.name)
			continue;

		var type = f.type.toLowerCase();
		var v = f.getValue();

		if(type=='radio'){
			if(v){
				ret[f.name] = f.value;
			}else{
				if(typeof ret[f.name]=='undefined')
					ret[f.name] = null;
			}
		}else{
			ret[f.name] = v;
		}
	}
	return ret;
}

Element.prototype.setValues = function(values, trigger_onchange, mark){
	if(this.nodeName.toLowerCase()!=='form')
		return false;
	if(typeof trigger_onchange==='undefined')
		trigger_onchange = true;
	if(typeof mark==='undefined')
		mark = null;

	var elements = this.elements;
	for (var i = 0, f; f = elements[i++];) {
		if(mark){
			if(f.getAttribute('data-'+mark))
				continue;
		}

		if(f.getAttribute('data-multilang') && f.getAttribute('data-lang') && typeof values[f.getAttribute('data-multilang')]==='object'){
			var name = f.getAttribute('data-multilang');
			if(typeof values[name]==='undefined')
				continue;
			var value = values[name][f.getAttribute('data-lang')];
		}else{
			var name = f.name;
			if(typeof values[name]==='undefined')
				continue;
			var value = values[name];
		}

		if(!name)
			continue;

		var type = f.type.toLowerCase();

		if(type==='radio'){
			if(f.value===value)
				f.checked = true;
		}else{
			f.setValue(value, trigger_onchange);
		}

		if(mark)
			f.setAttribute('data-'+mark, '1');
	}
	return true;
}

Element.prototype.fill = Element.prototype.setValues; // Alias

Object.defineProperty(NodeList.prototype, "value", {
	get: getRadioNodeListValue,
	set: setRadioNodeListValue,
	configurable: true
});

function getRadioNodeListValue() {
	for (var i = 0, len = this.length; i < len; i++) {
		var el = this[i];
		if (el.checked) {
			return el.value;
		}
	}
}

function setRadioNodeListValue(value) {
	for (var i = 0, len = this.length; i < len; i++) {
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
			if(fileBox = el.querySelector('.file-box[data-natural-width][data-natural-height]')){
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
	var fileBox = mainBox.querySelector('.file-box');

	if(v){
		if(typeof v==='string'){
			var filename = v.split('.');
			var isImage = false;
			if(filename.length>1){
				var ext = filename.pop().toLowerCase();
				if(ext==='jpg' || ext==='jpeg' || ext==='bmp' || ext==='png' || ext==='gif')
					isImage = true;
			}

			if(isImage){
				setFileImage(fileBox, base_path+v);
			}else{
				var filename = v.split('/').pop();
				setFileText(fileBox, filename);
				fileBox.setAttribute('onclick', 'window.open(\''+base_path+v+'\')');
			}
		}else{
			var reader = new FileReader();
			reader.onload = (function(box, file) {
				return function(e){
					var mime = e.target.result.match(/^data:(.*);/)[1];
					var fileBox = box.querySelector('.file-box');

					if(in_array(mime, ['image/jpeg', 'image/png', 'image/gif', 'image/x-png', 'image/pjpeg'])){
						setFileImage(fileBox, e.target.result);
					}else{
						setFileText(fileBox, file.name);
					}
				};
			})(mainBox, v);
			reader.readAsDataURL(v);
		}
	}else{
		setFileText(fileBox, '<img src="'+base_path+'model/Form/files/img/upload.png" alt="Upload" title="Upload" /> Click to upload');
		fileBox.setAttribute('onclick', 'document.getElementById(\'file-input-'+mainBox.getAttribute('data-file-box')+'\').click(); return false');
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