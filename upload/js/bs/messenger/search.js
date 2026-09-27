!function ( $, window, document ) {
    "use strict";

    XF.MessengerRoomsSearch = XF.Element.newHandler({
        options: {
            searchUrl: ''
        },

        loading: false,
        searchType: 'conversations',

        lastSearchQuery: '',

        init () {
            const $leftColumn = this.$target.closest('.left-column')

            this.$chat = this.$target.rtc()
            this.chat = XF.Element.getHandler(this.$chat, 'chat')

            this.$box = this.$target.closest('.js-searchBox')

            this.$filters = this.$box.find('.js-searchFilters')
            this.$container = $leftColumn.find('.js-searchContainer')
            this.$results = this.$container.find('.js-searchResults')
            this.$loader = this.$container.find('.js-loader')

            this.$target.on('focus', XF.proxy(this, 'onFocus'))

            this.$container.on('click', '.js-searchTab', XF.proxy(this, 'onSearchTabClick'))
            this.$container.on('click', '.js-roomResult', XF.proxy(this, 'onRoomResultClick'))
            this.$container.on('click', '.js-searchClose', XF.proxy(this, 'onSearchCloseClick'))

            this.$box.on('click', '.js-searchReset', XF.proxy(this, 'onSearchResetClick'))
            this.$box.on('click', '.js-searchClose', XF.proxy(this, 'onSearchCloseClick'))

            this.$target.on('keydown paste', this._throttle(() => {
                if (this.$target.val() && !this.$box.hasClass('has-query')) {
                    this.$box.addClass('has-query')
                } else if (!this.$target.val() && this.$box.hasClass('has-query')) {
                    this.$box.removeClass('has-query')
                }

                const val = this.$target.val()
                if (val === this.lastSearchQuery) {
                    return;
                }

                this.loadSearchResults()
            }, 400))

            this.$filters.on('change', XF.proxy(this, 'loadSearchResults'))
        },

        onFocus () {
            this.$box.addClass('is-active')
            this.$container.addClass('is-active')

            this.chat.$roomsPlaceholder.removeClass('visible')

            this.loadSearchResults()
        },

        onSearchTabClick ( e ) {
            e.preventDefault()

            if (this.loading) {
                return;
            }

            const $target = $(e.currentTarget)

            this.setSearchType($target.data('type'))

            this.$container.find('.js-searchTab.is-active').removeClass('is-active')
            $target.addClass('is-active')
        },

        onRoomResultClick ( e ) {
            e.preventDefault()

            const $target = $(e.currentTarget)
            const roomTag = $target.data('room-tag')
            const $roomInList = this.chat.$roomItems.find(`.js-room[data-room-tag="${roomTag}"]`)

            if ($roomInList.length) {
                this.chat.openRoom($roomInList)
                return;
            }

            this.chat.openRoom($target)
        },

        onSearchResetClick ( e ) {
            e.preventDefault()

            this.$target.val('')
            this.$box.removeClass('has-query')
            this.loadSearchResults()
        },

        onSearchCloseClick ( e ) {
            e.preventDefault()

            this.resetSearchForm()
        },

        setSearchType ( type ) {
            this.searchType = type
            this.loadSearchResults()
        },

        loadSearchResults () {
            if (this.loading) {
                return;
            }

            this.$results.html('')

            this.loading = true;
            this.$loader.addClass('is-active')

            const searchData = new FormData(this.$filters[0])
            searchData.append('q', this.$target.val())
            searchData.append('type', this.searchType)

            this.lastSearchQuery = searchData.get('q')

            // convert searchData to object
            const _searchData = {}
            for (const [key, value] of searchData.entries()) {
                _searchData[key] = value
            }

            XF.ajax(
                'GET',
                this.options.searchUrl,
                _searchData,
                ({ html }) => {
                    XF.setupHtmlInsert(html, $html => {
                        this.$results.html($html)
                    })
                },
                { global: false }
            ).always(() => {
                this.loading = false;
                this.$loader.removeClass('is-active')
            })
        },

        resetSearchForm () {
            this.$filters[0].reset()
            this.$target.val('')
            this.$box.removeClass('is-active has-query')
            this.$container.removeClass('is-active')

            this.chat.updateRoomsPlaceholderVisibility()
        },

        _throttle ( func, delay ) {
            let timeout = null;
            let previous = 0;

            return function ( ...args ) {
                const now = Date.now();
                const remaining = delay - (now - previous);

                if (remaining <= 0 || remaining > delay) {
                    if (timeout) {
                        clearTimeout(timeout);
                        timeout = null;
                    }
                    previous = now;
                    func.apply(this, args);
                } else if (!timeout) {
                    timeout = setTimeout(() => {
                        previous = Date.now();
                        timeout = null;
                        func.apply(this, args);
                    }, remaining);
                }
            };
        },
    })

    XF.Element.register('messenger-rooms-search', 'XF.MessengerRoomsSearch');
}
(window.jQuery, window, document);