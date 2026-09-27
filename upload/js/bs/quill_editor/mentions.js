Quill.register('modules/mentions', quillMention)

const MentionBlot = Quill.import('blots/mention');

class XFMentionBlot extends MentionBlot {
  static render ( item ) {
    const rootEl = document.createElement('span');

    rootEl.classList.add('mention');

    rootEl.innerText = item.id;

    return rootEl;
  }
}
XFMentionBlot.blotName = 'mention';

Quill.register(XFMentionBlot);

const XFQuillMentions = {
  allowedChars: /^[A-Za-z\sÅÄÖåäö0-9_-]*$/,
  mentionDenotationChars: ['@'],
  mentionListClass: 'autoCompleteList ql-mention-list',
  positioningStrategy: 'fixed',
  blotName: 'mention',

  source: function ( searchTerm, renderList, mentionChar ) {
    if (searchTerm.length < 2) {
      return;
    }

    XF.ajax(
      'GET',
      XF.getAutoCompleteUrl(),
      { q: searchTerm },
      ( { results } ) => {
        renderList(results, searchTerm);
      }
    );
  },

  renderItem: function ( item, searchTerm ) {
    const el = document.createElement('span');

    el.innerText = item.text;

    if (item.iconHtml) {
      const iconEl = document.createElement('span');
      iconEl.classList.add('autoCompleteList-icon');
      iconEl.innerHTML = item.iconHtml;
      el.appendChild(iconEl);
    }

    return el;
  },

  onOpen () {
    if (! XF.isRtl()) {
      return;
    }

    // fix position of autocomplete list in rtl
    const autoCompleteList = this.mentionContainer;

    const containerPos = this.quill.container.getBoundingClientRect();
    const mentionCharPos = this.quill.getBounds(this.mentionCharPos);

    const left = containerPos.left + mentionCharPos.left - autoCompleteList.offsetWidth;

    autoCompleteList.style.left = left + 'px';
  }
}

Quill.register('modules/mentionsMatcher', function ( quill, options ) {
  quill.clipboard.addMatcher('a.username', function ( node, delta ) {
    const username = node.getAttribute('data-username');
    if (! username) {
      return new Delta().insert(node.innerText);
    }

    const denotationChar = XFQuillMentions.mentionDenotationChars[0];
    const data = {
      mention: {
        denotationChar,
        // replace denotation chat in start
        id: username.replace(new RegExp('^' + denotationChar), ''),
      }
    }
    return new Delta().insert(data);
  });

  // remove mention from dom on touch
  quill.root.addEventListener('touchstart', function ( event ) {
    const target = event.target.closest('span.mention') || event.target;
    if (! target.matches('span.mention')) {
      return;
    }

    target.remove();
  });
});
