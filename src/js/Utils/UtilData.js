import React, { useState } from "react";

import {Button, Checkbox, Space, Input, Layout, Typography, Spin } from "antd";

const {
    Text,
    Paragraph
} = Typography;

import {useStateValue} from "./StateProvider";

import * as Types from "./actionType";

import { rubbishSingleDeleteAction, rubbishSingleIgnoreAction, rubbishSingleShowAction } from "./Data";

const { TextArea } = Input;

export const headerStyle = {
    height: 64,
    paddingInline: 0,
    lineHeight: '64px',
    backgroundColor: '#fff',
};

export const selectStyle = {
    width: 130,
    paddingInline: 0,
}

export const defaultBulkSubmitData = {
    bulkChecked : false,
    isModalOpen : false,
    progressBar : false,
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
    edit_by_attached_post_title: 'no',
    will_attached_post_title: [],
    post_categories : [],
}

export const bulkOprions = [
    {
        value: 'bulkedit',
        label: 'Bulk Edit',
    },
    {
        value: 'bulkEditPostTitle',
        label: 'Bulk Edit Based on Post Title',
    },
    {
        value: 'trash',
        label: 'Move to Trash',
    },
    {
        value: 'inherit',
        label: 'Restore',
    },
    {
        value: 'delete',
        label: 'Delete Permanently ',
    },
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
        title: `Parents ID`,
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
        title: `Category`,
        key: 'Category',
    },
];

