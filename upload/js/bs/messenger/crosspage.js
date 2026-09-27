!function ( $, window, document ) {
    "use strict";

    String.prototype.replaceTagsToValues = String.prototype.replaceTagsToValues || function ( pairs ) {
        let str = this;

        for (const key in pairs) {
            str = str.replace(new RegExp(`<${key}>`, 'g'), pairs[key]);
        }

        return str;
    }

    XF.Messenger = $.extend(XF.Messenger || {}, {
        noticesUrl: '',
        popupUrl: '',
        popup: null,
        enableSound: false,
        soundPath: '',

        $audio: null,

        init () {
            this.noticesUrl = XF.config.messenger.noticesUrl
            this.popupUrl = XF.config.messenger.popupUrl

            XF.EchoManager.channels['visitor']
                .listen('XFM.NewMessage', XF.proxy(this, 'onNewMessage'))

            if (XF.config.messenger.popupEnabled) {
                this.popup = new XF.ChatPopup({
                    url: this.popupUrl,
                    eventPrefix: 'XFM',
                    draggable: true
                })

                this.isValidPage() && this.popup.setupFromCookie()
            }

            if (XF.config.messenger.enableSound) {
                this.enableSound = true
                this.soundPath = XF.config.messenger.soundPath

                this.$audio = $('<audio />').attr('src', this.soundPath)
                    .appendTo(document.body);
            }
        },

        isValidPage () {
            return !this.isConversationsPage() && !this.isAdminPage()
        },

        isConversationsPage () {
            return !!window.location.href.match(/conversations/)?.length
        },

        isAdminPage () {
            return !!window.location.href.match(/admin\.php/)?.length
        },

        onNewMessage ( { message } ) {
            if (message.user_id === XF.config.userId) {
                return;
            }

            if (!this.isValidPage()) {
                return;
            }

            if (this.popup && this.popup.isOpened()) {
                return;
            }

            const shouldPlaySound = () => {
                if (!this.enableSound) {
                    return false
                }

                const soundConfig = this.getSoundConfig()
                const roomSoundEnabled = soundConfig[message.room_tag]
                    || soundConfig[parseInt(message.room_tag, 10)] // if room_tag is numeric, after json decode it will be converted to int, so we need to check both variants

                if (typeof roomSoundEnabled === 'undefined') {
                    return true
                }

                if (! roomSoundEnabled) {
                    return false
                }

                // if browser tab is active, don't play sound
                return !document.hasFocus()
            }
            if (shouldPlaySound()) {
                this.playSound()
            }

            XF.ajax(
                'GET',
                this.noticesUrl.replaceTagsToValues({ tag: message.room_tag }),
                { start_message_id: message.id },
                ({ html }) => {
                    XF.setupHtmlInsert(html, $html => {
                        if (! document.hasFocus()) {
                            // show notices after document is focused
                            $(document).one('focus', () => {
                                this.showFloatingNotices($html)
                            })
                            return;
                        }

                        this.showFloatingNotices($html)
                    })
                },
                { global: false }
            )
        },

        showFloatingNotices ( $notices ) {
            let $floatingNotices = $('.js-notices.notices--floating')
            if (!$floatingNotices.length) {
                $floatingNotices = $('<ul class="notices notices--floating js-notices" data-xf-init="notices" data-type="floating" />')
                    .appendTo($('.u-bottomFixer'))
            }

            $notices.on('click', '.js-locationChanger', e => {
                e.preventDefault()
                const $target = $(e.currentTarget)
                window.location.href = $target.data('href')
            })

            $notices.appendTo($floatingNotices)

            XF.activate($notices)
            XF.activate($floatingNotices)

            this.setupNoticesDestructTimer($notices)

            const $xfNotices = XF.Element.getHandler($floatingNotices, 'notices')
            $xfNotices.updateNoticeList()
            $xfNotices.filter()
            $xfNotices.start()
        },

        setupNoticesDestructTimer ($notices) {
            $notices.find('.js-notice').each((i, el) => {
                const $notice = $(el)
                const duration = $notice.data('display-duration')
                let timeoutId
                const removeNotice = () => {
                    $notice.xfFadeUp(XF.config.speed.slow, () => {
                        $notice.remove()
                    })
                }

                if (duration) {
                    timeoutId = setTimeout(removeNotice, duration)
                }

                $notice.find('.js-alertToggle').click(() => {
                    timeoutId && clearTimeout(timeoutId);
                    removeNotice()
                })
            })
        },

        playSound () {
            this.$audio[0].play()
        },

        getSoundConfig () {
            return XF.Cookie.getJson(this.getSoundConfigCookieName()) || {};
        },

        getSoundConfigCookieName () {
            return 'xfm_chat_sound';
        },
    })

    $(document).ready(() => XF.Messenger.init())
}
(window.jQuery, window, document);