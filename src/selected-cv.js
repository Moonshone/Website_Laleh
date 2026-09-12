const selectedCvGrid = document.querySelector('[data-selected-cv-grid]');

if (selectedCvGrid) {
  const desktopQuery = window.matchMedia('(min-width: 769px)');
  const sections = Array.from(selectedCvGrid.querySelectorAll('.selected-cv-section')).map((section) => ({
    category: section.querySelector('.selected-cv-category')?.textContent ?? '',
    entries: Array.from(section.querySelectorAll('.selected-cv-entry')).map((entry) => entry.cloneNode(true)),
  }));
  let resizeTimer;

  const entryCount = sections.reduce((total, section) => total + section.entries.length, 0);
  const categoryBreaks = new Set();
  let runningEntryCount = 0;

  sections.slice(0, -1).forEach((section) => {
    runningEntryCount += section.entries.length;
    categoryBreaks.add(runningEntryCount);
  });

  function createColumn() {
    const column = document.createElement('div');
    column.className = 'selected-cv-column';
    return column;
  }

  function createSection(section, entries, showCategory) {
    const sectionElement = document.createElement('div');
    sectionElement.className = 'selected-cv-section';

    if (showCategory) {
      const category = document.createElement('h3');
      category.className = 'selected-cv-category';
      category.textContent = section.category;
      sectionElement.append(category);
    } else {
      sectionElement.classList.add('selected-cv-section-continuation');
      sectionElement.setAttribute('aria-label', `${section.category}, continued`);
    }

    const entriesElement = document.createElement('div');
    entriesElement.className = 'selected-cv-entries';
    entriesElement.append(...entries.map((entry) => entry.cloneNode(true)));
    sectionElement.append(entriesElement);
    return sectionElement;
  }

  function render(splitAfter = entryCount) {
    const columns = [createColumn(), createColumn()];
    let entryIndex = 0;

    sections.forEach((section) => {
      const firstColumnEntries = [];
      const secondColumnEntries = [];

      section.entries.forEach((entry) => {
        (entryIndex < splitAfter ? firstColumnEntries : secondColumnEntries).push(entry);
        entryIndex += 1;
      });

      if (firstColumnEntries.length) {
        columns[0].append(createSection(section, firstColumnEntries, true));
      }
      if (secondColumnEntries.length) {
        columns[1].append(createSection(section, secondColumnEntries, firstColumnEntries.length === 0));
      }
    });

    selectedCvGrid.replaceChildren(...columns.filter((column) => column.childElementCount));
    return columns;
  }

  function differenceAt(splitAfter) {
    const columns = render(splitAfter);
    return Math.abs(columns[0].getBoundingClientRect().height - columns[1].getBoundingClientRect().height);
  }

  function bestBreak(candidates) {
    return candidates.reduce((best, splitAfter) => {
      const difference = differenceAt(splitAfter);
      return difference < best.difference ? { splitAfter, difference } : best;
    }, { splitAfter: candidates[0], difference: Number.POSITIVE_INFINITY });
  }

  function balanceColumns() {
    if (!desktopQuery.matches || entryCount < 2) {
      render();
      return;
    }

    const allBreaks = Array.from({ length: entryCount - 1 }, (_, index) => index + 1);
    const completeCategoryBreaks = allBreaks.filter((splitAfter) => categoryBreaks.has(splitAfter));
    const categoryResult = completeCategoryBreaks.length ? bestBreak(completeCategoryBreaks) : null;
    const entryResult = bestBreak(allBreaks);

    // Prefer an intact category at an effectively equal result. Otherwise use
    // the measurably best safe boundary between complete CV entries.
    const result = categoryResult && categoryResult.difference <= entryResult.difference + 1
      ? categoryResult
      : entryResult;

    render(result.splitAfter);
  }

  function scheduleBalance() {
    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(balanceColumns, 100);
  }

  balanceColumns();
  document.fonts?.ready.then(balanceColumns);
  window.addEventListener('resize', scheduleBalance, { passive: true });
}
