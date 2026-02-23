import React, { useState } from "react";

import { useStore } from "@/js/Utils/store";
import * as Types from "@/js/Utils/actionType";

import { rubbishSingleDeleteAction, rubbishSingleIgnoreAction, rubbishSingleShowAction } from "./Data";
import { CopyToClipboard } from "@/js/Component/CopyToClipboard";

export const headerStyle = {
    height: 'auto',
    paddingInline: 0,
    lineHeight: '1',
    backgroundColor: '#fff',
    padding: '15px 0'
};

export const selectStyle = {
    width: 250,
    paddingInline: 0,
}

export const defaultBulkSubmitData = {
    bulkChecked : false,
    isModalOpen : false,
    progressBar : 0,
    progressTotal : 0,
    ids: [],
    type: '',
    data : {
        post_title : '',
        alt_text : '',
        caption : '',
        post_description : '',
        file_name: '',
    },
    will_attached_post_title: [],
    post_categories : [],
}

export const bulkOprions = [
    {
        value: 'bulkedit',
        label: 'Bulk Edit',
    },
    {
        value: 'csv_export',
        label: 'CSV Export',
    },
    {
        value: 'bulkEditPostTitle',
        label: 'Edit Based on Attached Post Title',
    },
    {
        value: 'inherit',
        label: 'Restore',
    },
    {
        value: 'searchUses',
        label: 'Search Used Images (Attached Post)',
    },
    {
        value: 'trash',
        label: 'Move to Trash',
    },
    {
        value: 'delete',
        label: 'Delete Permanently ',
    }
];

export const columnList = [
    {
        title: 'ID',
        key: 'ID',
    },
    {
        title: 'File',
        key: 'Image',
    },
    {
        title: `Attached Post (Parent)`,
        key: 'Parents',
    },
    {
        title: `Title`,
        key: 'Title',
    },
    {
        title: `Alt`,
        key: 'Alt',
    },
    {
        title: `Caption`,
        key: 'Caption',
    },
    {
        title: `Description`,
        key: 'Description',
    },
    {
        title: `Groups`,
        key: 'Category',
    },
];

const theImage = ( record ) => {
    let type = record.post_mime_type.split("/"),
        width = 80,
        url;
    type = Array.isArray( type ) ? type[0] : '';
    switch ( type ) {
        case 'image':
            url = record.uploaddir + '/' + record.thefile.file;
            break;
        case 'audio':
            url = `${tsmltParams.includesUrl}/images/media/audio.png`
            break;
        case 'video':
            url = `${tsmltParams.includesUrl}/images/media/video.png`
            break;
        case 'application':
            if( 'application/zip' === record.post_mime_type ){
                url = `${tsmltParams.includesUrl}/images/media/archive.png`
            } else if ( 'application/pdf' === record.post_mime_type  ){
                url = `${tsmltParams.includesUrl}/images/media/document.png`
            }
            break;
        case 'text':
            url = `${tsmltParams.includesUrl}/images/media/text.png`
            break;
        default:
            url = `${tsmltParams.includesUrl}/images/media/text.png`
    }

    return <img width={ width } src={url} /> ;

};

