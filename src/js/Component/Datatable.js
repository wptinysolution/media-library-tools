// Table.js

import React, {useState, useEffect } from "react";

import {
    Pagination,
    Table,
    Input,
    Modal,
    Checkbox,
    Select,
    Layout,
    Button,
    Space,
    Typography
} from 'antd';

const { Title } = Typography;

const {
    Header,
    Content,
    Footer
} = Layout;

import {
    getMedia,
    bulkUpdateMedia,
    upDateSingleMedia,
    submitBulkMediaAction
} from "../Utils/Data";

import EditButton from "./EditButton";

const { TextArea } = Input;

const headerStyle = {
    height: 64,
    paddingInline: 10,
    lineHeight: '64px',
    backgroundColor: '#fff',
};

const defaultPosts = {
    posts : [],
    total_post: 0,
    max_pages: 0,
    current_pag: 0,
    posts_per_page: 0,
}

const locakedText = 'Locked Edit';

const unlocakedText = 'Unlocked Edit';

const defaultColText = {
    title : locakedText,
    alt : locakedText,
    caption : locakedText ,
    description : locakedText,
}

const defaultEditingStatus = {
    titleEditing : false,
    altEditing : false,
    captionEditing : false,
    descriptionEditing : false,
}

const defaultBulkData = {
    ids: [],
    type: '',
    data: '',
}

const defaultBulkSubmitData = {
    ids: [],
    type: '',
    data : {
        post_title : '',
        alt_text : '',
        caption : '',
        post_description : '',
    }
}

const defaultSort = {
    orderby: '',
    order: 'DESC',
}

const selectStyle = {
    width: 160,
    paddingInline: 0,
}


