import { useState, useEffect } from "react";
import toast from "react-hot-toast";
import Modal from "@/js/Component/Common/Modal";
import { getEditableExif, saveExif } from "@/js/Utils/Data";

interface ExifFields {
    make: string;
    model: string;
    date_time_original: string;
    iso: string;
    aperture: string;
    shutter_speed: string;
    gps_lat: string;
    gps_lng: string;
    copyright: string;
    artist: string;
}

const emptyFields: ExifFields = {
    make: "",
    model: "",
    date_time_original: "",
    iso: "",
    aperture: "",
    shutter_speed: "",
    gps_lat: "",
    gps_lng: "",
    copyright: "",
    artist: "",
};

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

interface ExifEditModalProps {
    isOpen: boolean;
    onClose: () => void;
    attachmentIds: number[];
    onSaved: () => void;
}

export default function ExifEditModal({ isOpen, onClose, attachmentIds, onSaved }: ExifEditModalProps) {
    const [fields, setFields] = useState<ExifFields>({ ...emptyFields });
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

        if (isSingle) {
            loadExif(attachmentIds[0]);
        } else {
            setFields({ ...emptyFields });
        }
    }, [isOpen, attachmentIds]);

    const loadExif = async (id: number) => {
        setIsLoading(true);
        try {
            const result = await getEditableExif({ attachment_id: id });
            if (result.supported) {
                setFields({
                    make: (result.make as string) || "",
                    model: (result.model as string) || "",
                    date_time_original: (result.date_time_original as string) || "",
                    iso: result.iso != null ? String(result.iso) : "",
                    aperture: result.aperture != null ? String(result.aperture) : "",
                    shutter_speed: (result.shutter_speed as string) || "",
                    gps_lat: result.gps_lat != null ? String(result.gps_lat) : "",
                    gps_lng: result.gps_lng != null ? String(result.gps_lng) : "",
                    copyright: (result.copyright as string) || "",
                    artist: (result.artist as string) || "",
                });
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
        const { date_time_original, iso, aperture, shutter_speed, gps_lat, gps_lng } = fields;

        if (date_time_original) {
            // EXIF format is "YYYY:MM:DD HH:MM:SS" — accept that exact shape.
            if (!/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/.test(date_time_original) || isNaN(new Date(exifDateToInput(date_time_original)).getTime())) {
                errs.push("Date Taken is not a valid date");
            }
        }

        if (iso && (isNaN(Number(iso)) || Number(iso) < 1 || Number(iso) > 102400)) {
            errs.push("ISO must be 1\u2013102400");
        }
        if (aperture && (isNaN(Number(aperture)) || Number(aperture) <= 0)) {
            errs.push("Aperture must be a positive number");
        }
        if (shutter_speed && !/^(1\/[1-9]\d*|0\.\d+)$/.test(shutter_speed)) {
            errs.push("Shutter speed must be like 1/250 or 0.5");
        }
        if (gps_lat && (isNaN(Number(gps_lat)) || Number(gps_lat) < -90 || Number(gps_lat) > 90)) {
            errs.push("Latitude must be -90 to 90");
        }
        if (gps_lng && (isNaN(Number(gps_lng)) || Number(gps_lng) < -180 || Number(gps_lng) > 180)) {
            errs.push("Longitude must be -180 to 180");
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

        let successCount = 0;
        let failCount = 0;

        try {
            for (const id of attachmentIds) {
                try {
                    const result = await saveExif({ attachment_id: id, fields });
                    if (result.success) {
                        successCount++;
                    } else {
                        failCount++;
                    }
                } catch {
                    failCount++;
                }
            }

            if (failCount === 0) {
                onClose();
                toast.success(isSingle ? "EXIF data saved successfully." : `EXIF saved for ${successCount} image${successCount !== 1 ? "s" : ""}.`);
                onSaved();
            } else {
                setErrors([`${successCount} saved, ${failCount} failed.`]);
            }
        } catch {
            setErrors(["An error occurred while saving."]);
        } finally {
            setIsSaving(false);
        }
    };

    const updateField = (key: keyof ExifFields, value: string) => {
        setFields(prev => ({ ...prev, [key]: value }));
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={
                <h3 className="text-lg font-semibold text-gray-900 m-0!">
                    {isSingle ? "Edit EXIF Data" : `Edit EXIF — ${attachmentIds.length} Images`}
                </h3>
            }
            maxWidth="max-w-[600px]"
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
            <div className="px-6 py-5">
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

                        {/* Camera */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Make</label>
                                <input
                                    type="text"
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    value={fields.make}
                                    onChange={(e) => updateField("make", e.target.value)}
                                    maxLength={64}
                                    placeholder="Canon"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Model</label>
                                <input
                                    type="text"
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    value={fields.model}
                                    onChange={(e) => updateField("model", e.target.value)}
                                    maxLength={64}
                                    placeholder="EOS R5"
                                />
                            </div>
                        </div>

                        {/* Date Taken */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Date Taken</label>
                            <input
                                type="datetime-local"
                                step="1"
                                max={maxDate}
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                value={exifDateToInput(fields.date_time_original)}
                                onChange={(e) => {
                                    const picked = normalizePickerValue(e.target.value);
                                    const clamped = isFuture(picked) ? nowLocalISO() : picked;
                                    updateField("date_time_original", inputDateToExif(clamped));
                                }}
                                onBlur={(e) => {
                                    const picked = normalizePickerValue(e.target.value);
                                    if (isFuture(picked)) {
                                        updateField("date_time_original", inputDateToExif(nowLocalISO()));
                                    }
                                }}
                            />
                        </div>

                        {/* Exposure */}
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">ISO</label>
                                <input
                                    type="number"
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    value={fields.iso}
                                    onChange={(e) => updateField("iso", e.target.value)}
                                    min={1}
                                    max={102400}
                                    placeholder="100"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Aperture (f/)</label>
                                <input
                                    type="text"
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    value={fields.aperture}
                                    onChange={(e) => updateField("aperture", e.target.value)}
                                    placeholder="1.8"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Shutter Speed</label>
                                <input
                                    type="text"
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    value={fields.shutter_speed}
                                    onChange={(e) => updateField("shutter_speed", e.target.value)}
                                    placeholder="1/250"
                                />
                            </div>
                        </div>

                        {/* GPS */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                                <input
                                    type="text"
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    value={fields.gps_lat}
                                    onChange={(e) => updateField("gps_lat", e.target.value)}
                                    placeholder="-90 to 90"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                                <input
                                    type="text"
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    value={fields.gps_lng}
                                    onChange={(e) => updateField("gps_lng", e.target.value)}
                                    placeholder="-180 to 180"
                                />
                            </div>
                        </div>

                        {/* Copyright + Artist */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Copyright</label>
                                <input
                                    type="text"
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    value={fields.copyright}
                                    onChange={(e) => updateField("copyright", e.target.value)}
                                    placeholder="© 2026 Author"
                                    maxLength={128}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Artist</label>
                                <input
                                    type="text"
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    value={fields.artist}
                                    onChange={(e) => updateField("artist", e.target.value)}
                                    placeholder="Photographer name"
                                    maxLength={128}
                                />
                            </div>
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
