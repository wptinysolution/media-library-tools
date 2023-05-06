/* global rtsbParams */

import * as Types from "./actionType";

export const initialState = {
	saveType : null,
	mediaData: {
		postQuery: {
			status: 'inherit',
			filtering : false,
			paged: 1,
			order: 'DESC',
			media_per_page: 1,
			orderby: 'mediarename' == localStorage.getItem("current_menu") ? 'id' : 'menu_order'
		},
		isLoading: true,
		posts : [],
		total_post: 0,
		paged: 1,
		posts_per_page: 1,
	},
	single: {},
	rename:{
		formEdited : false,
	},
	options: {
		isLoading: true,
		default_alt_text: "none",
		media_table_column : [ 'ID', 'Image', 'Title', 'Alt', 'Caption', 'Category' ]
	},
	generalData:{
		selectedMenu : localStorage.getItem("current_menu") || 'mediatable',
		dateList:{},
		termsList:{},
	}
};

const reducer = (state, action) => {
	switch (action.type) {
		case Types.UPDATE_SINGLE_MEDIA:
			return {
				...state,
				single: action.single,
			};
		case Types.UPDATE_DATA_OPTIONS:
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

		default:
			return state;
	}
};

export default reducer;
