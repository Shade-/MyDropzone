var MyDropzone = {
	
	textarea: '',
	options: {
		use_editor: false,
		use_imgur: true
	},
	lang: {},
	
	init: function() {
		
		MyDropzone.options.use_editor = (MyDropzone.exists(MyBBEditor)) ? true : false;
		MyDropzone.textarea = $('#message');
		
		Dropzone.autoDiscover = false;
		
		MyDropzone.inputs = {'dropzone': 1};

		var target = $('#fileupload');
		var form = target.parents('form');

		form.find(':input').filter('[type="hidden"], [name="submit"], [name="newattachment"]').each(function() {
			MyDropzone.inputs[$(this).attr('name')] = $(this).val();
		});
		
		var options = {
			url: form.attr('action'),
			paramName: 'attachment',
			addRemoveLinks: true,
			headers: {
				"Accept": null,
				"Cache-Control": null,
				"X-Requested-With": null
			},
			init: function() {
				
				this.on('sending', function(file, xhr, formData) {
		
					$.each(MyDropzone.inputs, function(k, v) {
						formData.append(k, v);
					});
		
				});
				
				this.on('success', function(file, response) {

					if (file.status == 'error') {
						return;
					}

					if (response) {
						file.aid = Number(response);
					}

					// Live update to the attachments quota
					if (!response.data) {
						MyDropzone.update_quota(file.size);
					}

				});
				
				this.on('addedfile', function(file) {

					// Custom duplicates check
					if (this.files.length) {
						var _i, _len, _ref = this.files.slice();
						for (_i = 0, _len = this.files.length; _i < _len - 1; _i++) // -1 to exclude current file
						{
							if (_ref[_i].name === file.name && _ref[_i].size === file.size) {
								this.removeFile(_ref[_i]);
							}
						}
					}

				});
				
				this.on('removedfile', function(file) {

					if (!file.aid) {
						return;
					}

					// MyBB deletion support
					var params = {};

					$.each(MyDropzone.inputs, function(k, v) {
						params[k] = v;
					});

					params['attachmentaid'] = file.aid;
					params['attachmentact'] = 'remove';

					$.ajax({
						type: 'POST',
						url: form.attr('action'),
						data: params,
						success: function() {
							MyDropzone.update_quota(- file.size); // Live update to attachments quota
						}
						
					});

				});
				
			}
			
		};
		
		if (MyDropzone.options.remove_confirmation) {
			options['dictRemoveFileConfirmation'] = MyDropzone.lang.remove_confirmation;
		}
		
		MyDropzone.instance = new Dropzone ('#fileupload', options);
		
		if (MyDropzone.options.use_imgur) {
			MyDropzone.imgur();
		}
		
	},
	
	imgur: function() {
		
		if (!MyDropzone.exists(MyDropzone.imgur_client_id)) {
			return false;
		}
		
		MyDropzone.instance.on('sending', function(file, xhr, formData) {

			// Imgur support
			if ($.inArray(file.type, ['image/png','image/jpg','image/jpeg','image/gif']) != -1) {
				xhr.open('POST', 'https://api.imgur.com/3/image', true);
				xhr.setRequestHeader('Authorization', 'Client-ID ' + MyDropzone.imgur_client_id);
				formData.append('image', file);
			}
			
		});
		
		MyDropzone.instance.on('success', function(file, response) {

			// Imgur support
			if (response.data && response.data.link) {

				if (response.status != 200 && response.data.error) {
					MyDropzone.notification(response.data.error);
				}

				file.external_link = response.data.link;

				// Add the message to the textarea
				MyDropzone.text.add('[img]' + file.external_link + '[/img]');

				if (response.data.deletehash) {
					file.deletehash = response.data.deletehash;
				}

				MyDropzone.notification(MyDropzone.lang.uploaded_to_imgur);

				return;

			}
			
		});
		
		MyDropzone.instance.on('removedfile', function(file) {

			// Imgur deletion support
			if (file.deletehash) {

				$.ajax({
					type: 'DELETE',
					url: 'https://api.imgur.com/3/image/' + file.deletehash,
					headers: {
						'Authorization': 'Client-ID ' + MyDropzone.imgur_client_id
					},
					complete: function(response) {
						if (response.status == 200) {

							// Remove all occurencies from the textarea
							if (file.external_link) {
								MyDropzone.text.remove('[img]' + file.external_link + '[/img]');
							}

							MyDropzone.notification(MyDropzone.lang.removed_from_imgur);

						}
						else if (response.data && response.data.error) {
							MyDropzone.notification(response.data.error);
						}
					}
				});

				return;

			}

			if (!file.aid) {
				return;
			}
			
		});
		
	},
	
	update_quota: function(filesize) {
							
		var target = $('strong.quota'),
			quota;
		var text = target.text();
		var value = Number(text.slice(0, -3));
		
		// Normalize to bytes
		if (text.indexOf('KB')) {
			quota = value * 1024;
		}
		else {
			quota = value * 1024 * 1024;
		}
		
		// Back to KB
		quota = (quota + filesize) / 1024;
		
		if (quota.toFixed(0) == 0) {
			return target.text('N/A');
		}
		
		if (quota < 1000) {
			return target.text(quota.toFixed(2) + ' KB');
		}
		
		// And back to MB
		return target.text( (quota / 1024).toFixed(2) + ' MB');
		
	},
	
	text: {
		
		add: function(text) {
			
			if (!MyDropzone.exists(text)) {
				return false;
			}
			
			if (MyDropzone.options.use_editor) {
				MyBBEditor.insert(text);
			}
			else {
				MyDropzone.textarea.insertAtCaret(text);
			}
			
			return true;
			
		},
		
		remove: function(search) {
			
			if (MyDropzone.options.use_editor) {
				
				MyBBEditor.val(
					MyBBEditor.val().split(search).join('')
				);
				
			}
			else {
							
				MyDropzone.textarea.val(function(i, text) {
					return text.split(search).join('');
				});
				
			}
			
		}
			
	},
	
	notification: function(text) {
		return $.jGrowl(text);	
	},
	
	exists: function(variable) {
		return (typeof variable !== 'undefined' && variable !== null && variable);
	}
	
};

jQuery.fn.extend({
	insertAtCaret: function(myValue) {
		return this.each(function(i) {
			if (document.selection) {
				//For browsers like Internet Explorer
				this.focus();
				var sel = document.selection.createRange();
				sel.text = myValue;
				this.focus();
			} else if (this.selectionStart || this.selectionStart == '0') {
				//For browsers like Firefox and Webkit based
				var startPos = this.selectionStart;
				var endPos = this.selectionEnd;
				var scrollTop = this.scrollTop;
				this.value = this.value.substring(0, startPos) + myValue + this.value.substring(endPos, this.value.length);
				this.focus();
				this.selectionStart = startPos + myValue.length;
				this.selectionEnd = startPos + myValue.length;
				this.scrollTop = scrollTop;
			} else {
				this.value += myValue;
				this.focus();
			}
		});
	}
});