export default function DataTable() {
    // paged

    const [data, setData] = useState( defaultPosts );

    const [isUpdated, setIsUpdated] = useState(false );

    const [currentEdited, setCurrentEdited] = useState(false );

    const [isModalOpen, setIsModalOpen] = useState(false);

    const [isBulkModalOpen, setIsBulkModalOpen] = useState(false);

    const [bulkdata, setBulkdata] = useState(defaultBulkData );

    const [bulkSubmitdata, setbulkSubmitdata] = useState( defaultBulkSubmitData );

    const { posts, total_post, posts_per_page } = data;

    const [ formEdited, setFormEdited ] = useState(defaultEditingStatus );

    const [ colsText, setColsText ] = useState(defaultColText);

    const [ paged, setPaged ] = useState( 1 );

    const [ sort, setSort ] = useState( defaultSort );

    const [ bulkChecked, setBulkChecked ] = useState( false );

    const [ checkedData, setCheckedData ] = useState( [] );

    const modalClose = () => {
        setIsModalOpen(false);
        setBulkdata( defaultColText );
    };

    const handleBulkClick = ( event, type ) => {
        const ids = posts.map( x => x['ID']);
        setIsModalOpen(true);
        setBulkdata({
            ...bulkdata,
            ids,
            type,
            data: '',
        });
        setFormEdited( defaultEditingStatus );
        setColsText( defaultColText );
    }

    const handleCancel = () => {
        modalClose();
    };

    const getTheMedia = async () => {
        const params = {
            paged :  paged ? paged : 1
        };
        if( sort.order && sort.orderby ){
            params.order = sort.order;
            params.orderby = sort.orderby ;
        }
        const response = await getMedia('', params );
        setData( response );
    }

    const bulkUpdate = async () => {
        const response = await bulkUpdateMedia( bulkdata )
        200 === parseInt( response.status ) && response.data.updated && setIsUpdated( ! isUpdated );
    }

    const submitBulkMedia = async ( params ) => {
        const response = await submitBulkMediaAction( params );
        if( 200 === parseInt( response.status ) && response.data.updated ){
            setCheckedData( [] );
            setBulkChecked( false );
            setIsUpdated( ! isUpdated );
        }
    }

    const balkChange = ( event ) => {
        setBulkdata({
            ...bulkdata,
            data : event.target.value
        });
    };

    const handleOk = () => {
        bulkUpdate( bulkdata );
        modalClose();
    };

    const handleSortClick = ( event, getType ) => {
        setSort( (prevState) => ( {
            orderby: getType,
            order: getType === prevState.orderby && 'DESC' === prevState.order ? 'ASC' : 'DESC',
        })  );
    };

    useEffect(() => {
        getTheMedia()
    }, [isUpdated, paged, sort]  );

    const ColumnHandleClick = ( event, editable ) => {
        let formEditing = {};
        switch ( editable ) {
            case 'title':
                formEditing = {
                    ...formEdited,
                    titleEditing : ! formEdited.titleEditing
                }

                break;
            case 'alt':
                formEditing = {
                    ...formEdited,
                    altEditing : ! formEdited.altEditing
                }

                break;
            case 'caption':
                formEditing = {
                    ...formEdited,
                    captionEditing : ! formEdited.captionEditing
                }

                break;
            case 'description':
                formEditing = {
                    ...formEdited,
                    descriptionEditing : ! formEdited.descriptionEditing
                }
                break;
            default:
                formEditing = { ...formEdited }
        }
        setFormEdited( formEditing );
        setColsText( {
            ...colsText,
            title : ! formEditing.titleEditing ? locakedText : unlocakedText,
            alt : ! formEditing.altEditing ? locakedText : unlocakedText,
            caption : ! formEditing.captionEditing ? locakedText : unlocakedText,
            description : ! formEditing.descriptionEditing ? locakedText : unlocakedText,
        } );

    }

    const handleChange = ( event ) => {
        const currentItem = parseInt( event.target.getAttribute('current') );
        const currentData = {
            ID: posts[currentItem].ID,
            [event.target.name] : event.target.value.trim()
        }
        posts[currentItem][event.target.name] = event.target.value;
        setCurrentEdited( currentData );
        setData( {
            ...data,
            posts
        } );
    }

    const handleFocusout = async ( event ) => {
        const response = await upDateSingleMedia( currentEdited );
        200 === parseInt( response.status ) && setIsUpdated( ! isUpdated );
    }

    const handlePagination = ( current ) => {
        setPaged( current );
        setCheckedData( [] );
        setBulkChecked( false );
    }

    const onCheckboxChange = (event) => {
        const value = event.target.value ;
        const data = event.target.checked ? [
                ...checkedData,
                value
            ] : checkedData.filter(item => item !== value );

        const Checked_count = Object.keys(data).length;
        const post_count = Object.keys(posts).length;

        setCheckedData( data );
        setBulkChecked( Checked_count === post_count );

    };

    const onBulkCheck = (event) => {
        const data = event.target.checked ? posts.map( item => item.ID ) : [];
        setCheckedData( data );
        setBulkChecked( ! ! data.length );
    };

    const handleBulkSubmit = (event) => {
        const params = {
            ...bulkSubmitdata,
            ids :  checkedData ? checkedData : []
        };
        switch( bulkSubmitdata.type ){
            case 'trash':
            case 'update':
            case 'delete':
                submitBulkMedia( params );
                break;
            case 'edit':
                setIsBulkModalOpen( true );
                break;
            default:
        }

    };

    const handleBulkModalOk = (event) => {
        setIsBulkModalOpen( false );
    };

    const handleBulkModalCancel = (event) => {
        setIsBulkModalOpen( false );
    };

    const handleChangeBulkType = (value) => {
        const data = 'edit' === value ? bulkSubmitdata.data : defaultBulkSubmitData.data;
        setbulkSubmitdata( {
            ...bulkSubmitdata,
            type: value,
            data
        })

    };

    const columns = [
        {
            title: <Checkbox checked={ bulkChecked } onChange={onBulkCheck}/>,
            key: 'ID',
            dataIndex: 'ID',
            width: '100px',
            align: 'center',
            render:  ( id, record ) => <Checkbox checked={ -1 !== checkedData.indexOf( id ) } name="item_id" value={id} onChange={onCheckboxChange} />
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick, handleSortClick, bulkdata }} text={'Id'} hasButton={false}/>,
            key: 'ID',
            dataIndex: 'ID',
            width: '100px',
            align: 'top'
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick, handleSortClick, bulkdata }} text={'Image'} hasButton={false}/>,
            key: 'Image',
            dataIndex: 'guid',
            width: '150px',
            align: 'top',
            render:  ( text, record ) =>  <img width={`80`} src={text}  />,
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick, handleSortClick, bulkdata  }} text={'Title'} hasButton={true}/>,
            key: 'Title',
            dataIndex: 'post_title',
            align: 'top',
            width: '250px',
            render: ( text, record, i ) => <> { formEdited.titleEditing ? <TextArea name={`post_title`} placeholder={`Title Shouldn't leave empty`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick, handleSortClick, bulkdata }} text={'Alt'} hasButton={true}/>,
            key: 'Alt',
            dataIndex: 'alt_text',
            align: 'top',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited.altEditing ? <TextArea name={`alt_text`} placeholder={`Alt Text Shouldn't leave empty`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick, handleSortClick, bulkdata }} text={'Caption'} hasButton={true}/>,
            key: 'Caption',
            dataIndex: 'post_excerpt',
            width: '300px',
            render: ( text, record, i ) => <> { formEdited.captionEditing ? <TextArea name={`post_excerpt`} placeholder={`Caption Text`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
        {
            title: <EditButton prevdata={{ ColumnHandleClick, colsText, handleBulkClick, handleSortClick, bulkdata }} text={'Description'} hasButton={true}/>,
            key: 'Description',
            dataIndex: 'post_content',
            width: '350px',
            render: ( text, record, i ) => <> { formEdited.descriptionEditing ? <TextArea name={`post_content`} placeholder={`Description Text`} current={i} onBlur={handleFocusout}  onChange={handleChange} value={ text } /> : text }   </>
        },
    ];

    return (

        <Layout className="layout">
            <Header style={ headerStyle }>
                <Space wrap>
                    <Select
                        style={selectStyle}
                        allowClear
                        placeholder="Bulk actions"
                        onChange={ handleChangeBulkType }
                        size={`large`}
                        options={[
                            {
                                value: 'edit',
                                label: 'Edit',
                            },
                            {
                                value: 'trash',
                                label: 'Move to Trash',
                            },
                            {
                                value: 'delete',
                                label: 'Delete Permanently ',
                            },

                        ]}
                    />
                    <Button
                        type="primary"
                        size="large"
                        onClick = { handleBulkSubmit }
                    >  Apply </Button>
                    <Select
                        defaultValue={`0`}
                        style={selectStyle}
                        onChange={ () => console.log( `Status` ) }
                        size={`large`}
                        options={[
                            {
                                value: '0',
                                label: 'All',
                            },
                            {
                                value: 'trash',
                                label: 'Trash',
                            },

                        ]}
                    />
                    <Select
                        defaultValue={`0`}
                        style={selectStyle}
                        onChange={ () => console.log( `Date Change` ) }
                        size={`large`}
                        options={[
                            {
                                value: '0',
                                label: 'All dates',
                            },
                            {
                                value: '2023-01',
                                label: 'January 2023 ',
                            },
                            {
                                value: '2023-02',
                                label: 'February 2023 ',
                            },

                        ]}
                    />
                    <Select
                        defaultValue={`all`}
                        style={selectStyle}
                        onChange={ () => console.log( 'Hello' ) }
                        size={`large`}
                        options={[
                            {
                                value: 'all',
                                label: 'All Categories',
                            },
                            {
                                value: 'uncategorized',
                                label: 'Uncategorized',
                            },
                        ]}
                    />
                    <Button
                        type="primary"
                        size="large"
                        onClick = { () => console.log( 'Filter' ) }
                    >  Filter </Button>
                </Space>
            </Header>
            <Content>
                { posts &&
                    <Table
                        rowKey={ ( item ) =>  item.ID  }
                        pagination={false}
                        columns={columns}
                        dataSource={posts}
                        scroll={{
                            x: 1300,
                        }}
                    />
                }

            </Content>

            <Footer  style={{ textAlign: 'right' }}  >
                {
                    posts_per_page && paged &&
                    <Pagination
                        showTitle={true}
                        showTotal={(total, range) => `${range[0]}-${range[1]} of ${total} items`}
                        defaultPageSize={posts_per_page}
                        total={total_post}
                        current={paged}
                        onChange={ ( current ) => handlePagination( current ) }
                    />

                }
            </Footer>

            <Modal title={`${bulkdata.type} - Bulk Edit`} open={isModalOpen} onOk={handleOk} onCancel={handleCancel}>
                <TextArea onChange={balkChange} name={`modal_content`} value={bulkdata.data} placeholder={`Field Shouldn't leave empty`} />
            </Modal>

            <Modal title={`Bulk Edit`} open={isBulkModalOpen} onOk={handleBulkModalOk} onCancel={handleBulkModalCancel}>
                <Title level={5}> Title </Title>
                <TextArea onChange={balkChange} name={`modal_title`} value={bulkSubmitdata.data.post_title} placeholder={`Title`} />
                <Title level={5}> Alt Text </Title>
                <TextArea onChange={balkChange} name={`modal_alt_text`} value={bulkSubmitdata.data.alt_text} placeholder={`Alt text`} />
                <Title level={5}> Caption </Title>
                <TextArea onChange={balkChange} name={`modal_caption`} value={bulkSubmitdata.data.caption} placeholder={`Caption`} />
                <Title level={5}> Description </Title>
                <TextArea onChange={balkChange} name={`modal_description`} value={bulkSubmitdata.data.post_description} placeholder={`Description`} />
            </Modal>

        </Layout>
    );
}
