let Delta = Quill.import('delta');

!function ( $, window, document ) {
  "use strict";

  const Embed = Quill.import('blots/embed');

  class Quote extends Embed {
    constructor ( node ) {
      super(node);

      this.domNode.innerHTML = this.contentNode.innerHTML

      this.domNode.classList.add('bbCodeBlock');
      this.domNode.classList.add('bbCodeBlock--quote');

      const removeBtn = this.domNode.querySelector('.js-remove');
      removeBtn.addEventListener('click', () => {
        this.domNode.closest('p').remove();
      })
    }

    static create ( value ) {
      const node = super.create();

      const dataKeys = Object.keys(value.dataset);
      for (let key in dataKeys) {
        const dataKey = dataKeys[key];
        node.dataset[dataKey] = value.dataset[dataKey];
      }

      const content = document.createElement('div');
      content.innerHTML = value.content;
      content.classList.add('bbCodeBlock-content');
      content.setAttribute('contenteditable', true);

      const removeBtn = document.createElement('span');
      removeBtn.classList.add('bbCodeBlock-remove');
      removeBtn.classList.add('js-remove');

      node.appendChild(content);
      node.appendChild(removeBtn);

      return node;
    }

    static value ( node ) {
      let contentNode = node?.querySelector('.bbCodeBlock-expandContent')
        || node?.querySelector('.bbCodeBlock-content');
      if (! contentNode) {
        contentNode = node
      }

      return {
        dataset: node?.dataset || {},
        content: contentNode?.innerHTML || ''
      };
    }
  }

  Quote.blotName = 'blockquote';
  Quote.tagName = 'blockquote';

  Quill.register(Quote, true)
}
(jQuery, window, document);
