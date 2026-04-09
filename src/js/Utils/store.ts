import { create } from 'zustand';
import { defaultBulkSubmitData, localRetrieveData } from '@/js/Utils/UtilData';

export interface PostQuery {
    status: string | null;
    filtering: boolean;
    media_per_page: number;
    searchKeyWords: string | null;
    order: string;
    orderby: string;
    paged: number;
    isUpdate: boolean;
    date?: string | null;
    categories?: string | null;
}

export interface MediaPost {
    ID: number;
    title: string;
    alt_text: string;
    caption: string;
    description: string;
    post_mime_type: string;
    post_parents: { title?: string; permalink?: string; sku?: string };
    categories: string;
    uploaddir: string;
    thefile: { file: string; filebasename: string; fileextension: string; mainfilename: string; originalname?: string };
    guid: string;
    slug: string;
    url: string;
    custom_meta?: Record<string, unknown>;
    [key: string]: unknown;
}

export interface MediaData {
    postQuery: PostQuery;
    isLoading: boolean;
    posts: MediaPost[];
    total_post: number;
    paged: number;
    posts_per_page: number;
    total_page?: number;
}

export interface SingleMediaState {
    formEdited: boolean;
    ID?: number;
    alt_text?: string | null;
    post_content?: string | null;
    post_excerpt?: string | null;
    post_title?: string | null;
    [key: string]: unknown;
}

export interface BulkSubmitDataItem {
    post_title: string;
    alt_text: string;
    caption: string;
    post_description: string;
    file_name: string;
}

export interface BulkSubmitData {
    bulkChecked: boolean;
    isModalOpen: boolean;
    progressBar: number;
    progressTotal: number;
    ids: number[];
    type: string;
    data: BulkSubmitDataItem;
    will_attached_post_title: string[];
    post_categories: string[];
}

export interface RenameState {
    formEdited: boolean;
    postsdata?: { file: string; filebasename: string; fileextension: string; mainfilename: string; originalname?: string };
    ID?: number;
    newname?: string;
    [key: string]: unknown;
}

export interface ExtendedState {
    isLoading: boolean;
    extendedKey: string;
    isReadyToValidate: boolean;
    isValidate: boolean;
}

export interface OptionsState {
    isLoading: boolean;
    media_table_column: string[];
    default_alt_text: string;
    default_caption_text: string;
    default_desc_text: string;
    others_file_support: string[];
    enable_auto_rename: string;
    media_auto_rename_text: string;
    deregistered_image_sizes: string[];
    custom_image_sizes: Array<{ sizeKey: string; width: number | string; height: number | string; hardCrop: boolean }>;
    media_per_page?: number | string;
    rubbish_per_page?: number | string;
    alt_text_by_post_title?: string;
    caption_text_by_post_title?: string;
    desc_text_by_post_title?: string;
    media_default_alt?: string;
    media_default_caption?: string;
    media_default_desc?: string;
    media_rename_prefix?: string;
    media_rename_suffix?: string;
    auto_rename_by_post_title?: string;
    ai_provider?: 'chatgpt' | 'gemini' | 'claude';
    ai_send_image?: boolean;
    ai_suggestion_count?: number;
    ai_max_suggestion_count?: number;
    ai_chatgpt_key?: string;
    ai_chatgpt_model?: string;
    ai_gemini_key?: string;
    ai_gemini_model?: string;
    ai_claude_key?: string;
    ai_claude_model?: string;
    [key: string]: unknown;
}

export interface RubbishPostQuery {
    paged: number;
    postsPerPage: number;
    isQueryUpdate: boolean;
    fileStatus: string;
    filterExtension: string;
}

export interface RubbishMediaFile {
    id: string | number;
    file_path: string;
    [key: string]: unknown;
}

export interface RubbishMediaState {
    isLoading: boolean;
    isDirModalOpen: boolean;
    showRubbishNotice: boolean;
    mediaFile: RubbishMediaFile[];
    postQuery: RubbishPostQuery;
    paged: number;
    totalPost: number;
    postsPerPage: number;
}

export interface BulkRubbishFile {
    id: string | number;
    path: string;
}

export interface BulkRubbishData {
    bulkChecked: boolean;
    progressBar: boolean | number;
    progressTotal: number;
    isModalOpen: boolean;
    files: BulkRubbishFile[];
    type: string;
    ids: (string | number)[];
}

