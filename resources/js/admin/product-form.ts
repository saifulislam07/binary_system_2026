/*
 * Admin product form (Blade page, no Vue): rich-text description, key
 * features list, photo manager and the discount hint. Every part enhances
 * plain form fields, so the form still submits without this script. DOM is
 * built with textContent only.
 */
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const form = document.getElementById('product-form') as HTMLFormElement | null;

function el<K extends keyof HTMLElementTagNameMap>(
    tag: K,
    className = '',
    text?: string,
): HTMLElementTagNameMap[K] {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
}

function icon(name: string): HTMLElement {
    const i = el('i', `bi bi-${name}`);
    i.setAttribute('aria-hidden', 'true');

    return i;
}

/* ---------- Rich-text description ---------- */

function richText(textarea: HTMLTextAreaElement): void {
    const wrap = el('div', 'rte');
    const host = el('div');
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

/* ---------- Key features ---------- */

function features(list: HTMLElement): void {
    const template = document.querySelector<HTMLTemplateElement>(
        '[data-feature-template]',
    );
    const addButton =
        document.querySelector<HTMLButtonElement>('[data-feature-add]');
    const counter = document.querySelector<HTMLElement>('[data-feature-count]');
    const max = Number(list.dataset.max ?? 20);

    if (!template || !addButton) {
        return;
    }

    const rows = () =>
        Array.from(list.querySelectorAll<HTMLElement>('[data-feature-row]'));

    const refresh = () => {
        const all = rows();
        all.forEach((row, i) =>
            row
                .querySelector('input')
                ?.setAttribute('aria-label', `Key feature ${i + 1}`),
        );
        addButton.disabled = all.length >= max;

        if (counter) {
            counter.textContent = `${all.length} / ${max}`;
        }
    };

    const add = (after?: HTMLElement) => {
        if (rows().length >= max) {
            return;
        }

        const row = (template.content.cloneNode(true) as DocumentFragment)
            .firstElementChild as HTMLElement;

        if (after) {
            after.after(row);
        } else {
            list.append(row);
        }

        wire(row);
        refresh();
        row.querySelector('input')?.focus();
    };

    let dragged: HTMLElement | null = null;

    const wire = (row: HTMLElement) => {
        const input = row.querySelector('input');
        const handle = row.querySelector<HTMLElement>('.feature-handle');

        row.querySelector('[data-feature-remove]')?.addEventListener(
            'click',
            () => {
                const next = (row.nextElementSibling ??
                    row.previousElementSibling) as HTMLElement | null;
                row.remove();
                refresh();
                next?.querySelector('input')?.focus();
            },
        );

        input?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                add(row);
            }

            if (
                event.key === 'Backspace' &&
                input.value === '' &&
                rows().length > 1
            ) {
                event.preventDefault();
                const previous =
                    row.previousElementSibling as HTMLElement | null;
                row.remove();
                refresh();
                previous?.querySelector('input')?.focus();
            }
        });

        // Drag by the handle only, so text in the input stays selectable.
        handle?.addEventListener('pointerdown', () => (row.draggable = true));
        row.addEventListener('dragstart', () => {
            dragged = row;
            row.style.opacity = '0.4';
        });
        row.addEventListener('dragend', () => {
            row.draggable = false;
            row.style.opacity = '';
            dragged = null;
        });
        row.addEventListener('dragover', (event) => {
            if (!dragged || dragged === row) {
                return;
            }

            event.preventDefault();
            const box = row.getBoundingClientRect();
            const before = event.clientY < box.top + box.height / 2;
            row[before ? 'before' : 'after'](dragged);
        });
    };

    rows().forEach(wire);
    addButton.addEventListener('click', () => add());
    refresh();
}

/* ---------- Photos ---------- */

type Photo =
    | { kind: 'saved'; id: number; url: string; removed: boolean }
    | { kind: 'new'; file: File; url: string };

