
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
        posts
    } = useContext( TheMediaTableContext );

    const formEdited = true;
    const RenameTableColumns = renamerColumns(
        formEdited
    );

    return (
        <TheMediaTableContext.Provider value={ { formEdited } }>
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

        </TheMediaTableContext.Provider>
    );
}

export default RenamerTableData;

