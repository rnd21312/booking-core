/* Media-library picker for the destination / travel style image field. Vanilla JS. */
(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var pick = event.target.closest('[data-stz-image-pick]');
		var clear = event.target.closest('[data-stz-image-clear]');
		if (!pick && !clear) {
			return;
		}

		var box = event.target.closest('.stz-term-image');
		var input = box.querySelector('[data-stz-image-id]');
		var preview = box.querySelector('[data-stz-image-preview]');

		if (clear) {
			input.value = '0';
			preview.style.display = 'none';
			preview.removeAttribute('src');
			return;
		}

		if (!window.wp || !window.wp.media) {
			return;
		}

		var frame = window.wp.media({ title: 'Choose image', button: { text: 'Use image' }, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			input.value = String(attachment.id);
			preview.src = (attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url);
			preview.style.display = 'block';
		});
		frame.open();
	});
})();