function uploader(root: HTMLElement): void {
    const grid = root.querySelector<HTMLElement>('[data-uploader-grid]');
    const drop = root.querySelector<HTMLElement>('[data-uploader-drop]');
    const input = root.querySelector<HTMLInputElement>('[data-uploader-input]');
    const orderHost = root.querySelector<HTMLElement>('[data-uploader-order]');
    const errorBox = root.querySelector<HTMLElement>('[data-uploader-error]');
    const counter = document.querySelector<HTMLElement>('[data-photo-count]');
    const maxNew = Number(root.dataset.maxNew ?? 12);
    const maxBytes = Number(root.dataset.maxBytes ?? 3 * 1024 * 1024);

    if (!grid || !drop || !input || !orderHost || !form) {
        return;
    }

    // Saved photos come from the server-rendered grid.
    const photos: Photo[] = Array.from(
        grid.querySelectorAll<HTMLElement>('[data-media-id]'),
    ).map((item) => ({
        kind: 'saved',
        id: Number(item.dataset.mediaId),
        url: item.dataset.url ?? '',
        removed: Boolean(
            item.querySelector<HTMLInputElement>('input[type=checkbox]')
                ?.checked,
        ),
    }));

    // A separate picker, so choosing again adds files instead of replacing them.
    const picker = el('input');
    picker.type = 'file';
    picker.multiple = true;
    picker.accept = input.accept;
    picker.hidden = true;
    root.append(picker);
    input.hidden = true;
    input.removeAttribute('id');
    drop.id = 'images';

    const showError = (message: string) => {
        if (errorBox) {
            errorBox.textContent = message;
        }
    };

    const newCount = () => photos.filter((p) => p.kind === 'new').length;

    const addFiles = (files: FileList | File[]) => {
        const problems: string[] = [];

        for (const file of Array.from(files)) {
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
                problems.push(`${file.name}: not a JPG, PNG or WebP image`);
                continue;
            }

            if (file.size > maxBytes) {
                problems.push(`${file.name}: larger than 3 MB`);
                continue;
            }

            if (newCount() >= maxNew) {
                problems.push(
                    `Up to ${maxNew} new photos per save — save, then add more.`,
                );
                break;
            }

            photos.push({ kind: 'new', file, url: URL.createObjectURL(file) });
        }

        showError(problems.join(' · '));
        render();
    };

    let dragIndex: number | null = null;

    const move = (from: number, to: number) => {
        if (to < 0 || to >= photos.length || from === to) {
            return;
        }

        const [item] = photos.splice(from, 1);
        photos.splice(to, 0, item);
        render();
    };

    const button = (label: string, iconName: string, onClick: () => void) => {
        const b = el('button', 'btn btn-outline-secondary');
        b.type = 'button';
        b.title = label;
        b.setAttribute('aria-label', label);
        b.append(icon(iconName));
        b.addEventListener('click', onClick);

        return b;
    };

    const render = () => {
        grid.replaceChildren();
        const coverIndex = photos.findIndex(
            (p) => !(p.kind === 'saved' && p.removed),
        );

        photos.forEach((photo, index) => {
            const removed = photo.kind === 'saved' && photo.removed;
            const item = el(
                'div',
                'uploader-item' + (removed ? ' is-removed' : ''),
            );
            item.draggable = !removed;

            const img = el('img');
            img.src = photo.url;
            img.alt = '';
            item.append(img);

            const badges = el('div', 'uploader-badges');

            if (index === coverIndex) {
                badges.append(el('span', 'badge is-cover', 'Cover'));
            }

            if (photo.kind === 'new') {
                badges.append(el('span', 'badge', 'New'));
            }

            if (removed) {
                badges.append(el('span', 'badge', 'Will be removed'));
            }

            item.append(badges);

            const actions = el('div', 'uploader-actions');
            const left = el('div', 'd-flex gap-1');
            const right = el('div', 'd-flex gap-1');

            if (!removed) {
                left.append(
                    button('Move left', 'arrow-left', () =>
                        move(index, index - 1),
                    ),
                    button('Move right', 'arrow-right', () =>
                        move(index, index + 1),
                    ),
                );

                if (index !== coverIndex) {
                    left.append(
                        button('Make cover', 'star', () => move(index, 0)),
                    );
                }
            }

            if (photo.kind === 'new') {
                right.append(
                    button('Remove', 'trash', () => {
                        URL.revokeObjectURL(photo.url);
                        photos.splice(index, 1);
                        render();
                    }),
                );
            } else {
                right.append(
                    removed
                        ? button(
                              'Keep this photo',
                              'arrow-counterclockwise',
                              () => {
                                  photo.removed = false;
                                  render();
                              },
                          )
                        : button('Remove', 'trash', () => {
                              photo.removed = true;
                              render();
                          }),
                );
            }

            actions.append(left, right);
            item.append(actions);

            item.addEventListener('dragstart', (event) => {
                dragIndex = index;
                item.classList.add('is-dragging');
                event.dataTransfer?.setData('text/plain', String(index));
            });
            item.addEventListener('dragend', () => {
                item.classList.remove('is-dragging');
                dragIndex = null;
            });
            item.addEventListener('dragover', (event) => {
                if (dragIndex !== null && dragIndex !== index) {
                    event.preventDefault();
                    item.classList.add('is-drop-target');
                }
            });
            item.addEventListener('dragleave', () =>
                item.classList.remove('is-drop-target'),
            );
            item.addEventListener('drop', (event) => {
                event.preventDefault();
                item.classList.remove('is-drop-target');

                if (dragIndex !== null) {
                    move(dragIndex, index);
                }
            });

            grid.append(item);
        });

        const kept = photos.filter(
            (p) => !(p.kind === 'saved' && p.removed),
        ).length;

        if (counter) {
            counter.textContent = kept === 1 ? '1 photo' : `${kept} photos`;
        }
    };

    // Before submitting: the files to upload, what to remove, and the order.
    form.addEventListener('submit', () => {
        const transfer = new DataTransfer();
        orderHost.replaceChildren();
        let newIndex = 0;

        for (const photo of photos) {
            const token = el('input');
            token.type = 'hidden';

            if (photo.kind === 'new') {
                transfer.items.add(photo.file);
                token.name = 'image_order[]';
                token.value = `n:${newIndex++}`;
            } else if (photo.removed) {
                token.name = 'remove_images[]';
                token.value = String(photo.id);
            } else {
                token.name = 'image_order[]';
                token.value = `m:${photo.id}`;
            }

            orderHost.append(token);
        }

        input.files = transfer.files;
    });

    drop.addEventListener('click', () => picker.click());
    drop.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            picker.click();
        }
    });
    picker.addEventListener('change', () => {
        if (picker.files) {
            addFiles(picker.files);
        }

        picker.value = '';
    });

    for (const type of ['dragenter', 'dragover']) {
        drop.addEventListener(type, (event) => {
            if ((event as DragEvent).dataTransfer?.types.includes('Files')) {
                event.preventDefault();
                drop.classList.add('is-dragover');
            }
        });
    }

    drop.addEventListener('dragleave', () =>
        drop.classList.remove('is-dragover'),
    );
    drop.addEventListener('drop', (event) => {
        event.preventDefault();
        drop.classList.remove('is-dragover');

        if (event.dataTransfer?.files.length) {
            addFiles(event.dataTransfer.files);
        }
    });

    render();
}

