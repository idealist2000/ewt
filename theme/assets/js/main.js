document.addEventListener('DOMContentLoaded', function () {
	var toggle = document.getElementById('ewt-mobile-toggle');
	var nav = document.querySelector('.ewt-nav');
	if (toggle && nav) {
		toggle.addEventListener('click', function () {
			var open = nav.classList.toggle('ewt-nav-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	// Mobilde mega menu alt panellerini tıklayarak aç/kapat (hover mobilde çalışmaz)
	var megaItems = document.querySelectorAll('.ewt-menu-item.has-mega > .ewt-menu-link');
	megaItems.forEach(function (link) {
		link.addEventListener('click', function (e) {
			if (window.innerWidth > 900) return; // masaüstünde hover zaten çalışıyor
			e.preventDefault();
			this.parentElement.classList.toggle('ewt-mobile-open');
		});
	});

	// Menü dışına tıklayınca mobil menüyü kapat
	document.addEventListener('click', function (e) {
		if (!nav || window.innerWidth > 900) return;
		if (!nav.contains(e.target) && e.target !== toggle && !toggle.contains(e.target)) {
			nav.classList.remove('ewt-nav-open');
			if (toggle) toggle.setAttribute('aria-expanded', 'false');
		}
	});
});
