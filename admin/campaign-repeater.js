/**
 * Campaign tiers/goals repeater — plain JS, no build step (same convention as
 * blocks/editor.js). Wires "+ Add" (clones a <template> row), "Remove" (event
 * delegation, so it works on both pre-rendered and newly cloned rows), and the
 * goal rows' wp.media() image picker.
 */
(function () {
	function onReady(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	function initRepeater(root) {
		var rows = root.querySelector('.teda-repeater__rows');
		var template = root.querySelector('template[data-row-template]');
		var addButton = root.querySelector('[data-add-row]');

		if (addButton && template && rows) {
			addButton.addEventListener('click', function () {
				var fragment = template.content.cloneNode(true);
				rows.appendChild(fragment);
			});
		}

		root.addEventListener('click', function (e) {
			var removeRow = e.target.closest('[data-remove-row]');
			if (removeRow) {
				e.preventDefault();
				var row = removeRow.closest('[data-row]');
				if (row) {
					row.parentNode.removeChild(row);
				}
				return;
			}

			var selectImage = e.target.closest('[data-select-image]');
			if (selectImage && window.wp && window.wp.media) {
				e.preventDefault();
				var row = selectImage.closest('[data-row]');
				var input = row.querySelector('[data-image-input]');
				var preview = row.querySelector('[data-image-preview]');
				var removeImageBtn = row.querySelector('[data-remove-image]');

				var frame = window.wp.media({
					title: selectImage.getAttribute('data-title') || 'Select image',
					multiple: false,
				});
				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					input.value = attachment.id;
					var thumb = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
					while (preview.firstChild) {
						preview.removeChild(preview.firstChild);
					}
					var img = document.createElement('img');
					img.src = thumb;
					img.style.maxWidth = '80px';
					img.style.height = 'auto';
					preview.appendChild(img);
					if (removeImageBtn) {
						removeImageBtn.hidden = false;
					}
				});
				frame.open();
				return;
			}

			var removeImage = e.target.closest('[data-remove-image]');
			if (removeImage) {
				e.preventDefault();
				var goalRow = removeImage.closest('[data-row]');
				var imageInput = goalRow.querySelector('[data-image-input]');
				var imagePreview = goalRow.querySelector('[data-image-preview]');
				imageInput.value = 0;
				while (imagePreview.firstChild) {
					imagePreview.removeChild(imagePreview.firstChild);
				}
				removeImage.hidden = true;
			}
		});
	}

	onReady(function () {
		var repeaters = document.querySelectorAll('.teda-repeater');
		for (var i = 0; i < repeaters.length; i++) {
			initRepeater(repeaters[i]);
		}
	});
})();
