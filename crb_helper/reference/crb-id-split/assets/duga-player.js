(function () {
	'use strict';

	function playPreview(preview) {
		var wrap = preview.closest('.crb-duga-player');
		var video = preview.querySelector('video.play-video');
		if (!wrap || !video) {
			return;
		}
		wrap.classList.add('is-playing');
		var playPromise = video.play();
		if (playPromise && typeof playPromise.catch === 'function') {
			playPromise.catch(function () {
				video.setAttribute('controls', 'controls');
			});
		}
	}

	document.addEventListener(
		'click',
		function (event) {
			var preview = event.target.closest('.crb-duga-player .sample-preview');
			if (!preview) {
				return;
			}
			event.preventDefault();
			playPreview(preview);
		},
		true
	);
})();
