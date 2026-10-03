<!-- Rich editor for the invoice free-text blocks (Bank Details, Terms).
     Summernote writes plain HTML that Dompdf can render, and the output is
     sanitised again by invoice_rich_text_html() before it reaches a customer. -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<style>
    .note-editor.note-frame { background: #fff; border-color: #cbd5e1; }
    .note-editor .note-editable { background: #fff; color: #1e293b; font-size: .9rem; }
    .note-editor .note-toolbar { background: #f1f5f9; }
    .note-editor .note-placeholder { color: #94a3b8; }
</style>
<script>
    $(function () {
        // Bootstrap's modal traps focus, which stops typing inside the editor.
        document.addEventListener('focusin', function (e) {
            if (e.target.closest('.note-editable, .note-modal, .note-toolbar')) {
                e.stopImmediatePropagation();
            }
        }, true);

        $('.rich-editor').summernote({
            height: 170,
            disableDragAndDrop: true,
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['table', 'link']],
                ['misc', ['undo', 'redo', 'codeview']]
            ]
        });

        // Summernote syncs on change; sync again on submit so a value typed
        // and submitted without blurring is not lost.
        $('form').on('submit', function () {
            $(this).find('.rich-editor').each(function () {
                $(this).val($(this).summernote('code'));
            });
        });
    });
</script>