export interface GeneralData {
    isLoading: boolean;
    openProModal: boolean;
    selectedMenu: string;
    sidebarCollapsed: boolean;
    dateList: Array<{ value: string; label: string }>;
    termsList: Array<{ value: string; label: string }>;
    isDirModalOpen: boolean;
    autoStartScan: boolean;
    scanDir: string;
    scanRubbishDirList: Record<string, { total_items: number; counted: number }>;
    scanDirNextSchedule: string;
    scanRubbishDirLoading: boolean;
    pluginList: unknown[];
    allImageSizes: Record<string, string> | unknown[];
}

export interface ExportImportSettings {
    importUpdateContent?: string | boolean;
    importRename?: string | boolean;
}

export interface ExportImportState {
    isExport: boolean;
    isImport: boolean;
    runImporter: boolean;
    runExporter: boolean;
    pagesRemaining: number;
    mediaFiles: MediaPost[];
    totalPage: number;
    fileCount: number;
    percent: number;
    settings: ExportImportSettings | string[];
    csvFilename: string;
}

export interface BulkExportState {
    isModalOpen: boolean;
    selectedKeys: string[];
}

export interface SearchUsesState {
    isModalOpen: boolean;
}

export interface DuplicateItem {
    attachment_id: number;
    title: string;
    url: string;
    thumbnail: string;
    file_path: string;
    file_size: number;
    used_in: { title: string; permalink: string }[];
    upload_date: string;
}

export interface DuplicateGroup {
    file_hash: string;
    file_size: number;
    item_count: number;
    items: DuplicateItem[];
}

export interface DuplicateState {
    isLoading: boolean;
    isScanning: boolean;
    scanProgress: { processed: number; total: number };
    groups: DuplicateGroup[];
    totalGroups: number;
    potentialSavings: number;
    scanned: number;
    totalAttachments: number;
    paged: number;
    postsPerPage: number;
}

export interface StoreState {
    saveType: string | null;
    setSaveType: (saveType: string | null) => void;

    mediaData: MediaData;
    setMediaData: (update: Partial<MediaData>) => void;

    singleMedia: SingleMediaState;
    setSingleMedia: (update: Partial<SingleMediaState>) => void;

    bulkSubmitData: BulkSubmitData;
    setBulkSubmitData: (update: Partial<BulkSubmitData>) => void;

    rename: RenameState;
    setRename: (update: Partial<RenameState>) => void;

    extended: ExtendedState;
    setExtended: (update: Partial<ExtendedState>) => void;

    options: OptionsState;
    setOptions: (update: Partial<OptionsState>) => void;

    rubbishMedia: RubbishMediaState;
    setRubbishMedia: (update: Partial<RubbishMediaState>) => void;

    bulkRubbishData: BulkRubbishData;
    setBulkRubbishData: (update: Partial<BulkRubbishData>) => void;

    generalData: GeneralData;
    setGeneralData: (update: Partial<GeneralData>) => void;

    exportImport: ExportImportState;
    setExportImport: (update: Partial<ExportImportState>) => void;

    bulkExport: BulkExportState;
    setBulkExport: (update: Partial<BulkExportState>) => void;

    searchUses: SearchUsesState;
    setSearchUses: (update: Partial<SearchUsesState>) => void;

    duplicateData: DuplicateState;
    setDuplicateData: (update: Partial<DuplicateState>) => void;
}

export const initialExportImport: ExportImportState = {
    isExport: false,
    isImport: false,
    runImporter: false,
    runExporter: false,
    pagesRemaining: 0,
    mediaFiles: [],
    totalPage: 0,
    fileCount: 0,
    percent: 0,
    settings: [],
    csvFilename: '',
};

export const initialBulkExport: BulkExportState = {
    isModalOpen: false,
    selectedKeys: ['ID', 'slug', 'url', 'title', 'caption', 'description', 'alt_text'],
};

