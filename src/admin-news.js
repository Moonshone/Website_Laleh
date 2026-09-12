document.querySelectorAll('[data-format-toolbar]').forEach((toolbar) => {
  const target = document.getElementById(toolbar.dataset.formatTarget);

  const updatePreview = () => {
    if (!target) return;

    const size = toolbar.querySelector('select')?.value;
    const toggleValue = (suffix) => toolbar.querySelector(`input[name$="_${suffix}"]`)?.value === '1';
    const alignment = toolbar.querySelector('input[name$="_alignment"]')?.value;

    target.style.fontSize = size ? `${size}px` : '';
    target.style.fontWeight = toggleValue('bold') ? '700' : '400';
    target.style.fontStyle = toggleValue('italic') ? 'italic' : 'normal';
    target.style.textDecoration = toggleValue('underline') ? 'underline' : 'none';
    target.style.textAlign = alignment || 'left';
  };

  toolbar.querySelector('select')?.addEventListener('change', updatePreview);

  toolbar.querySelectorAll('[data-format-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = toolbar.querySelector(`input[name="${button.dataset.formatToggle}"]`);
      const active = input.value !== '1';
      input.value = active ? '1' : '0';
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
      updatePreview();
    });
  });

  toolbar.querySelectorAll('[data-format-align]').forEach((button) => {
    button.addEventListener('click', () => {
      const name = button.dataset.formatAlign;
      toolbar.querySelector(`input[name="${name}"]`).value = button.dataset.value;
      toolbar.querySelectorAll(`[data-format-align="${name}"]`).forEach((choice) => {
        const active = choice === button;
        choice.classList.toggle('is-active', active);
        choice.setAttribute('aria-pressed', String(active));
      });
      updatePreview();
    });
  });

  updatePreview();
});

document.querySelectorAll('[data-rich-text-toolbar]').forEach((toolbar) => {
  const editor = document.getElementById(toolbar.dataset.formatTarget);
  const form = toolbar.closest('form');
  const valueField = form?.querySelector('.news-content-value');
  if (!editor || !valueField) return;
  let savedRange = null;

  const rememberSelection = () => {
    const selection = window.getSelection();
    if (selection.rangeCount && editor.contains(selection.anchorNode)) {
      savedRange = selection.getRangeAt(0).cloneRange();
    }
  };

  const restoreSelection = () => {
    if (!savedRange) return;
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedRange);
  };

  const commandButtons = toolbar.querySelectorAll('[data-rich-text-command]');
  const updateState = () => {
    commandButtons.forEach((button) => {
      const active = document.queryCommandState(button.dataset.richTextCommand);
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
    });
  };

  const runCommand = (command, value = null) => {
    editor.focus();
    restoreSelection();
    document.execCommand(command, false, value);
    rememberSelection();
    updateState();
  };

  // Keep the editor selection intact while toolbar controls are clicked.
  commandButtons.forEach((button) => {
    button.addEventListener('mousedown', (event) => event.preventDefault());
    button.addEventListener('click', () => runCommand(button.dataset.richTextCommand));
  });

  const sizeSelect = toolbar.querySelector('[data-rich-text-size]');
  sizeSelect?.addEventListener('mousedown', rememberSelection);
  sizeSelect?.addEventListener('change', () => {
    if (!sizeSelect.value) return;
    runCommand('fontSize', '7');
    editor.querySelectorAll('font[size="7"]').forEach((font) => {
      const span = document.createElement('span');
      span.style.fontSize = `${sizeSelect.value}px`;
      while (font.firstChild) span.appendChild(font.firstChild);
      font.replaceWith(span);
    });
    sizeSelect.value = '';
  });

  editor.addEventListener('keyup', () => { rememberSelection(); updateState(); });
  editor.addEventListener('mouseup', () => { rememberSelection(); updateState(); });
  editor.addEventListener('paste', (event) => {
    event.preventDefault();
    document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
  });

  form.addEventListener('submit', (event) => {
    editor.querySelectorAll('div').forEach((block) => {
      const paragraph = document.createElement('p');
      for (const attribute of block.attributes) paragraph.setAttribute(attribute.name, attribute.value);
      while (block.firstChild) paragraph.appendChild(block.firstChild);
      block.replaceWith(paragraph);
    });
    valueField.value = editor.innerHTML;
    if (!editor.textContent.trim()) {
      event.preventDefault();
      editor.focus();
      editor.setAttribute('aria-invalid', 'true');
    }
  });
});
