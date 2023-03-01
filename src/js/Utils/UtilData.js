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
