
import React, {useState, useEffect, useContext} from "react";

import { TheContext } from '../../Utils/TheContext';

import DataTable from "./Datatable";

import BulkModal from "./BulkModal";

import {
    getTerms,
    getDates,
    getMedia,
    getOptions,
    upDateSingleMedia,
    updateOptins,
    submitBulkMediaAction
} from "../../Utils/Data";

import {
    defaultPosts,
    defaultPostsFilter,
    defaultBulkSubmitData,
    defaultPostsQuery
} from '../../Utils/UtilData'

function ProcessTableData() {
    const {
        dateList,
        termsList,
        optionsData,
        setOptionsData
    } = useContext( TheContext );

    const [data, setData] = useState( defaultPosts );

    const [postQuery, setPostQuery] = useState( defaultPostsQuery );

    const [filtering, setFiltering] = useState( defaultPostsFilter );

    const [isUpdated, setIsUpdated] = useState(false );

    const [currentItemEdited, setCurrentItemEdited] = useState(false );

    const [isBulkModalOpen, setIsBulkModalOpen] = useState(false);

    const [bulkSubmitdata, setbulkSubmitdata] = useState( defaultBulkSubmitData );

    const { posts, total_post, posts_per_page, paged } = data;

    const [formEdited, setFormEdited] = useState( false );

    const [bulkChecked, setBulkChecked] = useState( false );

    const [checkedData, setCheckedData] = useState( [] );

    const [isLoading, setIsloading] = useState( true );

    const getTheMedia = async () => {
        const response = await getMedia('', {
            ...postQuery
        } );
        setData( response );
    }

    const handleUpdateOption = async ( event ) => {
        const response = await updateOptins( optionsData );
        200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
    }

    const submitBulkMedia = async ( params ) => {
        // console.log( params )
        const response = await submitBulkMediaAction( params );
        if( 200 === parseInt( response.status ) && response.data.updated ){
            setFormEdited( false );
            setBulkChecked( false );
            setCheckedData( [] );
            setIsUpdated( ! isUpdated );
        }
    }

    const handleSortClick = ( orderby ) => {
        setIsloading( true );
        setFormEdited( false );
        setBulkChecked( false );
        setCheckedData( [] );
        setPostQuery( ( prevState) => ({
            ...postQuery,
            orderby,
            paged: 1,
            order: orderby === prevState.orderby && 'DESC' === prevState.order ? 'ASC' : 'DESC',
        } ));

        setIsUpdated( ! isUpdated );
    };

    const handleColumnEditMode = () => {
        setFormEdited( ! formEdited );
    }

    const handleChange = ( event ) => {
        const currentItem = parseInt( event.target.getAttribute('current') );
        const currentData = {
            ID: posts[currentItem].ID,
            [event.target.name] : event.target.value.trim()
        }
        posts[currentItem][event.target.name] = event.target.value;
        setCurrentItemEdited( currentData );
        setData( {
            ...data,
            posts
        } );
    }

    const handleFocusout = async ( event ) => {
        const response = await upDateSingleMedia( currentItemEdited );
        200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
    }

    const handlePagination = ( current ) => {
        setIsloading( true )
        setFormEdited( false );
        setBulkChecked( false );
        setCheckedData( [] );
        setPostQuery({
            ...postQuery,
            paged: current
        })
        setIsUpdated( ! isUpdated );
    }

    const onCheckboxChange = (event) => {
        const value = event.target.value ;
        const changeData = event.target.checked ? [
            ...checkedData,
            value
        ] : checkedData.filter(item => item !== value );

        const Checked_count = Object.keys(changeData).length;
        const post_count = Object.keys(posts).length;

        setCheckedData( changeData );
        setBulkChecked( Checked_count === post_count );
    };

    const onBulkCheck = (event) => {
        const data = event.target.checked ? posts.map( item => item.ID ) : [];
        setCheckedData( data );
        setBulkChecked( ! ! data.length );
    };

    const balkModalDataChange = ( event ) => {
        const data = {
            ...bulkSubmitdata.data,
            [event.target.name] : event.target.value
        }
        setbulkSubmitdata( {
            ...bulkSubmitdata,
            data
        })
    };

    const handleBulkSubmit = (event) => {
        const params = {
            ...bulkSubmitdata,
            ids :  checkedData ? checkedData : []
        };
        switch( bulkSubmitdata.type ){
            case 'trash':
            case 'inherit':
            case 'update':
            case 'delete':
                submitBulkMedia( params );
                break;
            case 'bulkedit':
                setIsBulkModalOpen( true );
                setbulkSubmitdata({
                    ...params,
                });

                break;
            default:
        }
    };

    const handleBulkModalOk = (event) => {
        submitBulkMedia( bulkSubmitdata );
        setIsBulkModalOpen( false );
        setbulkSubmitdata( {
            ...defaultBulkSubmitData,
            type: 'bulkedit',
            'post_categories': []
        } )
    };

    const handleBulkModalCancel = (event) => {
        setIsBulkModalOpen( false );
        setbulkSubmitdata( {
            ...defaultBulkSubmitData,
            type: 'bulkedit',
            'post_categories': []
        } )
    };

    const handleChangeBulkType = (value) => {
        const data = 'bulkedit' === value ? bulkSubmitdata.data : defaultBulkSubmitData.data;
        setbulkSubmitdata( {
            ...bulkSubmitdata,
            type: value,
            data
        })

    };

    const handleFilterData = () => {
        setIsloading( true )
        setFormEdited( false );
        setBulkChecked( false );
        setCheckedData( [] );

        setbulkSubmitdata( {
            ...defaultBulkSubmitData
        })
        setPostQuery({
            ...postQuery,
            ...filtering,
            filtering : true,
            paged: 1
        })
        setIsUpdated( ! isUpdated );
    }
    /*
    useEffect(() => {
        getDateList();
        getTermsList();
        getTheOptins();
    }, []  );
    */
    useEffect(() => {
        getTheMedia();
        setTimeout(() => {
            setIsloading( false )
        }, 200 );
    }, [isUpdated]  );

    return (
        <TheContext.Provider value={ {
            dateList,
            termsList,
            optionsData,
            setData,
            isLoading,
            postQuery,
            formEdited,
            setPostQuery,
            bulkSubmitdata,
            handleFilterData,
            handleBulkSubmit,
            handleUpdateOption,
            handleChangeBulkType,
            isBulkModalOpen,
            handleBulkModalOk,
            handleBulkModalCancel,
            balkModalDataChange,
            posts,
            total_post,
            posts_per_page,
            paged,
            handlePagination,
            bulkChecked,
            onBulkCheck,
            checkedData,
            onCheckboxChange,
            handleSortClick,
            handleColumnEditMode,
            handleChange,
            handleFocusout,
            setOptionsData,
            filtering,
            setFiltering,
            setbulkSubmitdata
        } }>
             <DataTable />
             <BulkModal />
        </TheContext.Provider>
    );
}

export default ProcessTableData;