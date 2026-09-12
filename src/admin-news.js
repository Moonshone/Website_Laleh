document.querySelectorAll('[data-format-toolbar]').forEach((toolbar) => {
  toolbar.querySelectorAll('[data-format-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = toolbar.querySelector(`input[name="${button.dataset.formatToggle}"]`);
      const active = input.value !== '1';
      input.value = active ? '1' : '0';
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
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
    });
  });
});