const theImage = ( record ) => {
    let type = record.post_mime_type.split("/"),
        width = 100,
        url;
    type = Array.isArray( type ) ? type[0] : '';
    switch ( type ) {
        case 'image':
            url = record.uploaddir + '/' + record.thefile.file;
            width = 100;
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

    const [stateValue, dispatch] = useStateValue();

    const onCheckboxChange = (event) => {
        const value = event.target.value ;
        const changeData = event.target.checked ? [
            ...stateValue.bulkSubmitData.ids,
            value
        ] : stateValue.bulkSubmitData.ids.filter( item => item !== value );

        const checkedCount = Object.keys( changeData ).length;
        const postCount = Object.keys( stateValue.mediaData.posts ).length;

        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData:{
                ...stateValue.bulkSubmitData,
                bulkChecked : checkedCount && checkedCount === postCount,
                ids: changeData
            }

        });

    };

    const onBulkCheck = (event) => {
        const postsId = event.target.checked ? stateValue.mediaData.posts.map( item => item.ID ) : [];
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                bulkChecked : ! ! postsId.length,
                ids: postsId
            },
        });
    };

    const handleSortClick = ( odrby ) => {
        const { orderby, order } = stateValue.mediaData.postQuery;
        dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                postQuery : {
                    ...stateValue.mediaData.postQuery,
                    orderby: odrby,
                    paged: 1,
                    order: odrby === orderby && 'DESC' === order ? 'ASC' : 'DESC',
                }
            },
        });
    };

    const handleChange = ( event ) => {
        const currentItem = parseInt( event.target.getAttribute('current') );
        let posts = stateValue.mediaData.posts;
        let currentData = {
            ID: posts[currentItem].ID,
            [event.target.name] : event.target.value.trim()
        }

        posts[currentItem][event.target.name] = event.target.value;

        dispatch({
            type: Types.GET_MEDIA_LIST,
            mediaData: {
                ...stateValue.mediaData,
                posts: posts,
                isLoading: false
            },
        });

        dispatch({
            type: Types.UPDATE_SINGLE_MEDIA,
            singleMedia: {
                ...stateValue.singleMedia,
                alt_text : null,
                post_content: null,
                post_excerpt: null,
                post_title: null,
                ...currentData,
            }
        });

    }
    const handleFocusout = ( event ) => {
        dispatch({
            ...stateValue,
            type: Types.UPDATE_SINGLE_MEDIA,
            saveType: Types.UPDATE_SINGLE_MEDIA,
        });
    }

    const formEdited = stateValue.singleMedia.formEdited;

    return [
        {
            title: <Checkbox indeterminate={ ! stateValue.bulkSubmitData.bulkChecked} checked={ stateValue.bulkSubmitData.bulkChecked } onChange={onBulkCheck}/>,
            key: 'CheckboxID',
            dataIndex: 'ID',
            width: '50px',
            align: 'center',
            render:  ( id, record ) => <Checkbox checked={ -1 !== stateValue.bulkSubmitData.ids.indexOf( id ) } name="item_id" value={id} onChange={onCheckboxChange} />
        },
        {
            title: <Space wrap> { `ID` } <Button size={`small`} onClick={ ( event ) => handleSortClick('id') }> {`Sort`} </Button> </Space>,
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
            render: ( text, record, i ) => <Space> { theImage( record ) }</Space>,
        },
        {
            title: <Space wrap> { `Uploaded to` } </Space>,
            key: 'Parents',
            dataIndex: 'post_parents',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { text['title'] ? <a target={'_blank'} href={ text['permalink'] }> { text['title'] } </a> : '' }</>
        },
        {
            title: <Space wrap> { `Title` } <Button size={`small`} onClick={ ( event ) => handleSortClick('title') }> Sort </Button> </Space>,
            key: 'Title',
            dataIndex: 'post_title',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`post_title`} placeholder={`Title Shouldn't leave empty`} current={i} onBlur={handleFocusout} onChange={handleChange} value={ text } /> : <a target={'_blank'} href={ `${record.uploaddir}/${record.thefile.file}` }> { text } </a> }   </>
        },
        {
            title: <Space wrap> { `Alt` } <Button size={`small`} onClick={ ( event ) => handleSortClick('alt') }> Sort </Button> </Space>,
            key: 'Alt',
            dataIndex: 'alt_text',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`alt_text`} placeholder={`Alt Text Shouldn't leave empty`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text } </>
        },
        {
            title: <Space wrap> { `Caption` } <Button size={`small`} onClick={ ( event ) => handleSortClick('caption') }> Sort </Button> </Space>,
            key: 'Caption',
            dataIndex: 'post_excerpt',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`post_excerpt`} placeholder={`Caption Text`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <Space wrap> { `Description` } <Button size={`small`} onClick={  ( event ) => handleSortClick('description') }> Sort </Button> </Space>,
            key: 'Description',
            dataIndex: 'post_content',
            width: '350px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`post_content`} placeholder={`Description Text`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <Space wrap> { `Category` } </Space>,
            key: 'Category',
            dataIndex: 'categories',
            width: '250px',
            render: ( text, record, i ) => {
                const items = JSON.parse(record.categories)
                return  items.map( item => item.id && <Button key={Math.random().toString(36).substr(2, 9)} size={`small`} > {  item.name } </Button>  )
            }
        },
    ];
}