/* ---------- Brand: pick one or type a new one ---------- */

function brandPicker(root: HTMLElement): void {
    const select = root.querySelector<HTMLSelectElement>('select');
    const newGroup = root.querySelector<HTMLElement>('[data-brand-new]');
    const newInput = newGroup?.querySelector('input');
    const toggle = root.querySelector<HTMLButtonElement>(
        '[data-brand-new-toggle]',
    );
    const cancel = root.querySelector<HTMLButtonElement>(
        '[data-brand-new-cancel]',
    );

    if (!select || !newGroup || !newInput || !toggle || !cancel) {
        return;
    }

    const setMode = (creating: boolean) => {
        select.hidden = creating;
        select.disabled = creating;
        newGroup.hidden = !creating;
        newInput.disabled = !creating;
        toggle.hidden = creating;

        (creating ? newInput : select).focus();
    };

    toggle.addEventListener('click', () => setMode(true));
    cancel.addEventListener('click', () => {
        newInput.value = '';
        setMode(false);
    });

    select.disabled = select.hidden;
    newInput.disabled = newGroup.hidden;
}

/* ---------- Discount hint ---------- */

function discountHint(): void {
    const price = document.getElementById('price') as HTMLInputElement | null;
    const was = document.getElementById(
        'compare_at_price',
    ) as HTMLInputElement | null;
    const out = document.querySelector<HTMLElement>('[data-discount]');

    if (!price || !was || !out) {
        return;
    }

    const update = () => {
        const p = Number(price.value);
        const w = Number(was.value);
        out.textContent =
            p > 0 && w > p
                ? `Customers see “Save ${Math.floor(((w - p) * 100) / w)}%”.`
                : '';
    };

    price.addEventListener('input', update);
    was.addEventListener('input', update);
    update();
}

const description =
    document.querySelector<HTMLTextAreaElement>('[data-rich-text]');
const featureList = document.querySelector<HTMLElement>('[data-feature-list]');
const photoManager = document.querySelector<HTMLElement>('[data-uploader]');
const brand = document.querySelector<HTMLElement>('[data-brand-picker]');

if (description) {
    richText(description);
}

if (featureList) {
    features(featureList);
}

if (photoManager) {
    uploader(photoManager);
}

if (brand) {
    brandPicker(brand);
}

discountHint();
