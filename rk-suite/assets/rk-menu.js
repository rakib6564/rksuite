/* RK Suite — group the merged admin submenu by module and collapse every group
   except the active one (accordion). Runs on all admin pages. */
(function () {
	'use strict';
	var D = window.RKMENU;
	if (!D || !D.groups || !D.groups.length) { return; }

	function run() {
		var top = document.getElementById('toplevel_page_rk-suite');
		if (!top) { return; }
		var ul = top.querySelector('.wp-submenu');
		if (!ul || ul.dataset.rkGrouped) { return; }
		ul.dataset.rkGrouped = '1';

		var items = Array.prototype.slice.call(ul.children).filter(function (li) {
			return li.tagName === 'LI' && !li.classList.contains('wp-submenu-head');
		});

		var idx = D.globals; // keep Modules + License ungrouped at the top
		D.groups.forEach(function (g) {
			if (idx >= items.length) { return; }
			var isActive = (g.module === D.active);

			var head = document.createElement('li');
			head.className = 'rk-mgroup' + (isActive ? ' is-open' : '');
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'rk-mgroup-btn';
			btn.innerHTML = '<span>' + g.label + '</span><i class="rk-mgroup-caret"></i>';
			head.appendChild(btn);
			ul.insertBefore(head, items[idx]);

			var groupItems = [];
			for (var k = 0; k < g.count && idx < items.length; k++, idx++) {
				var li = items[idx];
				li.classList.add('rk-mi');
				groupItems.push(li);
				if (!isActive) { li.style.display = 'none'; }
			}
			btn.addEventListener('click', function () {
				var open = head.classList.toggle('is-open');
				groupItems.forEach(function (li) { li.style.display = open ? '' : 'none'; });
			});
		});
	}

	if (document.readyState !== 'loading') { run(); }
	else { document.addEventListener('DOMContentLoaded', run); }
})();
