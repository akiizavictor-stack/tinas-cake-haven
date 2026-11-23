/**
 * Tina's Cake Haven – order forms.
 * Shows/hides fields and displays a live ESTIMATE only. The server recalculates
 * every price from the database; nothing here is trusted.
 */
(function () {
	'use strict';

	var money = function (n) { return 'UGX ' + Math.round(n).toLocaleString('en-US'); };
	var json = function (el) { try { return JSON.parse(el ? el.textContent : '{}') || {}; } catch (e) { return {}; } };

	function setupFulfilment(form) {
		var wrap = form.querySelector('[data-tch-delivery]');
		if (!wrap) { return; }
		var input = wrap.querySelector('input');
		var sync = function () {
			var checked = form.querySelector('input[name="fulfilment"]:checked');
			var on = checked && checked.value === 'delivery';
			wrap.hidden = !on;
			if (input) { input.required = on; }
		};
		form.querySelectorAll('input[name="fulfilment"]').forEach(function (r) { r.addEventListener('change', sync); });
		sync();
	}

	function planShare(form, total) {
		var pct = parseInt(form.getAttribute('data-tch-deposit'), 10) || 50;
		var plan = form.querySelector('input[name="payment_plan"]:checked');
		return plan && plan.value === 'deposit' ? Math.ceil(total * pct / 100) : total;
	}

	function showSummary(form, total) {
		var box = form.querySelector('[data-tch-summary]');
		if (!box) { return; }
		box.hidden = total <= 0;
		box.querySelector('[data-tch-total]').textContent = money(total);
		box.querySelector('[data-tch-now]').textContent = money(planShare(form, total));
	}

	/* ---------- Order Online ---------- */
	function setupOrderForm(form) {
		var products = json(form.querySelector('[data-tch-products]'));
		var list = form.querySelector('[data-tch-items]');
		var tpl = form.querySelector('[data-tch-item-template]');
		var addBtn = form.querySelector('[data-tch-add]');
		var counter = list.querySelectorAll('[data-tch-item]').length;

		function syncRow(row) {
			var pid = row.querySelector('[data-tch-product]').value;
			var p = products[pid];
			var previousPid = row.getAttribute('data-tch-product-id') || '';
			var productChanged = previousPid !== pid;
			row.setAttribute('data-tch-product-id', pid);
			var sizeWrap = row.querySelector('[data-tch-size-wrap]');
			var flavWrap = row.querySelector('[data-tch-flavour-wrap]');
			var size = row.querySelector('[data-tch-size]');
			var flav = row.querySelector('[data-tch-flavour]');
			var qty = row.querySelector('[data-tch-qty]');
			var hint = row.querySelector('[data-tch-qty-hint]');
			var sized = !!p && p.type === 'sized';
			sizeWrap.hidden = !sized;
			size.required = sized;
			flavWrap.hidden = !(p && p.flavours);
			flav.required = !!(p && p.flavours);
			if (sized) {
				var allowed = p.sizes.map(function (s) { return String(s.id); });
				Array.prototype.forEach.call(size.options, function (o) { if (o.value) { o.hidden = o.disabled = allowed.indexOf(o.value) === -1; } });
				if (size.value && allowed.indexOf(size.value) === -1) { size.value = ''; }
				if (allowed.length === 1) { size.value = allowed[0]; }
			}
			if (p) {
				qty.min = p.min; qty.max = p.max;
				qty.step = p.type === 'bundle' ? p.bundle : 1;
				hint.textContent = p.type === 'bundle' ? '(packs of ' + p.bundle + ')' : '';
				var currentQty = parseInt(qty.value, 10) || 0;
				if (productChanged && (currentQty < p.min || currentQty > p.max || currentQty % parseInt(qty.step, 10) !== 0)) {
					qty.value = p.min;
				} else if (!qty.value) {
					qty.value = p.min;
				}
			} else {
				hint.textContent = '';
				qty.setCustomValidity('');
				if ( productChanged ) { qty.value = ''; }
			}
			var line = 0;
			var q = parseInt(qty.value, 10) || 0;
			var quantityValid = !!p && q >= p.min && q <= p.max && 0 === q % ( p.type === 'bundle' ? p.bundle : 1 );
			if ( p && !quantityValid ) {
				qty.setCustomValidity( p.type === 'bundle' ? 'Choose a multiple of ' + p.bundle + ' cookies.' : 'Choose a quantity between ' + p.min + ' and ' + p.max + '.' );
			} else {
				qty.setCustomValidity('');
			}
			if (p && q > 0) {
				if (sized) {
					var s = p.sizes.filter(function (x) { return String(x.id) === size.value; })[0];
					line = s ? s.price * q : 0;
				} else if (p.type === 'bundle') {
					line = q % p.bundle === 0 ? (q / p.bundle) * p.price : 0;
				} else {
					line = p.price * q;
				}
			}
			row.querySelector('[data-tch-line]').textContent = line ? money(line) : '';
			return line;
		}

		function recalc() {
			var total = 0;
			var rows = list.querySelectorAll('[data-tch-item]');
			rows.forEach(function (r) { total += syncRow(r); });
			rows.forEach(function (r) { r.querySelector('[data-tch-remove]').hidden = rows.length < 2; });
			showSummary(form, total);
		}

		function bind(row) {
			row.addEventListener('change', recalc);
			row.addEventListener('input', recalc);
			row.querySelector('[data-tch-remove]').addEventListener('click', function () { row.remove(); recalc(); });
		}

		list.querySelectorAll('[data-tch-item]').forEach(bind);
		if (tpl && addBtn) {
			addBtn.hidden = false;
			addBtn.addEventListener('click', function () {
				if (list.querySelectorAll('[data-tch-item]').length >= 15) { return; }
				var html = tpl.innerHTML.replace(/__i__/g, String(counter++));
				var holder = document.createElement('div');
				holder.innerHTML = html;
				var row = holder.firstElementChild;
				list.appendChild(row);
				bind(row);
				recalc();
				row.querySelector('[data-tch-product]').focus();
			});
		}
		form.querySelectorAll('input[name="payment_plan"]').forEach(function (r) { r.addEventListener('change', recalc); });
		recalc();
	}

	/* ---------- Customized cake ---------- */
	function setupCustomForm(form) {
		var prices = json(form.querySelector('[data-tch-size-prices]'));
		var size = form.querySelector('[data-tch-custom-size]');
		var recalc = function () { showSummary(form, prices[size.value] || 0); };
		size.addEventListener('change', recalc);
		form.querySelectorAll('input[name="payment_plan"]').forEach(function (r) { r.addEventListener('change', recalc); });
		recalc();
	}

	function preventDoubleSubmit(form) {
		form.addEventListener('submit', function (event) {
			var invalidQuantity = Array.prototype.find.call( form.querySelectorAll('[data-tch-qty]'), function (quantity) {
				return ! quantity.checkValidity();
			} );
			if ( invalidQuantity ) {
				event.preventDefault();
				invalidQuantity.reportValidity();
				return;
			}
			var b = form.querySelector('button[type="submit"]');
			if (b) { setTimeout(function () { b.disabled = true; b.textContent = 'Sending…'; }, 0); }
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-tch-order-form]').forEach(function (f) { setupFulfilment(f); setupOrderForm(f); preventDoubleSubmit(f); });
		document.querySelectorAll('[data-tch-custom-form]').forEach(function (f) { setupFulfilment(f); setupCustomForm(f); preventDoubleSubmit(f); });
	});
}());
