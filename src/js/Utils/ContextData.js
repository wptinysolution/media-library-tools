import EditButton from '../Component/EditButton';
export const columns = [
    {
        title: <EditButton text={'Id'} hasButton={false}/>,
        key: 'Id',
        dataIndex: 'ID',
        width: '100px',
        align: 'top',
    },
    {
        title: <EditButton text={'Image'} text={'Image'} hasButton={false}/>,
        key: 'Image',
        dataIndex: 'guid',
        width: '100px',
        align: 'top',
        render:  ( text, record ) =>  <img width={`80`} src={text}  />
    },
    {
        title: <EditButton text={'Title'} hasButton={true}/>,
        key: 'Title',
        dataIndex: 'post_title',
        align: 'top',
        editable: true,

    },

    {
        title: <EditButton text={'Alt'} hasButton={true}/>,
        key: 'Alt',
        dataIndex: 'alt_text',
        align: 'top',
        editable: true,
    },
    {
        title: <EditButton text={'Caption'} hasButton={true}/>,
        key: 'Caption',
        dataIndex: 'post_excerpt',
        editable: true,
    },
    {
        title: <EditButton text={'Description'} hasButton={true}/>,
        key: 'Description',
        dataIndex: 'post_content',
        editable: true,
    },
];

export const defaultPosts = {
    posts : [],
    total_post: 0,
    max_pages: 0,
    current_pag: 0,
    posts_per_page: 0,
}