export function renamerColumns(){

    const [stateValue, dispatch] = useStateValue();

    const onCheckboxChange = (event) => {
        const value = event.target.value ;
        const changeData = event.target.checked ? [
            ...stateValue.bulkSubmitData.ids,
            value
        ] : stateValue.bulkSubmitData.ids.filter( item => item !== value );

        const checkedCount = Object.keys( changeData ).length;
        const postCount = Object.keys( stateValue.mediaData.posts ).length;

        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData:{
                ...stateValue.bulkSubmitData,
                bulkChecked : checkedCount && checkedCount === postCount,
                ids: changeData,
                progressTotal: checkedCount
            }
        });
    };

    const onBulkCheck = (event) => {
        const postsId = event.target.checked ? stateValue.mediaData.posts.map( item => item.ID ) : [];
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                bulkChecked : ! ! postsId.length,
                progressTotal: postsId.length,
                ids: postsId
            },
        });
    };

    return [
        {
            title: <Checkbox indeterminate={ ! stateValue.bulkSubmitData.bulkChecked} checked={ stateValue.bulkSubmitData.bulkChecked } onChange={onBulkCheck}/>,
            key: 'CheckboxID',
            dataIndex: 'ID',
            width: '50px',
            align: 'center',
            render:  ( id, record ) => <Checkbox checked={ -1 !== stateValue.bulkSubmitData.ids.indexOf( id ) } name="item_id" value={id} onChange={onCheckboxChange} />
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'guid',
            width: '130px',
            align: 'top',
            render:  ( text, record, i ) => <Space> { theImage( record ) }</Space>,
        },
        {
            title: <Space wrap> { `Uploaded to` } </Space>,
            key: 'Parents',
            dataIndex: 'post_parents',
            width: '150px',
            render: ( text, record, i ) => <> { text['title'] ? <a target={'_blank'} href={ text['permalink'] }> { text['title'] } </a> : '' }</>
        },
        {
            title: `File Name`,
            key: 'Image',
            dataIndex: 'guid',
            width: '300px',
            align: 'top',
            render:  ( text, record, i ) =>  <>
                { stateValue.rename.formEdited ?  <Layout style={{
                    display: 'flex',
                    flexDirection: 'row',
                    alignItems: 'center',
                    background: 'transparent'
                }}>
                    <Input
                        size="large"
                        name={`filebasename`}
                        placeholder={`The name Shouldn't leave empty`}
                        current={i}
                        style={{ maxWidth: 'calc( 100% - 50px)' }}
                        onBlur={
                            () => dispatch({
                                ...stateValue,
                                type: Types.UPDATE_RENAMER_MEDIA,
                                saveType: Types.UPDATE_RENAMER_MEDIA
                            })
                        }
                        onChange={
                            ( event ) => {
                                const currentItem = parseInt( event.target.getAttribute('current') );
                                if( 'filebasename' ===  event.target.name ){
                                    const pnlname = stateValue.mediaData.posts[currentItem].thefile;
                                    stateValue.mediaData.posts[currentItem].thefile.filebasename = event.target.value;
                                    dispatch({
                                        type: Types.UPDATE_RENAMER_MEDIA,
                                        rename : {
                                            ...stateValue.rename,
                                            postsdata: pnlname,
                                            ID: stateValue.mediaData.posts[currentItem].ID,
                                            newname: event.target.value
                                        }
                                    });
                                }
                            }
                        }
                        value={ record.thefile.filebasename }
                    />
                    {`.${record.thefile.fileextension}`}
                </Layout> : <a target={'_blank'} href={ `${record.uploaddir}/${record.thefile.file}` }> { record.thefile.mainfilename } </a>}

            </>,
        },
        {
            title: 'URL',
            key: 'Image',
            dataIndex: 'guid',
            align: 'top',
            render:  ( text, record, i ) => <Paragraph  copyable={{ text: `${record.uploaddir + '/' + record.thefile.file}` }} > <Text type="secondary" code style={{ fontSize: '15px', maxWidth:'calc( 100% - 50px)', display: 'inline-flex' }} > { record.uploaddir + '/' + record.thefile.file } </Text> </Paragraph>,
        },

        {
            title: <Space wrap> { `Title` } </Space>,
            key: 'Title',
            dataIndex: 'post_title',
            align: 'top',
            width: '200px',
        }
    ];
}

