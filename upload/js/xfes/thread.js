window.XFES = window.XFES || {}

;((window, document) =>
{
	'use strict'

	XFES.SuggestedThreads = XF.Element.newHandler({
		options:
		{
			searchUrl: null,
			searchContainer: '< form | .js-suggestedThreadContainer',
			searchDisabler: '.js-suggestedThreadDisable',
			delay: 500,
			onBlur: true,
		},

		enabled: true,
		searchContainer: null,
		searchedTitle: '',
		timeout: null,

		init ()
		{
			if (!this.options.searchUrl)
			{
				console.error('No search URL was provided: %o', this.target)
				return
			}

			const searchContainer = XF.findRelativeIf(
				this.options.searchContainer,
				this.target,
			)
			if (!searchContainer)
			{
				console.error('No search container was found: %o', this.target)
				return
			}
			this.searchContainer = searchContainer

			XF.onDelegated(
				this.searchContainer,
				'click',
				this.options.searchDisabler,
				this.disable.bind(this),
			)

			XF.on(this.target, 'keydown', this.keydown.bind(this))

			if (this.options.delay)
			{
				XF.on(this.target, 'input', this.debounceSearch.bind(this))
			}

			if (this.options.onBlur)
			{
				XF.on(this.target, 'blur', this.performSearch.bind(this))
			}

			const form = this.target.closest('form')
			if (form)
			{
				XF.on(form, 'reset', this.hideSearchContainer.bind(this))
			}
		},

		enable ()
		{
			this.enabled = true
		},

		disable ()
		{
			this.enabled = false
			this.hideSearchContainer()
		},

		/**
		 * @param {Event} e
		 */
		keydown (e)
		{
			if (e.key === 'Escape' && this.enabled)
			{
				this.disable()
				e.preventDefault()
				e.stopPropagation()
			}
		},

		debounceSearch ()
		{
			if (this.timeout)
			{
				clearTimeout(this.timeout)
			}

			this.timeout = setTimeout(
				this.performSearch.bind(this),
				this.options.delay,
			)
		},

		performSearch ()
		{
			if (this.timeout)
			{
				clearTimeout(this.timeout)
				this.timeout = null
			}

			if (!this.enabled)
			{
				return
			}

			const title = this.getInputTitle()
			if (title === this.searchedTitle)
			{
				return
			}

			this.searchedTitle = title

			if (title === '')
			{
				this.hideSearchContainer()
				return
			}

			XF.ajax(
				'POST',
				this.options.searchUrl,
				{ title },
				this.handleResponse.bind(this),
				{ skipDefault: true },
			)
		},

		/**
		 * @param {Object} data
		 */
		handleResponse (data)
		{
			if (data.errors || data.exception)
			{
				this.hideSearchContainer()
				return
			}

			if (data.title !== this.getInputTitle())
			{
				return
			}

			if (data.resultCount === 0)
			{
				this.hideSearchContainer()
				return
			}

			XF.setupHtmlInsert(data.html, (html) =>
			{
				this.showSearchContainer(html)
			})
		},

		/**
		 * @return {string}
		 */
		getInputTitle ()
		{
			return this.target.value.trim()
		},

		/**
		 * @param {Element} html
		 */
		showSearchContainer (html)
		{
			this.searchContainer.innerHTML = ''
			this.searchContainer.append(html)

			if (this.searchContainer.style.display === 'none')
			{
				XF.Animate.fadeDown(this.searchContainer, {
					speed: XF.config.speed.fast,
				})
			}
		},

		hideSearchContainer ()
		{
			XF.Animate.fadeUp(this.searchContainer, {
				speed: XF.config.speed.fast,
				complete: () =>
				{
					this.searchContainer.replaceChildren()
				},
			})
		},
	})

	XF.Element.register('suggested-threads', 'XFES.SuggestedThreads')
})(window, document)
