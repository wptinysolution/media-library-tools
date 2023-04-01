
import React, {useState, useEffect, useContext} from "react";

import { TheAppContext, TheMediaTableContext } from '../../Utils/TheContext';

import RenamerTableData from "./RenamerTableData";


import {
    getMedia,
    upDateSingleMedia
} from "../../Utils/Data";

import {
    defaultPosts,
    defaultPostsFilter,
    defaultBulkSubmitData,
    defaultPostsQuery
} from '../../Utils/UtilData'

function ProcessRenamerTableData() {
    const {
        isUpdated,
        setIsUpdated
    } = useContext( TheAppContext );

    const [data, setData] = useState( defaultPosts );

    const [postQuery, setPostQuery] = useState( defaultPostsQuery );

    const { posts, total_post, posts_per_page, paged } = data;

    const [formEdited, setFormEdited] = useState( false );

    const [currentItemEdited, setCurrentItemEdited] = useState(false );

    const getTheMedia = async () => {
        const response = await getMedia('', {
            ...postQuery
        } );
        setData( response );
    }

    const handleColumnEditMode = () => {
        setFormEdited( ! formEdited );
    }

    const handleChange = ( event ) => {
        const currentItem = parseInt( event.target.getAttribute('current') );
        let currentData = {
            ID: posts[currentItem].ID,
        }
        if( 'filebasename' ===  event.target.name ){
            currentData = {
                ...currentData,
                thefile : {
                    ...posts[currentItem].thefile,
                    newname: event.target.value
                }
            }
            posts[currentItem].thefile.filebasename = event.target.value;
            setCurrentItemEdited( currentData );
            setData( {
                ...data,
                posts
            } );
        }

    }

    const handleFocusout = async ( event ) => {
        let edited =  currentItemEdited.thefile.originalname.localeCompare( event.target.value );
        if( edited ){
            const response = await upDateSingleMedia( currentItemEdited );
            200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
            currentItemEdited.thefile.originalname = event.target.value;
        }
    }

    useEffect(() => {
        getTheMedia();
    }, [isUpdated]  );

    return (
        <TheMediaTableContext.Provider value={ {
            posts,
            formEdited,
            setFormEdited,
            handleColumnEditMode,
            handleFocusout,
            handleChange
        } }>
             <RenamerTableData/>
        </TheMediaTableContext.Provider>
    );
}

export default ProcessRenamerTableData;