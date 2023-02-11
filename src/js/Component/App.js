
import React from "react";

import { Pagination, Table, Input, Modal, Checkbox, Select, Layout, Button, Space } from 'antd';

const { Header, Footer, Sider, Content } = Layout;

import DataTable from "./Datatable";

function App() {
    return (
        <div className="tttme-App">

             <DataTable />
        </div>
    );
}
export default App