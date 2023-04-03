/* global rtsbParams */
import {
	UPDATE_DATA_OPTIONS,
	UPDATE_SINGLE_MEDIA
} from './actionType';

export const initialState = {
	saveType : null,
	single: {},
	options: {
		default_alt_text: "none",
		media_table_column : [ 'ID', 'Image', 'Title', 'Alt', 'Caption', 'Category' ]
	},

};

const reducer = (state, action) => {
	switch (action.type) {
		case UPDATE_SINGLE_MEDIA:
			return {
				...state,
				saveType : UPDATE_SINGLE_MEDIA,
				single: action.single,
			};
		case UPDATE_DATA_OPTIONS:
			return {
				...state,
				saveType : UPDATE_DATA_OPTIONS,
				options: action.options,
			};
		default:
			return state;
	}
};

export default reducer;
