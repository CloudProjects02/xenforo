(( window, document ) => {
  "use strict";

  window.ws ??= {}

  window.ws.appStateManager = {
    events: {
      readyToWork: 'ready-to-work',
      restoredFromBfCache: 'restored-from-bf-cache',
      csrfChanged: 'csrf-changed',
    },

    _readyToWork: false,
    _csrfBound: false,
    _csrfInput: null,
    _ensureReadyPromise: null,

    init ( fromBackForwardCache = false ) {
      this._readyToWork = false

      this.ensureDomReady().then(() => {
        this._bindCsrfChange();

        if (fromBackForwardCache) {
          this._initFromBackForwardCache();
          return;
        }

        this._markReadyToWork();
      })
    },

    _initFromBackForwardCache () {
      setTimeout(() => {
        this._refreshCsrf().then(() => {
          this._markReadyToWork();
          this.trigger(this.events.restoredFromBfCache)
        })
      })
    },

    // Triggers when the CSRF token and DOM is ready to be used and DOM
    ensureReady () {
      if (this._ensureReadyPromise) {
        return this._ensureReadyPromise
      }

      return this._ensureReadyPromise = new Promise(resolve => {
        this.ensureDomReady().then(() => {
          if (this._readyToWork) {
            resolve()
            return;
          }

          ws.event.on(
            this._getCsrfInput(),
            this.events.readyToWork,
            resolve,
            { prefixed: true}
          )
        })
      })
    },

    ensureDomReady () {
      return new Promise(resolve => {
        if (document.readyState !== 'complete') {
          window.addEventListener('DOMContentLoaded', () => {
            setTimeout(resolve, 0)
          })
        } else {
          resolve()
        }
      })
    },

    trigger ( name, data ) {
      this.ensureDomReady().then(() => {
        ws.event.trigger(this._getCsrfInput(), name, data, { prefixed: true })
      })
    },

    on ( name, callback, options ) {
      this.ensureDomReady().then(() => {
        ws.event.on(
          this._getCsrfInput(),
          name,
          callback,
          {
            ...options,
            prefixed: true
          }
        )
      })
    },

    _bindCsrfChange () {
      if (this._csrfBound) {
        return;
      }

      this._csrfBound = true
      const observer = new MutationObserver(e => {
        this.trigger(this.events.csrfChanged)
      })
      observer.observe(this._getCsrfInput(), {
        attributes: true,
        attributeFilter: ['value']
      })

      // Listen for CSRF changes from other tabs
      XF.CrossTab.on(this.events.csrfChanged, ({ csrf, user_id: userId }) => {
        XF.config.csrf = csrf;
        document.querySelectorAll('input[name="_xfToken"]')
          .forEach(input => input.value = csrf)

        if (typeof userId === 'undefined') {
          return;
        }

        const activeChangeMessage = document
          .querySelector('.js-activeUserChangeMessage');

        if (userId !== XF.config.userId && !activeChangeMessage) {
          XF.addFixedMessage(
            XF.phrase('active_user_changed_reload_page'),
            { 'class': 'js-activeUserChangeMessage' }
          );
        }
        if (userId === XF.config.userId && activeChangeMessage) {
          activeChangeMessage.remove();
        }
      })
    },

    _refreshCsrf () {
      return new Promise(resolve => {
        if (! XF.Cookie.get('csrf')) { // CSRF will be reloaded by XF.KeepAlive
          this.on(this.events.csrfChanged, () => {
            resolve(XF.config.csrf)
          }, { once: true })
          return;
        }

        this.ensureDomReady().then(() => {
          XF.ajax(
            'GET',
            XF.config.echo.csrfEndpoint,
            {},
            ( { csrf } ) => {
              if (csrf === XF.config.csrf) {
                resolve(csrf)
                return;
              }

              XF.config.csrf = csrf
              document.querySelectorAll('input[name="_xfToken"]')
                .forEach(input => input.value = csrf)

              // trigger csrf change to other tabs
              XF.CrossTab.trigger(this.events.csrfChanged, {
                csrf,
                time: XF.config.time.now,
                user_id: XF.config.userId
              });

              resolve(csrf)
            },
            {
              global: false,
            }
          )
        })
      })
    },

    _markReadyToWork () {
      this._readyToWork = true
      this.trigger(this.events.readyToWork, true)
    },

    _getCsrfInput () {
      if (this._csrfInput) {
        return this._csrfInput
      }

      let input = document.querySelector('input[name="_xfToken"]')
      if (!input) {
        input = document.createElement('input')
        input.name = '_xfToken'
        input.value = XF.config.csrf
        input.type = 'hidden'
        document.body.appendChild(input)
      }
      return this._csrfInput = input
    },
  }

  window.addEventListener('pageshow', e => {
    const isRestoredFromBfCache  = e.persisted
      || performance.getEntriesByType('navigation')[0].type === 'back_forward'

    ws.appStateManager.init(isRestoredFromBfCache)
  })
})(window, document)
