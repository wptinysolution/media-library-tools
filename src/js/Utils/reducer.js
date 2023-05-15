/* global rtsbParams */

import * as Types from "./actionType";

import {defaultBulkSubmitData} from "./UtilData";

export const initialState = {
	saveType: null,
	mediaData: {
		postQuery: {
			status: null,
			filtering : false,
			media_per_page: 1,
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
	options: {
		isLoading: true,
		media_table_column: [ 'ID', 'Image', 'Title', 'Alt', 'Caption', 'Category' ],
		default_alt_text: "none",
		default_caption_text: "none",
		default_desc_text: "none",
		others_file_support:[]
	},
	generalData:{
		isLoading: true,
		selectedMenu: localStorage.getItem("current_menu") || 'settings',
		dateList: {},
		termsList: {},
	},
	rubbishMedia:{
		isLoading: true,
		mediaFIle: []
	}
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

		default:
			return state;
	}
};

export default reducer;

