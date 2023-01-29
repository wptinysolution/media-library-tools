
export const columns = [
    {
        title: "Id",
        key: 'Id',
        dataIndex: 'ID',
        width: '50px',
    },
    {
        title: "Image",
        key: 'Image',
        dataIndex: 'guid',
        width: '50px',
        render:  ( text, record ) =>  <img width={`80`} src={text} />
    },
    {
        title: <>
            <div className={`heading-title`}>
                Title
                <span className={`on-hover`}> Sort </span>
            </div>
            <div className={`tttme-button-link`}>
                <span>  Make Editable </span>
                <span>Bulk Edit </span>
            </div>
        </>,

        key: 'Title',
        dataIndex: 'post_title',
        editable: true,

    },

    {
        title: "Alt",
        key: 'Alt',
        dataIndex: 'alt_text',
        editable: true,
    },
    {
        title: "Caption",
        key: 'Caption',
        dataIndex: 'post_excerpt',
        editable: true,
    },
    {
        title: "Description",
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

