/*
 * Rich-text editor (Quill) for admin Blade forms. Turns every
 * `<textarea data-rich-text>` into an editor that writes its HTML back into
 * the textarea, so the form posts as usual (and still works without this
 * script). The server sanitizes the HTML (App\Support\RichText).
 */
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

export function richText(textarea: HTMLTextAreaElement): void {
    const wrap = document.createElement('div');
    const host = document.createElement('div');
    wrap.className = 'rte';
    wrap.append(host);
    textarea.after(wrap);
    textarea.hidden = true;

    const quill = new Quill(host, {
        theme: 'snow',
        placeholder: textarea.placeholder,
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'link'],
                ['clean'],
            ],
        },
    });

    if (textarea.value.trim() !== '') {
        // Server-sanitized HTML (see App\Support\RichText).
        quill.clipboard.dangerouslyPasteHTML(textarea.value, 'silent');
    }

    const sync = () => {
        textarea.value =
            quill.getText().trim() === ''
                ? ''
                : quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
    };

    quill.on('text-change', sync);
    sync();
}

document
    .querySelectorAll<HTMLTextAreaElement>('textarea[data-rich-text]')
    .forEach(richText);
