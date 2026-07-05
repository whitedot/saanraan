(function () {
    var textareas = document.querySelectorAll('textarea[data-sr-editor="markdown"]');
    textareas.forEach(function (textarea) {
        textarea.classList.add('sr-markdown-editor-textarea');
        textarea.dataset.srEditorReady = '1';
    });
}());