export const useStore = create<StoreState>((set) => ({
    saveType: null,
    setSaveType: (saveType) => set({ saveType }),

    mediaData: {
        postQuery: {
            status: null,
            filtering: false,
            media_per_page: parseInt(localStorage.getItem('mlt_media_per_page') || '20', 10),
            searchKeyWords: null,
            order: 'DESC',
            orderby: 'id',
            paged: 1,
            isUpdate: false,
        },
        isLoading: true,
        posts: [],
        total_post: -1,
        paged: -1,
        posts_per_page: 1,
    },
    setMediaData: (update) => set((state) => ({ mediaData: { ...state.mediaData, ...update } })),

    singleMedia: {
        formEdited: false,
    },
    setSingleMedia: (update) => set((state) => ({ singleMedia: { ...state.singleMedia, ...update } })),

    bulkSubmitData: defaultBulkSubmitData,
    setBulkSubmitData: (update) => set((state) => ({ bulkSubmitData: { ...state.bulkSubmitData, ...update } })),

    rename: {
        formEdited: false,
    },
    setRename: (update) => set((state) => ({ rename: { ...state.rename, ...update } })),

    extended: {
        isLoading: true,
        extendedKey: '',
        isReadyToValidate: false,
        isValidate: false,
    },
    setExtended: (update) => set((state) => ({ extended: { ...state.extended, ...update } })),

    options: {
        isLoading: true,
        media_table_column: (localRetrieveData('media_table_column') as string[]) || ['Image', 'Title', 'Alt', 'Caption', 'Group'],
        default_alt_text: 'image_name_to_alt',
        default_caption_text: 'none',
        default_desc_text: 'none',
        others_file_support: [],
        enable_auto_rename: '',
        media_auto_rename_text: '',
        deregistered_image_sizes: [],
        custom_image_sizes: [],
        media_per_page: parseInt(localStorage.getItem('mlt_media_per_page') || '20', 10),
        rubbish_per_page: parseInt(localStorage.getItem('mlt_rubbish_per_page') || '20', 10),
    },
    setOptions: (update) => set((state) => ({ options: { ...state.options, ...update } })),

    rubbishMedia: {
        isLoading: true,
        isDirModalOpen: false,
        showRubbishNotice: 'disable' !== localRetrieveData('showRubbishNotice'),
        mediaFile: [],
        postQuery: {
            paged: 1,
            postsPerPage: parseInt(localStorage.getItem('mlt_rubbish_per_page') || '20', 10),
            isQueryUpdate: false,
            fileStatus: 'show',
            filterExtension: '',
        },
        paged: 0,
        totalPost: 0,
        postsPerPage: 0,
    },
    setRubbishMedia: (update) => set((state) => ({ rubbishMedia: { ...state.rubbishMedia, ...update } })),

    bulkRubbishData: {
        bulkChecked: false,
        progressBar: false,
        progressTotal: 0,
        isModalOpen: false,
        files: [],
        type: 'default',
        ids: [],
    },
    setBulkRubbishData: (update) => set((state) => ({ bulkRubbishData: { ...state.bulkRubbishData, ...update } })),

    generalData: {
        isLoading: true,
        openProModal: false,
        selectedMenu: '/',
        sidebarCollapsed: localStorage.getItem('mlt_sidebar_collapsed') === 'true',
        dateList: [],
        termsList: [],
        isDirModalOpen: false,
        autoStartScan: false,
        scanDir: '',
        scanRubbishDirList: {},
        scanDirNextSchedule: '',
        scanRubbishDirLoading: true,
        pluginList: [],
        allImageSizes: [],
    },
    setGeneralData: (update) => set((state) => ({ generalData: { ...state.generalData, ...update } })),

    exportImport: initialExportImport,
    setExportImport: (update) => set((state) => ({ exportImport: { ...state.exportImport, ...update } })),

    bulkExport: initialBulkExport,
    setBulkExport: (update) => set((state) => ({ bulkExport: { ...state.bulkExport, ...update } })),

    searchUses: {
        isModalOpen: false,
    },
    setSearchUses: (update) => set((state) => ({ searchUses: { ...state.searchUses, ...update } })),

    duplicateData: {
        isLoading: false,
        isScanning: false,
        scanProgress: { processed: 0, total: 0 },
        groups: [],
        totalGroups: 0,
        potentialSavings: 0,
        scanned: 0,
        totalAttachments: 0,
        paged: 1,
        postsPerPage: 20,
    },
    setDuplicateData: (update) => set((state) => ({ duplicateData: { ...state.duplicateData, ...update } })),
}));