export function columns(){

    const { mediaData, setMediaData, singleMedia, setSingleMedia, bulkSubmitData, setBulkSubmitData, setSaveType } = useStore();

    const onCheckboxChange = (event) => {
        const value = parseInt(event.target.value, 10);
        const changeData = event.target.checked ? [
            ...bulkSubmitData.ids,
            value
        ] : bulkSubmitData.ids.filter( item => item !== value );

        const checkedCount = Object.keys( changeData ).length;
        const postCount = Object.keys( mediaData.posts ).length;

        setBulkSubmitData({
            bulkChecked : checkedCount && checkedCount === postCount,
            ids: changeData
        });
    };

    const onBulkCheck = (event) => {
        const postsId = event.target.checked ? mediaData.posts.map( item => item.ID ) : [];
        setBulkSubmitData({
            bulkChecked : ! ! postsId.length,
            progressTotal: postsId.length,
            ids: postsId
        });
    };

    const handleSortClick = ( odrby ) => {
        const { orderby, order } = mediaData.postQuery;
        setMediaData({
            postQuery : {
                ...mediaData.postQuery,
                orderby: odrby,
                paged: 1,
                order: odrby === orderby && 'DESC' === order ? 'ASC' : 'DESC',
            }
        });
    };

    const handleChange = ( event ) => {
        const currentItem = parseInt( event.target.getAttribute('current') );
        let posts = mediaData.posts;
        let currentData = {
            ID: posts[currentItem].ID,
            [event.target.name] : event.target.value.trim()
        }

        posts[currentItem][event.target.name] = event.target.value;

        setMediaData({ posts: posts, isLoading: false });

        setSingleMedia({
            alt_text : null,
            post_content: null,
            post_excerpt: null,
            post_title: null,
            ...currentData,
        });
    }

    const handleFocusout = () => {
        setSaveType(Types.UPDATE_SINGLE_MEDIA);
    }

    const formEdited = singleMedia.formEdited;
    const hasIds = bulkSubmitData.ids.length > 0;

    return [
        {
            title: <input
                type="checkbox"
                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                ref={(el) => { if (el) el.indeterminate = hasIds && !bulkSubmitData.bulkChecked; }}
                checked={ bulkSubmitData.bulkChecked }
                onChange={onBulkCheck}
            />,
            key: 'CheckboxID',
            dataIndex: 'ID',
            width: '50px',
            align: 'center',
            render:  ( id, record ) => <input
                type="checkbox"
                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                checked={ -1 !== bulkSubmitData.ids.indexOf( id ) }
                name="item_id"
                value={id}
                onChange={onCheckboxChange}
            />
        },
        {
            title: <span className="inline-flex items-center gap-2 flex-wrap">
                ID
                <button className="px-2 py-0.5 text-xs font-medium border border-gray-300 rounded hover:bg-gray-100 cursor-pointer transition-colors" onClick={ () => handleSortClick('id') }>Sort</button>
            </span>,
            key: 'ID',
            dataIndex: 'ID',
            width: '150px',
            align: 'top'
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'guid',
            width: '150px',
            align: 'top',
            render: ( text, record, i ) => <span className="inline-flex items-center">{ theImage( record ) }</span>,
        },
        {
            title: <span className="inline-flex items-center gap-2 flex-wrap">
                Attached Post (Parent)
                <button className="px-2 py-0.5 text-xs font-medium border border-gray-300 rounded hover:bg-gray-100 cursor-pointer transition-colors" onClick={ () => handleSortClick('post_parents') }>Sort</button>
            </span>,
            key: 'Parents',
            dataIndex: 'post_parents',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { text['title'] ? <a target={'_blank'} href={ text['permalink'] }> { text['title'] } </a> : '' }</>
        },
        {
            title: <span className="inline-flex items-center gap-2 flex-wrap">
                Title
                <button className="px-2 py-0.5 text-xs font-medium border border-gray-300 rounded hover:bg-gray-100 cursor-pointer transition-colors" onClick={ () => handleSortClick('title') }>Sort</button>
            </span>,
            key: 'Title',
            dataIndex: 'title',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <textarea className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows="2" name="title" placeholder="Title Shouldn't leave empty" current={i} onBlur={handleFocusout} onChange={handleChange} value={ text } /> : <a className="w-[200px] flex overflow-x-auto" target={'_blank'} href={ `${record.uploaddir}/${record.thefile.file}` }> { text } </a> } </>
        },
        {
            title: <span className="inline-flex items-center gap-2 flex-wrap">
                Alt
                <button className="px-2 py-0.5 text-xs font-medium border border-gray-300 rounded hover:bg-gray-100 cursor-pointer transition-colors" onClick={ () => handleSortClick('alt') }>Sort</button>
            </span>,
            key: 'Alt',
            dataIndex: 'alt_text',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <textarea className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows="2" name="alt_text" placeholder="Alt Text Shouldn't leave empty" current={i} onBlur={handleFocusout} onChange={handleChange} value={ text } /> : <span className="w-[200px] flex overflow-x-auto">{text}</span> } </>
        },
        {
            title: <span className="inline-flex items-center gap-2 flex-wrap">
                Caption
                <button className="px-2 py-0.5 text-xs font-medium border border-gray-300 rounded hover:bg-gray-100 cursor-pointer transition-colors" onClick={ () => handleSortClick('caption') }>Sort</button>
            </span>,
            key: 'Caption',
            dataIndex: 'caption',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <textarea className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows="2" name="caption" placeholder="Caption Text" current={i} onBlur={handleFocusout} onChange={handleChange} value={ text } /> : text } </>
        },
        {
            title: <span className="inline-flex items-center gap-2 flex-wrap">
                Description
                <button className="px-2 py-0.5 text-xs font-medium border border-gray-300 rounded hover:bg-gray-100 cursor-pointer transition-colors" onClick={ () => handleSortClick('description') }>Sort</button>
            </span>,
            key: 'Description',
            dataIndex: 'description',
            width: '350px',
            render: ( text, record, i ) => <> { formEdited ? <textarea className="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" rows="2" name="description" placeholder="Description Text" current={i} onBlur={handleFocusout} onChange={handleChange} value={ text } /> : text } </>
        },
        {
            title: <span className="inline-flex items-center">Groups</span>,
            key: 'Category',
            dataIndex: 'categories',
            width: '250px',
            render: ( text, record, i ) => {
                const items = JSON.parse(record.categories)
                return <span className="flex flex-wrap gap-1">
                    { items.map( item => item.id && <span key={Math.random().toString(36).substr(2, 9)} className="inline-flex px-2 py-0.5 text-xs font-medium border border-gray-300 rounded bg-gray-50 text-gray-700">{ item.name }</span> ) }
                </span>
            }
        },
    ];
}

