import { useState, useEffect } from "react";
import toast from "react-hot-toast";
import Modal from "@/js/Component/Common/Modal";
import { getEditableExif, saveExif } from "@/js/Utils/Data";

// Editor fields, as the Pro EXIF editor (ExifEditorModule::FIELDS) accepts them.
const FIELD_KEYS = [
    "make",
    "model",
    "lens_make",
    "lens_model",
    "software",
    "date_time_original",
    "offset_time_original",
    "date_time_digitized",
    "offset_time_digitized",
    "iso",
    "aperture",
    "shutter_speed",
    "focal_length",
    "exposure_program",
    "exposure_mode",
    "metering_mode",
    "white_balance",
    "flash",
    "gps_lat",
    "gps_lng",
    "gps_altitude",
    "copyright",
    "artist",
    "image_description",
] as const;

type FieldKey = (typeof FIELD_KEYS)[number];
type ExifFields = Record<FieldKey, string>;

const emptyFields = (): ExifFields =>
    Object.fromEntries(FIELD_KEYS.map((key) => [key, ""])) as ExifFields;

// Coded EXIF values the editor offers (the values the EXIF standard defines).
type CodedKey = "exposure_program" | "exposure_mode" | "metering_mode" | "white_balance" | "flash";

const CODED_OPTIONS: Record<CodedKey, [number, string][]> = {
    exposure_program: [
        [0, "Not defined"],
        [1, "Manual"],
        [2, "Normal program"],
        [3, "Aperture priority"],
        [4, "Shutter priority"],
        [5, "Creative (slow speed)"],
        [6, "Action (high speed)"],
        [7, "Portrait"],
        [8, "Landscape"],
    ],
    exposure_mode: [
        [0, "Auto"],
        [1, "Manual"],
        [2, "Auto bracket"],
    ],
    metering_mode: [
        [0, "Unknown"],
        [1, "Average"],
        [2, "Center-weighted average"],
        [3, "Spot"],
        [4, "Multi-spot"],
        [5, "Multi-segment (pattern)"],
        [6, "Partial"],
        [255, "Other"],
    ],
    white_balance: [
        [0, "Auto"],
        [1, "Manual"],
    ],
    flash: [
        [0x00, "No flash"],
        [0x01, "Fired"],
        [0x05, "Fired, return not detected"],
        [0x07, "Fired, return detected"],
        [0x08, "On, did not fire"],
        [0x09, "On, fired"],
        [0x0d, "On, return not detected"],
        [0x0f, "On, return detected"],
        [0x10, "Off, did not fire"],
        [0x14, "Off, did not fire, return not detected"],
        [0x18, "Auto, did not fire"],
        [0x19, "Auto, fired"],
        [0x1d, "Auto, fired, return not detected"],
        [0x1f, "Auto, fired, return detected"],
        [0x20, "No flash function"],
        [0x30, "Off, no flash function"],
        [0x41, "Fired, red-eye reduction"],
        [0x45, "Fired, red-eye reduction, return not detected"],
        [0x47, "Fired, red-eye reduction, return detected"],
        [0x49, "On, red-eye reduction"],
        [0x4d, "On, red-eye reduction, return not detected"],
        [0x4f, "On, red-eye reduction, return detected"],
        [0x50, "Off, red-eye reduction"],
        [0x58, "Auto, did not fire, red-eye reduction"],
        [0x59, "Auto, fired, red-eye reduction"],
        [0x5d, "Auto, fired, red-eye reduction, return not detected"],
        [0x5f, "Auto, fired, red-eye reduction, return detected"],
    ],
};

// Time zone offsets from -12:00 to +14:00 in 15-minute steps.
const OFFSET_OPTIONS: string[] = (() => {
    const list: string[] = [];
    for (let minutes = -12 * 60; minutes <= 14 * 60; minutes += 15) {
        const sign = minutes < 0 ? "-" : "+";
        const abs = Math.abs(minutes);
        list.push(`${sign}${String(Math.floor(abs / 60)).padStart(2, "0")}:${String(abs % 60).padStart(2, "0")}`);
    }
    return list;
})();

