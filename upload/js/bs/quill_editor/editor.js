!function ( $, window, document ) {
  "use strict";

  function debounce ( func, delay, onSetup ) {
    let timeoutId;

    return function ( ...args ) {
      clearTimeout(timeoutId);

      timeoutId = setTimeout(() => {
        func.apply(this, args);
      }, delay);

      onSetup?.(timeoutId);
    };
  }

  const QuillToolbarFormatsMap = {
    background: "background",
    bold: "bold",
    code: "code",
    color: "color",
    font: "font",
    italic: "italic",
    link: "link",
    script: "script",
    size: "size",
    strike: "strike",
    underline: "underline",
    align: "align",
    blockquote: "blockquote",
    "code-block": "code-block",
    direction: "direction",
    header: "header",
    indent: "indent",
    list: "list",
    formula: "formula",
    image: "image",
    video: "video"
  }

  XF.QuillEditor = XF.Element.newHandler({
    options: {
      theme: 'bubble',
      placeholder: '',
      submitByEnter: false,
      active: true,
      toolbar: {},
      formats: [],
      name: 'message',
      removeNewLineMatcher: true,
      draftUrl: '',
      draftMinInterval: 700
    },

    lastDraftSave: 0,
    draftAutoSaveTimeoutId: null,
    saveDrafts: true,

    init () {
      this.id = this.$target.xfUniqueId()
      this.$container = this.$target.closest('.js-quillEditorContainer')

      this.triggerDraftAutoSave = debounce(
        XF.proxy(this, 'triggerDraftAutoSave'),
        250,
        ( timeoutId ) => {
          this.draftAutoSaveTimeoutId = timeoutId
        }
      )

      const quillOptions = {
        theme: this.options.theme,
        modules: this.getModules(),
        formats: this.getAllowedFormats(),
        placeholder: this.options.placeholder,
        bounds: this.$target[0]
      }

      this.$inputVal = $(`<input type="hidden" name="${this.options.name}" value="">`)
      this.$target.after(this.$inputVal)

      this.$target.trigger('xf-quill-editor:options', [quillOptions])

      this.quill = new Quill(this.$target[0], quillOptions)

      this.removeNewLineMatcher()
      this.toggle(this.options.active, {
        force: true
      })
      this.bindReset()
      this.bindInput()
      this.bindEnterSubmitter()
      this.bindResizeEvent()
      this.removeQuillLinkFromTooltip()

      this.$target.parent()
        .on('click', '.js-actionSubmit', XF.proxy(this, 'actionSubmitClicked'))
        .on('mousedown', '.js-actionSubmit', e => {
          e.preventDefault()
          e.stopPropagation()
        })

      this.$container.on('click', ( e ) => {
        const $target = $(e.target)
        if (!$target.is('.js-quillEditorContainer')) {
          return;
        }

        if (this.quill.hasFocus()) {
          return;
        }

        this.focus()
      })

      this.$target.trigger('xf-quill-editor:init', [this.quill])
    },

    triggerDraftAutoSave () {
      const now = Date.now()
      if (now - this.lastDraftSave < this.options.draftInterval) {
        return
      }

      this.lastDraftSave = now
      this.saveDraft();
    },

    saveDraft () {
      if (! this.saveDrafts) {
        return;
      }

      const html = this.getHtml()
      const formData = this.getFormData()

      formData.append(`${this.options.name}_html`, html)

      XF.ajax(
        'post',
        this.options.draftUrl,
        formData,
        null,
        { skipDefault: true, skipError: true, global: false }
      );
    },

    loadDraft () {
      if (!this.options.draftUrl) {
        return;
      }

      XF.ajax('get', this.options.draftUrl, {}, ( { draft } ) => {
        if (!draft) {
          return;
        }

        this.setHtml(draft.message)

        this.$target.trigger('xf-quill-editor:draft-loaded', [draft])
      }, { global: false });
    },

    removeNewLineMatcher () {
      if (!this.options.removeNewLineMatcher) {
        return;
      }

      // todo fix blocks inserting
      this.quill.clipboard.matchers.forEach(( matcher, index ) => {
        if (matcher[1].name !== 'matchNewline') {
          return;
        }

        const _matcher = this.quill.clipboard.matchers[index][1]
        this.quill.clipboard.matchers[index][1] = function ( node, delta ) {
          if (node.tagName) {
            const oldDisplay = node.style.display;
            node.style.display = 'inline-block'
            const result = _matcher.call(this, node, delta);
            node.style.display = oldDisplay
            return result;
          }

          return _matcher.call(this, node, delta);
        }
      })
    },

    getModules () {
      const formats = this.getAllowedFormats()

      const modules = {
        toolbar: this.options.toolbar,
        bbCodeMatchers: true,
        imageUploader: {
          upload: ( file ) => {
            return new Promise(( resolve, reject ) => {
              // trigger upload
              this.$target.trigger('xf-quill-editor:upload-image', [file]);

              const fileReader = new FileReader();

              fileReader.addEventListener(
                'load',
                () => {
                  resolve(fileReader.result);
                },
                false
              );

              if (file) {
                fileReader.readAsDataURL(file);
              } else {
                reject("No file selected");
              }
            });
          }
        },
      }

      if (formats.includes('smilie')) {
        modules.smilieImg = true
      }

      if (formats.includes('mention')) {
        modules.mentions = XFQuillMentions
        modules.mentionsMatcher = true
      }

      return modules
    },

    getAllowedFormats () {
      const formats = [
        ...this.options.formats,
        ...this.getFormatsFromToolbar(),
      ]
      return [...new Set(formats)]
    },

    getFormatsFromToolbar () {
      const formats = []

      for (const group of this.options.toolbar) {
        for (const button of group) {
          const format = QuillToolbarFormatsMap[button]
          if (format) {
            formats.push(format)
          }
        }
      }

      return formats
    },

    removeQuillLinkFromTooltip () {
      if (this.options.theme !== 'bubble') {
        return;
      }

      const tooltip = this.quill.theme.tooltip
      const input = tooltip.root.querySelector('input[data-link]');
      input.dataset.link = 'https://example.com';
    },

    bindReset () {
      const $form = this.$target.closest('form')
      if (!$form.length) {
        return
      }

      $form.on('reset', () => {
        this.quill.setText('', 'silent')
      })
    },

    bindInput () {
      this.quill.on('text-change', ( delta, oldDelta, source ) => {
        this.$inputVal.val(this.getHtml())

        if (this.options.draftUrl && source !== 'silent') {
          this.triggerDraftAutoSave()
        }
      })

      this.$inputVal.val(this.getHtml())
    },

    bindResizeEvent () {
      let oldOffsetHeight = this.$container[0].offsetHeight

      const resizeObserver = new ResizeObserver(( entries ) => {
        const newOffsetHeight = this.$container[0].offsetHeight
        if (oldOffsetHeight === newOffsetHeight) {
          return
        }

        this.$target.trigger('xf-quill-editor:resize', [newOffsetHeight, oldOffsetHeight])

        this.updateTooltipMarginTop()

        oldOffsetHeight = newOffsetHeight
      })

      resizeObserver.observe(this.$container[0])
    },

    updateTooltipMarginTop () {
      if (this.options.theme !== 'bubble') {
        return;
      }

      this.quill.theme.tooltip.root.style.marginTop = -1 * this.quill.root.scrollTop + 'px';
    },

    toggle ( value, options ) {
      options = Object.assign({
        force: false,
        // need to not lost focus when sending message
        hard: false
      }, options)

      if (value === this.options.active && !options.force) {
        return
      }

      if (value) {
        options.hard && this.quill.enable()
        this.options.active = true
        this.$container.removeClass('is-disabled')
        return
      }

      options.hard && this.quill.disable()
      this.options.active = false
      this.$container.addClass('is-disabled')
    },

    bindEnterSubmitter () {
      if (!this.options.submitByEnter) {
        return
      }

      // if we have touch events, we don't need to bind enter key
      // because we have submit button
      if (!XF.Feature.has('touchevents')) {
        this.quill.keyboard.bindings[13].unshift({
          key: 13,
          shiftKey: false,
          handler: () => {
            // if mention list is opened, we don't need to submit
            if ($('.ql-mention-list-container').length) {
              // call next binding
              return true;
            }

            this.actionSubmitClicked()
          }
        })
      }
    },

    actionSubmitClicked ( e ) {
      if (e && typeof e.preventDefault === 'function') {
        e.preventDefault();
      }

      if (!this.options.active) {
        return
      }

      if (this.draftAutoSaveTimeoutId) {
        clearTimeout(this.draftAutoSaveTimeoutId)
        this.draftAutoSaveTimeoutId = null
      }

      this.$target.trigger('xf-quill-editor:submit', [this.getHtml(), this.quill])
      this.$target.closest('form').submit()
    },

    focus ( options ) {
      options = Object.assign({
        toEnd: true
      }, options)

      this.quill.focus()

      if (options.toEnd) {
        this.setSelectionToEnd()
      }
    },

    setSelectionToEnd () {
      this.quill.setSelection(this.quill.getLength(), 0)
    },

    insertAtCurrentPosition ( content ) {
      if (Array.isArray(content)) {
        for (const item of content) {
          this.insertAtCurrentPosition(item)
        }
        return;
      }

      const cursorPosition = this.quill.getSelection(true).index;
      let insertedLength = 0

      if (typeof content === 'string') {
        this.quill.insertText(cursorPosition, content)
        insertedLength = content.length
      }

      if (typeof content === 'object') {
        const type = content.type

        const embed = Object.assign({}, content)
        delete embed.type

        this.quill.insertEmbed(cursorPosition, type, embed)

        insertedLength = 1
      }

      this.quill.setSelection(cursorPosition + insertedLength, 0)
    },

    getHtml () {
      return this.quill.root.innerHTML
    },

    setHtml ( html ) {
      if (html.length === 0) {
        this.quill.setText('')
        return this
      }

      if (! (html instanceof jQuery)) {
        html = $('<div/>').html(html)
      }

      html.find('script').remove();
      html.find('style').remove();

      const delta = this.quill.clipboard.convert(html[0].outerHTML)
      this.quill.setContents(delta, 'silent')
      return this
    },

    getFormData () {
      let form = this.$target[0].closest('form')

      if (! form) {
        form = document.createElement('form')
        form.innerHTML = this.$container.html()
      }

      return new FormData(form)
    }
  });

  XF.Element.register('quill-editor', 'XF.QuillEditor');
}
(jQuery, window, document);