export function renamerColumns(){

    const { mediaData, bulkSubmitData, setBulkSubmitData, rename, setSaveType } = useStore();

    const onCheckboxChange = (event) => {
        const value = parseInt(event.target.value, 10);
        const changeData = event.target.checked ? [
            ...bulkSubmitData.ids,
            value
        ] : bulkSubmitData.ids.filter( item => item !== value );

        const checkedCount = Object.keys( changeData ).length;
        const postCount = Object.keys( mediaData.posts ).length;

        setBulkSubmitData({
            bulkChecked : checkedCount && checkedCount === postCount,
            ids: changeData,
            progressTotal: checkedCount
        });
    };

    const onBulkCheck = (event) => {
        const postsId = event.target.checked ? mediaData.posts.map( item => item.ID ) : [];
        setBulkSubmitData({
            bulkChecked : ! ! postsId.length,
            progressTotal: postsId.length,
            ids: postsId
        });
    };

    const hasIds = bulkSubmitData.ids.length > 0;

    return [
        {
            title: <input
                type="checkbox"
                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                ref={(el) => { if (el) el.indeterminate = hasIds && !bulkSubmitData.bulkChecked; }}
                checked={ bulkSubmitData.bulkChecked }
                onChange={onBulkCheck}
            />,
            key: 'CheckboxID',
            dataIndex: 'ID',
            width: '50px',
            align: 'center',
            render:  ( id, record ) => <input
                type="checkbox"
                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                checked={ -1 !== bulkSubmitData.ids.indexOf( id ) }
                name="item_id"
                value={id}
                onChange={onCheckboxChange}
            />
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'guid',
            width: '100px',
            align: 'top',
            render:  ( text, record, i ) => <span className="inline-flex items-center">{ theImage( record ) }</span>,
        },
        {
            title: <span className="inline-flex items-center">Attached Post (Parent)</span>,
            key: 'Parents',
            dataIndex: 'post_parents',
            width: '150px',
            render: ( text, record, i ) => <> { text['title'] ? <a target={'_blank'} href={ text['permalink'] }> { text['title'] } </a> : '' }</>
        },
        {
            title: `File Name`,
            key: 'Image',
            dataIndex: 'guid',
            width: '350px',
            align: 'top',
            render:  ( text, record, i ) =>  <>
                { rename.formEdited ? <div className="flex items-center gap-1 bg-transparent">
                    <input
                        type="text"
                        className="w-[350px] h-[38px] px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        name="filebasename"
                        placeholder="The name Shouldn't leave empty"
                        current={i}
                        onBlur={() => setSaveType(Types.UPDATE_RENAMER_MEDIA)}
                        onChange={
                            ( event ) => {
                                const { setMediaData, setRename } = useStore.getState();
                                const currentItem = parseInt( event.target.getAttribute('current') );
                                if( 'filebasename' ===  event.target.name ){
                                    const pnlname = mediaData.posts[currentItem].thefile;
                                    mediaData.posts[currentItem].thefile.filebasename = event.target.value;
                                    setRename({
                                        postsdata: pnlname,
                                        ID: mediaData.posts[currentItem].ID,
                                        newname: event.target.value
                                    });
                                }
                            }
                        }
                        value={ record.thefile.filebasename }
                    />
                    <span className="text-sm text-gray-600">{`.${record.thefile.fileextension}`}</span>
                </div> : <a className="max-w-[300px] flex overflow-x-auto" target={'_blank'} href={ `${record.uploaddir}/${record.thefile.file}` }> { record.thefile.mainfilename } </a>}

            </>,
        },
        {
            title: 'URL',
            key: 'Image',
            dataIndex: 'guid',
            align: 'top',
            minWidth: 300,
            render:  ( text, record, i ) => <span className="inline-flex items-center gap-1">
                <code className="text-sm text-gray-500 bg-gray-100 px-2 py-0.5 rounded w-[300px] inline-flex overflow-x-auto">{ record.uploaddir + '/' + record.thefile.file }</code>
                <CopyToClipboard text={`${record.uploaddir + '/' + record.thefile.file}`} />
            </span>,
        },

        {
            title: <span className="inline-flex items-center">Title</span>,
            key: 'Title',
            dataIndex: 'title',
            align: 'top',
            render:  ( text, record, i ) => <span className="text-sm text-gray-500">{text}</span>,
        }
    ];
}

