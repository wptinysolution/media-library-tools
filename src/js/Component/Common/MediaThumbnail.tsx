import React, { useState, useEffect } from "react";

export type MediaKind =
    | "image"
    | "audio"
    | "video"
    | "pdf"
    | "archive"
    | "document"
    | "spreadsheet"
    | "presentation"
    | "code"
    | "text"
    | "unknown";

const EXT_TO_KIND: Record<string, MediaKind> = {
    // image
    jpg: "image", jpeg: "image", png: "image", gif: "image", webp: "image",
    bmp: "image", svg: "image", ico: "image", avif: "image", heic: "image", heif: "image",
    // audio
    mp3: "audio", wav: "audio", ogg: "audio", flac: "audio", aac: "audio", m4a: "audio",
    // video
    mp4: "video", mov: "video", webm: "video", mkv: "video", avi: "video", wmv: "video", m4v: "video",
    // archive
    zip: "archive", rar: "archive", "7z": "archive", tar: "archive", gz: "archive", bz2: "archive",
    // pdf
    pdf: "pdf",
    // document
    doc: "document", docx: "document", odt: "document", rtf: "document", pages: "document",
    // spreadsheet
    xls: "spreadsheet", xlsx: "spreadsheet", ods: "spreadsheet", csv: "spreadsheet", numbers: "spreadsheet",
    // presentation
    ppt: "presentation", pptx: "presentation", odp: "presentation", key: "presentation",
    // text / code
    txt: "text", md: "text", log: "text",
    js: "code", ts: "code", tsx: "code", jsx: "code", json: "code", html: "code", htm: "code",
    css: "code", scss: "code", php: "code", py: "code", rb: "code", sh: "code", xml: "code", yml: "code", yaml: "code",
};

const MIME_PREFIX_TO_KIND: Array<[string, MediaKind]> = [
    ["image/", "image"],
    ["audio/", "audio"],
    ["video/", "video"],
    ["text/", "text"],
];

const MIME_EXACT_TO_KIND: Record<string, MediaKind> = {
    "application/pdf": "pdf",
    "application/zip": "archive",
    "application/x-rar-compressed": "archive",
    "application/x-7z-compressed": "archive",
    "application/x-tar": "archive",
    "application/gzip": "archive",
    "application/msword": "document",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document": "document",
    "application/vnd.oasis.opendocument.text": "document",
    "application/rtf": "document",
    "application/vnd.ms-excel": "spreadsheet",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": "spreadsheet",
    "application/vnd.oasis.opendocument.spreadsheet": "spreadsheet",
    "application/vnd.ms-powerpoint": "presentation",
    "application/vnd.openxmlformats-officedocument.presentationml.presentation": "presentation",
    "application/vnd.oasis.opendocument.presentation": "presentation",
    "application/json": "code",
    "application/xml": "code",
};

const detectKindFromMime = (mime?: string | null): MediaKind | null => {
    if (!mime) return null;
    const normalized = mime.toLowerCase().trim();
    if (!normalized) return null;
    if (MIME_EXACT_TO_KIND[normalized]) return MIME_EXACT_TO_KIND[normalized];
    for (const [prefix, kind] of MIME_PREFIX_TO_KIND) {
        if (normalized.startsWith(prefix)) return kind;
    }
    return null;
};

const detectKindFromUrl = (url?: string | null): MediaKind | null => {
    if (!url) return null;
    const clean = url.split("?")[0].split("#")[0];
    const dot = clean.lastIndexOf(".");
    if (dot === -1 || dot === clean.length - 1) return null;
    const ext = clean.substring(dot + 1).toLowerCase();
    return EXT_TO_KIND[ext] ?? null;
};

export const resolveMediaKind = (
    mimeType?: string | null,
    url?: string | null,
    fileName?: string | null
): MediaKind => {
    return (
        detectKindFromMime(mimeType) ??
        detectKindFromUrl(fileName) ??
        detectKindFromUrl(url) ??
        "unknown"
    );
};

interface IconProps {
    className?: string;
}

const baseIconClass = "w-1/2 h-1/2 text-gray-400";

const AudioIcon = ({ className = baseIconClass }: IconProps) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M9 19V6l12-3v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-3a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
);

