(function () {
	'use strict';

	window.crbAdminBuild = '0.6.0-feed43';

	var form = document.querySelector('.crb-feed-form');
	if (!form) {
		return;
	}

	var SCROLL_RESTORE_KEY = 'crb_admin_scroll_y';

	function restoreScrollAfterPreview() {
		try {
			var raw = sessionStorage.getItem(SCROLL_RESTORE_KEY);
			if (raw === null || raw === '') {
				return;
			}
			sessionStorage.removeItem(SCROLL_RESTORE_KEY);
			var y = parseInt(raw, 10);
			if (isNaN(y) || y < 1) {
				return;
			}
			var apply = function () {
				window.scrollTo(0, y);
			};
			if (document.readyState === 'complete') {
				requestAnimationFrame(apply);
			} else {
				window.addEventListener('load', function onLoad() {
					window.removeEventListener('load', onLoad);
					requestAnimationFrame(apply);
				});
			}
		} catch (e) {
			/* sessionStorage unavailable */
		}
	}

	form.addEventListener('submit', function (ev) {
		var sub = ev.submitter;
		if (!sub || sub.name !== 'crb_action' || sub.value !== 'preview_posts') {
			return;
		}
		try {
			sessionStorage.setItem(
				SCROLL_RESTORE_KEY,
				String(window.scrollY || window.pageYOffset || 0)
			);
		} catch (e) {
			/* ignore */
		}
	});

	restoreScrollAfterPreview();

	var cfg = window.crbAdmin || {};
	var i18n = cfg.i18n || {};

	var preset = {
		scope_selector: '#review_list',
		item_selector: '.review_contents',
		link_selector: 'dt.work_name a[href*="product_id"]',
		title_mode: 'attr',
		title_attr: 'title',
		title_selector: '',
		image_selector: '.review_work .work_img_popover img',
		author_selector: 'dd.maker_name span.author a',
		review_title_selector: '.reveiw_title a[href*="reviewlist"]',
		summary_selector: 'dd.work_text',
		category_selector: '.review_work .work_category a',
		review_body_selector: '.review_main p.review_desc'
	};


	function getMode() {
		var checked = form.querySelector('input[name="extraction_mode"]:checked');
		return checked ? checked.value : 'css';
	}

	function togglePanels() {
		var mode = getMode();
		form.querySelectorAll('.crb-extract-panel').forEach(function (panel) {
			panel.hidden = panel.getAttribute('data-crb-mode') !== mode;
		});
		var template = form.querySelector('#crb-template');
		if (template) {
			template.required = mode === 'template';
		}
		toggleSlotAttrFields();
	}

	function toggleSlotAttrFields() {
		var modeEl = document.getElementById('crb-slot-0-mode');
		var attrEl = document.getElementById('crb-slot-0-attr');
		if (!modeEl || !attrEl) {
			return;
		}
		var show = modeEl.value === 'attr' || modeEl.value === 'el_attr';
		attrEl.hidden = !show;
		attrEl.disabled = !show;
	}

	function applyPreset() {
		var cssRadio = form.querySelector('input[name="extraction_mode"][value="css"]');
		if (cssRadio) {
			cssRadio.checked = true;
		}
		document.getElementById('crb-css-scope').value = preset.scope_selector;
		document.getElementById('crb-css-item').value = preset.item_selector;
		var linkSel = document.getElementById('crb-slot-1-sel');
		var titleMode = document.getElementById('crb-slot-0-mode');
		var titleAttr = document.getElementById('crb-slot-0-attr');
		var titleSel = document.getElementById('crb-slot-0-sel');
		if (linkSel) {
			linkSel.value = preset.link_selector;
		}
		if (titleMode) {
			titleMode.value = preset.title_mode;
		}
		if (titleAttr) {
			titleAttr.value = preset.title_attr;
		}
		if (titleSel) {
			titleSel.value = preset.title_selector;
		}
		var img = document.getElementById('crb-css-image');
		var author = document.getElementById('crb-css-author');
		var revTitle = document.getElementById('crb-css-review-title');
		if (img) {
			img.value = preset.image_selector;
		}
		if (author) {
			author.value = preset.author_selector;
		}
		if (revTitle) {
			revTitle.value = preset.review_title_selector;
		}
		var summary = document.getElementById('crb-css-summary');
		var category = document.getElementById('crb-css-category');
		var reviewBody = document.getElementById('crb-css-review-body');
		if (summary) {
			summary.value = preset.summary_selector;
		}
		if (category) {
			category.value = preset.category_selector;
		}
		if (reviewBody) {
			reviewBody.value = preset.review_body_selector;
		}
		togglePanels();
		toggleSlotAttrFields();
	}

	function collectCssConfigForDiscover() {
		var out = {
			item_selector: (document.getElementById('crb-css-item') || {}).value || '',
			link_selector: (document.getElementById('crb-slot-1-sel') || {}).value || '',
			title_mode: (document.getElementById('crb-slot-0-mode') || {}).value || 'attr',
			title_attr: (document.getElementById('crb-slot-0-attr') || {}).value || 'title',
			title_selector: (document.getElementById('crb-slot-0-sel') || {}).value || '',
			image_selector: (document.getElementById('crb-css-image') || {}).value || '',
			author_selector: (document.getElementById('crb-css-author') || {}).value || '',
			review_title_selector: (document.getElementById('crb-css-review-title') || {}).value || '',
			summary_selector: (document.getElementById('crb-css-summary') || {}).value || '',
			category_selector: (document.getElementById('crb-css-category') || {}).value || '',
			review_body_selector: (document.getElementById('crb-css-review-body') || {}).value || ''
		};
		form.querySelectorAll('.crb-slot-selector').forEach(function (el) {
			var name = el.getAttribute('name');
			if (name) {
				out[name.replace(/^css_/, '')] = el.value || '';
			}
		});
		form.querySelectorAll('.crb-slot-mode').forEach(function (el) {
			var name = el.getAttribute('name');
			if (name) {
				out[name.replace(/^css_/, '')] = el.value || '';
			}
		});
		return out;
	}

	function setSlotField(slotIndex, selector, mode) {
		var sel = form.querySelector('.crb-slot-selector[data-slot-index="' + slotIndex + '"]');
		var modeEl = form.querySelector('.crb-slot-mode[data-slot-index="' + slotIndex + '"]');
		if (sel && selector) {
			sel.value = selector;
		}
		if (slotIndex === 1) {
			if (modeEl && mode) {
				/* {%2} は href 固定 */
			} else if (mode === 'href' || !mode) {
				/* ok */
			}
			return;
		}
		if (modeEl && mode) {
			if (slotIndex === 0 && mode === 'href') {
				modeEl.value = 'el_href';
			} else if (slotIndex === 0 && mode === 'text' && selector) {
				modeEl.value = 'selector';
			} else {
				modeEl.value = mode;
			}
			toggleSlotAttrFields();
		}
	}

	function applySuggestedSlots(suggested) {
		if (!suggested) {
			return;
		}
		var link = document.getElementById('crb-slot-1-sel');
		if (link && suggested.link_selector && !link.value.trim()) {
			link.value = suggested.link_selector;
		}
		var map = {
			summary_selector: 2,
			image_selector: 3,
			category_selector: 4,
			review_title_selector: 5,
			review_body_selector: 6,
			author_selector: 7,
			slot_selector_9: 8,
			slot_selector_10: 9,
			slot_selector_11: 10,
			slot_selector_12: 11
		};
		Object.keys(map).forEach(function (key) {
			if (!suggested[key]) {
				return;
			}
			var idx = map[key];
			var sel = form.querySelector('.crb-slot-selector[data-slot-index="' + idx + '"]');
			if (!sel) {
				return;
			}
			var force = key === 'image_selector';
			if (!force && sel.value.trim()) {
				return;
			}
			var modeKey = 'slot_mode_' + (idx + 1);
			var mode =
				suggested[modeKey] && suggested[modeKey].mode
					? suggested[modeKey].mode
					: key === 'image_selector'
						? 'src'
						: 'text';
			setSlotField(idx, suggested[key], mode);
		});
	}

	function slotToken(index) {
		return '{%' + (parseInt(index, 10) + 1) + '}';
	}

	function formatSlotValue(slot, value) {
		var wrap = document.createElement('span');
		wrap.className = 'crb-slot-line__value';
		if (slot.is_html) {
			var htmlSpan = document.createElement('span');
			htmlSpan.className = 'crb-slot-line__html';
			htmlSpan.innerHTML = value;
			wrap.appendChild(htmlSpan);
		} else if (slot.is_url || /^https?:\/\//i.test(value) || String(value).indexOf('//') === 0) {
			var a = document.createElement('a');
			a.href = String(value).indexOf('//') === 0 ? 'https:' + value : value;
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.textContent = value;
			wrap.appendChild(a);
		} else {
			wrap.textContent = value;
		}
		return wrap;
	}

	function renderScopePreviewTable(rows, container, titleText, contextNote) {
		if (!container) {
			return;
		}
		var box = document.createElement('div');
		box.className = 'crb-discover-slot-rules crb-discover-scope-preview';
		if (titleText) {
			var title = document.createElement('p');
			title.className = 'crb-discover-slot-rules__title';
			title.textContent = titleText;
			box.appendChild(title);
		}
		if (contextNote) {
			var ctx = document.createElement('p');
			ctx.className = 'description';
			ctx.textContent = contextNote;
			box.appendChild(ctx);
		}
		if (!rows || !rows.length) {
			var empty = document.createElement('p');
			empty.className = 'description';
			empty.textContent = i18n.noScopePreview || 'この範囲では取れる候補がありませんでした。';
			box.appendChild(empty);
			container.appendChild(box);
			return;
		}
		var table = document.createElement('table');
		table.className = 'widefat crb-discover-slot-rules__table crb-discover-preview-table';
		var colValue = i18n.colValue || '取れた値';
		table.innerHTML =
			'<thead><tr><th>' +
			(i18n.colSlot || 'スロット') +
			'</th><th>CSS セレクタ</th><th>' +
			(i18n.colExtract || '取り方') +
			'</th><th>' +
			colValue +
			'</th></tr></thead>';
		var tbody = document.createElement('tbody');
		rows.forEach(function (rule) {
			var selText = rule.selector || '';
			var value = rule.value || '';
			var tr = document.createElement('tr');
			tr.innerHTML =
				'<td class="crb-discover-token"></td><td><code class="crb-discover-selector"></code></td><td class="crb-discover-mode"></td><td class="crb-discover-value"></td>';
			var tokenCell = tr.querySelector('.crb-discover-token');
			if (rule.token) {
				tokenCell.textContent = rule.token;
			} else if (typeof rule.index === 'number' && rule.index >= 0) {
				tokenCell.textContent = '{%' + String(rule.index + 1) + '}';
			} else {
				tokenCell.textContent = '—';
			}
			tr.querySelector('.crb-discover-selector').textContent = selText || '—';
			tr.querySelector('.crb-discover-mode').textContent = rule.mode_label || rule.mode || '';
			var valueCell = tr.querySelector('.crb-discover-value');
			if (value) {
				valueCell.appendChild(formatSlotValue(rule, value));
			} else {
				valueCell.textContent = i18n.noValueInScope || '（範囲内で値なし）';
			}
			tbody.appendChild(tr);
		});
		table.appendChild(tbody);
		box.appendChild(table);
		container.appendChild(box);
	}

	function renderSampleRecords(records, container, previewRows) {
		if (!records || !records.length || !container) {
			return;
		}
		previewRows = previewRows || [];
		if (records.length <= 1 || !previewRows.length) {
			return;
		}
		var box = document.createElement('div');
		box.className = 'crb-discover-sample-records';
		var title = document.createElement('p');
		title.className = 'crb-discover-sample-records__title';
		var lead = i18n.sampleRecordsLead || '抽出プレビュー（先頭%d件）';
		title.textContent = lead.replace('%d', String(records.length));
		box.appendChild(title);

		records.forEach(function (record, index) {
			var card = document.createElement('div');
			card.className = 'crb-record-preview crb-record-preview--compact';
			var heading = document.createElement('p');
			heading.className = 'crb-record-preview__heading';
			heading.textContent = (index + 1) + (i18n.recordSuffix || '件目');
			card.appendChild(heading);
			renderScopePreviewTable(previewRows, card, '', '');
			box.appendChild(card);
		});

		container.appendChild(box);
	}

	function renderDiscoverResults(data) {
		if (typeof slotToken !== 'function') {
			window.alert('admin.js が古いキャッシュです。Ctrl+Shift+R で再読み込みしてください。');
			return;
		}
		var box = document.getElementById('crb-discover-results');
		var wrap = box ? box.querySelector('.crb-discover-table-wrap') : null;
		var lead = box ? box.querySelector('.crb-discover-results__lead') : null;
		if (!box || !wrap || !lead) {
			return;
		}

		var scopeRows = data.scope_slot_rows || (data.scope_preview && data.scope_preview.rows) || [];
		var scopeNote =
			(data.scope_preview && data.scope_preview.context_note) || data.scope_label || '';
		lead.textContent = scopeNote || data.scope_label || '';

		wrap.innerHTML = '';
		if (data.suggested_slots && Object.keys(data.suggested_slots).length) {
			var applyBar = document.createElement('p');
			applyBar.className = 'crb-discover-apply-bar';
			var applyBtn = document.createElement('button');
			applyBtn.type = 'button';
			applyBtn.className = 'button button-secondary';
			applyBtn.textContent = i18n.applySuggested || 'おすすめを一括入力';
			applyBtn.addEventListener('click', function () {
				applySuggestedSlots(data.suggested_slots);
			});
			applyBar.appendChild(applyBtn);
			wrap.appendChild(applyBar);
		}

		var hint = document.createElement('p');
		hint.className = 'description crb-discover-results__hint';
		hint.textContent = i18n.discoverHint || '';
		wrap.appendChild(hint);

		renderScopePreviewTable(
			scopeRows,
			wrap,
			i18n.scopePreviewLead || 'この範囲での試し読み',
			''
		);

		if (data.sample_records && data.sample_records.length) {
			var slotRules = scopeRows.length ? scopeRows : data.slot_schema || [];
			renderSampleRecords(data.sample_records, wrap, slotRules);
		}

		if ((!scopeRows || !scopeRows.length) && (!data.sample_records || !data.sample_records.length)) {
			var emptyP = document.createElement('p');
			emptyP.className = 'description';
			emptyP.textContent = i18n.empty || '';
			wrap.appendChild(emptyP);
		}

		box.hidden = false;
	}

	function discoverElements() {
		var btnEl = arguments.length > 0 ? arguments[0] : null;
		if (!cfg.ajaxUrl || !cfg.nonce) {
			return;
		}
		var urlInput = document.getElementById('crb-url');
		var scopeInput = document.getElementById('crb-css-scope');
		var btn = btnEl || document.getElementById('crb-discover-elements');
		var spinner = document.querySelector('.crb-discover-spinner');
		if (!urlInput || !btn) {
			return;
		}
		var labelDefault = btn.getAttribute('data-label-default') || btn.textContent;

		var url = urlInput.value.trim();
		if (!url) {
			window.alert(i18n.needUrl || 'URL required');
			return;
		}

		btn.disabled = true;
		if (spinner) {
			spinner.classList.add('is-active');
		}
		btn.textContent = i18n.discovering || '…';

		var body = new URLSearchParams();
		body.append('action', 'crb_discover_elements');
		body.append('nonce', cfg.nonce);
		body.append('url', url);
		body.append('scope_selector', scopeInput ? scopeInput.value.trim() : '');
		var cssCfg = collectCssConfigForDiscover();
		Object.keys(cssCfg).forEach(function (key) {
			body.append(key, cssCfg[key]);
		});

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (!json || !json.success) {
					var msg = (json && json.data && json.data.message) || i18n.discoverFail;
					window.alert(msg);
					return;
				}
				renderDiscoverResults(json.data);
			})
			.catch(function () {
				window.alert(i18n.discoverFail || 'Failed');
			})
			.finally(function () {
				btn.disabled = false;
				if (spinner) {
					spinner.classList.remove('is-active');
				}
				btn.textContent = labelDefault || '調べる';
			});
	}

	function discoverScopeHtml(btnEl) {
		var btn = btnEl || document.getElementById('crb-discover-elements-scope');
		if (!cfg.ajaxUrl || !cfg.nonce || !btn) {
			return;
		}

		var urlInput = document.getElementById('crb-url');
		var scopeInput = document.getElementById('crb-css-scope');
		var scope = scopeInput ? scopeInput.value.trim() : '';
		var spinner = document.querySelector('.crb-discover-spinner');

		if (!urlInput || !scopeInput) {
			return;
		}

		var url = urlInput.value.trim();
		if (!url) {
			window.alert(i18n.needUrl || 'URL required');
			return;
		}
		if (!scope) {
			window.alert('範囲セレクタを入力してください。');
			return;
		}

		btn.disabled = true;
		if (spinner) {
			spinner.classList.add('is-active');
		}

		var labelDefault = btn.getAttribute('data-label-default') || btn.textContent;
		btn.textContent = i18n.discovering || '…';

		var body = new URLSearchParams();
		body.append('action', 'crb_discover_scope_html');
		body.append('nonce', cfg.nonce);
		body.append('url', url);
		body.append('scope_selector', scope);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (!json || !json.success) {
					var msg = (json && json.data && json.data.message) || i18n.discoverFail;
					window.alert(msg);
					return;
				}

				var data = json.data || {};
				var box = document.getElementById('crb-discover-results');
				var wrap = box ? box.querySelector('.crb-discover-table-wrap') : null;
				var lead = box ? box.querySelector('.crb-discover-results__lead') : null;
				if (!box || !wrap || !lead) {
					return;
				}

				lead.textContent = '範囲プレビュー';
				wrap.innerHTML = '';

				var p = document.createElement('p');
				p.className = 'description';
				p.textContent = (data.scope_label ? data.scope_label : scope) + '（一致 ' + (data.scope_match_count || 0) + ' 件）';
				wrap.appendChild(p);

				var pre = document.createElement('pre');
				pre.className = 'crb-scope-html-preview';
				pre.textContent = data.scope_html || '';
				wrap.appendChild(pre);

				if (data.scope_truncated) {
					var trunc = document.createElement('p');
					trunc.className = 'description';
					trunc.textContent = '長いので先頭を表示しています。';
					wrap.appendChild(trunc);
				}

				box.hidden = false;
			})
			.catch(function () {
				window.alert(i18n.discoverFail || 'Failed');
			})
			.finally(function () {
				btn.disabled = false;
				if (spinner) {
					spinner.classList.remove('is-active');
				}
				btn.textContent = labelDefault;
			});
	}

	form.querySelectorAll('input[name="extraction_mode"]').forEach(function (radio) {
		radio.addEventListener('change', togglePanels);
	});

	var titleMode = document.getElementById('crb-slot-0-mode');
	if (titleMode) {
		titleMode.addEventListener('change', toggleSlotAttrFields);
	}
	toggleSlotAttrFields();

	var presetBtn = document.getElementById('crb-preset-example');
	if (presetBtn) {
		presetBtn.addEventListener('click', applyPreset);
	}

	var discoverBtn = document.getElementById('crb-discover-elements');
	if (discoverBtn) {
		discoverBtn.setAttribute('data-label-default', discoverBtn.textContent);
		discoverBtn.addEventListener('click', function () {
			discoverElements(discoverBtn);
		});
	}

	var discoverScopeBtn = document.getElementById('crb-discover-elements-scope');
	if (discoverScopeBtn) {
		discoverScopeBtn.setAttribute('data-label-default', discoverScopeBtn.textContent);
		discoverScopeBtn.addEventListener('click', function () {
			discoverScopeHtml(discoverScopeBtn);
		});
	}

	togglePanels();
})();
