/* global rtsbParams */
import {
	UPDATE_DATA_OPTIONS,
	UPDATE_SINGLE_MEDIA,
	UPDATE_RENAMER_MEDIA, GET_MEDIA_LIST, GENERAL_DATA
} from './actionType';
import * as Types from "./actionType";

export const initialState = {
	saveType : null,
	selectedMenu : localStorage.getItem("current_menu") || 'mediatable',
	mediaData: {
		isLoading: true,
		posts : [],
		total_post: 0,
		paged: 1,
		posts_per_page: 1,
	},
	single: {},
	options: {
		default_alt_text: "none",
		media_table_column : [ 'ID', 'Image', 'Title', 'Alt', 'Caption', 'Category' ]
	},
	rename:{
		formEdited : false,
	}

};

const reducer = (state, action) => {
	switch (action.type) {
		case UPDATE_SINGLE_MEDIA:
			return {
				...state,
				single: action.single,
			};
		case UPDATE_DATA_OPTIONS:
			return {
				...state,
				saveType: action.saveType,
				options: action.options,
			};
		case UPDATE_RENAMER_MEDIA:
			return {
				...state,
				saveType: action.saveType,
				rename: action.rename,
			};
		case GET_MEDIA_LIST:
			return {
				...state,
				mediaData : action.mediaData,
			};
		case GENERAL_DATA:
			return {
				...state,
				selectedMenu : action.selectedMenu,
			};

		default:
			return state;
	}
};

export default reducer;
