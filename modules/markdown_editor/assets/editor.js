(function () {
    function enhance(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var textareas = scope.querySelectorAll('textarea[data-sr-editor="markdown"]');
        textareas.forEach(function (textarea) {
            textarea.classList.add('sr-markdown-editor-textarea');
            textarea.dataset.srEditorReady = '1';
        });
    }

    window.srMarkdownEditorEnhance = enhance;
    enhance(document);
}());