export function RubbishFileColumns(){

    const { rubbishMedia, setRubbishMedia, bulkRubbishData, setBulkRubbishData, generalData, setGeneralData } = useStore();

    const [ deleteCurrentItem, setDeleteCurrentItem ] = useState(null );
    const [ ignoreCurrentItem, setIgnoreCurrentItem ] = useState(null );

    const onRubbishBulkCheck = (event) => {
        const postsId = event.target.checked ? rubbishMedia.mediaFile.map( item => item.id ) : [];
        const files = event.target.checked ? rubbishMedia.mediaFile.map(item => ({
                id: item.id,
                path: item.file_path,
            })) : [];
        setBulkRubbishData({
            bulkChecked : ! ! postsId.length,
            ids: postsId,
            files: files,
            progressTotal: files.length
        });
    };

    const onCheckboxChange = (event, record) => {

        const value = event.target.value ;
        const changeData = event.target.checked ? [
            ...bulkRubbishData.ids,
            value
        ] : bulkRubbishData.ids.filter( item => item !== value );

        const changePath = event.target.checked ? [
            ...bulkRubbishData.files,
            {
                id: record.id,
                path: record.file_path,
            }
        ] : bulkRubbishData.files.filter( item => item.id !== record.id );

        const checkedCount = Object.keys( changeData ).length;
        const postCount = Object.keys( rubbishMedia.mediaFile ).length;

        setBulkRubbishData({
            bulkChecked: ! ! checkedCount && checkedCount === postCount,
            ids: changeData,
            files: changePath,
            progressTotal: checkedCount
        });
    };

    const onRubbishSingleAction = async (data, action ) => {
        if ( tsmltParams.hasExtended ){
            let response;
            if( 'ignore' === action ){
                setIgnoreCurrentItem( data.id );
                response = await rubbishSingleIgnoreAction( data );
            } else if ( 'delete' === action ) {
                setDeleteCurrentItem( data.id );
                response = await rubbishSingleDeleteAction( data );
            } else if ( 'show' === action ) {
                response = await rubbishSingleShowAction( data );
            }
            if( 200 === parseInt( response?.status ) ) {
                const mediaFile = response.data.updated ? rubbishMedia.mediaFile.filter( ( item ) => data.id !=  item.id ) : rubbishMedia.mediaFile;
                await setRubbishMedia({ mediaFile });
                setIgnoreCurrentItem( null );
                setDeleteCurrentItem( null );
            }
            return ;
        }

        setGeneralData({ openProModal: true });
    };

    const rubbishHead = [
        {
            title: <input
                type="checkbox"
                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                checked={ bulkRubbishData.bulkChecked }
                onChange={onRubbishBulkCheck}
            />,
            key: 'CheckboxID',
            dataIndex: 'id',
            width: '50px',
            align: 'center',
            render:  ( id, record ) => <input
                type="checkbox"
                className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                checked={ -1 !== bulkRubbishData.ids.indexOf( id ) }
                name="item_id"
                value={id}
                onChange={ ( event ) => onCheckboxChange(event, record) }
            />
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'file_path',
            width: '150px',
            align: 'top',
            render: ( file_path, record, i ) => <span className="inline-flex items-center"><img width={ 50 } src={`${tsmltParams.uploadUrl}/${file_path}`} /></span>,
        },
        {
            title: 'File URL',
            key: 'FileType',
            dataIndex: 'file_path',
            align: 'top',
            render: ( file_path, record, i ) => <span className="text-sm">{ `${tsmltParams.uploadUrl}/${file_path}` }</span>,
        },
        {
            title: 'Actions',
            key: 'FileType',
            dataIndex: 'file_path',
            align: 'top',
            width: '450px',
            render: ( text, record, i ) => <span className="flex flex-wrap gap-2">
                {
                    'ignore' == rubbishMedia.postQuery.fileStatus ? (
                        <button
                            className="px-3 py-1.5 text-sm font-medium border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-50"
                            onClick={ () => onRubbishSingleAction( record, 'show' ) }
                            disabled={ record.id === deleteCurrentItem }
                        >
                            { record.id === deleteCurrentItem ? 'Processing...' : 'Mark As Unnecessary File' }
                        </button>
                    ) :
                    (
                        <>
                            <button
                                className="px-3 py-1.5 text-sm font-medium text-red-600 border border-red-300 rounded-md hover:bg-red-50 cursor-pointer transition-colors disabled:opacity-50"
                                onClick={ () => onRubbishSingleAction( record, 'delete' ) }
                                disabled={ record.id === deleteCurrentItem }
                            >
                                { record.id === deleteCurrentItem ? 'Deleting...' : 'Delete Unnecessary File' }
                            </button>
                            <button
                                className="px-3 py-1.5 text-sm font-medium border border-gray-300 rounded-md hover:bg-gray-100 cursor-pointer transition-colors disabled:opacity-50"
                                onClick={ () => onRubbishSingleAction( record, 'ignore' ) }
                                disabled={ record.id === ignoreCurrentItem }
                            >
                                { record.id === ignoreCurrentItem ? 'Processing...' : 'Ignore Important File' }
                            </button>
                        </>
                    )
                }
            </span>
        }
    ];

    return rubbishHead;

}

