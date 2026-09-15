(function () {
	function init() {
		var root = document.getElementById('legacy-vacancies');
		if (!root) {
			return;
		}
		var base = root.getAttribute('data-base') || '/vacancies/';
		var favOn = root.getAttribute('data-fav-on') || '★ В избранном';
		var favOff = root.getAttribute('data-fav-off') || '☆ В избранное';
		var favError = root.getAttribute('data-fav-error') || 'Не удалось обновить избранное';

		root.addEventListener('click', function (e) {
			var link = e.target.closest('.lv-fav');
			if (!link) {
				return;
			}
			e.preventDefault();
			var id = link.getAttribute('data-id');
			fetch(base + 'ajax.php?action=favorite&id=' + encodeURIComponent(id), {credentials: 'same-origin'})
				.then(function (r) {
					return r.json();
				})
				.then(function (data) {
					if (!data || !data.success) {
						alert(data && data.error ? data.error : favError);
						return;
					}
					link.classList.toggle('lv-fav-on', !!data.favorite);
					if (link.textContent.trim() !== '★') {
						link.textContent = data.favorite ? favOn : favOff;
					}
				})
				.catch(function () {
					alert(favError);
				});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