export function RubbishFileColumns(){

    const [stateValue, dispatch] = useStateValue();

    const [ deleteCurrentItem, setDeleteCurrentItem ] = useState(null );
    const [ ignoreCurrentItem, setIgnoreCurrentItem ] = useState(null );
    /**
     *
     * @param event
     */
    const onRubbishBulkCheck = (event) => {
        const postsId = event.target.checked ? stateValue.rubbishMedia.mediaFile.map( item => item.id ) : [];
        const files = event.target.checked ? stateValue.rubbishMedia.mediaFile.map(item => ({
                id: item.id,
                path: item.file_path,
            })) : [];
        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                bulkChecked : ! ! postsId.length,
                ids: postsId,
                files: files,
                progressTotal: files.length
            },
        });
        console.log( files )
    };
    /**
     *
     * @param event
     */
    const onCheckboxChange = (event, record) => {

        const value = event.target.value ;
        const changeData = event.target.checked ? [
            ...stateValue.bulkRubbishData.ids,
            value
        ] : stateValue.bulkRubbishData.ids.filter( item => item !== value );

        const changePath = event.target.checked ? [
            ...stateValue.bulkRubbishData.files,
            {
                id: record.id,
                path: record.file_path,
            }
        ] : stateValue.bulkRubbishData.files.filter( item => item.id !== record.id );

        const checkedCount = Object.keys( changeData ).length;
        const postCount = Object.keys( stateValue.rubbishMedia.mediaFile ).length;

        dispatch({
            type: Types.BALK_RUBBISH,
            bulkRubbishData: {
                ...stateValue.bulkRubbishData,
                bulkChecked: ! ! checkedCount && checkedCount === postCount,
                ids: changeData,
                files: changePath,
                progressTotal: checkedCount
            },
        });

        console.log( changeData, changePath );

    };
    /**
     *
     * @param data
     * @returns {Promise<void>}
     */
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
                const mediaFile = response.data.updated ? stateValue.rubbishMedia.mediaFile.filter( ( item ) => data.id !=  item.id ) : stateValue.rubbishMedia.mediaFile;
                await dispatch({
                    type: Types.RUBBISH_MEDIA,
                    rubbishMedia:{
                        ...stateValue.rubbishMedia,
                        mediaFile: mediaFile
                    }
                });
                setIgnoreCurrentItem( null );
                setDeleteCurrentItem( null );
            }
            console.log( 'rubbishSingleAction' );
            return ;
        }

        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                openProModal: true,
            },
        });

    };

    const rubbishHead = [
        {
            title: <Checkbox checked={ stateValue.bulkRubbishData.bulkChecked } onChange={onRubbishBulkCheck}/>,
            key: 'CheckboxID',
            dataIndex: 'id',
            width: '50px',
            align: 'center',
            render:  ( id, record ) => <Checkbox checked={ -1 !== stateValue.bulkRubbishData.ids.indexOf( id ) } name="item_id" value={id} onChange={ ( event ) => onCheckboxChange(event, record) } />
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'file_path',
            width: '150px',
            align: 'top',
            render: ( file_path, record, i ) => <Space> <img width={ 50 } src={`${tsmltParams.uploadUrl}/${file_path}`} /> </Space>,
        },
        {
            title: 'File URL',
            key: 'FileType',
            dataIndex: 'file_path',
            align: 'top',
            render: ( file_path, record, i ) => <Space> { `${tsmltParams.uploadUrl}/${file_path}` } </Space>,
        },
        {
            title: 'Actions',
            key: 'FileType',
            dataIndex: 'file_path',
            align: 'top',
            width: '450px',
            render: ( text, record, i ) => <Space wrap>
                {
                    'ignore' == stateValue.rubbishMedia.postQuery.fileStatus ? (
                        <Button onClick={ () => onRubbishSingleAction( record, 'show' ) } loading={ record.id === deleteCurrentItem } > Mark As Unnecessary File </Button>
                    ) :
                    (
                        <>
                            <Button onClick={ () => onRubbishSingleAction( record, 'delete' ) } loading={ record.id === deleteCurrentItem } danger > Delete Unnecessary File  </Button>
                            <Button onClick={ () => onRubbishSingleAction( record, 'ignore' ) } loading={ record.id === ignoreCurrentItem } > Ignore Important File </Button>
                        </>
                    )
                }
            </Space>
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
