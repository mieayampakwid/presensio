import { useCallback } from 'react';

/**
 * Hook to provide spreadsheet-like keyboard navigation across inputs in a grid.
 * Inputs should have data-row and data-col attributes.
 */
export function useGridNavigation(totalRows: number, totalCols: number) {
    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent<HTMLInputElement>) => {
            const target = e.currentTarget;
            const rowAttr = target.getAttribute('data-row');
            const colAttr = target.getAttribute('data-col');

            if (rowAttr === null || colAttr === null) {
                return;
            }

            const row = parseInt(rowAttr, 10);
            const col = parseInt(colAttr, 10);

            let nextRow = row;
            let nextCol = col;

            switch (e.key) {
                case 'ArrowDown':
                case 'Enter':
                    e.preventDefault();
                    nextRow = Math.min(row + 1, totalRows - 1);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    nextRow = Math.max(row - 1, 0);
                    break;
                case 'ArrowRight':
                    // Only navigate if caret is at the end or entire text is selected
                    if (target.selectionEnd === target.value.length) {
                        e.preventDefault();
                        nextCol = Math.min(col + 1, totalCols - 1);
                    }
                    break;
                case 'ArrowLeft':
                    // Only navigate if caret is at the start
                    if (target.selectionStart === 0) {
                        e.preventDefault();
                        nextCol = Math.max(col - 1, 0);
                    }
                    break;
                default:
                    return;
            }

            if (nextRow !== row || nextCol !== col) {
                const nextInput = document.querySelector<HTMLInputElement>(
                    `input[data-row="${nextRow}"][data-col="${nextCol}"]`,
                );
                if (nextInput) {
                    nextInput.focus();
                    nextInput.select();
                }
            }
        },
        [totalRows, totalCols],
    );

    return { handleKeyDown };
}
