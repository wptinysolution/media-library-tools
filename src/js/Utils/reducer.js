/* global rtsbParams */

import * as Types from "./actionType";

import {defaultBulkSubmitData, localRetrieveData } from "./UtilData";

export const initialState = {
	saveType: null,
	mediaData: {
		postQuery: {
			status: null,
			filtering : false,
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
	singleMedia: {
		formEdited: false
	},
	bulkSubmitData: defaultBulkSubmitData,
	rename:{
		formEdited : false,
	},
	extended: {
		isLoading: true,
		extendedKey:'',
		isReadyToValidate: false,
		isValidate: false,
	},
	options: {
		isLoading: true,
		media_table_column: [ 'Image', 'Parents', 'Title', 'Alt', 'Caption' ],
		default_alt_text: "image_name_to_alt",
		default_caption_text: "none",
		default_desc_text: "none",
		others_file_support:[],
		enable_auto_rename: '',
		media_auto_rename_text: '',
        deregistered_image_sizes: [],
        custom_image_sizes: [],
    },
	rubbishMedia:{
		isLoading: true,
		isDirModalOpen : false,
		showRubbishNotice: 'disable' !== localRetrieveData( "showRubbishNotice" ),
		mediaFile: [],
		postQuery: {
			paged: 1,
			postsPerPage: 10,
			isQueryUpdate: false,
			fileStatus: 'show',
			filterExtension: ''
		},
		paged: 0,
		totalPost: 0,
		postsPerPage: 0
	},
	bulkRubbishData: {
		bulkChecked: false,
		progressBar : false,
		progressTotal : 0,
		isModalOpen: false,
		files: [],
		type: 'default',
		ids: []
	},
	generalData:{
        isLoading: true,
        openProModal: false,
		selectedMenu: '/',
		dateList: {},
		termsList: {},
		isDirModalOpen: false,
		scanDir: '',
		scanRubbishDirList: [],
		scanDirNextSchedule: '',
		scanRubbishDirLoading: true,
        pluginList: {},
        allImageSizes:[],
	},
    exportImport:{
        isExport: false,
        isImport: false,
        runImporter: false,
        runExporter: false,
        pagesRemaining: 0,
        mediaFiles: [],
        totalPage: 0,
        fileCount: 0,
        percent: 0,
        settings: []
    },
    bulkExport: {
        isModalOpen: false,
    },
    searchUses: {
        isModalOpen: false,
    },
};

const reducer = (state, action) => {
	switch (action.type) {
		case Types.BULK_SUBMIT:
			return {
				...state,
				saveType: action.saveType,
				bulkSubmitData: action.bulkSubmitData,
			};
		case Types.UPDATE_SINGLE_MEDIA:
			return {
				...state,
				saveType: action.saveType,
				singleMedia: action.singleMedia,
			};
		case Types.UPDATE_OPTIONS:
			return {
				...state,
				saveType: action.saveType,
				options: action.options,
			};
		case Types.UPDATE_EXTENSION:
			return {
				...state,
				saveType: action.saveType,
				extended: action.extended,
			};
		case Types.UPDATE_RENAMER_MEDIA:
			return {
				...state,
				saveType: action.saveType,
				rename: action.rename,
			};
		case Types.GET_MEDIA_LIST:
			return {
				...state,
				mediaData : action.mediaData,
			};
		case Types.GENERAL_DATA:
			return {
				...state,
				generalData : action.generalData,
			};
		case Types.RUBBISH_MEDIA:
			return {
				...state,
				rubbishMedia : action.rubbishMedia,
			};
		case Types.BALK_RUBBISH:
			return {
				...state,
				bulkRubbishData : action.bulkRubbishData,
			};
        case Types.EXPORT_IMPORT:
            return {
                ...state,
                exportImport : action.exportImport,
            };
        case Types.EXPORT_CSV:
            return {
                ...state,
                bulkExport : action.bulkExport,
            };
        case Types.SEARCH_USES:
            return {
                ...state,
                searchUses : action.searchUses,
            };

		default:
			return state;
	}
};

export default reducer;