// Text limits, as the server checks them (characters; Description: bytes).
const TEXT_LIMITS: Partial<Record<FieldKey, number>> = {
    make: 64,
    model: 64,
    lens_make: 64,
    lens_model: 128,
    software: 128,
    copyright: 256,
    artist: 256,
    image_description: 2000,
};

const MAX_ISO = 65535;
const MAX_EXPOSURE_SECONDS = 86400;

// Convert EXIF date "YYYY:MM:DD HH:MM:SS" to datetime-local input value "YYYY-MM-DDTHH:MM:SS"
function exifDateToInput(exif: string): string {
    if (!exif) return "";
    // Replace first two colons in date part only
    const parts = exif.split(" ");
    if (parts.length !== 2) return "";
    return parts[0].replace(/:/g, "-") + "T" + parts[1];
}

// Convert datetime-local input value "YYYY-MM-DDTHH:MM[:SS]" back to EXIF format "YYYY:MM:DD HH:MM:SS"
// Some browsers (notably Firefox) emit HH:MM without seconds even when step="1"; pad to :00.
function inputDateToExif(input: string): string {
    if (!input) return "";
    const [date, time] = input.split("T");
    if (!date || !time) return "";
    const fullTime = /^\d{2}:\d{2}$/.test(time) ? `${time}:00` : time;
    return date.replace(/-/g, ":") + " " + fullTime;
}

