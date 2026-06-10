(function () {
	'use strict';

	var cfg = window.crbAdmin || {};
	var i18n = cfg.i18n || {};
	window.crbAdminBuild =
		cfg.version && cfg.build
			? cfg.version + '-' + cfg.build
			: cfg.version || '0.9.0';

	var form = document.querySelector('.crb-feed-form');
	if (!form) {
		return;
	}

	var AJAX_FEED_ACTIONS = ['preview_posts', 'preview', 'save', 'import_posts'];

	function getAjaxNoticesEl() {
		return document.getElementById('crb-ajax-notices');
	}

	function showAjaxNotice(message, type) {
		var box = getAjaxNoticesEl();
		if (!box || !message) {
			return;
		}
		box.innerHTML = '';
		var notice = document.createElement('div');
		notice.className = 'notice notice-' + (type || 'success') + ' is-dismissible';
		var p = document.createElement('p');
		p.textContent = message;
		notice.appendChild(p);
		if (arguments.length > 2 && arguments[2]) {
			var detailWrap = document.createElement('p');
			var codeEl = document.createElement('code');
			codeEl.textContent = String(arguments[2]);
			detailWrap.appendChild(codeEl);
			notice.appendChild(detailWrap);
		}
		box.appendChild(notice);
	}

	function setFeedIdOnForm(feedId) {
		var id = parseInt(feedId, 10);
		if (isNaN(id) || id < 1) {
			return;
		}
		form.querySelectorAll('input[name="feed_id"]').forEach(function (input) {
			input.value = String(id);
		});
		var editUrl =
			(cfg.editBaseUrl || '') +
			'&feed_id=' +
			id;
		if (cfg.editBaseUrl) {
			form.setAttribute('action', editUrl);
			if (window.history && window.history.replaceState) {
				window.history.replaceState({}, '', editUrl);
			}
		}
		var tools = form.querySelector('.crb-feed-tools');
		if (tools) {
			tools.hidden = false;
		}
	}

	function applyPreviewHtml(payload) {
		var extractEl = document.getElementById('crb-preview-extract-body');
		var importEl = document.getElementById('crb-preview-import-body');
		if (extractEl && payload.extract_html !== undefined) {
			extractEl.innerHTML = payload.extract_html;
		}
		if (importEl && payload.import_html !== undefined) {
			importEl.innerHTML = payload.import_html;
		}
		var navItem = form.querySelector('.crb-workflow__item[data-crb-workflow-step="5"]');
		if (navItem) {
			navItem.classList.add('is-done');
		}
	}

	function submitFeedAction(action, buttonEl) {
		if (!cfg.ajaxUrl || !cfg.formNonce) {
			return;
		}
		var body = new FormData(form);
		body.append('action', 'crb_admin_feed');
		body.set('crb_action', action);
		body.append('nonce', cfg.formNonce);

		var labelDefault = buttonEl.getAttribute('data-label-default') || buttonEl.textContent;
		var busyLabel = i18n.working || '…';
		if (action === 'save') {
			busyLabel = i18n.saving || busyLabel;
		} else if (action === 'preview' || action === 'preview_posts') {
			busyLabel = i18n.previewing || busyLabel;
		}
		buttonEl.disabled = true;
		buttonEl.textContent = busyLabel;

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (!json || !json.success) {
					var msg = (json && json.data && json.data.message) || i18n.requestFail;
					window.alert(msg);
					return;
				}
				var data = json.data || {};
				if (data.message) {
					showAjaxNotice(data.message, data.message_type || 'success', data.detail || '');
				}
				if (data.feed_id) {
					setFeedIdOnForm(data.feed_id);
				}
				if (data.extract_html !== undefined || data.import_html !== undefined) {
					applyPreviewHtml(data);
				}
			})
			.catch(function () {
				window.alert(i18n.requestFail || 'Request failed');
			})
			.finally(function () {
				buttonEl.disabled = false;
				buttonEl.textContent = labelDefault;
			});
	}

	form.addEventListener('submit', function (ev) {
		var sub = ev.submitter;
		if (!sub || sub.name !== 'crb_action') {
			return;
		}
		if (AJAX_FEED_ACTIONS.indexOf(sub.value) === -1) {
			return;
		}
		ev.preventDefault();
		if (!sub.getAttribute('data-label-default')) {
			sub.setAttribute('data-label-default', sub.textContent);
		}
		submitFeedAction(sub.value, sub);
	});

	form.addEventListener(
		'input',
		function (ev) {
			var t = ev.target;
			if (!t || !t.classList) {
				return;
			}
			if (
				t.classList.contains('crb-slot-selector') ||
				t.classList.contains('crb-slot-mode') ||
				t.classList.contains('crb-slot-attr') ||
				t.id === 'crb-slot-0-sel' ||
				t.id === 'crb-slot-1-sel'
			) {
				scheduleExtractPreviewRefresh();
			}
		},
		true
	);

	var previewRefreshTimer = null;

	function refreshExtractPreview() {
		if (!cfg.ajaxUrl || !cfg.formNonce) {
			return;
		}
		var urlInput = document.getElementById('crb-url');
		if (!urlInput || !urlInput.value.trim()) {
			return;
		}
		var body = new FormData(form);
		body.append('action', 'crb_admin_feed');
		body.set('crb_action', 'preview');
		body.append('nonce', cfg.formNonce);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (json && json.success && json.data) {
					applyPreviewHtml(json.data);
				}
			})
			.catch(function () {
				/* silent */
			});
	}

	function scheduleExtractPreviewRefresh() {
		window.clearTimeout(previewRefreshTimer);
		previewRefreshTimer = window.setTimeout(refreshExtractPreview, 700);
	}

	function demoSampleById(id) {
		var list = cfg.demoSamples || [];
		for (var i = 0; i < list.length; i++) {
			if (list[i].id === id) {
				return list[i];
			}
		}
		return null;
	}

	function applyPresetFromSample(sample, fillUrl) {
		if (!sample) {
			return;
		}
		var scopeEl = document.getElementById('crb-css-scope');
		var itemEl = document.getElementById('crb-css-item');
		var urlEl = document.getElementById('crb-url');
		if (scopeEl) {
			scopeEl.value = sample.scope_selector || '';
		}
		if (itemEl) {
			itemEl.value = sample.item_selector || '';
		}
		if (fillUrl && urlEl && sample.url) {
			urlEl.value = sample.url;
		}
		var linkSel = document.getElementById('crb-slot-1-sel');
		var titleMode = document.getElementById('crb-slot-0-mode');
		var titleAttr = document.getElementById('crb-slot-0-attr');
		var titleSel = document.getElementById('crb-slot-0-sel');
		if (linkSel) {
			linkSel.value = sample.link_selector || '';
		}
		if (titleMode && sample.title_mode) {
			titleMode.value = sample.title_mode;
		}
		if (titleAttr) {
			titleAttr.value = sample.title_attr || 'title';
		}
		if (titleSel) {
			titleSel.value = sample.title_selector || '';
		}
		var summary = document.getElementById('crb-css-summary');
		if (summary) {
			summary.value = sample.summary_selector || '';
		}
		toggleSlotAttrFields();
		updateDiscoverButtonLabels();
		scheduleExtractPreviewRefresh();
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

	function isCssSelectorForStep4(selector) {
		var sel = (selector || '').trim();
		return sel !== '' && sel !== '—' && sel !== 'application/ld+json';
	}

	function setSlotField(slotIndex, selector, mode, options) {
		options = options || {};
		var force = !!options.force;
		var sel = form.querySelector('.crb-slot-selector[data-slot-index="' + slotIndex + '"]');
		var modeEl = form.querySelector('.crb-slot-mode[data-slot-index="' + slotIndex + '"]');
		if (sel && selector && (force || !sel.value.trim())) {
			sel.value = selector;
		}
		if (slotIndex === 1) {
			return;
		}
		if (modeEl && mode) {
			if (slotIndex === 0 && mode === 'href') {
				modeEl.value = 'el_href';
			} else if (slotIndex === 0 && mode === 'text' && selector) {
				modeEl.value = 'selector';
			} else if (force || modeEl.value !== mode) {
				modeEl.value = mode;
			}
			toggleSlotAttrFields();
		}
	}

	function slotToken(index) {
		return '{%' + (parseInt(index, 10) + 1) + '}';
	}

	var CRB_SLOT_COUNT = (window.crbAdmin && crbAdmin.maxSlotCount) ? parseInt(crbAdmin.maxSlotCount, 10) : 20;

	function guessCandidateSlotIndex(row) {
		var selector = (row.selector || '').trim();
		if (!selector || !form) {
			return '';
		}
		for (var i = 0; i < CRB_SLOT_COUNT; i++) {
			var sel = form.querySelector('.crb-slot-selector[data-slot-index="' + i + '"]');
			if (sel && sel.value.trim() === selector) {
				return String(i);
			}
		}
		return '';
	}

	function mapCandidateModeToSlotMode(mode, slotIndex) {
		mode = (mode || '').trim();
		if (mode === 'html') {
			return 'html';
		}
		if (mode === 'href' || mode === 'attr:href') {
			return 'href';
		}
		if (mode === 'text') {
			return 'text';
		}
		if (mode === 'src' || mode === 'attr:src' || mode === 'attr:data-src' || mode === 'attr:data-original') {
			return 'src';
		}
		if (mode.indexOf('attr:') === 0) {
			var attr = mode.slice(5).toLowerCase();
			if (attr === 'href') {
				return 'href';
			}
			if (
				attr.indexOf('src') !== -1 ||
				attr.indexOf('lazy') !== -1
			) {
				return 'src';
			}
		}
		return 'text';
	}

	function markStep4Updated() {
		var step4 = document.getElementById('crb-step-4');
		if (step4) {
			step4.classList.add('crb-step-4--synced');
			window.setTimeout(function () {
				step4.classList.remove('crb-step-4--synced');
			}, 2000);
		}
		var navItem = form.querySelector('.crb-workflow__item[data-crb-workflow-step="4"]');
		if (navItem) {
			navItem.classList.add('is-done');
		}
	}

	function applyCandidateRowToSlot(slotIndex, row) {
		var selector = (row.selector || '').trim();
		if (!selector) {
			return;
		}
		var mode = (row.mode || '').trim();
		var force = { force: true };

		if (slotIndex === 1) {
			setSlotField(1, selector, 'href', force);
			markStep4Updated();
			scheduleExtractPreviewRefresh();
			return;
		}

		if (slotIndex === 0) {
			var titleModeEl = document.getElementById('crb-slot-0-mode');
			var titleAttrEl = document.getElementById('crb-slot-0-attr');
			var titleSelEl = document.getElementById('crb-slot-0-sel');
			if (mode === 'text') {
				if (titleModeEl) {
					titleModeEl.value = 'selector';
				}
				if (titleSelEl) {
					titleSelEl.value = selector;
				}
			} else if (mode === 'href' || mode === 'attr:href') {
				if (titleModeEl) {
					titleModeEl.value = 'el_href';
				}
				if (titleSelEl) {
					titleSelEl.value = selector;
				}
			} else if (mode.indexOf('attr:') === 0) {
				if (titleModeEl) {
					titleModeEl.value = 'el_attr';
				}
				if (titleSelEl) {
					titleSelEl.value = selector;
				}
				if (titleAttrEl) {
					titleAttrEl.value = mode.slice(5);
				}
			}
			toggleSlotAttrFields();
			markStep4Updated();
			scheduleExtractPreviewRefresh();
			return;
		}

		setSlotField(slotIndex, selector, mapCandidateModeToSlotMode(mode, slotIndex), force);
		markStep4Updated();
		scheduleExtractPreviewRefresh();
	}

	function buildCandidateSlotSelect(initialValue) {
		var select = document.createElement('select');
		select.className = 'crb-candidate-slot-assign';
		var empty = document.createElement('option');
		empty.value = '';
		empty.textContent = '—';
		select.appendChild(empty);
		for (var i = 0; i < CRB_SLOT_COUNT; i++) {
			var opt = document.createElement('option');
			opt.value = String(i);
			opt.textContent = slotToken(i);
			select.appendChild(opt);
		}
		if (initialValue !== '' && initialValue !== null && initialValue !== undefined) {
			select.value = String(initialValue);
		}
		return select;
	}

	function buildDebugSnapshot(data, requestCfg) {
		var candidates = (data && data.extract_candidates) || {};
		return {
			timestamp: new Date().toISOString(),
			request: {
				scope_selector: requestCfg.scope_selector || '',
				item_selector: requestCfg.item_selector || '',
				link_selector: requestCfg.link_selector || '',
				title_mode: requestCfg.title_mode || '',
				title_attr: requestCfg.title_attr || '',
				title_selector: requestCfg.title_selector || ''
			},
			response: {
				scope_label: data && data.scope_label ? data.scope_label : '',
				extract_candidates_note: candidates.context_note || '',
				extract_candidates_count: candidates.row_count || 0,
				extract_candidates: candidates.rows || null,
				slot_schema: (data && data.slot_schema) || []
			}
		};
	}

	function renderExtractCandidatesTable(candidates, container) {
		if (!container || !candidates || !candidates.rows || !candidates.rows.length) {
			return;
		}
		var box = document.createElement('div');
		box.className = 'crb-extract-candidates';
		var title = document.createElement('p');
		title.className = 'crb-extract-candidates__title';
		var countLabel = i18n.extractCandidatesCount || '%d 件';
		var countText = countLabel.replace('%d', String(candidates.row_count || candidates.rows.length));
		title.textContent =
			(i18n.extractCandidatesLead || '範囲内で取れる値の一覧') + ' — ' + countText;
		box.appendChild(title);
		if (candidates.context_note) {
			var note = document.createElement('p');
			note.className = 'description crb-extract-candidates__note';
			note.textContent = candidates.context_note;
			box.appendChild(note);
		}
		var slotHint = document.createElement('p');
		slotHint.className = 'description crb-extract-candidates__slot-hint';
		var maxToken = '{%' + CRB_SLOT_COUNT + '%}';
		slotHint.textContent =
			i18n.extractCandidatesSlotHint ||
			('回数は範囲内の一致件数です。スロット列で {%1%}〜' + maxToken + ' を選ぶと、④の欄にセレクタと取り方が入ります。');
		box.appendChild(slotHint);
		var wrap = document.createElement('div');
		wrap.className = 'crb-extract-candidates__table-wrap';
		var table = document.createElement('table');
		table.className = 'widefat striped crb-discover-preview-table crb-extract-candidates__table';
		table.innerHTML =
			'<thead><tr><th class="crb-col-match-count">' +
			(i18n.colCount || '回数') +
			'</th><th>' +
			(i18n.colSlot || 'スロット') +
			'</th><th>CSS セレクタ</th><th>' +
			(i18n.colExtract || '取り方') +
			'</th><th>' +
			(i18n.colValue || '取れた値') +
			'</th></tr></thead>';
		var tbody = document.createElement('tbody');
		var slotSelects = [];
		candidates.rows.forEach(function (row) {
			var tr = document.createElement('tr');
			tr.innerHTML =
				'<td class="crb-col-match-count crb-discover-match-count"></td><td class="crb-discover-token"></td><td><code class="crb-discover-selector"></code></td><td class="crb-discover-mode"></td><td class="crb-discover-value"></td>';
			var countCell = tr.querySelector('.crb-discover-match-count');
			if (countCell) {
				var mc = row.match_count;
				countCell.textContent =
					mc !== undefined && mc !== null && mc !== '' ? String(mc) : '—';
			}
			var slotCell = tr.querySelector('.crb-discover-token');
			var slotSelect = buildCandidateSlotSelect(guessCandidateSlotIndex(row));
			slotSelects.push(slotSelect);
			slotSelect.addEventListener('change', function () {
				var picked = slotSelect.value;
				if (picked === '') {
					return;
				}
				var slotIndex = parseInt(picked, 10);
				slotSelects.forEach(function (other) {
					if (other !== slotSelect && other.value === picked) {
						other.value = '';
					}
				});
				applyCandidateRowToSlot(slotIndex, row);
			});
			slotCell.appendChild(slotSelect);
			tr.querySelector('.crb-discover-selector').textContent = row.selector || '';
			tr.querySelector('.crb-discover-mode').textContent = row.mode_label || row.mode || '';
			var valueCell = tr.querySelector('.crb-discover-value');
			var val = row.value || '';
			if (row.mode === 'html' && val.indexOf('<') !== -1) {
				var pre = document.createElement('pre');
				pre.className = 'crb-slot-line__html';
				pre.textContent = val;
				valueCell.appendChild(pre);
			} else {
				valueCell.textContent = val;
			}
			tbody.appendChild(tr);
		});
		table.appendChild(tbody);
		wrap.appendChild(table);
		box.appendChild(wrap);
		container.appendChild(box);
	}

	function renderDiscoverDebug(data, container, requestCfg) {
		if (!container) {
			return;
		}
		var details = document.createElement('details');
		details.className = 'crb-discover-debug';
		var summary = document.createElement('summary');
		summary.textContent = 'デバッグ: 実際に使った入出力を表示';
		details.appendChild(summary);
		var pre = document.createElement('pre');
		pre.className = 'crb-scope-html-preview';
		pre.textContent = JSON.stringify(buildDebugSnapshot(data, requestCfg || {}), null, 2);
		details.appendChild(pre);
		container.appendChild(details);
	}

	function renderDiscoverResults(data, requestCfg) {
		if (typeof slotToken !== 'function') {
			window.alert('admin.js が古いバージョンです。ページを再読み込みしてください。');
			return;
		}
		var box = document.getElementById('crb-discover-results');
		var wrap = box ? box.querySelector('.crb-discover-table-wrap') : null;
		var lead = box ? box.querySelector('.crb-discover-results__lead') : null;
		if (!box || !wrap || !lead) {
			return;
		}

		var candidates = data.extract_candidates || null;
		var itemSelectorError =
			data.item_selector_error ||
			data.extract_candidates_error ||
			(data.scope_preview && data.scope_preview.item_selector_error) ||
			'';

		if (itemSelectorError) {
			lead.textContent = itemSelectorError;
			wrap.innerHTML = '';
			var itemErrP = document.createElement('p');
			itemErrP.className = 'notice notice-error inline';
			itemErrP.textContent = itemSelectorError;
			wrap.appendChild(itemErrP);
			renderDiscoverDebug(data, wrap, requestCfg || {});
			box.hidden = false;
			return;
		}

		lead.textContent =
			(candidates && candidates.context_note) ||
			(data.scope_preview && data.scope_preview.context_note) ||
			data.scope_label ||
			'';

		wrap.innerHTML = '';
		if (candidates && candidates.rows && candidates.rows.length) {
			renderExtractCandidatesTable(candidates, wrap);
		} else if (data.extract_candidates_error) {
			var errP = document.createElement('p');
			errP.className = 'notice notice-error inline';
			errP.textContent = data.extract_candidates_error;
			wrap.appendChild(errP);
		} else {
			var emptyP = document.createElement('p');
			emptyP.className = 'description';
			emptyP.textContent = i18n.empty || '';
			wrap.appendChild(emptyP);
		}
		renderDiscoverDebug(data, wrap, requestCfg || {});

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
				renderDiscoverResults(json.data, cssCfg);
				var navItem = form.querySelector('.crb-workflow__item[data-crb-workflow-step="3"]');
				if (navItem) {
					navItem.classList.add('is-done');
				}
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

	function renderScopeHtmlPreview(container, html, previewLen) {
		var full = html || '';
		var limit = typeof previewLen === 'number' ? previewLen : 1000;
		var wrap = document.createElement('div');
		wrap.className = 'crb-scope-html-preview-wrap';

		if (full.length <= limit) {
			var preOnly = document.createElement('pre');
			preOnly.className = 'crb-scope-html-preview';
			preOnly.textContent = full;
			wrap.appendChild(preOnly);
			container.appendChild(wrap);
			return;
		}

		var preHead = document.createElement('pre');
		preHead.className = 'crb-scope-html-preview';
		preHead.textContent = full.slice(0, limit);
		wrap.appendChild(preHead);

		var moreBtn = document.createElement('button');
		moreBtn.type = 'button';
		moreBtn.className = 'crb-scope-html-more';
		moreBtn.textContent = '<more!>';
		wrap.appendChild(moreBtn);

		var preRest = document.createElement('pre');
		preRest.className = 'crb-scope-html-preview crb-scope-html-preview--rest';
		preRest.hidden = true;
		preRest.textContent = full.slice(limit);
		wrap.appendChild(preRest);

		moreBtn.addEventListener('click', function () {
			var expanded = !preRest.hidden;
			preRest.hidden = expanded;
			moreBtn.textContent = expanded ? '<more!>' : '<less!>';
			moreBtn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		});
		moreBtn.setAttribute('aria-expanded', 'false');

		container.appendChild(wrap);
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
					if (json && json.data && json.data.scope_suggestions && json.data.scope_suggestions.length) {
						msg += '\n\n' + json.data.scope_suggestions.map(function (row) {
							return row.selector + ' (' + row.count + ')';
						}).join(', ');
					}
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

				lead.textContent = i18n.scopeHtmlPreviewLead || '範囲の HTML プレビュー';
				wrap.innerHTML = '';

				var p = document.createElement('p');
				p.className = 'description';
				p.textContent = (data.scope_label ? data.scope_label : scope) + '（一致 ' + (data.scope_match_count || 0) + ' 件）';
				wrap.appendChild(p);

				renderScopeHtmlPreview(wrap, data.scope_html || '', 1000);

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

	var titleMode = document.getElementById('crb-slot-0-mode');
	if (titleMode) {
		titleMode.addEventListener('change', toggleSlotAttrFields);
	}
	toggleSlotAttrFields();

	var applySampleBtn = document.getElementById('crb-apply-preset-sample');
	var sampleSelect = document.getElementById('crb-preset-sample');
	if (applySampleBtn && sampleSelect) {
		applySampleBtn.addEventListener('click', function () {
			var sample = demoSampleById(sampleSelect.value);
			if (!sample) {
				window.alert(i18n.chooseSample || 'パターンを選んでください。');
				return;
			}
			applyPresetFromSample(sample, true);
		});
	}
	var fillSampleUrlBtn = document.getElementById('crb-fill-sample-url');
	if (fillSampleUrlBtn && sampleSelect) {
		fillSampleUrlBtn.addEventListener('click', function () {
			var sample = demoSampleById(sampleSelect.value);
			if (!sample || !sample.url) {
				window.alert(i18n.chooseSample || 'パターンを選んでください。');
				return;
			}
			var urlEl = document.getElementById('crb-url');
			if (urlEl) {
				urlEl.value = sample.url;
			}
		});
	}

	function updateDiscoverButtonLabels() {
		var scopeInput = document.getElementById('crb-css-scope');
		var scopeVal = scopeInput ? scopeInput.value.trim() : '';
		var hasScope = scopeVal !== '';
		var mainBtn = document.getElementById('crb-discover-elements');
		if (mainBtn) {
			var labelDefault = mainBtn.getAttribute('data-label-default');
			if (!labelDefault) {
				labelDefault = i18n.discoverPage || '取れる値を一覧表示';
				mainBtn.setAttribute('data-label-default', labelDefault);
			}
			mainBtn.textContent = hasScope
				? i18n.discoverInScope || '取れる値を一覧表示（範囲内）'
				: labelDefault;
		}
		var scopeHtmlBtn = document.getElementById('crb-discover-elements-scope');
		if (scopeHtmlBtn) {
			scopeHtmlBtn.disabled = !hasScope;
			scopeHtmlBtn.title = hasScope
				? ''
				: (i18n.scopeHtmlNeedsScope || '「一覧の場所」が空欄のときは使えません（省略可の欄です）');
		}
	}

	var scopeInputEl = document.getElementById('crb-css-scope');
	if (scopeInputEl) {
		scopeInputEl.addEventListener('input', updateDiscoverButtonLabels);
		scopeInputEl.addEventListener('change', updateDiscoverButtonLabels);
	}
	updateDiscoverButtonLabels();

	var discoverBtn = document.getElementById('crb-discover-elements');
	if (discoverBtn) {
		if (!discoverBtn.getAttribute('data-label-default')) {
			discoverBtn.setAttribute('data-label-default', discoverBtn.textContent);
		}
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

	(function initWorkflowNav() {
		var items = form.querySelectorAll('.crb-workflow__item');
		var steps = form.querySelectorAll('.crb-workflow-step');
		if (!items.length) {
			return;
		}

		function setActive(stepId) {
			items.forEach(function (li) {
				var btn = li.querySelector('.crb-workflow__btn');
				var on = btn && btn.getAttribute('data-crb-workflow-target') === stepId;
				li.classList.toggle('is-active', !!on);
			});
			steps.forEach(function (el) {
				el.classList.toggle('is-active', el.id === stepId);
			});
		}

		items.forEach(function (li) {
			var btn = li.querySelector('.crb-workflow__btn');
			if (!btn) {
				return;
			}
			btn.addEventListener('click', function () {
				var stepId = btn.getAttribute('data-crb-workflow-target');
				if (stepId) {
					setActive(stepId);
				}
			});
		});

		setActive('crb-step-1');
	})();

	var exportFeedBtn = document.getElementById('crb-export-feed-pack');
	var importFeedBtn = document.getElementById('crb-import-feed-pack');
	var importFeedFile = document.getElementById('crb-import-feed-pack-file');

	function applyFeedPackToForm(fields) {
		if (!fields) {
			return;
		}
		var nameEl = document.getElementById('crb-name');
		var urlEl = document.getElementById('crb-url');
		if (nameEl && fields.name !== undefined) {
			nameEl.value = fields.name;
		}
		if (urlEl && fields.url !== undefined) {
			urlEl.value = fields.url;
		}
		var css = fields.css || {};
		Object.keys(css).forEach(function (key) {
			var el = form.querySelector('[name="css_' + key + '"]');
			if (el) {
				el.value = css[key] == null ? '' : String(css[key]);
			}
		});
		var imp = fields.import || {};
		var titleTpl = document.getElementById('crb-import-post-title-template');
		var contentTpl = document.getElementById('crb-import-content-template');
		if (titleTpl && imp.post_title_template !== undefined) {
			titleTpl.value = imp.post_title_template;
		}
		if (contentTpl && imp.content_template !== undefined) {
			contentTpl.value = imp.content_template;
		}
		toggleSlotAttrFields();
		if (typeof updateDiscoverButtonLabels === 'function') {
			updateDiscoverButtonLabels();
		}
		scheduleExtractPreviewRefresh();
	}

	function downloadFeedPackFile(json, filename) {
		var blob = new Blob([json], { type: 'application/json;charset=utf-8' });
		var url = URL.createObjectURL(blob);
		var anchor = document.createElement('a');
		anchor.href = url;
		anchor.download = filename || 'crb-feed-pack.json';
		document.body.appendChild(anchor);
		anchor.click();
		document.body.removeChild(anchor);
		URL.revokeObjectURL(url);
	}

	if (exportFeedBtn) {
		exportFeedBtn.addEventListener('click', function () {
			if (!cfg.ajaxUrl || !cfg.formNonce) {
				return;
			}
			var feedId = parseInt(exportFeedBtn.getAttribute('data-feed-id') || '0', 10);
			if (!feedId) {
				var feedIdInput = form.querySelector('input[name="feed_id"]');
				feedId = feedIdInput ? parseInt(feedIdInput.value || '0', 10) : 0;
			}
			if (!feedId) {
				window.alert(i18n.exportNeedSave || '先に保存してください。');
				return;
			}
			var labelDefault = exportFeedBtn.getAttribute('data-label-default') || exportFeedBtn.textContent;
			if (!exportFeedBtn.getAttribute('data-label-default')) {
				exportFeedBtn.setAttribute('data-label-default', labelDefault);
			}
			exportFeedBtn.disabled = true;
			exportFeedBtn.textContent = i18n.exporting || '…';

			var body = new FormData();
			body.append('action', 'crb_admin_feed');
			body.append('crb_action', 'export');
			body.append('feed_id', String(feedId));
			body.append('nonce', cfg.formNonce);

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			})
				.then(function (res) {
					return res.json();
				})
				.then(function (json) {
					if (!json || !json.success) {
						var msg = (json && json.data && json.data.message) || i18n.exportFail;
						window.alert(msg);
						return;
					}
					var data = json.data || {};
					var packJson = data.pack_json || '';
					if (!packJson) {
						window.alert(i18n.exportFail || 'Export failed');
						return;
					}
					downloadFeedPackFile(packJson, data.filename || 'crb-feed-pack.json');
					showAjaxNotice(
						data.message || i18n.exportDone || 'Downloaded',
						data.message_type || 'success'
					);
				})
				.catch(function () {
					window.alert(i18n.requestFail || 'Request failed');
				})
				.finally(function () {
					exportFeedBtn.disabled = false;
					exportFeedBtn.textContent = labelDefault;
				});
		});
	}

	if (importFeedBtn && importFeedFile) {
		importFeedBtn.addEventListener('click', function () {
			importFeedFile.click();
		});

		importFeedFile.addEventListener('change', function () {
			var file = importFeedFile.files && importFeedFile.files[0];
			importFeedFile.value = '';
			if (!file) {
				return;
			}
			if (!cfg.ajaxUrl || !cfg.formNonce) {
				return;
			}
			var labelDefault =
				importFeedBtn.getAttribute('data-label-default') || importFeedBtn.textContent;
			if (!importFeedBtn.getAttribute('data-label-default')) {
				importFeedBtn.setAttribute('data-label-default', labelDefault);
			}
			importFeedBtn.disabled = true;
			importFeedBtn.textContent = i18n.working || '…';

			var reader = new FileReader();
			reader.onload = function (ev) {
				var packJson =
					ev.target && ev.target.result ? String(ev.target.result) : '';
				if (!packJson.trim()) {
					window.alert(i18n.importInvalidFile || i18n.importFail);
					importFeedBtn.disabled = false;
					importFeedBtn.textContent = labelDefault;
					return;
				}

				var body = new FormData();
				body.append('action', 'crb_admin_feed');
				body.append('crb_action', 'import_pack');
				body.append('pack_json', packJson);
				body.append('nonce', cfg.formNonce);

				fetch(cfg.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				})
					.then(function (res) {
						return res.json();
					})
					.then(function (json) {
						if (!json || !json.success) {
							var msg =
								(json && json.data && json.data.message) || i18n.importFail;
							window.alert(msg);
							return;
						}
						var data = json.data || {};
						applyFeedPackToForm(data.fields || {});
						showAjaxNotice(
							data.message || i18n.importDone,
							data.message_type || 'success'
						);
					})
					.catch(function () {
						window.alert(i18n.requestFail || 'Request failed');
					})
					.finally(function () {
						importFeedBtn.disabled = false;
						importFeedBtn.textContent = labelDefault;
					});
			};
			reader.onerror = function () {
				window.alert(i18n.importFail || 'Import failed');
				importFeedBtn.disabled = false;
				importFeedBtn.textContent = labelDefault;
			};
			reader.readAsText(file, 'UTF-8');
		});
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.crb-copy-cron-url');
		if (!btn) {
			return;
		}
		var targetId = btn.getAttribute('data-target');
		var codeEl = targetId ? document.getElementById(targetId) : null;
		if (!codeEl) {
			return;
		}
		var text = codeEl.textContent || '';
		function copied() {
			var label = btn.textContent;
			btn.textContent = i18n.copied || 'コピーしました';
			setTimeout(function () {
				btn.textContent = label;
			}, 2000);
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(copied).catch(function () {
				window.prompt(i18n.copyPrompt || 'URL をコピーしてください', text);
			});
		} else {
			window.prompt(i18n.copyPrompt || 'URL をコピーしてください', text);
			copied();
		}
	});

})();

