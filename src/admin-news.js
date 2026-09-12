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