/**
 * Function Debounce
 * @param func
 * @param delay
 * @returns {(function(...[*]): void)|*}
 */
export const functionDebounce =  (func, delay) => {
    let timeoutId;
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            func.apply(this, args);
        }, delay);
    };
}


// Store data in local storage with an expiration time of 1 hour
export  function localStoreData(key, value) {
    // Calculate the expiration time in milliseconds ( 1 day = 60 minutes * 60 seconds * 1000 milliseconds * 24 hours )
    var expirationTime = Date.now() + ( 60 * 60 * 1000 * 24 );

    // Create an object to store the data and expiration time
    var dataObject = {
        value: value,
        expirationTime: expirationTime
    };

    // Store the object in local storage
    localStorage.setItem(key, JSON.stringify(dataObject));
}

// Retrieve data from local storage
export function localRetrieveData(key) {
    // Get the stored data from local storage
    var data = localStorage.getItem(key);
    if (data) {
        // Parse the stored JSON data
        var dataObject = JSON.parse(data);
        // Check if the data has expired
        if (Date.now() <= dataObject.expirationTime) {
            // Return the stored value
            return dataObject.value;
        } else {
            // Data has expired, remove it from local storage
            localStorage.removeItem(key);
        }
    }
    // Return null if data doesn't exist or has expired
    return null;
}