(function () {
	'use strict';

	var btn = document.getElementById('crb-test-gemini-api');
	if (!btn) {
		return;
	}

	var cfg = window.crbAdmin || {};
	var resultEl = document.getElementById('crb-test-gemini-result');

	function setTestResult(text, ok) {
		if (!resultEl) {
			return;
		}
		resultEl.textContent = text;
		resultEl.classList.remove('crb-ai-api-test-result--ok', 'crb-ai-api-test-result--error');
		if (typeof ok === 'boolean') {
			resultEl.classList.add(ok ? 'crb-ai-api-test-result--ok' : 'crb-ai-api-test-result--error');
		}
	}

	btn.addEventListener('click', function () {
		setTestResult('接続テスト中…');
		btn.disabled = true;

		var body = new FormData();
		body.append('action', 'crb_test_gemini_api');
		body.append('nonce', cfg.formNonce || '');

		fetch(cfg.ajaxUrl || '', {
			method: 'POST',
			body: body,
			credentials: 'same-origin'
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (json.success && json.data && json.data.message) {
					setTestResult(json.data.message, true);
					return;
				}
				setTestResult(
					json.data && json.data.message ? json.data.message : '接続テストに失敗しました。',
					false
				);
			})
			.catch(function () {
				setTestResult('通信に失敗しました。', false);
			})
			.finally(function () {
				btn.disabled = false;
			});
	});
})();
