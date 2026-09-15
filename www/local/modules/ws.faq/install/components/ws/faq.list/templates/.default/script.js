;(function() {
	'use strict';

	BX.namespace('BX.Ws.Faq');

	/**
	 * Публичный список FAQ: поиск, табы, голосование.
	 *
	 * @param {HTMLElement|string} rootNode
	 * @constructor
	 */
	BX.Ws.Faq.List = function(rootNode)
	{
		this.root = BX.type.isDomNode(rootNode) ? rootNode : BX(rootNode);
		if (!this.root || this.root.getAttribute('data-ws-faq-ready') === 'Y')
		{
			return;
		}

		this.root.setAttribute('data-ws-faq-ready', 'Y');
		this.activeCategory = 'all';
		this.searchInput = this.root.querySelector('.ws-faq__search-input');
		this.emptyFilter = this.root.querySelector('.ws-faq__empty--filter');
		this.items = BX.findChildren(this.root, {className: 'ws-faq__item'}, true) || [];

		this.bindEvents();
		this.applyFilters();
	};

	BX.Ws.Faq.List.prototype = {
		bindEvents: function()
		{
			const list = this;

			BX.bindDelegate(
				this.root,
				'click',
				{className: 'ws-faq__tab'},
				function(event)
				{
					// this — DOM-узел .ws-faq__tab (штатное поведение bindDelegate)
					list.onTabClick(this, event);
				}
			);

			BX.bindDelegate(
				this.root,
				'click',
				{className: 'ws-faq__vote-btn'},
				function(event)
				{
					list.onVoteClick(this, event);
				}
			);

			if (this.searchInput)
			{
				BX.bind(this.searchInput, 'input', BX.proxy(this.applyFilters, this));
				BX.bind(this.searchInput, 'keyup', BX.proxy(this.applyFilters, this));
			}
		},

		onTabClick: function(tab)
		{
			this.activeCategory = tab.getAttribute('data-category') || 'all';

			const tabs = BX.findChildren(this.root, {className: 'ws-faq__tab'}, true) || [];
			for (let i = 0; i < tabs.length; i++)
			{
				const selected = tabs[i] === tab;
				BX.toggleClass(tabs[i], 'is-active', selected);
				tabs[i].setAttribute('aria-selected', selected ? 'true' : 'false');
			}

			this.applyFilters();
		},

		applyFilters: function()
		{
			const query = this.searchInput && BX.type.isNotEmptyString(this.searchInput.value)
				? BX.util.trim(this.searchInput.value).toLowerCase()
				: '';

			let visibleCount = 0;

			for (let i = 0; i < this.items.length; i++)
			{
				const item = this.items[i];
				const category = item.getAttribute('data-category') || '0';
				const haystack = item.getAttribute('data-search') || '';
				const categoryMatch =
					this.activeCategory === 'all'
					|| String(category) === String(this.activeCategory);
				const searchMatch = !query || haystack.indexOf(query) !== -1;
				const visible = categoryMatch && searchMatch;

				if (visible)
				{
					item.removeAttribute('hidden');
					BX.show(item);
					visibleCount++;
				}
				else
				{
					BX.hide(item);
					item.setAttribute('hidden', 'hidden');
				}
			}

			if (this.emptyFilter)
			{
				if (visibleCount > 0 || this.items.length === 0)
				{
					BX.hide(this.emptyFilter);
					this.emptyFilter.setAttribute('hidden', 'hidden');
				}
				else
				{
					this.emptyFilter.removeAttribute('hidden');
					BX.show(this.emptyFilter);
				}
			}
		},

		onVoteClick: function(button, event)
		{
			BX.PreventDefault(event);

			const voteBlock = BX.findParent(button, {className: 'ws-faq__vote'}, this.root);
			if (!voteBlock || BX.hasClass(voteBlock, 'is-loading'))
			{
				return;
			}

			const questionId = parseInt(voteBlock.getAttribute('data-question-id') || '0', 10);
			const isUseful = button.getAttribute('data-useful') === 'Y';
			if (!questionId)
			{
				return;
			}

			const messageNode = voteBlock.querySelector('.ws-faq__vote-message');
			const errorNode = voteBlock.querySelector('.ws-faq__vote-error');
			const buttons = BX.findChildren(voteBlock, {className: 'ws-faq__vote-btn'}, true) || [];

			BX.addClass(voteBlock, 'is-loading');
			if (errorNode)
			{
				BX.hide(errorNode);
				errorNode.setAttribute('hidden', 'hidden');
			}

			BX.ajax.runAction('ws:faq.Vote.vote', {
				data: {
					questionId: questionId,
					isUseful: isUseful,
				},
			}).then(
				BX.proxy(function(response)
				{
					const data = BX.type.isPlainObject(response.data) ? response.data : {};
					this.updateCounters(voteBlock, data);

					for (let i = 0; i < buttons.length; i++)
					{
						const useful = buttons[i].getAttribute('data-useful') === 'Y';
						BX.toggleClass(buttons[i], 'is-active', useful === isUseful);
					}

					BX.addClass(voteBlock, 'is-voted');

					if (messageNode)
					{
						BX.adjust(messageNode, {
							text: data.message || BX.message('WS_FAQ_LIST_THANKS'),
						});
						messageNode.removeAttribute('hidden');
						BX.show(messageNode);
						BX.removeClass(messageNode, 'is-visible');
						void messageNode.offsetWidth;
						BX.addClass(messageNode, 'is-visible');
					}
				}, this),
				BX.proxy(function()
				{
					if (errorNode)
					{
						BX.adjust(errorNode, {
							text: BX.message('WS_FAQ_LIST_VOTE_ERROR'),
						});
						errorNode.removeAttribute('hidden');
						BX.show(errorNode);
					}
				}, this)
			).then(
				BX.proxy(function()
				{
					BX.removeClass(voteBlock, 'is-loading');
				}, this),
				BX.proxy(function()
				{
					BX.removeClass(voteBlock, 'is-loading');
				}, this)
			);
		},

		updateCounters: function(voteBlock, data)
		{
			const useful = voteBlock.querySelector('[data-counter="useful"]');
			const notUseful = voteBlock.querySelector('[data-counter="not-useful"]');

			if (useful && !BX.type.isUndefined(data.usefulCount))
			{
				BX.adjust(useful, {text: String(data.usefulCount)});
			}

			if (notUseful && !BX.type.isUndefined(data.notUsefulCount))
			{
				BX.adjust(notUseful, {text: String(data.notUsefulCount)});
			}
		},
	};
})();
