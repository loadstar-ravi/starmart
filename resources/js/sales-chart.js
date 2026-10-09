/**
 * Sales chart: the columns are a single stop in the tab order. Once a column has focus,
 * the arrow keys, Home and End move along the days, and each day's figures show the way
 * they do on hover. Without JavaScript the most recent day can still be focused.
 *
 * A chart too wide for the screen starts scrolled to its end, where the latest days are.
 */
document.querySelectorAll('[data-sales-chart]').forEach((chart) => {
    const columns = [...chart.querySelectorAll('[data-sales-chart-column]')];
    const scroller = chart.closest('[data-sales-chart-scroller]');

    if (scroller) {
        scroller.scrollLeft = scroller.scrollWidth;
    }

    const focusColumn = (index) => {
        const target = columns[Math.max(0, Math.min(columns.length - 1, index))];

        columns.forEach((column) => {
            column.tabIndex = column === target ? 0 : -1;
        });

        target.focus();
    };

    chart.addEventListener('keydown', (event) => {
        const current = columns.indexOf(document.activeElement);

        if (current === -1) {
            return;
        }

        const moves = {
            ArrowLeft: current - 1,
            ArrowRight: current + 1,
            Home: 0,
            End: columns.length - 1,
        };

        if (!(event.key in moves)) {
            return;
        }

        event.preventDefault();
        focusColumn(moves[event.key]);
    });
});
