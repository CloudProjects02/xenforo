(( window, document ) => {
  "use strict";

  window.ws ??= {}

  /**
   * Event manager for compatibility with jQuery and VanillaJS
   */
  window.ws.event = {
    prefix: 'websockets:',
    hasJquery: typeof jQuery !== 'undefined',
    hasXFEventSystem: typeof XF.eventHandlers !== 'undefined',

    on ( element, event, selector, callback, options ) {
      options ??= {}

      if (typeof callback === 'object') {
        options = callback
        callback = selector
        selector = null
      }

      if (options.prefixed) {
        event = this.prefix + event
      }
      delete options.prefixed

      if (this.hasJquery && !this.hasXFEventSystem) {
        const fn = options.once ? 'one' : 'on'
        $(element)[fn](event, selector, callback)
        return
      }

      if (!callback) {
        callback = selector
        selector = null
      }

      // XF.trigger doesn't trigger event if no handlers registered in XF.eventHandlers
      XF.on(element, event, e => {
        const shouldCall = selector ? e.target?.matches(selector) : true
        const args = Array.isArray(e.detail) ? e.detail : [e.detail]
        shouldCall && callback(e, ...args)
      }, options)
    },

    trigger ( element, eventName, data, options ) {
      options ??= {}

      if (options.prefixed) {
        eventName = this.prefix + eventName
      }

      if (this.hasJquery) {
        $(element).trigger(eventName, data)
        return
      }

      const event = new CustomEvent(eventName, { detail: data })
      element.dispatchEvent(event)
    }
  }
})(window, document)
