Quill.register('modules/bbCodeMatchers', function ( quill, options ) {
  // [IMG]
  quill.clipboard.addMatcher('img.bbImage', function ( node, delta ) {
    const url = node.getAttribute('data-url');
    if (! url) {
      return new Delta().insert('');
    }

    return new Delta().insert(`[IMG]${url}[/IMG]`);
  });

  // [CODE]
  quill.clipboard.addMatcher('.bbCodeBlock.bbCodeBlock--code', function ( node, delta ) {
    const code = node.querySelector('pre.bbCodeCode').innerText;

    // TODO: Add support for code-block
    return new Delta().insert(`[CODE]${code}[/CODE]`);
  });

  // [URL]
  quill.clipboard.addMatcher('.bbCodeBlock.bbCodeBlock--unfurl', function ( node, delta ) {
    const link = node.querySelector('a.fauxBlockLink-blockLink');
    const url = link?.getAttribute('href');
    if (! url) {
      return new Delta().insert('');
    }

    return new Delta().insert(`[URL unfurl="true"]${url}[/URL]`);
  })
});


Quill.register('modules/imageUploader', ImageUploader);