// Current local wall-clock as "YYYY-MM-DDTHH:MM:SS" — matches what <input type="datetime-local"> emits.
function nowLocalISO(): string {
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

// Normalize picker value to a comparable "YYYY-MM-DDTHH:MM:SS" — pads missing seconds.
function normalizePickerValue(v: string): string {
    if (!v) return "";
    return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(v) ? `${v}:00` : v;
}

// True when the picker value is meaningfully in the future. 60-second grace absorbs
// picker rounding, the maxDate setInterval tick, and clock skew so "now" is never flagged.
function isFuture(pickerValue: string): boolean {
    if (!pickerValue) return false;
    const picked = new Date(normalizePickerValue(pickerValue)).getTime();
    if (isNaN(picked)) return false;
    return picked - Date.now() > 60_000;
}

// Exposure time in seconds of "1/250", "10/2500", "0.5", "2" or "30" (optional trailing "s"), or null.
function exposureSeconds(value: string): number | null {
    const text = value.trim().replace(/\s*(?:s|secs?|seconds?|")\.?$/i, "");
    let seconds: number;
    const fraction = /^(\d{1,10})\/(\d{1,10})$/.exec(text);
    if (fraction) {
        const num = Number(fraction[1]);
        const den = Number(fraction[2]);
        if (num < 1 || den < 1 || num > 0xffffffff || den > 0xffffffff) return null;
        seconds = num / den;
    } else if (/^(\d{1,5}(\.\d{1,6})?|\.\d{1,6})$/.test(text)) {
        seconds = Number(text);
    } else {
        return null;
    }
    return seconds > 0 && seconds <= MAX_EXPOSURE_SECONDS ? seconds : null;
}

// A decimal with an optional unit suffix ("50mm", "-12.5 m"), or null.
function decimalValue(value: string, unit: string, digits: number, decimals: number, signed = false): number | null {
    const text = value.trim().replace(new RegExp(`\\s*${unit}$`, "i"), "");
    const pattern = new RegExp(`^${signed ? "-?" : ""}(\\d{1,${digits}}(\\.\\d{1,${decimals}})?|\\.\\d{1,${decimals}})$`);
    return pattern.test(text) ? Number(text) : null;
}

function isValidExifDate(value: string): boolean {
    const m = /^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})$/.exec(value);
    if (!m) return false;
    const [y, mo, d, h, mi, s] = m.slice(1).map(Number);
    const date = new Date(y, mo - 1, d, h, mi, s);
    return date.getFullYear() === y && date.getMonth() === mo - 1 && date.getDate() === d && h < 24 && mi < 60 && s < 60;
}

const inputClass =
    "w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500";

interface ExifEditModalProps {
    isOpen: boolean;
    onClose: () => void;
    attachmentIds: number[];
    onSaved: () => void;
}

export default function ExifEditModal({ isOpen, onClose, attachmentIds, onSaved }: ExifEditModalProps) {
    const [fields, setFields] = useState<ExifFields>(emptyFields());
    // Values loaded for a single image, to tell an intentionally cleared field from one left blank.
    const [loadedFields, setLoadedFields] = useState<ExifFields>(emptyFields());
    // False when the image is not a JPEG: values are then saved in the database only.
    const [writesFile, setWritesFile] = useState(true);
    const [isLoading, setIsLoading] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const [maxDate, setMaxDate] = useState<string>(nowLocalISO());

    const isSingle = attachmentIds.length === 1;

    // Tick max forward so the picker can never offer a future second.
    useEffect(() => {
        if (!isOpen) return;
        setMaxDate(nowLocalISO());
        const t = setInterval(() => setMaxDate(nowLocalISO()), 1000);
        return () => clearInterval(t);
    }, [isOpen]);

    // Load existing EXIF for single edit.
    useEffect(() => {
        if (!isOpen) return;
        setErrors([]);
        setWritesFile(true);

        if (isSingle) {
            loadExif(attachmentIds[0]);
        } else {
            setFields(emptyFields());
            setLoadedFields(emptyFields());
        }
    }, [isOpen, attachmentIds]);

    const loadExif = async (id: number) => {
        setIsLoading(true);
        try {
            const result = await getEditableExif({ attachment_id: id });
            if (result.supported) {
                const loaded = emptyFields();
                for (const key of FIELD_KEYS) {
                    const value = result[key];
                    loaded[key] = value === null || value === undefined ? "" : String(value);
                }
                setFields(loaded);
                setLoadedFields(loaded);
                setWritesFile(result.writes_file !== false);
            } else {
                setErrors([(result.message as string) || "Cannot load EXIF data."]);
            }
        } catch {
            setErrors(["Failed to load EXIF data."]);
        } finally {
            setIsLoading(false);
        }
    };

    const validate = (): string[] => {
        const errs: string[] = [];
        const f = fields;

        for (const [key, label] of [
            ["date_time_original", "Date Taken"],
            ["date_time_digitized", "Date Digitized"],
        ] as [FieldKey, string][]) {
            if (f[key] && !isValidExifDate(f[key])) {
                errs.push(`${label} is not a valid date`);
            }
        }
        if (f.iso && (!/^\d+$/.test(f.iso) || Number(f.iso) < 1 || Number(f.iso) > MAX_ISO)) {
            errs.push(`ISO must be a whole number from 1 to ${MAX_ISO}`);
        }
        if (f.aperture && (isNaN(Number(f.aperture)) || Math.round(Number(f.aperture) * 10) < 1 || Number(f.aperture) > 128)) {
            errs.push("Aperture must be a number from 0.1 to 128");
        }
        if (f.shutter_speed && exposureSeconds(f.shutter_speed) === null) {
            errs.push("Shutter speed must be like 1/250, 0.5 or 30 (seconds)");
        }
        if (f.focal_length) {
            const focal = decimalValue(f.focal_length, "mm", 5, 2);
            if (focal === null || focal <= 0 || focal > 10000) {
                errs.push("Focal length must be millimetres from 0.01 to 10000, like 50 or 4.25");
            }
        }
        if (f.gps_lat && (isNaN(Number(f.gps_lat)) || Number(f.gps_lat) < -90 || Number(f.gps_lat) > 90)) {
            errs.push("Latitude must be -90 to 90");
        }
        if (f.gps_lng && (isNaN(Number(f.gps_lng)) || Number(f.gps_lng) < -180 || Number(f.gps_lng) > 180)) {
            errs.push("Longitude must be -180 to 180");
        }
        if (f.gps_altitude) {
            const altitude = decimalValue(f.gps_altitude, "m", 6, 2, true);
            if (altitude === null || Math.abs(altitude) > 100000) {
                errs.push("Altitude must be metres from -100000 to 100000 (negative below sea level)");
            }
        }
        if (f.image_description && new TextEncoder().encode(f.image_description).length > (TEXT_LIMITS.image_description as number)) {
            errs.push("Description is too long");
        }
        return errs;
    };

    const handleSave = async () => {
        const validationErrors = validate();
        if (validationErrors.length > 0) {
            setErrors(validationErrors);
            return;
        }
        setErrors([]);
        setIsSaving(true);

        // Single edit: a field that had a value and is now empty is cleared.
        // Bulk edit: empty fields are skipped, never cleared.
        const clear = isSingle
            ? FIELD_KEYS.filter((key) => loadedFields[key].trim() !== "" && fields[key].trim() === "")
            : [];
        let successCount = 0;
        let failCount = 0;
        const messages: string[] = [];
        let notice = "";

        try {
            for (const id of attachmentIds) {
                try {
                    const result = await saveExif({ attachment_id: id, fields, clear });
                    if (result.success) {
                        successCount++;
                        if (result.file_updated === false && result.message) {
                            notice = result.message as string;
                        }
                    } else {
                        failCount++;
                        if (result.message && !messages.includes(result.message as string)) {
                            messages.push(result.message as string);
                        }
                    }
                } catch {
                    failCount++;
                }
            }

            if (failCount === 0) {
                onClose();
                if (isSingle && notice) {
                    toast.success(notice);
                } else {
                    toast.success(isSingle ? "EXIF data saved successfully." : `EXIF saved for ${successCount} image${successCount !== 1 ? "s" : ""}.`);
                }
                onSaved();
            } else if (isSingle) {
                setErrors(messages.length > 0 ? messages[0].split(" | ") : ["The EXIF data could not be saved."]);
            } else {
                setErrors([`${successCount} saved, ${failCount} failed.`, ...messages.slice(0, 3)]);
            }
        } catch {
            setErrors(["An error occurred while saving."]);
        } finally {
            setIsSaving(false);
        }
    };

    const updateField = (key: FieldKey, value: string) => {
        setFields((prev) => ({ ...prev, [key]: value }));
    };

    // Changing Date Taken (or its time zone) also changes Date Digitized (or its
    // time zone) while the two were the same and Date Digitized was not edited —
    // the same rule the server applies.
    const updateLinked = (key: FieldKey, linked: FieldKey, value: string) => {
        setFields((prev) => {
            const next = { ...prev, [key]: value };
            if (value !== "" && loadedFields[key] !== "" && loadedFields[key] === loadedFields[linked] && prev[linked] === loadedFields[linked]) {
                next[linked] = value;
            }
            return next;
        });
    };

    const label = (text: string, hint?: string) => (
        <label className="block text-sm font-medium text-gray-700 mb-1">
            {text}
            {hint && <span className="ml-1 font-normal text-gray-400">{hint}</span>}
        </label>
    );

    const textInput = (key: FieldKey, text: string, placeholder = "") => (
        <div>
            {label(text)}
            <input
                type="text"
                className={inputClass}
                value={fields[key]}
                onChange={(e) => updateField(key, e.target.value)}
                maxLength={TEXT_LIMITS[key]}
                placeholder={placeholder}
            />
        </div>
    );

    const blankOption = isSingle ? "— Not set —" : "— Leave unchanged —";

    const codedSelect = (key: CodedKey, text: string) => {
        const options = CODED_OPTIONS[key];
        const current = fields[key];
        const known = current === "" || options.some(([value]) => String(value) === current);
        return (
            <div>
                {label(text)}
                <select className={inputClass} value={current} onChange={(e) => updateField(key, e.target.value)}>
                    <option value="">{blankOption}</option>
                    {!known && <option value={current}>{`Current value (${current})`}</option>}
                    {options.map(([value, name]) => (
                        <option key={value} value={String(value)}>
                            {name}
                        </option>
                    ))}
                </select>
            </div>
        );
    };

    const offsetSelect = (key: FieldKey, linked: FieldKey | null, text: string) => {
        const current = fields[key];
        const known = current === "" || OFFSET_OPTIONS.includes(current);
        return (
            <div>
                {label(text, "(UTC offset)")}
                <select
                    className={inputClass}
                    value={current}
                    onChange={(e) => (linked ? updateLinked(key, linked, e.target.value) : updateField(key, e.target.value))}
                >
                    <option value="">{blankOption}</option>
                    {!known && <option value={current}>{`Current value (${current})`}</option>}
                    {OFFSET_OPTIONS.map((offset) => (
                        <option key={offset} value={offset}>
                            {offset}
                        </option>
                    ))}
                </select>
            </div>
        );
    };

    const dateInput = (key: FieldKey, linked: FieldKey | null, text: string) => {
        const set = (value: string) => (linked ? updateLinked(key, linked, value) : updateField(key, value));
        return (
            <div>
                {label(text)}
                <input
                    type="datetime-local"
                    step="1"
                    max={maxDate}
                    className={inputClass}
                    value={exifDateToInput(fields[key])}
                    onChange={(e) => {
                        const picked = normalizePickerValue(e.target.value);
                        const clamped = isFuture(picked) ? nowLocalISO() : picked;
                        set(inputDateToExif(clamped));
                    }}
                    onBlur={(e) => {
                        const picked = normalizePickerValue(e.target.value);
                        if (isFuture(picked)) {
                            set(inputDateToExif(nowLocalISO()));
                        }
                    }}
                />
            </div>
        );
    };

    const section = (title: string) => (
        <h4 className="text-xs font-semibold uppercase tracking-wide text-gray-500 m-0! pt-2">{title}</h4>
    );

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={
                <h3 className="text-lg font-semibold text-gray-900 m-0!">
                    {isSingle ? "Edit EXIF Data" : `Edit EXIF — ${attachmentIds.length} Images`}
                </h3>
            }
            maxWidth="max-w-[720px]"
            closeOnBackdrop={false}
            footer={
                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={onClose}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        className="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 cursor-pointer transition-colors disabled:opacity-50"
                        onClick={handleSave}
                        disabled={isSaving || isLoading}
                    >
                        {isSaving ? "Saving..." : "Save EXIF"}
                    </button>
                </div>
            }
        >
            <div className="px-6 py-5 max-h-[70vh] overflow-y-auto">
                {isLoading ? (
                    <div className="flex items-center justify-center py-8">
                        <svg className="w-6 h-6 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <span className="ml-2 text-sm text-gray-500">Loading EXIF data...</span>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {!isSingle && (
                            <div className="bg-blue-50 border border-blue-200 rounded-md px-4 py-3 mb-4">
                                <p className="text-sm text-blue-800 m-0!">
                                    Values entered will be applied to all {attachmentIds.length} selected images. Leave fields empty to skip them.
                                </p>
                            </div>
                        )}

                        {isSingle && !writesFile && (
                            <div className="bg-amber-50 border border-amber-200 rounded-md px-4 py-3">
                                <p className="text-sm text-amber-800 m-0!">
                                    This image is not a JPEG. Values are saved in Media Library Tools only; the image file is not changed.
                                </p>
                            </div>
                        )}

                        {section("Camera & Lens")}
                        <div className="grid grid-cols-2 gap-4">
                            {textInput("make", "Make", "Canon")}
                            {textInput("model", "Model", "EOS R5")}
                            {textInput("lens_make", "Lens Make", "Canon")}
                            {textInput("lens_model", "Lens Model", "RF 24-70mm F2.8 L IS USM")}
                        </div>
                        {textInput("software", "Software", "Firmware or editing app")}

                        {section("Date & Time")}
                        <div className="grid grid-cols-2 gap-4">
                            {dateInput("date_time_original", "date_time_digitized", "Date Taken")}
                            {offsetSelect("offset_time_original", "offset_time_digitized", "Date Taken Time Zone")}
                            {dateInput("date_time_digitized", null, "Date Digitized")}
                            {offsetSelect("offset_time_digitized", null, "Date Digitized Time Zone")}
                        </div>
                        <p className="text-xs text-gray-500 m-0!">
                            When Date Digitized matches Date Taken, it follows changes to Date Taken.
                        </p>

                        {section("Exposure")}
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                {label("ISO")}
                                <input
                                    type="number"
                                    className={inputClass}
                                    value={fields.iso}
                                    onChange={(e) => updateField("iso", e.target.value)}
                                    min={1}
                                    max={MAX_ISO}
                                    step={1}
                                    placeholder="100"
                                />
                            </div>
                            <div>
                                {label("Aperture", "(f/)")}
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={fields.aperture}
                                    onChange={(e) => updateField("aperture", e.target.value)}
                                    placeholder="1.8"
                                />
                            </div>
                            <div>
                                {label("Shutter Speed", "(s)")}
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={fields.shutter_speed}
                                    onChange={(e) => updateField("shutter_speed", e.target.value)}
                                    placeholder="1/250 or 30"
                                />
                            </div>
                            <div>
                                {label("Focal Length", "(mm)")}
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={fields.focal_length}
                                    onChange={(e) => updateField("focal_length", e.target.value)}
                                    placeholder="50"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            {codedSelect("exposure_program", "Exposure Program")}
                            {codedSelect("exposure_mode", "Exposure Mode")}
                            {codedSelect("metering_mode", "Metering Mode")}
                            {codedSelect("white_balance", "White Balance")}
                        </div>
                        {codedSelect("flash", "Flash")}

                        {section("Location")}
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                {label("Latitude")}
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={fields.gps_lat}
                                    onChange={(e) => updateField("gps_lat", e.target.value)}
                                    placeholder="-90 to 90"
                                />
                            </div>
                            <div>
                                {label("Longitude")}
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={fields.gps_lng}
                                    onChange={(e) => updateField("gps_lng", e.target.value)}
                                    placeholder="-180 to 180"
                                />
                            </div>
                            <div>
                                {label("Altitude", "(m)")}
                                <input
                                    type="text"
                                    className={inputClass}
                                    value={fields.gps_altitude}
                                    onChange={(e) => updateField("gps_altitude", e.target.value)}
                                    placeholder="Below sea level: -12"
                                />
                            </div>
                        </div>

                        {section("Rights & Description")}
                        <div className="grid grid-cols-2 gap-4">
                            {textInput("copyright", "Copyright", "© 2026 Author")}
                            {textInput("artist", "Artist", "Photographer name")}
                        </div>
                        <div>
                            {label("Description", "(EXIF ImageDescription)")}
                            <textarea
                                className={inputClass}
                                rows={2}
                                value={fields.image_description}
                                onChange={(e) => updateField("image_description", e.target.value)}
                                maxLength={TEXT_LIMITS.image_description}
                                placeholder="Short description of the image"
                            />
                        </div>

                        {/* Errors */}
                        {errors.length > 0 && (
                            <div className="bg-red-50 border border-red-200 rounded-md px-4 py-3">
                                {errors.map((err, i) => (
                                    <p key={i} className="text-sm text-red-700 m-0!">{err}</p>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </Modal>
    );
}
