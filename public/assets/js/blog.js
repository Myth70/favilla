(function() {
    'use strict';

    // --- Quill rich text editor ---
    var quillContainer = document.getElementById('bl-quill-editor');
    var quillInput     = document.getElementById('bl-content-input');
    if (quillContainer && quillInput && typeof Quill !== 'undefined') {
        var quill = new Quill('#bl-quill-editor', {
            theme: 'snow',
            placeholder: t('js.blog.editor_placeholder', 'Scrivi il tuo articolo...'),
            modules: {
                toolbar: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ indent: '-1' }, { indent: '+1' }],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });

        // Load existing content (HTML from hidden JSON script tag)
        var initialScript = document.getElementById('bl-quill-initial');
        if (initialScript) {
            var initialHtml = JSON.parse(initialScript.textContent || '""');
            if (initialHtml) {
                quill.clipboard.dangerouslyPasteHTML(initialHtml);
            }
        }

        // Sync Quill HTML to hidden input before form submit
        var form = quillContainer.closest('form');
        if (form) {
            form.addEventListener('formdata', function(e) {
                e.formData.set('content', quill.root.innerHTML === '<p><br></p>' ? '' : quill.root.innerHTML);
            });
            // Fallback for browsers without FormData event
            form.addEventListener('submit', function() {
                quillInput.value = quill.root.innerHTML === '<p><br></p>' ? '' : quill.root.innerHTML;
            });
        }
    }

    // --- Cover image preview ---
    var coverInput = document.getElementById('bl-cover-input');
    var coverPreview = document.getElementById('bl-cover-preview');
    if (coverInput && coverPreview) {
        coverInput.addEventListener('change', function() {
            coverPreview.innerHTML = '';
            if (this.files && this.files[0]) {
                if (this.files[0].size > 2 * 1024 * 1024) {
                    coverPreview.innerHTML = '<div class="text-danger small">' + t('js.blog.file_too_large', 'Il file supera 2 MB.') + '</div>';
                    this.value = '';
                    return;
                }
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-thumbnail bl-cover-preview-img';
                    coverPreview.appendChild(img);
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    // --- Author form: visibility + schedule toggles ---
    var visRolesBox = document.getElementById('bl-vis-roles-box');
    var visInputs = document.querySelectorAll('input[data-bl-vis-mode]');
    if (visRolesBox && visInputs.length > 0) {
        function syncVisibilityMode() {
            var selected = document.querySelector('input[data-bl-vis-mode]:checked');
            var mode = selected ? selected.getAttribute('data-bl-vis-mode') : 'all';
            visRolesBox.classList.toggle('d-none', mode !== 'roles');
        }

        visInputs.forEach(function (input) {
            input.addEventListener('change', syncVisibilityMode);
        });
        syncVisibilityMode();
    }

    var scheduleToggle = document.querySelector('[data-bl-schedule-toggle="1"]');
    var scheduleBox = document.getElementById('bl-schedule-box');
    var publishAt = document.getElementById('bl-publish-at');
    if (scheduleToggle && scheduleBox && publishAt) {
        function syncScheduleBox() {
            var enabled = !!scheduleToggle.checked;
            scheduleBox.classList.toggle('d-none', !enabled);
            if (!enabled) {
                publishAt.value = '';
            }
        }

        scheduleToggle.addEventListener('change', syncScheduleBox);
        syncScheduleBox();
    }

    // --- Reply toggle (event delegation) ---
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.bl-reply-toggle');
        if (!btn) return;
        e.preventDefault();
        var commentId = btn.getAttribute('data-comment-id');
        var form = document.getElementById('reply-form-' + commentId);
        if (form) {
            form.classList.toggle('d-none');
            if (!form.classList.contains('d-none')) {
                var input = form.querySelector('input[name="body"]');
                if (input) input.focus();
            }
        }
    });

    // --- Delete confirmation ---
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-confirm]');
        if (btn && !confirm(btn.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });

    // --- File picker open (cover image) ---
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-bl-open-picker="1"]');
        if (!btn || typeof FilePicker === 'undefined') return;

        var inputId = btn.getAttribute('data-picker-input') || '';
        var previewId = btn.getAttribute('data-picker-preview') || '';
        var type = btn.getAttribute('data-picker-type') || 'image';
        FilePicker.open(inputId, previewId, type);
    });

    // --- Auto-generate Table of Contents ---
    var articleContent = document.querySelector('.bl-article-content');
    var tocContainer = document.getElementById('bl-toc');
    if (articleContent && tocContainer) {
        var headings = articleContent.querySelectorAll('h2, h3');
        // We use line breaks as pseudo-headings for plain text articles:
        // Lines that are ALL CAPS or start with ## could be headings,
        // but since content is plain text we skip TOC generation for now.
        // TOC only activates if future HTML content has real headings.
        if (headings.length >= 3) {
            var title = document.createElement('div');
            title.className = 'bl-toc-title';
            title.textContent = t('js.blog.toc_title', 'Indice');
            tocContainer.appendChild(title);

            var list = document.createElement('ul');
            list.className = 'bl-toc-list';

            headings.forEach(function(h, i) {
                var id = 'bl-heading-' + i;
                h.id = id;

                var li = document.createElement('li');
                if (h.tagName === 'H3') {
                    li.className = 'bl-toc-h3';
                }

                var a = document.createElement('a');
                a.href = '#' + id;
                a.textContent = h.textContent;
                li.appendChild(a);
                list.appendChild(li);
            });

            tocContainer.appendChild(list);
            tocContainer.classList.remove('d-none');
        }
    }

    // --- Reading progress bar ---
    var progressBar = document.getElementById('bl-reading-progress');
    var articleEl = document.querySelector('.bl-article');
    if (progressBar && articleEl) {
        window.addEventListener('scroll', function() {
            var rect = articleEl.getBoundingClientRect();
            var articleTop = rect.top + window.scrollY;
            var articleHeight = articleEl.offsetHeight;
            var scrolled = window.scrollY - articleTop;
            var progress = Math.max(0, Math.min(100, (scrolled / (articleHeight - window.innerHeight)) * 100));
            progressBar.style.width = progress + '%';
        }, { passive: true });
    }

    // --- Reinit tooltips after HTMX swap ---
    document.body.addEventListener('htmx:afterSwap', function() {
        var tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(function(el) {
            new bootstrap.Tooltip(el);
        });
    });

})();
