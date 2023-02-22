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
    Typography,
    Spin,
    Divider
} from 'antd';

import { LoadingOutlined } from '@ant-design/icons';

const { Title } = Typography;

const {
    Header,
    Content,
    Footer
} = Layout;

import {
    getTerms,
    getDates,
    getMedia,
    upDateSingleMedia,
    submitBulkMediaAction
} from "../Utils/Data";

import {value} from "lodash/seq";

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
    paged: 1,
    posts_per_page: 1,
}

const defaultPostsQuery = {
    status: 'inherit',
    filtering : false,
    paged: 1,
    orderby: 'menu_order',
    order: 'DESC',
}

const defaultPostsFilter = {
    date: '',
    categories: '',
    filtering : false,
}

const defaultBulkSubmitData = {
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

const selectStyle = {
    width: 160,
    paddingInline: 0,
}

const bulkOprions = [
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

export default function DataTable() {
    // paged

    const [data, setData] = useState( defaultPosts );

    const [postQuery, setPostQuery] = useState( defaultPostsQuery );

    const [filtering, setFiltering] = useState( defaultPostsFilter );

    const [dateList, setDateList] = useState( [] );

    const [termsList, setTermsList] = useState( [] );

    const [isUpdated, setIsUpdated] = useState(false );

    const [currentItemEdited, setCurrentItemEdited] = useState(false );

    const [isBulkModalOpen, setIsBulkModalOpen] = useState(false);

    const [bulkSubmitdata, setbulkSubmitdata] = useState( defaultBulkSubmitData );

    const { posts, total_post, posts_per_page, paged } = data;

    const [formEdited, setFormEdited] = useState( false );

    const [bulkChecked, setBulkChecked] = useState( false );

    const [checkedData, setCheckedData] = useState( [] );

    const [isLoading, setIsloading] = useState( true );

    const getDateList = async () => {
        const response = await getDates();
        const preparedData =  JSON.parse( response.data );
        setDateList( preparedData );
    }

    const getTermsList = async () => {
        const response = await getTerms();
        const preparedData =  JSON.parse( response.data );
        setTermsList( preparedData );
    }

    const getTheMedia = async () => {
        const response = await getMedia('', {
            ...postQuery
        } );
        setData( response );
    }

    const submitBulkMedia = async ( params ) => {
        // console.log( params )
        const response = await submitBulkMediaAction( params );
        if( 200 === parseInt( response.status ) && response.data.updated ){
            setCheckedData( [] );
            setBulkChecked( false );
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
        setCheckedData([]);
        setBulkChecked(false);
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
                })
                break;
            default:
        }
    };

    const handleBulkModalOk = (event) => {
        submitBulkMedia( bulkSubmitdata );
        setIsBulkModalOpen( false );
        setbulkSubmitdata( {
            ...defaultBulkSubmitData,
            type: 'bulkedit'
        } )

    };

    const handleBulkModalCancel = (event) => {
        setIsBulkModalOpen( false );
        setbulkSubmitdata( {
            ...defaultBulkSubmitData,
            type: 'bulkedit'
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
        setCheckedData([]);
        setBulkChecked( false );
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

    const columns = [
        {
            title: <Checkbox checked={ bulkChecked } onChange={onBulkCheck}/>,
            key: 'ID',
            dataIndex: 'ID',
            width: '50px',
            align: 'center',
            render:  ( id, record ) => <Checkbox checked={ -1 !== checkedData.indexOf( id ) } name="item_id" value={id} onChange={onCheckboxChange} />
        },
        {
            title: <Space wrap> { `ID` } <Button size={`small`} sort-by={`id`} onClick={ () => handleSortClick( 'id' )}> {`Sort`} </Button> </Space>,
            key: 'ID',
            dataIndex: 'ID',
            width: '105px',
            align: 'top'
        },
        {
            title: 'File',
            key: 'Image',
            dataIndex: 'guid',
            width: '130px',
            align: 'top',
            render:  ( text, record ) =>  <img width={`80`} src={text}  />,
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
    ];

    const antIcon = <LoadingOutlined style={{ fontSize: 24 }} spin />;

    useEffect(() => {
        getDateList();
        getTermsList();
    }, []  );

    useEffect(() => {
        getTheMedia();
        setTimeout(() => {
            setIsloading( false )
        }, 200 );
    }, [isUpdated]  );

    return (
            <Layout className="layout">
                {/*{ console.log( termsList ) }*/}
                <Header style={headerStyle}>
                    <Space wrap>
                        <Select
                            size="large"
                            defaultValue={``}
                            style={selectStyle}
                            onChange={handleChangeBulkType}
                            options={
                                postQuery.filtering && 'trash' == postQuery.status ? [...bulkOprions.filter(item => 'trash' !== item.value)] : [...bulkOprions.filter(item => 'inherit' !== item.value)]
                            }
                        />
                        <Button
                            type="primary"
                            size="large"
                            onClick={handleBulkSubmit}
                        > Apply </Button>
                        <Select
                            size="large"
                            defaultValue={``}
                            style={selectStyle}
                            onChange={(value) =>
                                setFiltering({
                                    ...filtering,
                                    status: value,
                                })
                            }
                            options={[
                                {
                                    value: '',
                                    label: 'All',
                                },
                                {
                                    value: 'trash',
                                    label: 'Trash',
                                },

                            ]}
                        />
                        <Select
                            size="large"
                            defaultValue={``}
                            style={selectStyle}
                            onChange={ (value) => setFiltering({
                                ...filtering,
                                date: value,
                            }) }
                            options={dateList}
                        />
                        <Select
                            size="large"
                            defaultValue={``}
                            style={selectStyle}
                            onChange={(value) => setFiltering({
                                ...filtering,
                                categories: value,
                            })
                            }
                            options={termsList}
                        />
                        <Button
                            type="primary"
                            size="large"
                            onClick={handleFilterData}
                        > Filter </Button>
                        <Button
                            style={{
                                width: '180px'
                            }}
                            type="primary"
                            size="large"
                            onClick={ () => handleColumnEditMode() }
                            ghost={ ! formEdited }>  { formEdited ? 'Disable Edit Mode' : 'Enable Edit Mode' }
                        </Button>
                    </Space>
                </Header>
                { isLoading || ! total_post > 0 ?
                    <Layout className="spain-icon" style={{height: "90vh", justifyContent: 'center'}}> <Spin indicator={antIcon}/></Layout>
                    : <>
                        <Content>
                            <Table
                                rowKey={(item) => item.ID}
                                pagination={false}
                                columns={columns}
                                dataSource={posts}
                                scroll={{
                                    x: 1300,
                                }}
                            />
                        </Content>
                        <Footer style={{textAlign: 'right'}}>
                            <Pagination
                                showTitle={true}
                                showSizeChanger={false}
                                showQuickJumper={true}
                                showTotal={(total, range) => `${range[0]}-${range[1]} of ${total} items`}
                                total={total_post}
                                pageSize={posts_per_page}
                                current={paged}
                                onChange={(current) => handlePagination(current)}
                            />
                        </Footer>
                    </>
                }

                <Modal
                    title={`Bulk Edit`}
                    open={isBulkModalOpen}
                    onOk={handleBulkModalOk}
                    onCancel={handleBulkModalCancel}
                    >
                    <Divider />
                    <Content>
                        <Title style={{marginTop:'0px'}} level={5}> Title </Title>
                        <TextArea
                            onChange={ balkModalDataChange }
                            name={`post_title`}
                            value={bulkSubmitdata.data.post_title}
                            placeholder={`Title`}
                        />
                        <Title style={{marginTop:'10px'}} level={5}> Alt Text </Title>
                        <TextArea
                            onChange={balkModalDataChange}
                            name={`alt_text`}
                            value={bulkSubmitdata.data.alt_text}
                            placeholder={`Alt text`}
                        />
                        <Title style={{marginTop:'10px'}} level={5}> Caption </Title>
                        <TextArea
                            onChange={balkModalDataChange}
                            name={`caption`}
                            value={bulkSubmitdata.data.caption}
                            placeholder={`Caption`}
                        />
                        <Title style={{marginTop:'10px'}} level={5}> Description </Title>
                        <TextArea
                            onChange={balkModalDataChange}
                            name={`post_description`}
                            value={bulkSubmitdata.data.post_description}
                            placeholder={`Description`}
                        />
                        <Title style={{marginTop:'10px'}} level={5}> Categories </Title>
                        <Select
                            onChange={ (value) => setbulkSubmitdata({
                                ...bulkSubmitdata,
                                'post_categories': value
                            }) }
                            size="large"
                            mode="multiple"
                            style={{
                                width: '100%',
                            }}
                            showArrow
                            options={termsList}
                        />

                    </Content>
                    <Divider />
                </Modal>
            </Layout>
    );
}

