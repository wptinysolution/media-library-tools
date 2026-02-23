import { create } from 'zustand';
import { defaultBulkSubmitData, localRetrieveData } from '@/js/Utils/UtilData';

export const initialExportImport = {
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
};

export const initialBulkExport = {
    isModalOpen: false,
    selectedKeys: ['ID', 'slug', 'url', 'title', 'caption', 'description', 'alt_text'],
};

export const useStore = create((set) => ({
    // ─── saveType ────────────────────────────────────────────────────────────
    saveType: null,
    setSaveType: (saveType) => set({ saveType }),

    // ─── mediaData ───────────────────────────────────────────────────────────
    mediaData: {
        postQuery: {
            status: null,
            filtering: false,
            media_per_page: 1,
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

    // ─── singleMedia ─────────────────────────────────────────────────────────
    singleMedia: {
        formEdited: false,
    },
    setSingleMedia: (update) => set((state) => ({ singleMedia: { ...state.singleMedia, ...update } })),

    // ─── bulkSubmitData ──────────────────────────────────────────────────────
    bulkSubmitData: defaultBulkSubmitData,
    setBulkSubmitData: (update) => set((state) => ({ bulkSubmitData: { ...state.bulkSubmitData, ...update } })),

    // ─── rename ──────────────────────────────────────────────────────────────
    rename: {
        formEdited: false,
    },
    setRename: (update) => set((state) => ({ rename: { ...state.rename, ...update } })),

    // ─── extended ────────────────────────────────────────────────────────────
    extended: {
        isLoading: true,
        extendedKey: '',
        isReadyToValidate: false,
        isValidate: false,
    },
    setExtended: (update) => set((state) => ({ extended: { ...state.extended, ...update } })),

    // ─── options ─────────────────────────────────────────────────────────────
    options: {
        isLoading: true,
        media_table_column: ['Image', 'Parents', 'Title', 'Alt', 'Caption'],
        default_alt_text: 'image_name_to_alt',
        default_caption_text: 'none',
        default_desc_text: 'none',
        others_file_support: [],
        enable_auto_rename: '',
        media_auto_rename_text: '',
        deregistered_image_sizes: [],
        custom_image_sizes: [],
    },
    setOptions: (update) => set((state) => ({ options: { ...state.options, ...update } })),

    // ─── rubbishMedia ────────────────────────────────────────────────────────
    rubbishMedia: {
        isLoading: true,
        isDirModalOpen: false,
        showRubbishNotice: 'disable' !== localRetrieveData('showRubbishNotice'),
        mediaFile: [],
        postQuery: {
            paged: 1,
            postsPerPage: 10,
            isQueryUpdate: false,
            fileStatus: 'show',
            filterExtension: '',
        },
        paged: 0,
        totalPost: 0,
        postsPerPage: 0,
    },
    setRubbishMedia: (update) => set((state) => ({ rubbishMedia: { ...state.rubbishMedia, ...update } })),

    // ─── bulkRubbishData ─────────────────────────────────────────────────────
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

    // ─── generalData ─────────────────────────────────────────────────────────
    generalData: {
        isLoading: true,
        openProModal: false,
        selectedMenu: '/',
        dateList: [],
        termsList: [],
        isDirModalOpen: false,
        scanDir: '',
        scanRubbishDirList: [],
        scanDirNextSchedule: '',
        scanRubbishDirLoading: true,
        pluginList: [],
        allImageSizes: [],
    },
    setGeneralData: (update) => set((state) => ({ generalData: { ...state.generalData, ...update } })),

    // ─── exportImport ────────────────────────────────────────────────────────
    exportImport: initialExportImport,
    setExportImport: (update) => set((state) => ({ exportImport: { ...state.exportImport, ...update } })),

    // ─── bulkExport ──────────────────────────────────────────────────────────
    bulkExport: initialBulkExport,
    setBulkExport: (update) => set((state) => ({ bulkExport: { ...state.bulkExport, ...update } })),

    // ─── searchUses ──────────────────────────────────────────────────────────
    searchUses: {
        isModalOpen: false,
    },
    setSearchUses: (update) => set((state) => ({ searchUses: { ...state.searchUses, ...update } })),
}));