const VideoIcon = ({ className = baseIconClass }: IconProps) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
    </svg>
);

const PdfIcon = ({ className = baseIconClass }: IconProps) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
);

const ArchiveIcon = ({ className = baseIconClass }: IconProps) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
    </svg>
);

const DocumentIcon = ({ className = baseIconClass }: IconProps) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M9 12h6m-6 4h6m-7 5h8a2 2 0 002-2V7l-5-5H6a2 2 0 00-2 2v14a2 2 0 002 2z" />
    </svg>
);

const SpreadsheetIcon = ({ className = baseIconClass }: IconProps) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M4 6h16M4 10h16M4 14h16M4 18h16M9 4v16M15 4v16" />
    </svg>
);

const PresentationIcon = ({ className = baseIconClass }: IconProps) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M3 4h18v12H3zM12 16v4m-4 0h8M7 8h6m-6 4h10" />
    </svg>
);

const CodeIcon = ({ className = baseIconClass }: IconProps) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
    </svg>
);

const TextIcon = ({ className = baseIconClass }: IconProps) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M7 8h10M7 12h10M7 16h6M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
    </svg>
);

const UnknownIcon = ({ className = baseIconClass }: IconProps) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M14 2v6h6" />
    </svg>
);

const KIND_TO_ICON: Record<Exclude<MediaKind, "image">, React.ComponentType<IconProps>> = {
    audio: AudioIcon,
    video: VideoIcon,
    pdf: PdfIcon,
    archive: ArchiveIcon,
    document: DocumentIcon,
    spreadsheet: SpreadsheetIcon,
    presentation: PresentationIcon,
    code: CodeIcon,
    text: TextIcon,
    unknown: UnknownIcon,
};

export interface MediaThumbnailProps {
    /** Direct URL to the attachment (image or otherwise). */
    url?: string | null;
    /** WordPress post_mime_type — the primary signal for kind detection. */
    mimeType?: string | null;
    /** Optional file name; used as a secondary signal when mimeType is absent. */
    fileName?: string | null;
    /** Alt text for the image; ignored for placeholder icons. */
    alt?: string;
    /** Class applied to the rendered <img>. */
    className?: string;
    /** Class applied to the placeholder <svg> when not an image. */
    iconClassName?: string;
    /** Width in px applied as the HTML attribute (legacy callers that size by width). */
    width?: number;
    /** Optional click handler proxied to the underlying element. */
    onClick?: React.MouseEventHandler<HTMLElement>;
}

/**
 * Centralised thumbnail renderer.
 *
 * Decides between an <img> and a MIME-appropriate placeholder so non-image
 * attachments (audio, video, PDF, archives, docs, …) never produce broken
 * image previews. If an <img> fails to load at runtime (404, hot-linked
 * source moved, etc.), it transparently swaps to the placeholder.
 *
 * Extending: add a new entry to EXT_TO_KIND / MIME_EXACT_TO_KIND and a
 * matching icon in KIND_TO_ICON.
 */
export default function MediaThumbnail({
    url,
    mimeType,
    fileName,
    alt = "",
    className,
    iconClassName,
    width,
    onClick,
}: MediaThumbnailProps) {
    const kind = resolveMediaKind(mimeType, url, fileName);
    const [errored, setErrored] = useState(false);

    // Reset the errored flag when the source genuinely changes, so a fresh
    // record in the same component slot gets a real attempt at <img>.
    useEffect(() => {
        setErrored(false);
    }, [url, mimeType]);

    const showImage = "image" === kind && !!url && !errored;

    if (showImage) {
        return (
            <img
                src={url as string}
                alt={alt}
                className={className}
                width={width}
                onClick={onClick}
                onError={() => setErrored(true)}
            />
        );
    }

    const IconComponent = KIND_TO_ICON[kind === "image" ? "unknown" : kind];
    return (
        <span
            className={className}
            style={{ display: "inline-flex", alignItems: "center", justifyContent: "center", width: width ? `${width}px` : undefined }}
            aria-label={alt || kind}
            onClick={onClick}
        >
            <IconComponent className={iconClassName ?? baseIconClass} />
        </span>
    );
}
