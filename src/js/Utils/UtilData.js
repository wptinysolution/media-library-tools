import React, { useEffect } from "react";

import {Button, Checkbox, Space, Input, Layout } from "antd";
import {useStateValue} from "./StateProvider";
import * as Types from "./actionType";
const { TextArea } = Input;

export const headerStyle = {
    height: 64,
    paddingInline: 10,
    lineHeight: '64px',
    backgroundColor: '#fff',
};

export const  selectStyle = {
    width: 160,
    paddingInline: 0,
}

export const  defaultPosts = {
    posts : [],
    total_post: 0,
    paged: 1,
    posts_per_page: 1,
}

export const  defaultPostsQuery = {
    status: 'inherit',
    filtering : false,
    paged: 1,
    orderby: 'menu_order',
    order: 'DESC',
}

export const  defaultPostsFilter = {
    date: '',
    categories: '',
    filtering : false,
}

export const  defaultBulkSubmitData = {
    ids: [],
    type: '',
    data : {
        post_title : '',
        alt_text : '',
        caption : '',
        post_description : '',
    },
    post_categories : [],
}

export const bulkOprions = [
    {
        value: '',
        label: 'Bulk actions',
    },
    {
        value: 'bulkedit',
        label: 'Bulk Edit',
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

export function columns(
    bulkChecked,
    onBulkCheck,
    checkedData,
    onCheckboxChange,
    handleSortClick,
    formEdited,
    handleFocusout,
    handleChange,
    filtering,
    setFiltering,
    handleFilterData,
){
    useEffect(() => {
        handleFilterData();
    }, [filtering]);

    return [
        {
            title: <Checkbox checked={ bulkChecked } onChange={onBulkCheck}/>,
            key: 'CheckboxID',
            dataIndex: 'ID',
            width: '80px',
            align: 'center',
            render:  ( id, record ) => <Checkbox checked={ -1 !== checkedData.indexOf( id ) } name="item_id" value={id} onChange={onCheckboxChange} />
        },
        {
            title: <Space wrap> { `ID` } <Button size={`small`} sort-by={`id`} onClick={ () => handleSortClick( 'id' )}> {`Sort`} </Button> </Space>,
            key: 'ID',
            dataIndex: 'ID',
            width: '150px',
            align: 'top'
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'guid',
            width: '100px',
            align: 'top',
            render:  ( text, record, i ) => <Space> <img width={`80`} src={text} /> </Space>,
        },
        {
            title: <Space wrap> { `Title` } <Button size={`small`} onClick={ () => handleSortClick( 'title' )} > Sort </Button> </Space>,
            key: 'Title',
            dataIndex: 'post_title',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`post_title`} placeholder={`Title Shouldn't leave empty`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <Space wrap> { `Alt` } <Button size={`small`} onClick={ () => handleSortClick( 'alt' )}> Sort </Button> </Space>,
            key: 'Alt',
            dataIndex: 'alt_text',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`alt_text`} placeholder={`Alt Text Shouldn't leave empty`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <Space wrap> { `Caption` } <Button size={`small`} onClick={   () => handleSortClick( 'caption' ) }> Sort </Button> </Space>,
            key: 'Caption',
            dataIndex: 'post_excerpt',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited ? <TextArea name={`post_excerpt`} placeholder={`Caption Text`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <Space wrap> { `Description` } <Button size={`small`} onClick={  () => handleSortClick( 'description' ) }> Sort </Button> </Space>,
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
               return  items.map( item => item.id && <Button key={Math.random().toString(36).substr(2, 9)} size={`small`} onClick={  () => {
                   setFiltering({
                       ...filtering,
                       categories: item.id,
                   })
               } }> {  item.name } </Button>  )
           }
        },
    ];
}


export function renamerColumns(){
    const [stateValue, dispatch] = useStateValue();
    return [
        {
            title: <Space wrap> { `ID` } </Space>,
            key: 'ID',
            dataIndex: 'ID',
            width: '50px',
            align: 'top'
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'guid',
            width: '130px',
            align: 'top',
            render:  ( text, record, i ) => <img width={`80`} src={text} /> ,
        },
        {
            title: `File Name`,
            key: 'Image',
            dataIndex: 'guid',
            width: '400px',
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
                </Layout> : record.thefile.mainfilename }
            </>,
        },
        {
            title: <Space wrap> { `Title` } </Space>,
            key: 'Title',
            dataIndex: 'post_title',
            align: 'top',
        }
    ];
}
