
import React, {useState, useEffect, useContext} from "react";

import { TheAppContext, TheMediaTableContext } from '../../Utils/TheContext';


import {Layout, Pagination, Spin, Space, Table} from "antd";

const {
    Header,
    Content,
    Footer
} = Layout;

import {
    headerStyle,
    renamerColumns
} from '../../Utils/UtilData'

import RenamerMainHeader from "./RenamerMainHeader";

function RenamerTableData() {

    const {
        optionsData,
        setOptionsData,
        handleUpdateOption,
        isUpdated,
        setIsUpdated
    } = useContext( TheAppContext );

    const {
        posts,
        formEdited,
        handleFocusout,
        handleChange
    } = useContext( TheMediaTableContext );


    const RenameTableColumns = renamerColumns(
        formEdited,
        handleFocusout,
        handleChange
    );

    return (
            <Layout className="layout">
                <RenamerMainHeader/>
                <Content>
                    <Table
                        rowKey={(item) => item.ID}
                        pagination={false}
                        columns={ RenameTableColumns }
                        dataSource={ posts }
                        scroll={{
                            x: 1300,
                        }}
                    />
                </Content>
            </Layout>
    );
}

export default RenamerTableData;

