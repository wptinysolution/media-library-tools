// Table.js

import React, {useContext, useEffect} from "react";
import {  useState, useRef } from "react";
import { Pagination, Table, Form, InputNumber, Popconfirm } from 'antd';
import SystemContext from '../SystemContext';
import {bulkUpdateMedia, getMedia, upDateSingleMedia} from "../Utils/Data";

export default function DataTable() {

    const  { columns, defaultPosts }  = useContext( SystemContext );

    const [data, setData] = useState( defaultPosts );

    const [isUpdated, setIsUpdated] = useState(false );


    const bulkUpdate = async () => {
        const response = await bulkUpdateMedia()

    }
    const getTheMedia = async () => {
        const response = await getMedia()
        setData( response );
    }

    useEffect(() => {
        getTheMedia( )
    }, [isUpdated]  );

    const { posts, total_post, max_pages, current_page, posts_per_page } = data;

    return (
            <>
                { posts &&
                    <>
                    <Table
                        pagination={false}
                        columns={columns}
                        dataSource={posts}
                        scroll={{
                            x: 1300,
                        }}
                    />
                    <div className={`post-pagination`}>
                       {
                            posts_per_page &&
                            <Pagination
                                showTitle={true}
                                showTotal={(total, range) => `${range[0]}-${range[1]} of ${total} items`}
                                defaultPageSize={posts_per_page}
                                total={total_post}
                                current={current_page}
                            />
                       }
                    </div>
                    </>
                }
            </>
    );
}
