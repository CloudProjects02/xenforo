const MAD = {
  config: {},
  everCookie: null,
  ecKey: null,

  start () {
    this.config = XF.config.mad

    this.init()
    this.madCheck()
  },

  init () {
    if (this.config.checkEvc) {
      this.everCookie = new EverCookie()
      this.ecKey = CryptoJS.MD5(window.location.host).toString() + '_evc'
    }
  },

  async madCheck () {
    const fpJs = await this.getFingerprintForReportIfShould()
    const ec = this.getEvercookiesForReportIfShould()

    this.silentPost(this.config.madCheckLink, { fpJs, ec })
      .then(() => ec.length && this.evercookiesReported(ec))
  },

  getEvercookiesForReportIfShould () {
    if (!this.config.checkEvc)
      return []

    return this.differentCookies()
  },

  evercookiesReported ( differentCookies ) {
    if (!this.config.checkEvc)
      return

    const finalCookies = this.config.evc
    finalCookies.push(...differentCookies)
    this.everCookie.setItem(true, this.ecKey, JSON.stringify(this.uniqueNotEmptySet(finalCookies)))
  },

  differentCookies () {
    const diff = []
    const evercookies = this.evercookies()

    for (const cookie of evercookies) {
      if (!this.config.evc.includes(cookie))
        diff.push(cookie)
    }

    for (const cookie of this.config.evc) {
      if (!evercookies.includes(cookie))
        diff.push(cookie)
    }

    return diff
  },

  evercookies () {
    if (! this.config.checkEvc)
      return []

    const evercookieValue = this.everCookie
      .getItem(true, this.ecKey)

    return typeof evercookieValue === 'string' ? JSON.parse(evercookieValue) : []
  },

  uniqueNotEmptySet ( arr ) {
    return [...new Set(arr)].filter(Boolean)
  },

  getFingerprintForReportIfShould () {
    return new Promise(( resolve, reject ) => {
      if (!this.config.checkFpt)
        resolve('')

      const handleResult = ( result ) => {
        if (!this.hasSameFingerprint(result.visitorId))
          resolve(result.visitorId)

        resolve('')
      }

      if (this.config.fptProToken) {
        this.getFingerprintFromPro()
          .then(handleResult)
          .catch(reject)
      } else {
        this.getFingerprint()
          .then(handleResult)
          .catch(reject)
      }
    })
  },

  hasSameFingerprint ( value ) {
    return this.config.fpt.includes(value)
  },

  getFingerprint () {
    return new Promise(( resolve, reject ) => {
      FingerprintJS.load()
        .then(fp => fp.get())
        .then(resolve)
        .catch(reject)
    })
  },

  getFingerprintFromPro () {
    return new Promise(( resolve, reject ) => {
      FingerprintJS.load({ token: this.config.fptProToken })
        .then(fp => fp.get())
        .then(resolve)
        .catch(reject)
    })
  },

  silentPost ( link, data ) {
    return XF.ajax(
      'POST',
      link,
      data ?? {},
      () => {},
      {
        global: false,
        onError: () => {},
        onComplete: () => {},
        onRedirect: () => {}
      }
    )
  }
};

MAD.start();