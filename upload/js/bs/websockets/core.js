(( window, document ) => {
  "use strict";

  window.ws ??= {}

  window.ws.managerEvents = {
    connected: 'connected',
    disconnected: 'disconnected',
    initiated: 'initiated',
  }

  const buildOptions = () => {
    let options = {
      namespace: '',
      broadcaster: 'pusher',
      key: XF.config.echo.key,
      forceTLS: false,
      encrypted: true,
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
      csrfToken: XF.config.csrf,
      cluster: XF.config.echo.cluster,
      authEndpoint: XF.config.echo.authEndpoint,
      userAuthentication: {
        endpoint: XF.config.echo.userAuthEndpoint,
        headers: {
          'X-XF-Csrf-Token': XF.config.csrf
        }
      },
      auth: {
        headers: {
          'X-XF-Csrf-Token': XF.config.csrf
        }
      }
    }

    const isPusher = !!options.cluster
    if (!isPusher) {
      options = {
        ...options,
        wsHost: XF.config.echo.host || window.location.host,
        wsPort: XF.config.echo.port,
        wssPort: XF.config.echo.port
      }
    }

    return options
  }
  const setupEcho = () => {
    window.ws.echo = new Echo(buildOptions())
    window.ws.manager ??= {
      initiated: false,
      connected: false,
      channels: {},

      init () {
        this.echo = ws.echo

        this.joinDefaultChannels()
        this.addPageUidToAjaxHeaders()
        this.listenCsrfChanges()

        this.echo.connector.pusher.connection.bind('connected', () => {
          this.connected = true

          ws.event.trigger(
            document,
            ws.managerEvents.connected,
            this,
            { prefixed: true }
          );
        });
        this.echo.connector.pusher.connection.bind('disconnected', () => {
          this.connected = false

          ws.event.trigger(
            document,
            ws.managerEvents.disconnected,
            this,
            { prefixed: true }
          );
        });

        this.initiated = true

        ws.event.trigger(
          document,
          ws.managerEvents.initiated,
          this,
          { prefixed: true }
        );
      },

      listenCsrfChanges () {
        ws.appStateManager.on(ws.appStateManager.events.csrfChanged, () => {
          this.updateCsrfInConnector()
        })
      },

      updateCsrfInConnector () {
        const token = XF.config.csrf

        const updateOptions = options => {
          options.csrfToken = token

          options.userAuthentication.headers['X-CSRF-TOKEN'] = token
          options.auth.headers['X-CSRF-TOKEN'] = token

          options.userAuthentication.headers['X-XF-Csrf-Token'] = token
          options.auth.headers['X-XF-Csrf-Token'] = token
        }

        updateOptions(ws.echo.connector.options)
        updateOptions(ws.echo.options)
      },

      joinDefaultChannels () {
        this.channels['forum'] = this.echo.private('Forum')

        if (XF.config.userId) {
          this.channels['visitor'] = this.echo.private('User.' + XF.config.userId)
        }
      },

      addPageUidToAjaxHeaders () {
        ws.event.on(document, 'ajax:send', ( e, xhr ) => {
          if (xhr) {
            xhr.setRequestHeader('X-WebSockets-Page-Uid', XF.config.echo.pageUid)
            return
          }

          const request = e?.request
          if (! request) {
            return
          }

          request.headers.set('X-WebSockets-Page-Uid', XF.config.echo.pageUid)
        })
      }
    }

    /**
     * @deprecated Use `window.ws.echo` instead
     */
    XF.Echo = window.ws.echo
    /**
     * @deprecated Use `window.ws.manager` instead
     */
    XF.EchoManager ??= window.ws.manager

    window.ws.manager.init()
  }

  window.getWebsocketsPromise = () => {
    return new Promise(( resolve, reject ) => {
      ws.appStateManager.ensureReady().then(() => {
        if (ws.manager?.initiated) {
          resolve({
            echo: ws.echo,
            manager: ws.manager
          })
          return;
        }

        ws.event.on(document, ws.managerEvents.initiated, ( e, manager ) => {
          resolve({
            echo: manager.echo,
            manager: manager
          })
        })
      })
    })
  }

  ws.appStateManager.ensureReady().then(setupEcho)
})(window, document)
