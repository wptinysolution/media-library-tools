/**
 * The Pro-only "Remove GPS Location on Upload" setting in EXIF on Upload.
 *
 * The free plugin never saves `auto_remove_gps_on_upload`; the Pro plugin
 * saves it while licensed and keeps the saved value otherwise.
 */
export const GPS_ON_UPLOAD = {
    key: 'auto_remove_gps_on_upload',
    rowLabel: 'Remove GPS Location on Upload:',
    label: 'Remove GPS Location on Upload',
    description: 'Automatically remove GPS location metadata from supported images when they are uploaded.',
} as const;

export interface GpsOnUploadState {
    /** Shown as checked. */
    checked: boolean;
    /** Cannot be changed because Process EXIF on Upload is off. */
    disabled: boolean;
    /** Pro is not licensed: shows the Pro label and opens the upgrade modal. */
    locked: boolean;
}

/**
 * How the toggle is shown. Checked from the saved value only when licensed,
 * like the other Pro toggles, because removal does not run without a licence.
 * Off processing disables it but keeps the saved value.
 */
export function gpsOnUploadState(options: Record<string, unknown>, isPro: boolean): GpsOnUploadState {
    const enabled = !!Number(options.auto_process_exif);
    return {
        checked: isPro && !!Number(options[GPS_ON_UPLOAD.key]),
        disabled: !enabled,
        locked: !isPro,
    };
}
