
import React, {useState, useEffect, useContext} from "react";

import { TheAppContext, TheMediaTableContext } from '../../Utils/TheContext';


import {Layout, Pagination, Spin, Table} from "antd";

const {
    Content,
    Footer
} = Layout;

import {
    renamerColumns
} from '../../Utils/UtilData'


function RenamerTableData() {
    const {
        posts
    } = useContext( TheMediaTableContext );


    const RenameTableColumns = renamerColumns();

    return (
        <TheMediaTableContext.Provider value={ {  } }>

            <Layout className="layout">
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

