/**
 * Move one image of a duplicate group to the trash (Duplicates page, Free).
 *
 * The server (`tsmlt_duplicate_trash`) does every check and only ever moves
 * the image to the trash. This file holds the dialog's fixed text and reads
 * the server's answer.
 */

/** The server's answer, read into one of two outcomes. */
export type DuplicateTrashOutcome =
    | { kind: 'trashed'; message: string }
    | { kind: 'refused'; code: string; message: string };

const FALLBACK_ERROR = 'The image could not be moved to the trash. Nothing was changed.';

/**
 * The confirmation dialog's text, shown the same way before every move,
 * whether or not the image is used.
 */
export const TRASH_DIALOG = {
    title: 'Move Image to Trash?',
    warning: 'If this image is used anywhere on your website, moving it to Trash may cause it to disappear from posts, pages, products, or other locations. This could also create broken image URLs.',
    promoTitle: 'Want to avoid broken images?',
    promoText: 'Upgrade to Media Library Tools Pro and use Duplicate Merge. It replaces supported references to the duplicate image with the image you want to keep before moving the duplicate to Trash, helping reduce the risk of broken images.',
    promoAction: 'Explore Pro – Merge Duplicates',
    cancel: 'Cancel',
    confirm: 'Move to Trash Anyway',
    footer: 'The image will be moved to Trash, not permanently deleted.',
} as const;

/**
 * Read the server's answer (already unwrapped by ajaxPost). Anything that is
 * not a recognised success is a refusal, so the image stays listed.
 */
export const interpretTrashResponse = (data: unknown): DuplicateTrashOutcome => {
    if (!data || typeof data !== 'object') {
        return { kind: 'refused', code: 'unavailable', message: FALLBACK_ERROR };
    }
    const answer = data as Record<string, unknown>;
    const message = typeof answer.message === 'string' && answer.message !== '' ? answer.message : FALLBACK_ERROR;
    if (answer.updated === true) {
        return { kind: 'trashed', message };
    }
    return { kind: 'refused', code: typeof answer.code === 'string' && answer.code !== '' ? answer.code : 'failed', message };
};
