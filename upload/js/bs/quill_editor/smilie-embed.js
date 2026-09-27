!function ( $, window, document ) {
  "use strict";

  const Embed = Quill.import('blots/embed');
  const Delta = Quill.import('delta');

  // todo assert always have space after to fix cursor
  // or fix cursor in other way

  class SmilieEmbed extends Embed {
    static create ( value ) {
      const node = super.create(value);
      const attributes = value.attributes || [];

      for (let key in attributes) {
        const attribute = attributes[key];
        node.setAttribute(attribute.name, attribute.value);
      }

      return node;
    }

    static value ( node ) {
      return node.querySelector('img')
        ?.getAttribute('data-shortcut')
    }
  }

  SmilieEmbed.blotName = 'smilie';
  SmilieEmbed.tagName = 'img';
  SmilieEmbed.className = 'smilie';

  Quill.register('modules/smilieImg', function ( quill, options ) {
    quill.clipboard.addMatcher('img.smilie', function ( node, delta ) {
      const data = {
        smilie: {
          attributes: Array.from(node.attributes)
        }
      }
      const smilieDelta = new Delta().insert(data);
      delta = delta.compose(smilieDelta);
      return delta;
    });
  });
  Quill.register(SmilieEmbed, true)
}
(jQuery, window, document);
